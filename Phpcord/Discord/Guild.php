<?php
/**
 * This file is part of PhpCord. This file is subject to the license found at LICENSE.md at the root of this project.
 * Copyright (c) 2017 Dylan Akhawais <dylan@akhawais.co.uk>
 */

namespace Phpcord\Discord;

use Phpcord\Stores\ChannelStore;
use Phpcord\Stores\MemberStore;
use Phpcord\Stores\RoleStore;

class Guild extends Model
{
    protected $fillable = [
        'id',
        'name',
        'icon',
        'region',
        'owner_id',
        'roles',
        'joined_at',
        'afk_channel_id',
        'afk_timeout',
        'embed_enabled',
        'embed_channel_id',
        'features',
        'splash',
        'emojis',
        'large',
        'verification_level',
        'member_count',
        'default_message_notifications',
        'explicit_content_filter',
        'mfa_level'
    ];

    protected $stores = [
        'members'  => MemberStore::class,
        'roles'    => RoleStore::class,
        'channels' => ChannelStore::class,
        //'bans'     => Repository\BanRepository::class,
        //'invites'  => Repository\InviteRepository::class,
        //'emojis'   => Repository\EmojiRepository::class,
    ];

    public function getCreatableAttributes()
    {
        return [
            'name'   => $this->name,
            'region' => $this->region,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getUpdatableAttributes()
    {
        return [
            'name'                          => $this->name,
            'region'                        => $this->region,
            'logo'                          => $this->logo,
            'splash'                        => $this->splash,
            'verification_level'            => $this->verification_level,
            'afk_channel_id'                => $this->afk_channel_id,
            'afk_timeout'                   => $this->afk_timeout,
            'default_message_notifications' => $this->default_message_notifications,
            'explicit_content_filter'       => $this->explicit_content_filter,
            'mfa_level'                     => $this->mfa_level,
        ];
    }

    public function getStoreAttributes()
    {
        return [
            'guild_id' => $this->id,
        ];
    }
}