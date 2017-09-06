<?php
/**
 * This file is part of PhpCord. This file is subject to the license found at LICENSE.md at the root of this project.
 * Copyright (c) 2017 Dylan Akhawais <dylan@akhawais.co.uk>
 */

namespace Phpcord;

class Container
{
    /**
     * The current globally available container (if any).
     *
     * @var \Illuminate\Contracts\Container\Container
     */
    protected static $instance;

    /**
     * Set the globally available instance of the container.
     *
     * @return \Illuminate\Contracts\Container\Container
     */
    public static function getInstance()
    {
        if (is_null(static::$instance)) {
            static::$instance = new static;
        }

        return static::$instance;
    }

    /**
     * Set the shared instance of the container.
     *
     * @param  \Illuminate\Contracts\Container\Container|null  $container
     * @return \Illuminate\Contracts\Container\Container
     */
    public static function setInstance(\Illuminate\Contracts\Container\Container $container = null)
    {
        return static::$instance = $container;
    }
}