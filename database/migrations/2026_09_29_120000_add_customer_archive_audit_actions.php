<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->enum('action', [
                'CREATED',
                'UPDATED',
                'DELETED',
                'ARCHIVED',
                'RESTORED',
                'VIEWED',
                'APPROVED',
                'REJECTED',
                'LOGGED_IN',
                'LOGGED_OUT',
                'OTHER',
            ])->default('OTHER')->change();
        });
    }

    public function down(): void
    {
        DB::table('audit_logs')->whereIn('action', ['ARCHIVED', 'RESTORED'])->update(['action' => 'UPDATED']);

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->enum('action', [
                'CREATED',
                'UPDATED',
                'DELETED',
                'VIEWED',
                'APPROVED',
                'REJECTED',
                'LOGGED_IN',
                'LOGGED_OUT',
                'OTHER',
            ])->default('OTHER')->change();
        });
    }
};