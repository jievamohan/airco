<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calls', function (Blueprint $table): void {
            // Het belvenster wordt op twee plekken afgedwongen: bij het inplannen
            // en nog eens bij elke tik. Zonder deze vlag schuift die tweede
            // controle een bewust buiten het venster gepland gesprek alsnog
            // vooruit, en gebeurt er niets waar niemand een reden voor ziet.
            $table->boolean('ignores_calling_window')->default(false)->after('scheduled_for');
        });
    }

    public function down(): void
    {
        Schema::table('calls', function (Blueprint $table): void {
            $table->dropColumn('ignores_calling_window');
        });
    }
};
