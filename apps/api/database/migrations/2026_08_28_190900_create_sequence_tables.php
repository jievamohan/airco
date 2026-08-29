<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sequences', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('sequence_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sequence_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('position');
            $table->string('channel'); // call|email
            $table->string('action'); // purpose voor call, template voor email
            $table->unsignedInteger('delay_minutes')->default(0);
            $table->string('label');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['sequence_id', 'position']);
        });

        Schema::create('lead_sequence_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sequence_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('next_position')->default(1);
            $table->string('status')->default('active')->index(); // active|completed|stopped
            $table->timestamp('next_run_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->string('stop_reason')->nullable();
            $table->timestamps();

            $table->unique(['lead_id', 'sequence_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_sequence_runs');
        Schema::dropIfExists('sequence_steps');
        Schema::dropIfExists('sequences');
    }
};
