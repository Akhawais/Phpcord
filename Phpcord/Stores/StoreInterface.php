<?php
/**
 * This file is part of PhpCord. This file is subject to the license found at LICENSE.md at the root of this project.
 * Copyright (c) 2017 Dylan Akhawais <dylan@akhawais.co.uk>
 */

namespace Phpcord\Stores;

use Phpcord\Discord\Model;

interface StoreInterface
{
    /**
     * Builds a new, empty model.
     *
     * @param array $attributes The attributes for the new model.
     *
     * @return Model The new model.
     */
    public function create(array $attributes = []);

    /**
     * Attempts to save a model to the Discord servers.
     *
     * @param Model $model The model to save.
     *
     * @return PromiseInterface
     */
    public function save(Model &$model);

    /**
     * Attempts to delete a model on the Discord servers.
     *
     * @param Model $model The model to delete.
     *
     * @return PromiseInterface
     */
    public function delete(Model &$model);

    /**
     * Returns a model with fresh values.
     *
     * @param Model $model The model to get fresh values.
     *
     * @return PromiseInterface
     */
    public function fresh(Model &$model);

    /**
     * Force gets a model from the Discord servers.
     *
     * @param string $id The ID to search for.
     *
     * @return PromiseInterface
     */
    public function fetch($id);
}