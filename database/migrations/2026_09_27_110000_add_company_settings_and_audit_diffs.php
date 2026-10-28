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
            $table->string('module')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->index(['module', 'created_at'], 'audit_logs_module_created_idx');
        });

        DB::table('audit_logs')->whereNull('module')->update(['module' => DB::raw('table_name')]);

        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->string('company_name')->default('Internal CRM');
            $table->string('logo_path')->nullable();
            $table->text('address')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('timezone', 64)->default('Asia/Yangon');
            $table->char('currency', 3)->default('USD');
            $table->string('date_format', 20)->default('Y-m-d');
            $table->json('working_days');
            $table->time('work_start')->default('09:00:00');
            $table->time('work_end')->default('17:00:00');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_settings');

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_logs_module_created_idx');
            $table->dropColumn(['module', 'old_values', 'new_values']);
        });
    }
};