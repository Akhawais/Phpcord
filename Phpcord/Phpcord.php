<?php
/**
 * This file is part of PhpCord. This file is subject to the license found at LICENSE.md at the root of this project.
 * Copyright (c) 2017 Dylan Akhawais <dylan@akhawais.co.uk>
 */

namespace Phpcord;

use Evenement\EventEmitterTrait;
use GuzzleHttp\HandlerStack;
use Illuminate\Container\Container;
use Phpcord\Container as ContainerHolder;
use Illuminate\Cache\CacheManager;
use Illuminate\Redis\RedisManager;
use Illuminate\Support\Fluent;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Phpcord\Discord\Channel;
use Phpcord\Discord\Client;
use Phpcord\Discord\Member;
use Phpcord\Events\Event;
use Phpcord\Events\GuildCreate;
use Phpcord\Events\Handler;
use Phpcord\Stores\GuildStore;
use Phpcord\Stores\PrivateChannelStore;
use Phpcord\Stores\UserStore;
use Ratchet\Client\Connector;
use Ratchet\Client\WebSocket;
use React\EventLoop\Factory;
use React\Promise\Deferred;
use WyriHaximus\React\GuzzlePsr7\HttpClientAdapter;

class Phpcord
{
    use EventEmitterTrait;

    const PHPCORD_VERSION     = 'v0.0.5';
    const API_VERSION         = 6;
    const GATEWAY_VERSION     = 6;
    const MODEL_ATTRIBUTE_TTL = 5;

    /** @var  \Illuminate\Support\Fluent */
    protected $options;

    protected $encoding = 'json';
    protected $gateway;

    /** @var  Logger */
    protected $log;

    protected $connected      = false;
    protected $connecting     = false;
    protected $closing        = false;
    protected $reconnecting   = false;
    protected $reconnectCount = 0;

    /** @var  \React\EventLoop\Timer\TimerInterface */
    protected $heartbeat;

    protected $heartbeatInterval;

    /** @var  \React\EventLoop\Timer\TimerInterface */
    protected $heartbeatAcknowledgeTimeout;

    protected $sessionId = '';
    protected $sequence  = 0;

    protected $isReady          = false;
    protected $prematurePackets = [];

    /** @var  Client */
    public $client;

    protected $largeGuilds = [];
    protected $largeSent   = [];

    public function __construct(array $options = [])
    {
        if (php_sapi_name() !== 'cli') {
            trigger_error('This application may only run via CLI.', E_USER_ERROR);
        }

        $this->options = new Fluent($options);
        $this->bootstrap();
        $this->log->info('Starting Phpcord ' . self::PHPCORD_VERSION);
        $this->init();
    }

    protected function bootstrap()
    {
        $app = new Container;
        $app->instance(Phpcord::class, $this);
        $app->singleton('config', function ($app) {
            return $this->options;
        });
        $app->singleton('storage', function () {
            return new Fluent();
        });

        $app->singleton('http', function () use ($app) {
            $handler = new HttpClientAdapter($app['react']);
            return new HttpClient(['handler' => HandlerStack::create($handler)]);
        });

        $app->singleton('redis', function ($app) {
            return new RedisManager('predis', $app['config']['redis']);
        });
        $app->singleton('redis.connection', function ($app) {
            return $app['redis']->connection();
        });
        $app->singleton('cache', function ($app) {
            return new CacheManager($app);
        });
        $app->singleton('cache.store', function ($app) {
            return $app['cache']->driver();
        });
        if ($this->options->get('loop', false) === false) {
            $loop = Factory::create();
            $app->singleton('react', function () use ($loop) {
                return $loop;
            });
        } else {
            $app->instance('react', $this->options->get('loop'));
        }
        $app->singleton('ratchet', function ($app) {
            return new Connector($app['react']);
        });
        $app->singleton(Handler::class, function () {
            return new Handler();
        });
        $app->alias(Handler::class, 'handler');

        $this->log = new Logger("Phpcord");
        if ($this->options->get('logging', true)) {
            $this->log->pushHandler(new StreamHandler('php://stdout', $this->options->get('loggingLevel', Logger::INFO)));
        }
        $app->instance(Logger::class, $this->log);
        ContainerHolder::setInstance($app);
    }

    protected function init()
    {
        $this->retrieveGateway()->then(function () {
            $this->log->info('Connecting to Discord gateway...');
            $this->log->debug('URL: ' . $this->gateway);
            $this->connect();
        });
    }

    public function connect()
    {
        (phpcord('ratchet')($this->gateway))->then([$this, 'wsOpen'], [$this, 'wsError']);
    }

    protected function retrieveGateway()
    {
        $deferred = new Deferred();
        $return   = function ($gateway) use ($deferred) {
            $params        = [
                'v'        => self::GATEWAY_VERSION,
                'encoding' => $this->encoding,
            ];
            $query         = http_build_query($params);
            $this->gateway = trim($gateway, '/') . '/?' . $query;
            $deferred->resolve($this->gateway);
        };
        if (phpcord_cache()->has('gateway')) {
            $return(phpcord_cache()->get('gateway'));
        } else {
            phpcord_http()->request('gateway')->then(function ($response) use ($return) {
                phpcord_cache()->forever('gateway', $response->url);
                $return($response->url);
            }, function ($e) use ($return) {
                $return('wss://gateway.discord.gg'); // Fallback
            });
        }
        return $deferred->promise();
    }

    public function wsOpen($connection)
    {
        $this->connected = true;
        ContainerHolder::getInstance()->instance(WebSocket::class, $connection);

        $this->log->info('Connected to Discord gateway');

        $connection->on('message', function ($payload) {
            $this->processPayload($payload);
        });
        $connection->on('close', [$this, 'wsClose']);
        $connection->on('error', [$this, 'wsError']);
    }

    public function wsClose($code, $reason)
    {
        $this->connected = false;

        if (!is_null($this->heartbeat)) {
            $this->heartbeat->cancel();
            $this->heartbeat = null;
        }

        if (!is_null($this->heartbeatAcknowledgeTimeout)) {
            $this->heartbeatAcknowledgeTimeout->cancel();
            $this->heartbeatAcknowledgeTimeout = null;
        }

        if ($this->closing) {
            return;
        }

        $this->log->warning('Gateway connection closed', ['code' => $code, 'reason' => $reason]);

        if ($code == Enums::GATEWAY_ERRORS['authentication_failed']) {
            $this->emit('error', ['token is invalid', $this]);
            $this->log->error('Invalid Discord Token provided, authentication failed.');
            return;
        }

        ++$this->reconnectCount;
        $this->reconnecting = true;
        $this->log->info('Reconnecting to Discord Gateway', ['reconnect_count' => $this->reconnectCount]);
        $this->connect();
    }

    public function wsError($e)
    {
        if (strpos($e->getMessage(), 'Tried to write to closed stream') !== false) {
            return;
        }

        $this->emit('error', [$e, $this]);
        $this->log->error('WebSocket error', ['error' => $e->getMessage()]);
        $this->wsClose(0, 'websocket error');
    }

    public function send($payload)
    {
        phpcord(WebSocket::class)->send(json_encode($payload));
    }

    public function authorize($resume = true)
    {
        if ($resume && $this->reconnecting && !is_null($this->sessionId)) {
            $payload = [
                'op' => Enums::OPCODES['Resume'],
                'd'  => [
                    'session_id' => $this->sessionId,
                    'seq'        => $this->sequence,
                    'token'      => phpcord_token()
                ],
            ];
        } else {
            $payload = [
                'op' => Enums::OPCODES['Identify'],
                'd'  => [
                    'token'      => phpcord_token(),
                    'properties' => [
                        '$os'      => PHP_OS,
                        '$browser' => 'Phpcord/' . Phpcord::PHPCORD_VERSION,
                        '$device'  => 'Phpcord/' . Phpcord::PHPCORD_VERSION
                    ],
                    'compress'   => true
                ]
            ];
            if (isset($this->options->shardId) && isset($this->options->shardCount)) {
                $payload['d']['shard'] = [
                    $this->options->shardId,
                    $this->options->shardCount
                ];
            }
        }

        $this->send($payload);
        $this->log->info('Authenticating with Discord Gateway');
        $this->log->debug('Using the following payload', $payload);

        return $payload['op'] == Enums::OPCODES['Resume'];
    }

    /**
     * @param \Ratchet\RFC6455\Messaging\Message $payload
     */
    public function processPayload($payload)
    {
        if ($payload->isBinary()) {
            $payload = zlib_decode($payload->getPayload());
        } else {
            $payload = $payload->getPayload();
        }
        $payload = json_decode($payload);

        $this->emit('raw_payload', [$payload, $this]);

        $opcode         = $payload->op;
        $data           = $payload->d ?? null;
        $event          = $payload->t ?? null;
        $this->sequence = $payload->s ?? null;

        if (is_null($event) || !in_array($event, ['PRESENCE_UPDATE'])) {
            $this->log->debug('Got ' . $event ?: $opcode, ['data' => $data]);
        }

        $method = 'handle' . Enums::OPS[$opcode];
        if (method_exists($this, $method)) {
            $this->{$method}($data, $event);
        }
    }

    protected function handleDispatch($data, $event)
    {
        if (!is_null($handleData = phpcord('handler')->getHandler($event))) {
            $handler  = new $handleData['class']();
            $deferred = new Deferred;
            $deferred->promise()->then(function ($d) use ($data, $event, $handleData) {
                if (is_array($d) && count($d) == 2) {
                    list($new, $old) = $d;
                } else {
                    $new = $d;
                    $old = null;
                }
                $this->emit($event, [$new, $this, $old]);
                foreach ($handleData['alternatives'] as $alternative) {
                    $this->emit($alternative, [$d, $this]);
                }
                if ($event == Event::MESSAGE_CREATE && mentioned($this->client->user, $new)) {
                    $this->emit('mention', [$new, $this, $old]);
                }
            }, function ($e) use ($event) {
                $this->log->warning('Error while trying to handle dispatch packet', ['packet' => $event, 'error' => $e]);
            }, function ($d) use ($data, $event) {
                $this->log->warning('Event notification', ['data' => $data, 'packet' => $event]);
            });
            $waitForReady = [
                Event::GUILD_CREATE,
            ];
            if (!$this->isReady && (array_search($event, $waitForReady) === false)) {
                $this->log->debug('Holding for ready');
                $this->prematurePackets[] = function () use (&$handler, &$deferred, &$data) {
                    $handler->handle($deferred, $data);
                };
            } else {
                $handler->handle($deferred, $data);
            }
        }

        $handlers = [
            //Event::VOICE_SERVER_UPDATE => 'handleVoiceServerUpdate',
            Event::RESUMED             => 'handleResume',
            Event::READY               => 'handleReady',
            Event::GUILD_MEMBERS_CHUNK => 'handleGuildMembersChunk',
            Event::VOICE_STATE_UPDATE  => 'handleVoiceStateUpdate',
        ];

        if (isset($handlers[$event])) {
            $this->{$handlers[$event]}($data, $event);
        }
    }

    protected function handleHello($data, $event)
    {
        $this->log->debug('Hello Discord!');
        $this->heartbeatInterval = $data->heartbeat_interval;
        $hasResumed              = $this->authorize();
        if (!$hasResumed) {
            $this->setupHeartbeat();
        }
    }

    protected function handleHeartbeat($data, $event)
    {
        $this->log->debug('Heartbeat event received');
        $this->send([
            'op' => Enums::OPCODES['Heartbeat'],
            'd'  => $data->d
        ]);
    }

    protected function handleHeartbeatACK($data, $event)
    {
        $this->log->debug('Heartbeat ACK event received');
        $this->heartbeatAcknowledgeTimeout->cancel();
        $this->emit('heartbeat_acknowledge', [$this]);
    }

    protected function handleReconnect($data, $event)
    {
        $this->log->debug('Reconnect event received');
        phpcord(WebSocket::class)->close(1000, 'Reconnect instruction received');
    }

    protected function handleResume($data, $event)
    {
        $this->log->info('Reconnected to Discord Gateway');
        $this->emit('reconnected', [$this]);
    }

    protected function handleInvalidSession($data, $event)
    {
        $this->log->debug('Invalid session, reconnecting...', ['data' => $data]);
        $this->authorize(false);
    }

    public function handleReady($data, $event)
    {
        $this->log->debug('Ready event received');
        if ($this->reconnecting) {
            $this->reconnecting = false;
            return;
        }
        $this->client = new Client((array)$data->user, true);
        phpcord()->instance(Client::class, $this->client);
        $this->sessionId = $data->session_id;

        $private_channels = new PrivateChannelStore();
        if ($this->options['pmChannels']) {
            foreach ($data->private_channels as $channel) {
                $channelPart = new Channel($channel, true);
                phpcord_cache()->forever("pm_channels.{$channelPart->recipient->id}", $channelPart);
                $private_channels->push($channelPart);
            }
            $this->log->info('Stored private channels', ['count' => $private_channels->count()]);
        } else {
            $this->log->info('Not parsing private channels');
        }
        discord()->private_channels = $private_channels;

        $unavailable      = [];
        $guildCreate      = new GuildCreate();
        discord()->guilds = new GuildStore();
        discord()->users  = new UserStore();
        foreach ($data->guilds as $guild) {
            $deferred = new Deferred();
            $deferred->promise()->then(null, function ($d) use (&$unavailable) {
                list($status, $data) = $d;
                if ($status == 'unavailable') {
                    $unavailable[$data] = $data;
                }
            });
            $guildCreate->handle($deferred, $guild);
        }

        $this->log->info('Stored ' . discord()->guilds->count() . ' guilds');
        if (count($unavailable) < 1) {
            return $this->ready();
        }
        // Emit ready after 60 seconds
        phpcord('react')->addTimer(60, function () {
            $this->ready();
        });
        $function = function ($guild) use (&$function, &$unavailable) {
            if (array_key_exists($guild->id, $unavailable)) {
                unset($unavailable[$guild->id]);
            }
            // todo setup timer to continue after x amount of time
            if (count($unavailable) < 1) {
                $this->log->info('All guilds are now available', ['count' => discord()->guilds->count()]);
                $this->removeListener(Event::GUILD_CREATE, $function);
                $this->setupChunking();
            }
        };
        $this->on(Event::GUILD_CREATE, $function);
    }

    protected function setupHeartbeat()
    {
        if (!is_null($this->heartbeat)) {
            $this->heartbeat->cancel();
        }

        phpcord('react')->addPeriodicTimer($this->heartbeatInterval / 1000, [$this, 'heartbeat']);
        $this->heartbeat();
    }

    public function heartbeat()
    {
        $this->log->debug('Heartbeating');
        $payload = [
            'op' => Enums::OPCODES['Heartbeat'],
            'd'  => $this->sequence ?? null
        ];

        $this->send($payload);
        $this->heartbeatAcknowledgeTimeout = phpcord('react')->addTimer($this->heartbeatInterval / 1000, function () {
            if (!$this->connected) {
                return;
            }
            phpcord(WebSocket::class)->close(1001, 'Heartbeat Acknowledge not received');
        });
    }

    protected function ready()
    {
        if ($this->isReady) {
            return false;
        }
        $this->on('ready', function () {
            $this->isReady = true;
        });

        $this->emit('ready', [$this]);
        $this->log->info('Phpcord is ready.');
        foreach ($this->prematurePackets as $prematurePacket) {
            $prematurePacket();
        }
    }

    public function addLargeGuild($guild)
    {
        $this->largeGuilds[] = $guild->id;
    }

    protected function setupChunking()
    {
        if (!$this->options['loadAllMembers']) {
            $this->log->info('loadAllMembers option is disabled, not setting chunking up');
            return $this->ready();
        }
        $checkForChunks = function () {
            if ((count($this->largeGuilds) < 1) && (count($this->largeSent) < 1)) {
                $this->ready();
                return;
            }
            $chunks = array_chunk($this->largeGuilds, 50);
            $this->log->debug('Sending  ' . count($chunks) . ' chunks with ' . count($this->largeGuilds) . ' large guilds.');
            $this->largeSent   = array_merge($this->largeGuilds, $this->largeSent);
            $this->largeGuilds = [];
            $sendChunks        = function () use (&$sendChunks, &$chunks) {
                $chunk = array_pop($chunks);
                if (is_null($chunk)) {
                    return;
                }
                $this->log->debug('Sending chunk with ' . count($chunk) . ' large guilds');
                $payload = [
                    'op' => Enums::OPCODES['RequestGuildMembers'],
                    'd'  => [
                        'guild_id' => $chunk,
                        'query'    => '',
                        'limit'    => 0,
                    ],
                ];
                $this->send($payload);
                phpcord('react')->addTimer(1, $sendChunks);
            };
            $sendChunks();
        };
        phpcord('react')->addPeriodicTimer(5, $checkForChunks);
        $this->log->info('Setting up chunking, checking for chunks every 5 seconds');
        $checkForChunks();
    }

    protected function handleGuildMembersChunk($data, $event)
    {
        $guild   = $this->client->guilds->get('id', $data->guild_id);
        $members = $data->members;
        $this->log->debug('Received guild member chunk', ['guild_id' => $guild->id, 'guild_name' => $guild->name, 'member_count' => count($members)]);
        $count = 0;
        foreach ($members as $member) {
            if (array_key_exists($member->user->id, $guild->members)) {
                continue;
            }
            $member             = (array)$member;
            $member['guild_id'] = $guild->id;
            $member['status']   = 'offline';
            $member['game']     = null;
            $memberPart         = new Member($member, true);
            $guild->members->push($memberPart);
            discord()->users->push($memberPart->user);
            ++$count;
        }
        $this->log->debug('Parsed ' . $count . ' members', ['repository_count' => $guild->members->count(), 'actual_count' => $guild->member_count]);
        if ($guild->members->count() >= $guild->member_count) {
            if (($key = array_search($guild->id, $this->largeSent)) !== false) {
                unset($this->largeSent[$key]);
            }
            $this->log->debug('All users have been loaded', ['guild' => $guild->id, 'member_collection' => $guild->members->count(), 'member_count' => $guild->member_count]);
        }
        if (count($this->largeSent) < 1) {
            $this->ready();
        }
    }

    /**
     * Handles `VOICE_STATE_UPDATE` packets.
     *
     * @param object $data Packet data.
     */
    protected function handleVoiceStateUpdate($data)
    {
        //if (isset($this->voiceClients[$data->d->guild_id])) {
        //    $this->logger->debug('voice state update received', ['guild' => $data->d->guild_id, 'data' => $data->d]);
        //    $this->voiceClients[$data->d->guild_id]->handleVoiceStateUpdate($data->d);
        //}
    }
}