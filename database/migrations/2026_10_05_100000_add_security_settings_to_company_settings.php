<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('password_min_length')->default(6);
            $table->boolean('password_require_uppercase')->default(false);
            $table->boolean('password_require_number')->default(false);
            $table->boolean('password_require_special_character')->default(false);
            $table->unsignedSmallInteger('max_failed_login_attempts')->default(5);
            $table->unsignedSmallInteger('account_lockout_minutes')->default(15);
            $table->unsignedSmallInteger('session_timeout_minutes')->default(120);
            $table->boolean('audit_logging_enabled')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn([
                'password_min_length',
                'password_require_uppercase',
                'password_require_number',
                'password_require_special_character',
                'max_failed_login_attempts',
                'account_lockout_minutes',
                'session_timeout_minutes',
                'audit_logging_enabled',
            ]);
        });
    }
};