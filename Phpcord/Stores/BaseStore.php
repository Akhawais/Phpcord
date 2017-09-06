<?php
/**
 * This file is part of PhpCord. This file is subject to the license found at LICENSE.md at the root of this project.
 * Copyright (c) 2017 Dylan Akhawais <dylan@akhawais.co.uk>
 */

namespace Phpcord\Stores;

use Phpcord\Collection;
use Phpcord\Discord\Model;
use React\Promise\Deferred;

/**
 * Class BaseStore
 * @package Phpcord\Stores
 *
 */
abstract class BaseStore implements \ArrayAccess, \Countable, \IteratorAggregate, StoreInterface
{
    protected $model = Model::class;

    protected $endpoints = [];

    /** @var  Collection */
    protected $items;

    protected $vars = [];

    /**
     * Builds a new, empty model.
     *
     * @param array $attributes The attributes for the new model.
     *
     * @return Model The new model.
     */
    public function create(array $attributes = [])
    {
        // TODO: Implement create() method.
    }

    /**
     * Attempts to save a model to the Discord servers.
     *
     * @param Model $model The model to save.
     *
     * @return PromiseInterface
     */
    public function save(Model &$model)
    {
        // TODO: Implement save() method.
    }

    /**
     * Attempts to delete a model on the Discord servers.
     *
     * @param Model $model The model to delete.
     *
     * @return PromiseInterface
     */
    public function delete(Model &$model)
    {
        // TODO: Implement delete() method.
    }

    /**
     * Returns a model with fresh values.
     *
     * @param Model $model The model to get fresh values.
     *
     * @return PromiseInterface
     */
    public function fresh(Model &$model)
    {
        // TODO: Implement fresh() method.
    }

    /**
     * Force gets a model from the Discord servers.
     *
     * @param string $id The ID to search for.
     *
     * @return PromiseInterface
     */
    public function fetch($id)
    {
        // TODO: Implement fetch() method.
    }

    public function __construct(array $vars = [])
    {
        $this->items = new Collection();
        $this->vars  = $vars;
    }

    /**
     * Replaces variables in string with syntax :{varname}.
     *
     * @param string $string A string with placeholders.
     *
     * @return string A string with placeholders replaced.
     */
    protected function replaceWithVariables($string)
    {
        if (preg_match_all('/:([a-z_]+)/', $string, $matches)) {
            list(
                $original,
                $vars
                ) = $matches;
            foreach ($vars as $key => $var) {
                if (isset($this->vars[$var])) {
                    $string = str_replace($original[$key], $this->vars[$var], $string);
                }
            }
        }
        return $string;
    }

    /**
     * Returns how many items are in the repository.
     *
     * @return int Count.
     */
    public function count()
    {
        return $this->items->count();
    }
    /**
     * Get an iterator for the items.
     *
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        return $this->items->getIterator();
    }
    /**
     * Determine if an item exists at an offset.
     *
     * @param mixed $key
     *
     * @return bool
     */
    public function offsetExists($key)
    {
        return $this->items->offsetExists($key);
    }
    /**
     * Get an item at a given offset.
     *
     * @param mixed $key
     *
     * @return mixed
     */
    public function offsetGet($key)
    {
        return $this->items->offsetGet($key);
    }
    /**
     * Set the item at a given offset.
     *
     * @param mixed $key
     * @param mixed $value
     *
     * @return void
     */
    public function offsetSet($key, $value)
    {
        $this->items->offsetSet($key, $value);
    }
    /**
     * Unset the item at a given offset.
     *
     * @param string $key
     *
     * @return void
     */
    public function offsetUnset($key)
    {
        $this->items->offsetUnset($key);
    }
    /**
     * Convert the object into something JSON serializable.
     *
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->items->jsonSerialize();
    }
    /**
     * Handles debug calls from var_dump and similar functions.
     *
     * @return array An array of attributes.
     */
    public function __debugInfo()
    {
        return $this->all();
    }
    /**
     * Handles dynamic calls to the repository.
     *
     * @param string $function The function called.
     * @param array  $params   Array of parameters.
     *
     * @return mixed
     */
    public function __call($function, array $params)
    {
        return call_user_func_array([$this->items, $function], $params);
    }
}