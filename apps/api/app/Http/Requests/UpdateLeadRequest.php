<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

/**
 * Bewerken van een lead vanuit het dashboard. Strikte allowlist: statusvelden,
 * tellers en tijdstempels zijn hier bewust niet aanpasbaar — die volgen uit de
 * workflow, niet uit een formulier.
 */
class UpdateLeadRequest extends FormRequest
{
    /** @var list<string> */
    private const ALLOWED = [
        'name', 'email', 'phone', 'address', 'postcode', 'city',
        'space_size', 'space_unit', 'rooms_count', 'insulation', 'building_year',
        'floor_level', 'wall_type', 'outdoor_unit_placement', 'pipe_length_m',
        'needs_condensate_pump', 'needs_extra_group', 'desired_start', 'notes',
        'tier', 'do_not_contact',
    ];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'min:2', 'max:120'],
            'email' => ['sometimes', 'nullable', 'email:rfc', 'max:190'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'address' => ['sometimes', 'nullable', 'string', 'max:180'],
            'postcode' => ['sometimes', 'nullable', 'string', 'regex:/^\d{4}\s?[A-Za-z]{2}$/'],
            'city' => ['sometimes', 'nullable', 'string', 'max:120'],
            'space_size' => ['sometimes', 'nullable', 'numeric', 'min:1', 'max:10000'],
            'space_unit' => ['sometimes', 'nullable', 'in:m2,m3'],
            'rooms_count' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'insulation' => ['sometimes', 'nullable', 'in:good,average,poor'],
            'building_year' => ['sometimes', 'nullable', 'integer', 'min:1800', 'max:2100'],
            'floor_level' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:20'],
            'wall_type' => ['sometimes', 'nullable', 'string', 'max:80'],
            'outdoor_unit_placement' => ['sometimes', 'nullable', 'string', 'max:120'],
            'pipe_length_m' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
            'needs_condensate_pump' => ['sometimes', 'boolean'],
            'needs_extra_group' => ['sometimes', 'boolean'],
            'desired_start' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'tier' => ['sometimes', 'nullable', 'in:budget,mid,premium'],
            'do_not_contact' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $unknown = array_diff(array_keys($this->all()), self::ALLOWED);

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'payload' => 'Onbekende velden in het verzoek: '.implode(', ', $unknown).'.',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function leadAttributes(): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->validated();

        if (isset($data['postcode']) && is_string($data['postcode'])) {
            $data['postcode'] = strtoupper(preg_replace('/\s+/', ' ', trim($data['postcode'])) ?? $data['postcode']);
        }

        return $data;
    }
}
