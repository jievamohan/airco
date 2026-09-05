<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Maakt de catalogus geschikt voor echte leveranciersprijslijsten.
 *
 * De proof of concept rekende met bedragen uit marktonderzoek. Die mochten er
 * zijn om het geheel te laten draaien, maar ze zijn niet van ons. Vanaf nu
 * staat per regel vast waar de prijs vandaan komt, welke productlijn het is en
 * welke bruto-prijs en inkoopkorting eronder liggen — zodat een volgende
 * prijslijst een vergelijking is en geen gok.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_items', function (Blueprint $table): void {
            // Productlijn, bijvoorbeeld `mhi-srk-zs-wf`. De offerte kiest per
            // kwaliteitsklasse een lijn; binnen een multisplit moeten buiten-
            // en binnenunit uit hetzelfde merk komen, en dat laat `tier` alleen
            // niet vastleggen.
            $table->string('series')->nullable()->after('brand');

            // Het echte vermogen (`capacity_kw`) is zelden precies een
            // rekenklasse: een 2,6 kW-unit bedient de klasse 2,5. Deze kolom
            // zegt welke klasse hij dekt, zodat de offerte niet onnodig een
            // maat groter pakt.
            $table->decimal('capacity_class_kw', 5, 2)->nullable()->after('capacity_kw');

            $table->unsignedInteger('list_price_cents')->nullable()->after('cost_cents');
            $table->decimal('purchase_discount_pct', 5, 2)->nullable()->after('list_price_cents');

            $table->string('price_source')->default('provisional')->after('source_note');
            $table->string('price_list_ref')->nullable()->after('price_source');
            $table->date('priced_at')->nullable()->after('price_list_ref');

            $table->index(['price_source', 'active']);
            $table->index(['series', 'capacity_class_kw']);
        });

        // Alles wat er nu staat komt uit het marktonderzoek van de proof of
        // concept — behalve wat de ondernemer zelf al heeft aangepast. Dat
        // laatste is wél een echt cijfer en mag niet als voorlopig te boek
        // komen te staan.
        DB::table('catalog_items')
            ->where('source_note', 'like', 'Aangepast in het dashboard%')
            ->update(['price_source' => 'dashboard']);

        // De bestaande regels dragen het echte vermogen al als rekenklasse;
        // zonder deze stap vindt de offerte na de migratie even niets.
        DB::table('catalog_items')
            ->whereNotNull('capacity_kw')
            ->update(['capacity_class_kw' => DB::raw('capacity_kw')]);
    }

    public function down(): void
    {
        Schema::table('catalog_items', function (Blueprint $table): void {
            $table->dropIndex(['price_source', 'active']);
            $table->dropIndex(['series', 'capacity_class_kw']);
            $table->dropColumn([
                'series',
                'capacity_class_kw',
                'list_price_cents',
                'purchase_discount_pct',
                'price_source',
                'price_list_ref',
                'priced_at',
            ]);
        });
    }
};
