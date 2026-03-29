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
        Schema::create('cart_abandonments', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->json('cart_data');
            $table->uuid('token')->unique();
            $table->timestamp('abandoned_at');
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamp('recovered_at')->nullable();
            $table->timestamps();

            $table->index(['email', 'recovered_at']);
            $table->index(['abandoned_at', 'reminder_sent_at', 'recovered_at'], 'cart_abandonments_scheduling_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cart_abandonments');
    }
};
