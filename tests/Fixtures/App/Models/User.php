<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * @property string $name
 */
class User extends Authenticatable
{
    protected $guarded = [];
}
