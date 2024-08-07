<?php

namespace App\Traits;
use Spatie\Activitylog\LogOptions;

trait CustomLogOptions {
    public function getActivitylogOptions(): LogOptions {
        $options = LogOptions::defaults();

        $options
            ->dontSubmitEmptyLogs()
            ->logOnlyDirty()
            
            ;

        return $options;
    }
}