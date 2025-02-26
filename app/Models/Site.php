<?php

namespace App\Models;

use App\Traits\Metable;
use Illuminate\Database\Eloquent\Model;

class Site extends Model
{
    use Metable;
    protected $guarded = [];
}
