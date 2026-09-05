<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PriceSource;
use App\Models\CatalogItem;
use App\Services\Pricing\PriceListImporter;
use App\Services\Pricing\PriceListRegistry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

/**
 * Zet de echte inkoopprijzen in de catalogus en haalt de voorlopige set weg.
 *
 * De apparatuurregels van de proof of concept waren afgeleid uit marktonderzoek
 * — bruikbaar om het geheel te laten draaien, maar het waren niet onze prijzen.
 * Nu er echte prijslijsten liggen, worden die regels op inactief gezet: ze
 * blijven bestaan omdat er offertes naar verwijzen, maar er wordt niet meer mee
 * gerekend.
 *
 * Twee grendels, want deze seeder draait bij elke deploy:
 *
 * - Wat de ondernemer zelf heeft aangepast blijft actief en ongewijzigd.
 * - Er wordt pas iets uitgezet als er voor dat soort ook echt iets nieuws in de
 *   catalogus staat. Ontbreekt het CSV-bestand of loopt de import stuk, dan
 *   blijft de oude regel gewoon staan en kan de agent doorwerken.
 */
class PriceListSeeder extends Seeder
{
    /** Soorten waarvoor de prijslijsten een volwaardige vervanging leveren. */
    private const REPLACED_KINDS = [
        PriceListRegistry::KIND_SET,
        PriceListRegistry::KIND_OUTDOOR,
        PriceListRegistry::KIND_INDOOR,
    ];

    public function run(): void
    {
        $importer = app(PriceListImporter::class);
        $registry = app(PriceListRegistry::class);

        foreach ($registry->all() as $definition) {
            $path = $importer->path($definition);

            if (! is_file($path)) {
                Log::warning('Prijslijst ontbreekt, catalogus niet bijgewerkt.', ['lijst' => $definition->key, 'pad' => $path]);

                continue;
            }

            $result = $importer->import($definition);

            foreach ($result['waarschuwingen'] as $waarschuwing) {
                Log::warning('Prijslijst: '.$waarschuwing, ['lijst' => $definition->key]);
            }
        }

        $this->retireProvisionalEquipment();
    }

    /**
     * Zet de voorlopige apparatuurregels uit, per soort en alleen als er een
     * echte vervanger klaarstaat.
     */
    private function retireProvisionalEquipment(): void
    {
        foreach (self::REPLACED_KINDS as $kind) {
            $vervangers = CatalogItem::query()->active()->realPriced()->where('kind', $kind)->count();

            if ($vervangers === 0) {
                continue;
            }

            CatalogItem::query()
                ->where('kind', $kind)
                ->where('price_source', PriceSource::Provisional->value)
                ->where('active', true)
                ->update([
                    'active' => false,
                    'source_note' => 'Vervallen: vervangen door de inkoopprijzen uit de leveranciersprijslijst.',
                ]);
        }
    }
}
