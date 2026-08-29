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
            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at');
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
