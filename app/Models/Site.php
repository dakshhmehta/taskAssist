<?php

namespace App\Models;

use App\Traits\Metable;
use Illuminate\Database\Eloquent\Model;

class Site extends Model
{
    use Metable;
    protected $guarded = [];

    public function scopeNoLatestBackup($q)
    {
        $q->hasMeta('wp_version')
            ->whereHas('meta', function ($q2) {
                $q2->where(function ($q3) {
                    $q3->where('key', 'last_backup')
                        ->where('value', '<=', now()->subDays(1)->startOfDay()->format('Y-m-d H:i:s'));
                });
            })->orWhereDoesntHave('meta', function ($q3) {
                $q3->where('key', 'last_backup');
            });
    }
}
