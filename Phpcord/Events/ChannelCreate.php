<?php
/**
 * This file is part of PhpCord. This file is subject to the license found at LICENSE.md at the root of this project.
 * Copyright (c) 2017 Dylan Akhawais <dylan@akhawais.co.uk>
 */

namespace Phpcord\Events;

use Phpcord\Discord\Channel;
use React\Promise\Deferred;

class ChannelCreate extends Event
{

    /**
     * {@inheritdoc}
     */
    public function handle(Deferred $deferred, $data)
    {
        $channel = new Channel((array) $data, true);

        if (!in_array($channel->type, [Channel::TYPE_TEXT, Channel::TYPE_VOICE])) {
            discord()->private_channels->push($channel);
            phpcord_cache()->forever("pm_channels.{$channel->id}", $channel);
        } else {
            $guild = discord()->guilds->get('id', $channel->guild_id);
            $guild->channels->push($channel);
        }

        $deferred->resolve($channel);
    }
}