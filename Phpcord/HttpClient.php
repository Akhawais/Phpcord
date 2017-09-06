<?php
/**
 * This file is part of PhpCord. This file is subject to the license found at LICENSE.md at the root of this project.
 * Copyright (c) 2017 Dylan Akhawais <dylan@akhawais.co.uk>
 */

namespace Phpcord;

use Carbon\Carbon;
use GuzzleHttp\Client;
use React\Promise\Deferred;

class HttpClient
{
    /** Cache TTL in seconds */
    const TTL = 300;

    /** Discord API Base URI */
    const BASE_URI = 'https://discordapp.com/api';

    /** @var  \GuzzleHttp\Client */
    protected $guzzle;

    protected $rateLimited = false;

    protected $backlog = [];

    public function __construct(array $config = [])
    {
        $config = array_merge($config, [
            'base_uri'        => self::BASE_URI . '/v' . Phpcord::API_VERSION,
            'headers'         => [
                'Authorization' => phpcord_token(),
                'User-Agent'    => 'Phpcord/' . Phpcord::PHPCORD_VERSION,
            ],
            'http_errors'     => false,
            'allow_redirects' => true,
        ]);
        $this->guzzle = new Client($config);
    }

    public function request($uri, $method = 'get', $options = [], $cache = true)
    {
        $deferred = new Deferred;
        if ($cache && strtolower($method) === 'get' && phpcord_cache()->has('http-cache.' . sha1($uri))) {
            $deferred->resolve(phpcord_cache()->get('http-cache.' . sha1($uri)));
            return $deferred->promise();
        }

        $count = 0;

        $req = function () use (&$req, &$count, $method, $uri, $options, $cache, $deferred) {
            $guzzle = $this->guzzle->requestAsync($method, $uri, $options);
            $guzzle->then(function ($response) use (&$req, &$count, $deferred, $uri) {
                if ($response->getStatusCode() !== 429 && $response->getHeader('X-RateLimit-Remaining') == 0) {
                    $this->rateLimited = true;
                    $resetTime         = Carbon::createFromTimestampUTC($response->getHeader('X-RateLimit-Reset'));
                    phpcord('react')->addTimer(Carbon::now()->diffInSeconds($resetTime), $this->processBacklog());
                    $deferred->notify('Next request will be rate limited.');
                }

                if ($response->getStatusCode() == 429) {
                    $resetTime         = (int)$response->getHeader('Retry-After')[0] / 1000;
                    $this->rateLimited = true;

                    $deferred = new Deferred;
                    $deferred->promise()->then($req);
                    $this->backlog[] = $deferred;
                    phpcord('react')->addTimer($resetTime, $this->processBacklog());
                    $deferred->notify('This request has been rate limited.');
                } else if (in_array($response->getStatusCode(), [502, 525])) {
                    if ($count > 3) {
                        $deferred->reject($response);
                        return;
                    }

                    phpcord('react')->addTimer(0.1, $req);
                } else if ($response->getStatusCode() < 200 || $response->getStatusCode() > 299) {
                    $deferred->reject($response);
                } else {
                    $json = json_decode($response->getBody());
                    phpcord_cache()->put('http-cache.' . sha1($uri), $json, self::TTL);
                    $deferred->resolve($json);
                }
            }, function ($error) use ($deferred) {
                $deferred->reject($error);
            });
        };

        if ($this->rateLimited) {
            $deferred = new Deferred;
            $deferred->promise()->then($req);
            $this->backlog[] = $deferred;
        } else {
            $req();
        }

        return $deferred->promise();

    }

    protected function processBacklog()
    {
        foreach ($this->backlog as $i => $d) {
            $d->resolve();
            unset($this->backlog[$i]);
        }

        $this->rateLimited = false;
    }
}