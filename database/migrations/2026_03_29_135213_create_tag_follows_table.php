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
        Schema::create('tag_follows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->boolean('notify_new_drops')->default(true);
            $table->boolean('notify_discounts')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'tag_id'], 'tag_follows_user_tag_unique');
            $table->index(['tag_id', 'notify_new_drops'], 'tag_follows_tag_drop_index');
            $table->index(['tag_id', 'notify_discounts'], 'tag_follows_tag_discount_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tag_follows');
    }
};
