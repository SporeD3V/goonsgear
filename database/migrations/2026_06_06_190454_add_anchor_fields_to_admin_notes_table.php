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
        Schema::table('admin_notes', function (Blueprint $table) {
            $table->string('anchor_key', 191)->nullable()->after('context_label');
            $table->string('anchor_label', 255)->nullable()->after('anchor_key');
            $table->string('anchor_value', 191)->nullable()->after('anchor_label');
            $table->json('anchor_meta')->nullable()->after('anchor_value');

            $table->index('anchor_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admin_notes', function (Blueprint $table) {
            $table->dropIndex(['anchor_key']);
            $table->dropColumn(['anchor_key', 'anchor_label', 'anchor_value', 'anchor_meta']);
        });
    }
};
