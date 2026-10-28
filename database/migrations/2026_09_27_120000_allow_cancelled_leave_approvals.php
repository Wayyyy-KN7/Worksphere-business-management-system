<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_approvals', function (Blueprint $table) {
            $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED', 'CANCELLED'])->default('PENDING')->change();
        });
    }

    public function down(): void
    {
        DB::table('leave_approvals')->where('status', 'CANCELLED')->update(['status' => 'REJECTED']);

        Schema::table('leave_approvals', function (Blueprint $table) {
            $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED'])->default('PENDING')->change();
        });
    }
};