<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Lookup extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'key',
        'name',
        'colour',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saved(function () {
            Cache::forget('lookups.all');
        });

        static::deleted(function () {
            Cache::forget('lookups.all');
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Get all active lookups of a type, ordered by sort_order.
     */
    public static function forType(string $type, bool $onlyActive = true)
    {
        $all = Cache::rememberForever('lookups.all', function () {
            return static::orderBy('sort_order')->orderBy('name')->get();
        });

        return $all->where('type', $type)
            ->when($onlyActive, fn ($collection) => $collection->where('is_active', true))
            ->values();
    }

    /**
     * Resolve badge styling and label for a given type & status key.
     *
     * @return array{label: string, colour: string}
     */
    public static function resolveStatus(string $type, ?string $key): array
    {
        if (empty($key)) {
            return ['label' => 'Unknown', 'colour' => 'zinc'];
        }

        $lookups = static::forType($type, false);
        $found = $lookups->first(function ($l) use ($key) {
            return strtolower($l->key ?: $l->name) === strtolower($key)
                || strtolower($l->name) === strtolower(str_replace('_', ' ', $key));
        });

        if ($found) {
            return [
                'label' => $found->name,
                'colour' => $found->colour ?: 'zinc',
            ];
        }

        return [
            'label' => ucwords(str_replace('_', ' ', $key)),
            'colour' => 'zinc',
        ];
    }
}
