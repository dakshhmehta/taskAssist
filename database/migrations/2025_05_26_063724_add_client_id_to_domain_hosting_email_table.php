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
            $table->unsignedBigInteger('client_id')->nullable();

            $table->foreign('client_id')->references('id')->on('clients')->onDelete('SET NULL');
        });

        Schema::table('hostings', function (Blueprint $table) {
            $table->unsignedBigInteger('client_id')->nullable();

            $table->foreign('client_id')->references('id')->on('clients')->onDelete('SET NULL');
        });

        Schema::table('emails', function (Blueprint $table) {
            $table->unsignedBigInteger('client_id')->nullable();

            $table->foreign('client_id')->references('id')->on('clients')->onDelete('SET NULL');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropColumn('client_id');
        });

        Schema::table('hostings', function (Blueprint $table) {
            $table->dropColumn('client_id');
        });

        Schema::table('emails', function (Blueprint $table) {
            $table->dropColumn('client_id');
        });
    }
};
