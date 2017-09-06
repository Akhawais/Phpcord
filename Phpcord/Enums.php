<?php
/**
 * This file is part of PhpCord. This file is subject to the license found at LICENSE.md at the root of this project.
 * Copyright (c) 2017 Dylan Akhawais <dylan@akhawais.co.uk>
 */

namespace Phpcord;

class Enums
{
    const OPS             = [
        0  => 'Dispatch',
        1  => 'Heartbeat',
        2  => 'Identify',
        3  => 'StatusUpdate',
        4  => 'VoiceStatusUpdate',
        5  => 'VoiceServerPing',
        6  => 'Resume',
        7  => 'Reconnect',
        8  => 'RequestGuildMembers',
        9  => 'InvalidSession',
        10 => 'Hello',
        11 => 'HeartbeatACK'
    ];

    const OPCODES = [
        'Dispatch'            => 0,
        'Heartbeat'           => 1,
        'Identify'            => 2,
        'StatusUpdate'        => 3,
        'VoiceStatusUpdate'   => 4,
        'VoiceServerPing'     => 5,
        'Resume'              => 6,
        'Reconnect'           => 7,
        'RequestGuildMembers' => 8,
        'InvalidSession'      => 9,
        'Hello'               => 10,
        'HeartbeatACK'        => 11
    ];

    const GATEWAY_ERRORS = [
        'unknown'               => 4000,
        'unknown_opcode'        => 4001,
        'decode_error'          => 4002,
        'not_authenticated'     => 4003,
        'authentication_failed' => 4004,
        'already_authenticated' => 4005,
        'invalid_seq'           => 4007,
        'rate_limited'          => 4008,
        'session_timeout'       => 4009,
        'invalid_shard'         => 4010,
        'sharding_required'     => 4011,
    ];
}