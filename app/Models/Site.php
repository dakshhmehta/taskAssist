<?php

namespace App\Models;

use App\Traits\IgnorableTrait;
use App\Traits\Metable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class Site extends Model
{
    use Notifiable;
    use Metable, IgnorableTrait;

    protected $guarded = [];

    public function routeNotificationForTelegram()
    {
        return '-1002752505542'; // Telegram group ID
    }

    public function scopeNoLatestBackup($q)
    {
        $q->hasMeta('wp_version')
            ->where(function($mainQ){
                $mainQ->whereHas('meta', function ($q2) {
                    $q2->where(function ($q3) {
                        $q3->where('key', 'last_backup')
                            ->where('value', '<=', now()->subDays(7)->startOfDay()->format('Y-m-d H:i:s'));
                    });
                })->orWhereDoesntHave('meta', function ($q3) {
                    $q3->where('key', 'last_backup');
                });
            });
    }
}
