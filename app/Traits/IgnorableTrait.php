<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait IgnorableTrait
{
    public function scopeIncludeIgnored(Builder $query)
    {
        return $query->whereNull('ignored_at')->orWhereNotNull('ignored_at');
    }

    public function scopeExcludeIgnored(Builder $query)
    {
        return $query->whereNull('ignored_at');
    }

    public function ignore()
    {
        $this->ignored_at = now();
        $this->save();
    }

    public function unIgnore()
    {
        $this->ignored_at = null;
        $this->save();
    }

    public function isIgnored()
    {
        return $this->ignored_at !== null;
    }
}
