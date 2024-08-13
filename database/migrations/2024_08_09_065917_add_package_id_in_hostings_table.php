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
        Schema::table('hostings', function (Blueprint $table) {
            $table->unsignedBigInteger('package_id')->nullable();

            $table->foreign('package_id')->references('id')->on('hosting_packages')->onDelete('SET NULL');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hostings', function (Blueprint $table) {
            $table->dropColumn('package_id');
        });
    }
};
