<?php
/**
 * This file is part of PhpCord. This file is subject to the license found at LICENSE.md at the root of this project.
 * Copyright (c) 2017 Dylan Akhawais <dylan@akhawais.co.uk>
 */

namespace Phpcord\Events;

use Evenement\EventEmitterTrait;
use React\Promise\Deferred;

abstract class Event
{
    use EventEmitterTrait;

    const READY                = 'READY';
    const RESUMED              = 'RESUMED';
    const PRESENCE_UPDATE      = 'PRESENCE_UPDATE';
    const PRESENCES_REPLACE    = 'PRESENCES_REPLACE';
    const TYPING_START         = 'TYPING_START';
    const USER_SETTINGS_UPDATE = 'USER_SETTINGS_UPDATE';
    const VOICE_STATE_UPDATE   = 'VOICE_STATE_UPDATE';
    const VOICE_SERVER_UPDATE  = 'VOICE_SERVER_UPDATE';
    const GUILD_MEMBERS_CHUNK  = 'GUILD_MEMBERS_CHUNK';
    // Guild
    const GUILD_CREATE        = 'GUILD_CREATE';
    const GUILD_DELETE        = 'GUILD_DELETE';
    const GUILD_UPDATE        = 'GUILD_UPDATE';
    const GUILD_BAN_ADD       = 'GUILD_BAN_ADD';
    const GUILD_BAN_REMOVE    = 'GUILD_BAN_REMOVE';
    const GUILD_MEMBER_ADD    = 'GUILD_MEMBER_ADD';
    const GUILD_MEMBER_REMOVE = 'GUILD_MEMBER_REMOVE';
    const GUILD_MEMBER_UPDATE = 'GUILD_MEMBER_UPDATE';
    const GUILD_ROLE_CREATE   = 'GUILD_ROLE_CREATE';
    const GUILD_ROLE_UPDATE   = 'GUILD_ROLE_UPDATE';
    const GUILD_ROLE_DELETE   = 'GUILD_ROLE_DELETE';
    // Channel
    const CHANNEL_CREATE = 'CHANNEL_CREATE';
    const CHANNEL_DELETE = 'CHANNEL_DELETE';
    const CHANNEL_UPDATE = 'CHANNEL_UPDATE';
    // Messages
    const MESSAGE_CREATE      = 'MESSAGE_CREATE';
    const MESSAGE_DELETE      = 'MESSAGE_DELETE';
    const MESSAGE_UPDATE      = 'MESSAGE_UPDATE';
    const MESSAGE_DELETE_BULK = 'MESSAGE_DELETE_BULK';

    /**
     * Transforms the given data, and updates the
     * Discord instance if necessary.
     *
     * @param Deferred $deferred The promise to use
     * @param array    $data     The data that was sent with the WebSocket
     */
    abstract public function handle(Deferred $deferred, $data);
}