<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->foreignId('catalog_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind'); // equipment|material|labour|surcharge|discount
            $table->string('sku')->nullable();
            $table->string('description');
            $table->decimal('quantity', 8, 2)->default(1);
            $table->string('unit')->default('stuk');
            $table->unsignedInteger('unit_cost_cents')->default(0);
            $table->decimal('margin_pct', 5, 2)->default(0);
            $table->integer('unit_price_cents')->default(0);
            $table->integer('line_total_cents')->default(0);
            $table->unsignedInteger('labour_minutes')->default(0);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_items');
    }
};
