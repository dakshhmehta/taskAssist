<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Meta extends Model
{
    protected $guarded = [];
    protected $table = 'meta';

    public $touches = ['model'];

    public function model()
    {
        return $this->morphTo();
    }
}
