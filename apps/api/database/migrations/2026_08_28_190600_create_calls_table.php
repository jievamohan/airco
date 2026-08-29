<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calls', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->string('provider')->default('elevenlabs');
            $table->string('provider_call_id')->nullable()->index();
            $table->string('conversation_id')->nullable()->index();
            $table->string('purpose')->index(); // qualification|conversion|chase|final
            $table->unsignedTinyInteger('attempt_no')->default(1);
            $table->string('status')->default('queued')->index(); // queued|initiated|in_progress|completed|failed
            $table->string('outcome')->nullable(); // answered|no_answer|voicemail|busy|failed|declined|appointment_booked
            $table->string('to_number')->nullable();
            $table->timestamp('scheduled_for')->nullable()->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->longText('transcript')->nullable();
            $table->text('summary')->nullable();
            $table->json('collected')->nullable();
            $table->string('recording_url')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calls');
    }
};
