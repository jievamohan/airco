<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Leest instellingen uit de database met de config als vangnet.
 *
 * Zo kan de ondernemer alles vanuit het dashboard aanpassen zonder deploy,
 * terwijl een verse installatie meteen met werkbare standaardwaarden draait.
 */
class SettingsRepository
{
    private const CACHE_KEY = 'agent.settings';

    /** @var array<string, mixed>|null */
    private ?array $cache = null;

    public function get(string $key, mixed $default = null): mixed
    {
        $values = $this->all();

        if (array_key_exists($key, $values) && $values[$key] !== null && $values[$key] !== '') {
            return $values[$key];
        }

        // Databasewaarde ontbreekt: de config (en daarmee de .env) beslist,
        // en pas als die ook niets zegt de meegegeven fallback.
        return config($key, $default);
    }

    public function int(string $key, int $default): int
    {
        $value = $this->get($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    public function float(string $key, float $default): float
    {
        $value = $this->get($key, $default);

        return is_numeric($value) ? (float) $value : $default;
    }

    public function bool(string $key, bool $default): bool
    {
        $value = $this->get($key, $default);

        return is_bool($value) ? $value : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->get($key, $default);

        return is_scalar($value) ? (string) $value : $default;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        /** @var array<string, mixed> $values */
        $values = Cache::remember(self::CACHE_KEY, 300, static function (): array {
            $result = [];

            foreach (Setting::all() as $setting) {
                $result[$setting->key] = $setting->typedValue();
            }

            return $result;
        });

        return $this->cache = $values;
    }

    public function set(string $key, mixed $value): void
    {
        $setting = Setting::where('key', $key)->first();

        if ($setting === null) {
            return;
        }

        $setting->value = is_array($value) ? json_encode($value) : (is_bool($value) ? ($value ? '1' : '0') : (string) $value);
        $setting->save();

        $this->flush();
    }

    public function flush(): void
    {
        $this->cache = null;
        Cache::forget(self::CACHE_KEY);
    }
}
