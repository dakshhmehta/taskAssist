<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Models\Meta;

trait Metable {
    /**
     * Define a polymorphic relationship.
     */
    public function meta(): MorphMany
    {
        return $this->morphMany(Meta::class, 'model');
    }

    /**
     * Set meta key-value pair.
     */
    public function setMeta(string $key, mixed $value): void
    {
        $this->meta()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /**
     * Get meta value by key.
     */
    public function getMeta(string $key, mixed $default = null): mixed
    {
        return $this->meta()->where('key', $key)->value('value') ?? $default;
    }

    /**
     * Scope query to include models having a specific meta key and optional value.
     */
    public function scopeHasMeta($query, string $key, mixed $value = null)
    {
        return $query->whereHas('meta', function ($q) use ($key, $value) {
            $q->where('key', $key);
            if (!is_null($value)) {
                $q->where('value', $value);
            }
        });
    }
}
