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
        Schema::create('invoice_extras', function (Blueprint $table) {
            $table->id();
            $table->string('line_title');
            $table->string('line_description');
            $table->string('line_duration')->nullable();
            $table->decimal('price');

            $table->unsignedBigInteger('invoice_id');

            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_extras');
    }
};
