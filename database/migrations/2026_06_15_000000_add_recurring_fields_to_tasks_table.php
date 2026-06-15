<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->boolean('is_recurring')->default(false)->after('auto_schedule');
            $table->string('recurrence_type')->nullable()->after('is_recurring');
            $table->integer('recurrence_interval')->default(1)->after('recurrence_type');
            $table->json('recurrence_days')->nullable()->after('recurrence_interval');
            $table->date('recurrence_end_date')->nullable()->after('recurrence_days');
            $table->unsignedInteger('recurrence_occurrences_count')->default(0)->after('recurrence_end_date');
            $table->unsignedInteger('recurrence_max_occurrences')->nullable()->after('recurrence_occurrences_count');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn([
                'is_recurring',
                'recurrence_type',
                'recurrence_interval',
                'recurrence_days',
                'recurrence_end_date',
                'recurrence_occurrences_count',
                'recurrence_max_occurrences',
            ]);
        });
    }
};
