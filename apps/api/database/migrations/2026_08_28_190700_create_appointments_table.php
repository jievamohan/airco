<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quote_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider')->default('none'); // google|apple|none
            $table->string('provider_event_id')->nullable();
            $table->string('calendar_ref')->nullable();
            $table->string('ics_uid')->unique();
            $table->string('kind')->default('installation'); // survey|installation
            $table->string('title');
            $table->text('location')->nullable();
            $table->text('notes')->nullable();
            // dateTime en niet timestamp: staat `explicit_defaults_for_timestamp`
            // uit — de standaard in MariaDB en MySQL 5.7 — dan geeft MySQL de
            // eerste TIMESTAMP NOT NULL stilzwijgend DEFAULT CURRENT_TIMESTAMP
            // en elke volgende '0000-00-00', wat in strict mode geweigerd wordt.
            // Deze tabel draagt bovendien zijn eigen `timezone`-kolom, dus de
            // UTC-omrekening van TIMESTAMP is hier sowieso niet wat we willen.
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at');
            $table->string('timezone')->default('Europe/Amsterdam');
            $table->string('status')->default('scheduled')->index(); // scheduled|confirmed|cancelled|completed|no_show
            $table->text('sync_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
