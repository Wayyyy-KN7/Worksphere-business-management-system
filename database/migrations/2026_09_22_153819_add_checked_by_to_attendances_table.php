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
    Schema::table('attendances', function (Blueprint $table) {
        $table->unsignedBigInteger('checked_in_by')->nullable()->after('manual_reason');
        $table->unsignedBigInteger('checked_out_by')->nullable()->after('checked_in_by');
    });
}

public function down(): void
{
    Schema::table('attendances', function (Blueprint $table) {
        $table->dropColumn(['checked_in_by', 'checked_out_by']);
    });
}
};
