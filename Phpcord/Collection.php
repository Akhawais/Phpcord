<?php
/**
 * This file is part of PhpCord. This file is subject to the license found at LICENSE.md at the root of this project.
 * Copyright (c) 2017 Dylan Akhawais <dylan@akhawais.co.uk>
 */

namespace Phpcord;

use Illuminate\Support\Collection as BaseCollection;

class Collection extends BaseCollection
{
    protected $discrim = 'id';

    /**
     * {@inheritdoc}
     *
     * @param string $discrim The discriminator.
     */
    public function __construct($items = [], $discrim = 'id')
    {
        $this->discrim = $discrim;

        parent::__construct($items);
    }

    /**
     * Get an item from the collection with a key and value.
     *
     * @param mixed $key   The key to match with the value.
     * @param mixed $value The value to match with the key.
     *
     * @return mixed The value or null.
     */
    public function get($key, $value = null)
    {
        if ($key == $this->discrim && array_key_exists($value, $this->items)) {
            return $this->items[$value];
        }

        foreach ($this->items as $item) {
            if (is_array($item)) {
                if ($item[$key] == $value) {
                    return $item;
                }
            } elseif (is_object($item)) {
                if ($item->{$key} == $value) {
                    return $item;
                }
            }
        }
    }

    /**
     * Gets a collection of items from the repository with a key and value.
     *
     * @param mixed $key   The key to match with the value.
     * @param mixed $value The value to match with the key.
     *
     * @return Collection A collection.
     */
    public function getAll($key, $value = null)
    {
        $collection = new self();

        foreach ($this->items as $item) {
            if ($item->{$key} == $value) {
                $collection->push($item);
            }
        }

        return $collection;
    }

    /**
     * {@inheritdoc}
     */
    public function offsetSet($key, $value)
    {
        if (! is_null($this->discrim)) {
            if (! is_array($value)) {
                $this->items[$value->{$this->discrim}] = $value;
            } else {
                $this->items[$value[$this->discrim]] = $value;
            }

            return;
        }

        if (is_null($key)) {
            $this->items[] = $value;
        } else {
            $this->items[$key] = $value;
        }
    }

    /**
     * Handles debug calls from var_dump and similar functions.
     *
     * @return array An array of public attributes.
     */
    public function __debugInfo()
    {
        return $this->items;
    }
}