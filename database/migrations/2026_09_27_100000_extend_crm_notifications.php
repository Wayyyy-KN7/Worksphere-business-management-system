<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('type', 80)->default('GENERAL')->change();
            $table->string('event_key', 160)->nullable()->unique('notifications_event_key_unique');
        });
    }

    public function down(): void
    {
        DB::table('notifications')->whereNotIn('type', [
            'LEAVE_REQUEST', 'LEAVE_APPROVED', 'LEAVE_REJECTED',
            'DOCUMENT_SHARED', 'DOCUMENT_APPROVED', 'DOCUMENT_REJECTED',
            'FOLLOW_UP_DUE', 'REMINDER_DUE', 'GENERAL',
        ])->update(['type' => 'GENERAL']);

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropUnique('notifications_event_key_unique');
            $table->dropColumn('event_key');
            $table->enum('type', [
                'LEAVE_REQUEST', 'LEAVE_APPROVED', 'LEAVE_REJECTED',
                'DOCUMENT_SHARED', 'DOCUMENT_APPROVED', 'DOCUMENT_REJECTED',
                'FOLLOW_UP_DUE', 'REMINDER_DUE', 'GENERAL',
            ])->default('GENERAL')->change();
        });
    }
};