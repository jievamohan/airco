<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table): void {
            // indication|final — zie App\Enums\QuoteKind. Alleen een `final`
            // bindt; die gaat pas de deur uit na de opname ter plaatse.
            $table->string('kind')->default('indication')->after('version')->index();
        });

        // Alles wat er al stond, is als offerte verstuurd. Die historie blijft
        // waar: het is geen indicatie geworden doordat wij het onderscheid
        // achteraf zijn gaan maken.
        DB::table('quotes')->update(['kind' => 'final']);
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table): void {
            $table->dropColumn('kind');
        });
    }
};
