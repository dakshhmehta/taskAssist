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
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->unsignedDecimal('discount_value')->default(0);
        });

        Schema::table('invoice_extras', function (Blueprint $table) {
            $table->unsignedDecimal('discount_value')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn('discount_value');
        });

        Schema::table('invoice_extras', function (Blueprint $table) {
            $table->dropColumn('discount_value');
        });
    }
};
