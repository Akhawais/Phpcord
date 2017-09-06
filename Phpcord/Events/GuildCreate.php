<?php
/**
 * This file is part of PhpCord. This file is subject to the license found at LICENSE.md at the root of this project.
 * Copyright (c) 2017 Dylan Akhawais <dylan@akhawais.co.uk>
 */

namespace Phpcord\Events;

use Phpcord\Discord\Channel;
use Phpcord\Discord\Guild;
use Phpcord\Discord\Member;
use Phpcord\Discord\Role;
use Phpcord\Stores\ChannelStore;
use Phpcord\Stores\MemberStore;
use Phpcord\Stores\RoleStore;
use React\Promise\Deferred;

class GuildCreate extends Event
{
    /**
     * {@inheritdoc}
     */
    public function handle(Deferred $deferred, $data)
    {
        if (isset($data->unavailable) && $data->unavailable) {
            $deferred->reject(['unavailable', $data->id]);

            return $deferred->promise();
        }

        $guildPart = new Guild((array) $data, true);

        $roles = new RoleStore($guildPart->getStoreAttributes());

        foreach ($data->roles as $role) {
            $role             = (array) $role;
            $role['guild_id'] = $guildPart->id;
            $rolePart         = new Role($role, true);

            $roles->push($rolePart);
        }

        $channels = new ChannelStore($guildPart->getStoreAttributes());

        foreach ($data->channels as $channel) {
            $channel             = (array) $channel;
            $channel['guild_id'] = $data->id;
            $channelPart         = new Channel($channel, true);

            $channels->push($channelPart);
        }

        $members = new MemberStore(
            $guildPart->getStoreAttributes()
        );

        foreach ($data->members as $member) {
            $memberPart = new Member([
                'user'      => $member->user,
                'roles'     => $member->roles,
                'mute'      => $member->mute,
                'deaf'      => $member->deaf,
                'joined_at' => $member->joined_at,
                'nick'      => (property_exists($member, 'nick')) ? $member->nick : null,
                'guild_id'  => $data->id,
                'status'    => 'offline',
                'game'      => null,
            ], true);

            foreach ($data->presences as $presence) {
                if ($presence->user->id == $member->user->id) {
                    $memberPart->status = $presence->status;
                    $memberPart->game   = $presence->game;
                }
            }

            discord()->users->push($memberPart->user);
            $members->push($memberPart);
        }

        $guildPart->roles    = $roles;
        $guildPart->channels = $channels;
        $guildPart->members  = $members;

        /*foreach ($data->voice_states as $state) {
            if ($channel = $guildPart->channels->get('id', $state->channel_id)) {
                $channel->members->push($this->factory->create(VoiceStateUpdatePart::class, (array) $state, true));
            }
        }*/

        $resolve = function () use (&$guildPart, $deferred) {
            if ($guildPart->large) {
                client()->addLargeGuild($guildPart);
            }

            discord()->guilds->push($guildPart);

            $deferred->resolve($guildPart);
        };

        /*if ($this->discord->options['retrieveBans']) {
            $this->http->get("guilds/{$guildPart->id}/bans")->then(function ($rawBans) use (&$guildPart, $resolve) {
                $bans = new BanRepository(
                    $this->http,
                    $this->cache,
                    $this->factory,
                    $guildPart->getRepositoryAttributes()
                );

                foreach ($rawBans as $ban) {
                    $ban = (array) $ban;
                    $ban['guild'] = $guildPart;

                    $banPart = $this->factory->create(Ban::class, $ban, true);

                    $bans->push($banPart);
                }

                $guildPart->bans = $bans;
                $resolve();
            }, $resolve);
        } else {
            $resolve();
        }*/

        $resolve();
    }
}