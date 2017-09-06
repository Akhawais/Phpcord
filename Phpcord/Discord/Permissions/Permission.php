<?php
/**
 * This file is part of PhpCord. This file is subject to the license found at LICENSE.md at the root of this project.
 * Copyright (c) 2017 Dylan Akhawais <dylan@akhawais.co.uk>
 */

namespace Phpcord\Discord\Permissions;

use Phpcord\Discord\Model;

/**
 * The permissions object of a role or channel.
 */

class Permission extends Model
{
    /**
     * Stores the bits.
     *
     * For anyone confused, this is the position of the bit minus 1. i.e. Manage Server has an integer permission of 32 which is 100000 in binary.
     * The 6th bit is set to 1 in 10000. Minus 1 is 5 and that's the value here.
     *
     * @var array
     */
    protected $bitwise;

    /**
     * {@inheritdoc}
     */
    public function __construct(array $attributes = [], $created = false) {
        $this->fillable   = array_keys($this->bitwise);
        $this->fillable[] = 'bitwise';

        $default = [];

        foreach ($this->bitwise as $key => $bit) {
            $default[$key] = false;
        }

        $default = array_merge($default, $this->getDefault());

        parent::__construct($default, $created);
        $this->fill($attributes);
    }

    /**
     * Decodes a bitwise integer.
     *
     * @param int $bitwise The bitwise integer to decode.
     *
     * @return $this
     */
    public function decodeBitwise($bitwise)
    {
        $result = [];

        foreach ($this->bitwise as $key => $value) {
            $result[$key] = ((($bitwise >> $value) & 1) == 1);
        }

        $this->fill($result);

        return $this;
    }

    /**
     * Retrieves the bitwise integer.
     *
     * @return int
     */
    public function getBitwiseAttribute()
    {
        $bitwise = 0;

        foreach ($this->attributes as $key => $value) {
            if ($value) {
                $bitwise |= (1 << $this->bitwise[$key]);
            } else {
                $bitwise &= ~(1 << $this->bitwise[$key]);
            }
        }

        return $bitwise;
    }

    /**
     * Returns the default permissions.
     *
     * @return array Default perms.
     */
    public function getDefault()
    {
        return [];
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