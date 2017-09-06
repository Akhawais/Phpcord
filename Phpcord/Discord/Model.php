<?php
/**
 * This file is part of PhpCord. This file is subject to the license found at LICENSE.md at the root of this project.
 * Copyright (c) 2017 Dylan Akhawais <dylan@akhawais.co.uk>
 */

namespace Phpcord\Discord;

use ArrayAccess;
use Carbon\Carbon;
use Illuminate\Support\Str;
use JsonSerializable;
use Phpcord\Phpcord;
use React\Promise\Promise;
use Serializable;

abstract class Model implements ArrayAccess, Serializable, JsonSerializable
{

    /**
     * The parts fillable attributes.
     *
     * @var array The array of attributes that can be mass-assigned.
     */
    protected $fillable = [];

    /**
     * The parts attributes.
     *
     * @var array The parts attributes and content.
     */
    protected $attributes = [];

    /**
     * The parts attributes cache.
     *
     * @var array Attributes which are cached such as parts that are retrieved over REST.
     */
    protected $attributes_cache = [];

    /**
     * Attributes that are hidden from debug info.
     *
     * @var array Attributes that are hidden from public.
     */
    protected $hidden = [];

    /**
     * An array of stores that can exist in a part.
     *
     * @var array Stores.
     */
    protected $stores = [];

    /**
     * Is the part already created in the Discord servers?
     *
     * @var bool Whether the part has been created.
     */
    public $created = false;

    /**
     * The regex pattern to replace variables with.
     *
     * @var string The regex which is used to replace placeholders.
     */
    protected $regex = '/:([a-z_]+)/';

    /**
     * Should we fill the part after saving?
     *
     * @var bool Whether the part will be saved after being filled.
     */
    protected $fillAfterSave = true;

    /**
     * Create a new part instance.
     *
     * @param array        $attributes An array of attributes to build the part.
     * @param bool         $created    Whether the part has already been created.
     *
     * @return void
     */
    public function __construct(
        $attributes = [],
        $created = false
    ) {
        $attributes = (array) $attributes;
        $this->created = $created;
        $this->fill($attributes);

        if (is_callable([$this, 'afterConstruct'])) {
            $this->afterConstruct();
        }
    }

    /**
     * @param \Psr\Http\Message\ResponseInterface $response
     * @param \React\Promise\Deferred $deferred
     */
    public function resolve($response, $deferred) {
        $deferred->resolve(true);
    }

    /**
     * @param \GuzzleHttp\Psr7\Response|\Exception $e
     * @param \React\Promise\Deferred $deferred
     */
    public function reject($e, $deferred) {
        monolog()->debug('Model HTTP request rejected', (method_exists($e, 'getBody')) ? ['response' => $e->getBody()->getContents(), 'code' => $e->getStatusCode()] : ['e' => $e->getMessage()]);
        $deferred->reject($e);
    }

    /**
     * Fills the parts attributes from an array.
     *
     * @param array $attributes An array of attributes to build the part.
     *
     * @return void
     */
    public function fill($attributes)
    {
        foreach ($attributes as $key => $value) {
            if (in_array($key, $this->fillable)) {
                $this->setAttribute($key, $value);
            }
        }
    }

    /**
     * Clears the attribute cache.
     *
     * @return bool Whether the attempt to clear the cache succeeded or failed.
     */
    public function clearCache()
    {
        $this->attributes_cache = [];

        return true;
    }

    abstract public function getUpdatableAttributes();
    abstract public function getCreatableAttributes();

    /**
     * Checks if there is a mutator present.
     *
     * @param string $key  The attribute name to check.
     * @param string $type Either get or set.
     *
     * @return mixed Either a string if it is callable or false.
     */
    public function checkForMutator($key, $type)
    {
        $str = $type.Str::studly($key).'Attribute';

        if (is_callable([$this, $str])) {
            return $str;
        }

        return false;
    }

    /**
     * Replaces variables in string with syntax :{varname}.
     *
     * @param string $string A string with placeholders.
     *
     * @return string A string with placeholders replaced.
     */
    public function replaceWithVariables($string)
    {
        $matches = null;
        $matcher = preg_match_all($this->regex, $string, $matches);

        $original = $matches[0];
        $vars     = $matches[1];

        foreach ($vars as $key => $variable) {
            if ($attribute = $this->getAttribute($variable)) {
                $string = str_replace($original[$key], $attribute, $string);
            }
        }

        return $string;
    }

    /**
     * Replaces variables in one of the URIs.
     *
     * @param string $key    A key from URIs.
     * @param array  $params Parameters to replace placeholders with.
     *
     * @return string A string with placeholders replaced.
     *
     * @see self::$uris The URIs that can be replaced.
     */
    public function uriReplace($key, $params)
    {
        $string = $this->uris[$key];

        $matches = null;
        $matcher = preg_match_all($this->regex, $string, $matches);

        $original = $matches[0];
        $vars     = $matches[1];

        foreach ($vars as $key => $variable) {
            if ($attribute = $params[$variable]) {
                $string = str_replace($original[$key], $attribute, $string);
            }
        }

        return $string;
    }

    /**
     * Gets an attribute on the part.
     *
     * @param string $key The key to the attribute.
     *
     * @return mixed Either the attribute if it exists or void.
     */
    public function getAttribute($key)
    {
        /*if (isset($this->stores[$key])) {
            $className = str_replace('\\', '', $this->stores[$key]);

            if (cache()->has("stores.{$className}.{$this->id}.{$key}")) {
                return cache()->get("stores.{$className}.{$this->id}.{$key}");
            }

            $class = $this->stores[$key];

            $thing = new $class(
                $this->getStoreAttributes()
            );

            cache()->forever(
                "stores.{$className}.{$this->id}.{$key}",
                $thing
            );

            return $thing;
        }*/

        if ($str = $this->checkForMutator($key, 'get')) {
            $result = $this->{$str}();

            return $result;
        }

        if (! isset($this->attributes[$key])) {
            return;
        }

        return $this->attributes[$key];
    }

    /**
     * Sets an attribute on the part.
     *
     * @param string $key   The key to the attribute.
     * @param mixed  $value The value of the attribute.
     *
     * @return void
     */
    public function setAttribute($key, $value)
    {
        /*if (isset($this->stores[$key])) {
            if (! ($value instanceof $this->stores[$key])) {
                return;
            }

            $className = str_replace('\\', '', $this->stores[$key]);

            cache()->forever(
                "stores.{$className}.{$this->id}.{$key}",
                $value
            );

            return;
        }*/

        if ($str = $this->checkForMutator($key, 'set')) {
            $this->{$str}($value);

            return;
        }

        if (array_search($key, $this->fillable) !== false || isset($this->stores[$key])) {
            $this->attributes[$key] = $value;
        }
    }

    /**
     * Sets a cache attribute on the part.
     *
     * @param string $key   The cache key.
     * @param mixed  $value The cache value.
     *
     * @return void
     */
    public function setCache($key, $value)
    {
        $this->attributes_cache[$key] = $value;
    }

    /**
     * Checks if the cache has a specific key.
     *
     * @param string $key The key to check for.
     *
     * @return bool Whether the cache has the key.
     */
    public function cacheHas($key)
    {
        return isset($this->attributes_cache[$key]);
    }

    /**
     * Gets an attribute via key. Used for ArrayAccess.
     *
     * @param string $key The attribute key.
     *
     * @return mixed
     *
     * @see self::getAttribute() This function forwards onto getAttribute.
     */
    public function offsetGet($key)
    {
        return $this->getAttribute($key);
    }

    /**
     * Checks if an attribute exists via key. Used for ArrayAccess.
     *
     * @param string $key The attribute key.
     *
     * @return bool Whether the offset exists.
     */
    public function offsetExists($key)
    {
        return isset($this->attributes[$key]);
    }

    /**
     * Sets an attribute via key. Used for ArrayAccess.
     *
     * @param string $key   The attribute key.
     * @param mixed  $value The attribute value.
     *
     * @return void
     *
     * @see self::setAttribute() This function forwards onto setAttribute.
     */
    public function offsetSet($key, $value)
    {
        $this->setAttribute($key, $value);
    }

    /**
     * Unsets an attribute via key. Used for ArrayAccess.
     *
     * @param string $key The attribute key.
     *
     * @return void
     */
    public function offsetUnset($key)
    {
        if (isset($this->attributes[$key])) {
            unset($this->attributes[$key]);
        }
    }

    /**
     * Serializes the data. Used for Serializable.
     *
     * @return mixed A string of serialized data.
     */
    public function serialize()
    {
        return serialize($this->attributes);
    }

    /**
     * Unserializes some data and stores it. Used for Serializable.
     *
     * @param mixed $data Some serialized data.
     *
     * @return mixed Unserialized data.
     *
     * @see self::setAttribute() The unserialized data is stored with setAttribute.
     */
    public function unserialize($data)
    {
        $data = unserialize($data);

        foreach ($data as $key => $value) {
            $this->setAttribute($key, $value);
        }
    }

    /**
     * Provides data when the part is encoded into
     * JSON. Used for JsonSerializable.
     *
     * @return array An array of public attributes.
     *
     * @see self::getPublicAttributes() This function forwards onto getPublicAttributes.
     */
    public function jsonSerialize()
    {
        return $this->getPublicAttributes();
    }

    /**
     * Returns an array of public attributes.
     *
     * @return array An array of public attributes.
     */
    public function getPublicAttributes()
    {
        $data = [];

        foreach ($this->fillable as $key) {
            if (in_array($key, $this->hidden)) {
                continue;
            }

            $value = $this->getAttribute($key);

            if ($value instanceof Carbon) {
                $value = $value->format('Y-m-d\TH:i:s\Z');
            }

            $data[$key] = $value;
        }

        return $data;
    }

    /**
     * Returns an array of raw attributes.
     *
     * @return array Raw attributes.
     */
    public function getRawAttributes()
    {
        return $this->attributes;
    }

    /**
     * Gets the attributes to pass to stores.
     *
     * @return array Attributes.
     */
    public function getStoreAttributes()
    {
        return $this->attributes;
    }

    /**
     * Converts the part to a string.
     *
     * @return string A JSON string of attributes.
     *
     * @see self::getPublicAttributes() This function encodes getPublicAttributes into JSON.
     */
    public function __toString()
    {
        return json_encode($this->getPublicAttributes());
    }

    /**
     * Handles debug calls from var_dump and similar functions.
     *
     * @return array An array of public attributes.
     *
     * @see self::getPublicAttributes() This function forwards onto getPublicAttributes.
     */
    public function __debugInfo()
    {
        return $this->getPublicAttributes();
    }

    /**
     * Handles dynamic get calls onto the part.
     *
     * @param string $key The attributes key.
     *
     * @return mixed The value of the attribute.
     *
     * @see self::getAttribute() This function forwards onto getAttribute.
     */
    public function __get($key)
    {
        return $this->getAttribute($key);
    }

    /**
     * Handles dynamic set calls onto the part.
     *
     * @param string $key   The attributes key.
     * @param mixed  $value The attributes value.
     *
     * @return void
     *
     * @see self::setAttribute() This function forwards onto setAttribute.
     */
    public function __set($key, $value)
    {
        $this->setAttribute($key, $value);
    }
}