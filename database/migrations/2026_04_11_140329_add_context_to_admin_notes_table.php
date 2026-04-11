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
            $table->string('context', 100)->nullable()->after('color');
            $table->string('context_label', 255)->nullable()->after('context');
            $table->index('context');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admin_notes', function (Blueprint $table) {
            $table->dropIndex(['context']);
            $table->dropColumn(['context', 'context_label']);
        });
    }
};
