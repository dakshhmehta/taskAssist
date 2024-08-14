<?php

namespace App\Models;

use App\Traits\CustomLogOptions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;

class HostingPackage extends Model
{
    use HasFactory;

    use LogsActivity, CustomLogOptions;

    protected $guarded = [];

    public function hostings(){
        return $this->hasMany(Hosting::class);
    }
}