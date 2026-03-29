<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bundle_discount_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bundle_discount_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('min_quantity')->default(1);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['bundle_discount_id', 'product_variant_id'], 'bundle_discount_items_bundle_variant_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bundle_discount_items');
    }
};
