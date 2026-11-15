<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SettingService
{
    public const CACHE_PREFIX = 'app_setting_';

    public const ALL_CACHE_KEY = 'app_settings_all_dict';

    /**
     * Get a setting value by key, with default fallback.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();

        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    /**
     * Set a setting value by key.
     */
    public function set(string $key, mixed $value, ?string $group = null, ?string $type = null): Setting
    {
        $group = $group ?? explode('.', $key)[0] ?? 'general';

        if ($type === null) {
            $type = match (true) {
                is_bool($value) => 'boolean',
                is_int($value) => 'integer',
                is_array($value) => 'json',
                default => 'string',
            };
        }

        $setting = Setting::updateOrCreate(
            ['key' => $key],
            [
                'group' => $group,
                'value' => $value,
                'type' => $type,
            ]
        );

        $this->clearCache();

        return $setting;
    }

    /**
     * Retrieve all settings mapped as key => value.
     */
    public function all(): array
    {
        return Cache::rememberForever(self::ALL_CACHE_KEY, function () {
            try {
                return Setting::all()->pluck('value', 'key')->toArray();
            } catch (\Throwable) {
                return [];
            }
        });
    }

    /**
     * Retrieve all settings records grouped by group.
     */
    public function getGroup(string $group): array
    {
        try {
            return Setting::where('group', $group)->pluck('value', 'key')->toArray();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Clear all cached settings.
     */
    public function clearCache(): void
    {
        Cache::forget(self::ALL_CACHE_KEY);
    }
}
