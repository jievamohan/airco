<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table): void {
            // Kostprijs en marge worden per offerte bevroren, zodat later
            // gewijzigde inkoopprijzen de historie niet herschrijven.
            $table->unsignedInteger('cost_cents')->default(0)->after('discount_cents');
            $table->decimal('margin_pct', 6, 2)->default(0)->after('cost_cents');
            $table->boolean('margin_warning')->default(false)->after('margin_pct');
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table): void {
            $table->dropColumn(['cost_cents', 'margin_pct', 'margin_warning']);
        });
    }
};
