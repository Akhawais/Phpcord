<?php
/**
 * This file is part of PhpCord. This file is subject to the license found at LICENSE.md at the root of this project.
 * Copyright (c) 2017 Dylan Akhawais <dylan@akhawais.co.uk>
 */

namespace Phpcord\Stores;

use Phpcord\Discord\Member;

class MemberStore extends BaseStore
{
    protected $model = Member::class;
}