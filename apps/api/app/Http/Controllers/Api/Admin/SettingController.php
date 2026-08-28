<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\SettingsRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SettingController extends Controller
{
    /**
     * Geheime waarden gaan nooit terug over de lijn; het dashboard toont
     * alleen of er iets is ingevuld.
     */
    public function index(SettingsRepository $settings): JsonResponse
    {
        $grouped = [];

        foreach (Setting::orderBy('group')->orderBy('id')->get() as $setting) {
            $grouped[$setting->group][] = [
                'key' => $setting->key,
                'label' => $setting->label,
                'description' => $setting->description,
                'type' => $setting->type,
                'is_secret' => $setting->is_secret,
                'is_set' => $setting->value !== null && $setting->value !== '',
                'value' => $setting->is_secret ? null : $setting->typedValue(),
                'effective' => $setting->is_secret ? null : $settings->get($setting->key),
            ];
        }

        return response()->json(['groups' => $grouped]);
    }

    public function update(Request $request, SettingsRepository $settings): JsonResponse
    {
        $data = $request->validate([
            'values' => ['required', 'array', 'min:1'],
            'values.*' => ['nullable'],
        ]);

        /** @var array<string, mixed> $values */
        $values = $data['values'];
        $known = Setting::whereIn('key', array_keys($values))->get()->keyBy('key');

        $unknown = array_diff(array_keys($values), $known->keys()->all());

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'values' => 'Onbekende instellingen: '.implode(', ', $unknown).'.',
            ]);
        }

        foreach ($values as $key => $value) {
            /** @var Setting $setting */
            $setting = $known[$key];

            if ($value === null || $value === '') {
                $setting->value = null;
            } else {
                $setting->value = match ($setting->type) {
                    'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0',
                    'json' => json_encode($value),
                    default => (string) $value,
                };
            }

            $setting->save();
        }

        $settings->flush();

        return response()->json(['message' => 'Instellingen bijgewerkt.']);
    }
}
