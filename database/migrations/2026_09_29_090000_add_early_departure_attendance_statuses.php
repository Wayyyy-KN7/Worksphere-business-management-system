<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->enum('status', ['PRESENT', 'LATE', 'ABSENT', 'LEAVE', 'HOLIDAY', 'EARLY_DEPARTURE', 'HALF_DAY'])->default('PRESENT')->change();
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->enum('status', ['PRESENT', 'LATE', 'ABSENT', 'LEAVE', 'HOLIDAY'])->default('PRESENT')->change();
        });
    }
};