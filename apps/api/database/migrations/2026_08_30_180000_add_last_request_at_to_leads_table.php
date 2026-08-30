<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Een herhaalde aanvraag werd stil bij de bestaande lead gevoegd: alleen een
 * regel in de tijdlijn, niets in de lijst. Wie zelf net het formulier had
 * ingevuld, zocht daarna tevergeefs naar een nieuwe lead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->dateTime('last_request_at')->nullable()->after('last_contact_at');
            // Tellen in plaats van tijdstippen vergelijken: twee aanvragen
            // binnen een minuut zijn er nog steeds twee.
            $table->unsignedSmallInteger('requests_count')->default(1)->after('last_request_at');
        });

        // Bestaande leads hebben er minstens één gehad: die van hun aanmaak.
        DB::table('leads')->update(['last_request_at' => DB::raw('created_at')]);

        // Herhaalde aanvragen van vóór deze migratie staan alleen in de
        // tijdlijn. Ze daaruit terughalen is de enige manier waarop ze alsnog
        // zichtbaar worden — anders blijft juist de aanvraag onvindbaar die
        // deze kolom moest oplossen.
        $herhaald = DB::table('lead_events')
            ->select('lead_id', DB::raw('COUNT(*) as aantal'), DB::raw('MAX(occurred_at) as laatste'))
            ->where('type', 'lead_duplicate')
            ->groupBy('lead_id')
            ->get();

        foreach ($herhaald as $rij) {
            DB::table('leads')->where('id', $rij->lead_id)->update([
                'requests_count' => 1 + (int) $rij->aantal,
                'last_request_at' => $rij->laatste,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->dropColumn(['last_request_at', 'requests_count']);
        });
    }
};
