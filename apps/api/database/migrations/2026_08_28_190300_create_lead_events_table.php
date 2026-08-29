<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->string('type')->index(); // status_changed|call_started|call_completed|quote_sent|...
            $table->string('actor')->default('system'); // system|voice_agent|user|lead
            $table->string('actor_label')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('payload')->nullable();
            // dateTime en niet timestamp: met `explicit_defaults_for_timestamp`
            // uit krijgt de eerste TIMESTAMP NOT NULL van een tabel er
            // ongevraagd ON UPDATE CURRENT_TIMESTAMP bij. Het moment waarop
            // iets gebeurde zou dan opschuiven zodra de regel bijgewerkt wordt,
            // en de tijdlijn van een lead klopt stilletjes niet meer.
            $table->dateTime('occurred_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_events');
    }
};
