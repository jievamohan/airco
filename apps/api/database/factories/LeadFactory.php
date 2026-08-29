<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    protected $model = Lead::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'status' => 'new',
            'source' => 'web_form',
            'name' => 'Jan Jansen',
            'email' => 'jan@example.nl',
            'phone' => '+31612345678',
            'address' => 'Keizersgracht 1',
            'postcode' => '1015 CJ',
            'city' => 'Amsterdam',
            'space_size' => 32,
            'space_unit' => 'm2',
            'rooms_count' => 1,
            'building_year' => 1998,
        ];
    }
}
