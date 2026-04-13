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
        Schema::create('wc_sync_payloads', function (Blueprint $table) {
            $table->id();
            $table->string('event', 50)->index();
            $table->string('wc_entity_type', 30)->nullable()->index();
            $table->unsignedBigInteger('wc_entity_id')->nullable()->index();
            $table->json('payload');
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable()->index();
            $table->string('processing_error')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);

            $table->index(['wc_entity_type', 'wc_entity_id']);
            $table->index(['processed_at', 'attempts']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wc_sync_payloads');
    }
};
