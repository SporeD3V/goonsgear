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
        Schema::create('tag_notification_dispatches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('notification_type', 20);
            $table->string('reference', 64);
            $table->timestamp('dispatched_at');
            $table->timestamps();

            $table->unique(
                ['user_id', 'tag_id', 'product_id', 'product_variant_id', 'notification_type', 'reference'],
                'tag_notification_dispatches_unique'
            );
            $table->index(['notification_type', 'dispatched_at'], 'tag_notification_dispatches_type_dispatched_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tag_notification_dispatches');
    }
};
