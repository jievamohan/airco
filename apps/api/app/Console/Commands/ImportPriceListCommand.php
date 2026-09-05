<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Pricing\PriceListImporter;
use App\Services\Pricing\PriceListRegistry;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Leest een prijslijst van de leverancier in de catalogus.
 *
 *   php artisan catalog:import-pricelist              alle bekende lijsten
 *   php artisan catalog:import-pricelist mhi-2026     één lijst
 *   php artisan catalog:import-pricelist --dry-run    alleen tellen
 */
class ImportPriceListCommand extends Command
{
    protected $signature = 'catalog:import-pricelist
                            {lijst? : Sleutel van de prijslijst, bijvoorbeeld mhi-2026}
                            {--dry-run : Toon wat er zou gebeuren zonder iets op te slaan}';

    protected $description = 'Neemt de netto inkoopprijzen uit een leveranciersprijslijst over in de catalogus';

    public function handle(PriceListImporter $importer, PriceListRegistry $registry): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $key = $this->argument('lijst');

        try {
            $definitions = is_string($key) && $key !== '' ? [$registry->get($key)] : $registry->all();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        foreach ($definitions as $definition) {
            $path = $importer->path($definition);

            if (! is_file($path)) {
                $this->error(sprintf('Prijslijst %s ontbreekt: %s', $definition->key, $path));

                return self::FAILURE;
            }

            $result = $importer->import($definition, $dryRun);

            $this->line(sprintf(
                '%s — %d aangemaakt, %d bijgewerkt, %d overgeslagen, %d ongemoeid (eigen invoer)%s',
                $definition->key,
                $result['aangemaakt'],
                $result['bijgewerkt'],
                $result['overgeslagen'],
                $result['ongemoeid'],
                $dryRun ? ' [proefrit]' : '',
            ));

            foreach ($result['waarschuwingen'] as $waarschuwing) {
                $this->warn('  '.$waarschuwing);
            }
        }

        return self::SUCCESS;
    }
}
