<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Publieke aanvraag vanaf het websiteformulier.
 * Strikte allowlist: onbekende velden leiden tot een 422 (rule 47).
 */
class StoreLeadRequest extends FormRequest
{
    /** @var list<string> */
    private const ALLOWED = [
        'name', 'email', 'phone', 'address', 'postcode', 'city',
        'space_size', 'space_unit', 'rooms_count', 'notes', 'source',
    ];

    /**
     * Welke landingspagina de aanvraag stuurde. Een allowlist, want `source`
     * belandt ongefilterd in het dashboard en in de tijdlijn; een bezoeker mag
     * daar geen eigen tekst in kunnen zetten.
     *
     * @var list<string>
     */
    public const SOURCES = ['web_form', 'web_form_v2'];

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'phone' => ['required', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:180'],
            'postcode' => ['nullable', 'string', 'regex:/^\d{4}\s?[A-Za-z]{2}$/'],
            'city' => ['nullable', 'string', 'max:120'],
            'space_size' => ['nullable', 'numeric', 'min:1', 'max:10000'],
            'space_unit' => ['nullable', 'in:m2,m3'],
            'rooms_count' => ['nullable', 'integer', 'min:1', 'max:20'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'source' => ['nullable', 'string', Rule::in(self::SOURCES)],
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

    protected function failedValidation(Validator $validator): void
    {
        throw new ValidationException($validator);
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

        // `source` hoort bij de herkomst, niet bij de leadgegevens: LeadIntake
        // zet hem zelf op de lead.
        unset($data['source']);

        return $data;
    }

    /**
     * Herkomst van de aanvraag, standaard de eerste landingspagina.
     */
    public function leadSource(): string
    {
        $source = $this->validated('source');

        return is_string($source) && $source !== '' ? $source : 'web_form';
    }
}
