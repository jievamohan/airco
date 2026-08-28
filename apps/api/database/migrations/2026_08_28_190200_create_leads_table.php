<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('status')->default('new')->index();
            $table->string('source')->default('manual')->index(); // web_form|mailbox|manual|api
            $table->string('source_reference')->nullable(); // message-id of portaal-referentie
            $table->string('dedupe_hash')->nullable()->index();

            $table->string('name');
            $table->string('email')->nullable()->index();
            $table->string('phone')->nullable()->index();
            $table->string('address')->nullable();
            $table->string('postcode', 12)->nullable();
            $table->string('city')->nullable();
            $table->string('country', 2)->default('NL');

            $table->decimal('space_size', 8, 2)->nullable();
            $table->string('space_unit', 3)->nullable(); // m2|m3
            $table->unsignedTinyInteger('rooms_count')->default(1);
            $table->string('insulation')->nullable(); // good|average|poor
            $table->unsignedSmallInteger('building_year')->nullable();
            $table->unsignedTinyInteger('floor_level')->nullable();
            $table->string('wall_type')->nullable();
            $table->string('outdoor_unit_placement')->nullable();
            $table->unsignedSmallInteger('pipe_length_m')->nullable();
            $table->boolean('needs_condensate_pump')->default(false);
            $table->boolean('needs_extra_group')->default(false);
            $table->date('desired_start')->nullable();
            $table->text('notes')->nullable();

            $table->decimal('estimated_kw', 5, 2)->nullable();
            $table->string('recommended_system')->nullable(); // single_split|multi_split
            $table->string('tier')->nullable();

            $table->boolean('do_not_contact')->default(false);
            $table->unsignedTinyInteger('call_attempts')->default(0);
            $table->unsignedTinyInteger('email_attempts')->default(0);
            $table->timestamp('last_contact_at')->nullable();
            $table->timestamp('next_action_at')->nullable()->index();
            $table->timestamp('owner_notified_at')->nullable();
            $table->timestamp('won_at')->nullable();
            $table->timestamp('lost_at')->nullable();
            $table->string('lost_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
