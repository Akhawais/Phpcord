<?php
/**
 * This file is part of PhpCord. This file is subject to the license found at LICENSE.md at the root of this project.
 * Copyright (c) 2017 Dylan Akhawais <dylan@akhawais.co.uk>
 */

namespace Phpcord\Discord;

use Carbon\Carbon;
use Phpcord\Collection;

/**
 * A message which is posted to a Discord text channel.
 *
 * @property string                   $id               The unique identifier of the message.
 * @property \Phpcord\Discord\Channel $channel          The channel that the message was sent in.
 * @property string                   $channel_id       The unique identifier of the channel that the message was went in.
 * @property string                   $content          The content of the message if it is a normal message.
 * @property int                      $type             The type of message.
 * @property Collection[User]               $mentions         A collection of the users mentioned in the message.
 * @property User                     $author           The author of the message.
 * @property bool                     $mention_everyone Whether the message contained an @everyone mention.
 * @property Carbon                   $timestamp        A timestamp of when the message was sent.
 * @property Carbon|null              $edited_timestamp A timestamp of when the message was edited, or null.
 * @property bool                     $tts              Whether the message was sent as a text-to-speech message.
 * @property array                    $attachments      An array of attachment objects.
 * @property Collection[Embed]              $embeds           A collection of embed objects.
 * @property string|null              $nonce            A randomly generated string that provides verification for the client. Not required.
 * @property Collection[Role]               $mention_roles    A collection of roles that were mentioned in the message.
 * @property bool                     $pinned           Whether the message is pinned to the channel.
 */
class Message extends Model
{
    const DEFAULT                = 0;
    const RECIPIENT_ADDED        = 1;
    const RECIPIENT_REMOVED      = 2;
    const CALL                   = 3;
    const CHANNEL_NAME_CHANGE    = 4;
    const CHANNEL_ICON_CHANGE    = 5;
    const CHANNEL_PINNED_MESSAGE = 6;
    const GUILD_MEMBER_JOIN      = 7;

    /**
     * {@inheritdoc}
     */
    protected $fillable = [
        'id',
        'channel_id',
        'content',
        'type',
        'mentions',
        'author',
        'mention_everyone',
        'timestamp',
        'edited_timestamp',
        'tts',
        'attachments',
        'embeds',
        'nonce',
        'mention_roles',
        'pinned',
    ];

    /**
     * Replies to the message.
     *
     * @param string $text The text to reply with.
     *
     * @return \React\Promise\Promise
     */
    public function reply($text)
    {
        return $this->channel->sendMessage("{$this->author}, {$text}");
    }

    /**
     * Returns the channel attribute.
     *
     * @return Channel The channel the message was sent in.
     */
    public function getChannelAttribute()
    {
        if (phpcord_cache()->has("pm_channels.{$this->channel_id}")) {
            return phpcord_cache()->get("pm_channels.{$this->channel_id}");
        }

        foreach (discord()->guilds as $guild) {
            if ($guild->channels->has($this->channel_id)) {
                return $guild->channels->get('id', $this->channel_id);
            }
        }

        return new Channel([
            'id'   => $this->channel_id,
            'type' => Channel::TYPE_DM,
        ], true);
    }

    /**
     * Returns the mention_roles attribute.
     *
     * @return Collection The roles that were mentioned.
     */
    public function getMentionRolesAttribute()
    {
        $roles = new Collection([], 'id');

        foreach ($this->channel->guild->roles as $role) {
            if (array_search($role->id, $this->attributes['mention_roles']) !== false) {
                $roles->push($role);
            }
        }

        return $roles;
    }

    /**
     * Returns the mention attribute.
     *
     * @return Collection The users that were mentioned.
     */
    public function getMentionsAttribute()
    {
        $users = new Collection([], 'id');

        foreach ($this->attributes['mentions'] as $mention) {
            $users->push(new User($mention, true));
        }

        return $users;
    }

    /**
     * Returns the author attribute.
     *
     * @return Member|User The member that sent the message. Will return a User object if it is a PM.
     */
    public function getAuthorAttribute()
    {
        if ($this->channel->type != Channel::TYPE_TEXT) {
            return new User((array) $this->attributes['author'], true);
        }

        return $this->channel->guild->members->get('id', $this->attributes['author']->id);
    }

    /**
     * Returns the embed attribute.
     *
     * @return Collection A collection of embeds.
     *
     * public function getEmbedsAttribute()
     * {
     * $embeds = new Collection();
     *
     * foreach ($this->attributes['embeds'] as $embed) {
     * $embeds->push($this->factory->create(Embed::class, $embed, true));
     * }
     *
     * return $embeds;
     * }*/

    /**
     * Returns the timestamp attribute.
     *
     * @return Carbon The time that the message was sent.
     */
    public function getTimestampAttribute()
    {
        return new Carbon($this->attributes['timestamp']);
    }

    /**
     * Returns the edited_timestamp attribute.
     *
     * @return Carbon|null The time that the message was edited.
     */
    public function getEditedTimestampAttribute()
    {
        if (!$this->attributes['edited_timestamp']) {
            return;
        }

        return new Carbon($this->attributes['edited_timestamp']);
    }

    /**
     * {@inheritdoc}
     */
    public function getCreatableAttributes()
    {
        return [
            'content'  => $this->content,
            'mentions' => $this->mentions,
            'tts'      => $this->tts,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getUpdatableAttributes()
    {
        return [
            'content'  => $this->content,
            'mentions' => $this->mentions,
        ];
    }
}