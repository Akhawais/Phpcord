<?php

/*
 * This file is apart of the DiscordPHP project.
 *
 * Copyright (c) 2016 David Cole <david@team-reflex.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the LICENSE.md file.
 */

namespace Phpcord\Discord;

/**
 * The OAuth2 application of the bot.
 *
 * @property string                   $id          The client ID of the OAuth application.
 * @property string                   $name        The name of the OAuth application.
 * @property string                   $description The description of the OAuth application.
 * @property string                   $icon        The icon hash of the OAuth application.
 * @property string                   $invite_url  The invite URL to invite the bot to a guild.
 * @property array[string]            $rpc_origins An array of RPC origin URLs.
 * @property int                      $flags       ?
 * @property User $owner       The owner of the OAuth application.
 */
class Application extends Model
{
    /**
     * {@inheritdoc}
     */
    protected $fillable = ['id', 'name', 'description', 'icon', 'rpc_origins', 'flags', 'owner'];

    /**
     * Returns the owner of the application.
     *
     * @return User Owner of the application.
     */
    public function getOwnerAttribute()
    {
        return new User((array) $this->attributes['owner'], true);
    }

    /**
     * Returns the invite URL for the application.
     *
     * @param int $permissions Permissions to set.
     *
     * @return string Invite URL.
     */
    public function getInviteURLAttribute($permissions = 0)
    {
        return "https://discordapp.com/oauth2/authorize?client_id={$this->id}&scope=bot&permissions={$permissions}";
    }

    public function getUpdatableAttributes()
    {
        // TODO: Implement getUpdatableAttributes() method.
    }

    public function getCreatableAttributes()
    {
        // TODO: Implement getCreatableAttributes() method.
    }
}
