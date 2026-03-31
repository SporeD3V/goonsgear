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
        Schema::create('import_legacy_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legacy_wc_order_id')->unique();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index('legacy_wc_order_id');
            $table->index('order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_legacy_orders');
    }
};
