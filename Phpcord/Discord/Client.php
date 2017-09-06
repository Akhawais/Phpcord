<?php
/**
 * This file is part of PhpCord. This file is subject to the license found at LICENSE.md at the root of this project.
 * Copyright (c) 2017 Dylan Akhawais <dylan@akhawais.co.uk>
 */

namespace Phpcord\Discord;

use Phpcord\Stores\GuildStore;
use Phpcord\Stores\PrivateChannelRepositoryStore;
use Phpcord\Stores\PrivateChannelStore;
use Phpcord\Stores\UserStore;
use React\Promise\Deferred;

/**
 * The client is the main interface for the client. Most calls on the main class are forwarded here.
 *
 * @property string                                       $id            The unique identifier of the client.
 * @property string                                       $username      The username of the client.
 * @property bool                                         $verified      Whether the client has verified their email.
 * @property string                                       $avatar        The avatar URL of the client.
 * @property string                                       $avatar_hash   The avatar hash of the client.
 * @property string                                       $discriminator The unique discriminator of the client.
 * @property bool                                         $bot           Whether the client is a bot.
 * @property User                                         $user          The user instance of the client.
 * @property Application                                  $application   The OAuth2 application of the bot.
 * @property GuildStore                                   $guilds
 * @property \Phpcord\Stores\PrivateChannelStore          $private_channels
 * @property UserStore                                    $users
 */
class Client extends Model
{
    /**
     * {@inheritdoc}
     */
    protected $fillable = ['id', 'username', 'verified', 'avatar', 'discriminator', 'user', 'application'];

    /**
     * {@inheritdoc}
     */
    protected $stores = [
        'guilds'           => GuildStore::class,
        'private_channels' => PrivateChannelStore::class,
        'users'            => UserStore::class,
    ];

    /**
     * Runs any extra construction tasks.
     *
     * @return void
     */
    public function afterConstruct()
    {
        $this->user        = new User(
            [
                'id'            => $this->id,
                'username'      => $this->username,
                'avatar'        => $this->attributes['avatar'],
                'discriminator' => $this->discriminator,
            ], true
        );
        $this->application = new Application([], true);

        phpcord_http()->request('oauth2/applications/@me')->then(function ($response) {
            $this->application->fill((array)$response);
        });
    }

    /**
     * Returns the avatar URL for the client.
     *
     * @return string The URL to the client's avatar.
     */
    public function getAvatarAttribute()
    {
        if (empty($this->attributes['avatar'])) {
            return;
        }

        return "https://discordapp.com/api/users/{$this->id}/avatars/{$this->attributes['avatar']}.jpg";
    }

    /**
     * Returns the avatar hash for the client.
     *
     * @return string The avatar hash for the client.
     */
    public function getAvatarHashAttribute()
    {
        return $this->attributes['avatar'];
    }

    /**
     * Saves the client instance.
     *
     * @return \React\Promise\Promise
     */
    public function save()
    {
        $deferred = new Deferred();

        phpcord_http()->request('users/@me', 'patch', $this->getUpdatableAttributes())->then(
            \React\Partial\bind_right([$this, 'resolve'], $deferred),
            \React\Partial\bind_right([$this, 'reject'], $deferred)
        );

        return $deferred->promise();
    }

    /**
     * {@inheritdoc}
     */
    public function getUpdatableAttributes()
    {
        $attributes = [
            'username' => $this->attributes['username'],
        ];

        if (isset($this->attributes['avatarhash'])) {
            $attributes['avatar'] = $this->attributes['avatarhash'];
        }

        /*if (! $this->bot) {
            if (empty($this->attributes['password'])) {
                throw new PasswordEmptyException('You must enter your password to update your profile.');
            }

            $attributes['email']    = $this->email;
            $attributes['password'] = $this->attributes['password'];

            if (! empty($this->attributes['new_password'])) {
                $attributes['new_password'] = $this->attributes['new_password'];
            }
        }*/

        return $attributes;
    }

    /**
     * {@inheritdoc}
     */
    public function getStoreAttributes()
    {
        return [];
    }

    public function getCreatableAttributes()
    {
        // TODO: Implement getCreatableAttributes() method.
    }
}