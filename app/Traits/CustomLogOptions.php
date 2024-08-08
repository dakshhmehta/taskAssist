<?php

namespace App\Traits;

use Spatie\Activitylog\LogOptions;

trait CustomLogOptions
{
    public function getActivitylogOptions(): LogOptions
    {
        $options = LogOptions::defaults();

        $options
            ->logAll()
            ->dontSubmitEmptyLogs()
            ->logOnlyDirty();

        return $options;
    }
}
