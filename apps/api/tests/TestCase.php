<?php

declare(strict_types=1);

namespace Tests;

use Database\Seeders\CatalogSeeder;
use Database\Seeders\SequenceSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Catalogus, instellingen en cadans zijn de basis waarop alles draait;
     * elke test start met dezelfde bekende uitgangssituatie.
     */
    protected function seedDomain(): void
    {
        $this->seed(SettingsSeeder::class);
        $this->seed(CatalogSeeder::class);
        $this->seed(SequenceSeeder::class);
    }
}
