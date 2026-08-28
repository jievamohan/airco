<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->string('number')->unique();
            $table->unsignedTinyInteger('version')->default(1);
            $table->string('status')->default('draft')->index(); // draft|sent|viewed|accepted|declined|expired
            $table->string('public_token', 64)->unique();

            $table->string('system_type')->nullable();
            $table->string('tier')->nullable();
            $table->decimal('total_kw', 5, 2)->nullable();

            $table->unsignedInteger('subtotal_cents')->default(0);
            $table->decimal('vat_rate', 5, 2)->default(21);
            $table->unsignedInteger('vat_cents')->default(0);
            $table->unsignedInteger('total_cents')->default(0);
            $table->integer('discount_cents')->default(0);
            $table->string('currency', 3)->default('EUR');

            $table->unsignedInteger('labour_minutes')->default(0);
            $table->unsignedInteger('onsite_minutes')->default(0);

            $table->json('assumptions')->nullable();
            $table->date('valid_until')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
