<?php
/**
 * This file is part of PhpCord. This file is subject to the license found at LICENSE.md at the root of this project.
 * Copyright (c) 2017 Dylan Akhawais <dylan@akhawais.co.uk>
 */

namespace Phpcord\Events;

use Phpcord\Discord\Message;
use React\Promise\Deferred;

class MessageCreate extends Event
{

    /**
     * Transforms the given data, and updates the
     * Discord instance if necessary.
     *
     * @param Deferred $deferred The promise to use
     * @param array    $data The data that was sent with the WebSocket
     */
    public function handle(Deferred $deferred, $data)
    {
        $message = new Message((array) $data, true);
        $message->channel->last_message_id = $message->id;
        $message->channel->save();
        $deferred->resolve($message);
    }
}