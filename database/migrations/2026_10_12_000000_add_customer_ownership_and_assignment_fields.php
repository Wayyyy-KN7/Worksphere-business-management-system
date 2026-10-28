<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('source_id')->constrained('users')->nullOnDelete();
            $table->foreignId('created_department_id')->nullable()->after('created_by')->constrained('departments')->nullOnDelete();
            $table->foreignId('assigned_department_id')->nullable()->after('created_department_id')->constrained('departments')->nullOnDelete();
            $table->index(['created_department_id', 'created_by']);
            $table->index(['assigned_department_id', 'assigned_to', 'status']);
        });

        DB::table('customers')
            ->whereNotNull('assigned_to')
            ->orderBy('id')
            ->chunkById(200, function ($customers): void {
                foreach ($customers as $customer) {
                    $departmentId = DB::table('users')
                        ->where('id', $customer->assigned_to)
                        ->value('department_id');

                    DB::table('customers')
                        ->where('id', $customer->id)
                        ->update(['assigned_department_id' => $departmentId]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['created_department_id', 'created_by']);
            $table->dropIndex(['assigned_department_id', 'assigned_to', 'status']);
            $table->dropConstrainedForeignId('assigned_department_id');
            $table->dropConstrainedForeignId('created_department_id');
            $table->dropConstrainedForeignId('created_by');
        });
    }
};
