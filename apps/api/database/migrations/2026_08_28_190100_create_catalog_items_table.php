<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_items', function (Blueprint $table): void {
            $table->id();
            $table->string('sku')->unique();
            $table->string('kind'); // equipment_outdoor|equipment_indoor|equipment_set|material|labour|surcharge
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('brand')->nullable();
            $table->string('tier')->nullable(); // budget|mid|premium
            $table->decimal('capacity_kw', 5, 2)->nullable();
            $table->unsignedTinyInteger('ports')->nullable(); // multisplit-aansluitingen
            $table->string('unit')->default('stuk'); // stuk|set|meter|uur|post
            $table->unsignedInteger('cost_cents')->default(0); // inkoop excl. btw
            $table->decimal('margin_pct', 5, 2)->default(0);
            $table->unsignedInteger('labour_minutes')->default(0); // monteursminuten
            $table->boolean('active')->default(true);
            $table->string('source_note')->nullable();
            $table->timestamps();

            $table->index(['kind', 'tier', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_items');
    }
};
