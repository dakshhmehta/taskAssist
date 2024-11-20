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
        Schema::table('domains', function (Blueprint $table) {
            $table->datetime('ignored_at')->nullable();
        });

        Schema::table('hostings', function (Blueprint $table) {
            $table->datetime('ignored_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropColumn('ignored_at');
        });

        Schema::table('hostings', function (Blueprint $table) {
            $table->datetime('ignored_at')->nullable();
        });
    }
};
