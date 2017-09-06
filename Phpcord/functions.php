<?php
/**
 * This file is part of PhpCord. This file is subject to the license found at LICENSE.md at the root of this project.
 * Copyright (c) 2017 Dylan Akhawais <dylan@akhawais.co.uk>
 */

if (!function_exists('phpcord')) {
    /**
     * Get the available Phpcord container instance.
     *
     * @param  string $make
     * @param  array  $parameters
     *
     * @return mixed|\Illuminate\Contracts\Container\Container
     */
    function phpcord($make = null, $parameters = [])
    {
        if (is_null($make)) {
            return Phpcord\Container::getInstance();
        }
        return Phpcord\Container::getInstance()->make($make, $parameters);
    }
}
if (!function_exists('client')) {
    /**
     * Get the available Phpcord instance.
     *
     * @return \Phpcord\Phpcord
     */
    function client()
    {
        return phpcord(Phpcord\Phpcord::class);
    }
}
if (!function_exists('discord')) {
    /**
     * Get the available Discord client instance.
     *
     * @return \Phpcord\Discord\Client
     */
    function discord()
    {
        return phpcord(\Phpcord\Discord\Client::class);
    }
}
if (!function_exists('monolog')) {
    /**
     * Get the available Phpcord logging instance.
     *
     * @return \Monolog\Logger
     */
    function monolog()
    {
        return phpcord(Monolog\Logger::class);
    }
}
if (!function_exists('phpcord_config')) {
    /**
     * Get / set the specified configuration value.
     *
     * If an array is passed as the key, we will assume you want to set an array of values.
     *
     * @param  array|string $key
     * @param  mixed        $default
     *
     * @return mixed
     */
    function phpcord_config($key = null, $default = null)
    {
        if (is_null($key)) {
            return phpcord('config');
        }
        if (is_array($key)) {
            return phpcord('config')->set($key);
        }
        return phpcord('config')->get($key, $default);
    }
}
if (!function_exists('phpcord_storage')) {
    /**
     * Get / set the specified storage value.
     *
     * If an array is passed as the key, we will assume you want to set an array of values.
     *
     * @param  array|string $key
     * @param  mixed        $default
     *
     * @return mixed
     */
    function phpcord_storage($key = null, $default = null)
    {
        if (is_null($key)) {
            return phpcord('storage');
        }
        if (is_array($key)) {
            return phpcord('storage')->set($key);
        }
        return phpcord('storage')->get($key, $default);
    }
}
if (!function_exists('phpcord_cache')) {
    /**
     * @return \Illuminate\Cache\RedisStore
     */
    function phpcord_cache()
    {
        return phpcord('cache');
    }
}
if (!function_exists('phpcord_token')) {
    function phpcord_token()
    {
        return phpcord_config('token');
    }
}
if (!function_exists('phpcord_http')) {
    /**
     * @return \Phpcord\HttpClient
     */
    function phpcord_http()
    {
        return phpcord('http');
    }
}
if (!function_exists('mentioned')) {
    /**
     * Checks to see if a part has been mentioned.
     *
     * @param \Phpcord\Discord\Part|string $part    The part or mention to look for.
     * @param Message     $message The message to check.
     *
     * @return bool Whether the part was mentioned.
     */
    function mentioned($part, Message $message)
    {
        if ($part instanceof User || $part instanceof Member) {
            return $message->mentions->has($part->id);
        } elseif ($part instanceof Role) {
            return $message->mention_roles->has($part->id);
        } elseif ($part instanceof Channel) {
            return strpos($message->content, "<#{$part->id}>") !== false;
        } else {
            return strpos($message->content, $part) !== false;
        }
    }
}