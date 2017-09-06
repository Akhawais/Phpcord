<?php
/**
 * This file is part of PhpCord. This file is subject to the license found at LICENSE.md at the root of this project.
 * Copyright (c) 2017 Dylan Akhawais <dylan@akhawais.co.uk>
 */

namespace Phpcord\Events;

class Handler
{
    protected $handlers = [];

    public function __construct()
    {
        $this->addHandler(Event::MESSAGE_CREATE, MessageCreate::class, ['message']);
        $this->addHandler(Event::GUILD_CREATE, GuildCreate::class);
        $this->addHandler(Event::CHANNEL_CREATE, ChannelCreate::class);
    }

    /**
     * Adds a handler to the list.
     *
     * @param string $event        The WebSocket event name.
     * @param string $classname    The Event class name.
     * @param array  $alternatives Alternative event names for the handler.
     *
     * @return void
     */
    public function addHandler($event, $classname, array $alternatives = [])
    {
        $this->handlers[$event] = [
            'class'        => $classname,
            'alternatives' => $alternatives,
        ];
    }
    /**
     * Returns a handler.
     *
     * @param string $event The WebSocket event name.
     *
     * @return string|null The Event class name or null;
     */
    public function getHandler($event)
    {
        if (isset($this->handlers[$event])) {
            return $this->handlers[$event];
        }
    }
    /**
     * Returns the handlers array.
     *
     * @return array Array of handlers.
     */
    public function getHandlers()
    {
        return $this->handlers;
    }
    /**
     * Returns the handlers.
     *
     * @return array Array of handler events.
     */
    public function getHandlerKeys()
    {
        return array_keys($this->handlers);
    }
    /**
     * Removes a handler.
     *
     * @param string $event The event handler to remove.
     *
     * @return void
     */
    public function removeHandler($event)
    {
        unset($this->handlers[$event]);
    }
}