<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('customers')
            ->whereNull('created_by')
            ->orderBy('id')
            ->chunkById(200, function ($customers): void {
                foreach ($customers as $customer) {
                    $creatorId = DB::table('audit_logs')
                        ->where('table_name', 'customers')
                        ->where('record_id', $customer->id)
                        ->where('action', 'CREATED')
                        ->whereNotNull('user_id')
                        ->orderBy('created_at')
                        ->orderBy('id')
                        ->value('user_id');

                    if (!$creatorId) {
                        continue;
                    }

                    $departmentId = DB::table('users')
                        ->where('id', $creatorId)
                        ->value('department_id');

                    DB::table('customers')
                        ->where('id', $customer->id)
                        ->whereNull('created_by')
                        ->update([
                            'created_by' => $creatorId,
                            'created_department_id' => $departmentId,
                        ]);
                }
            });
    }

    public function down(): void
    {
        // Creator attribution recovered from audit history is intentionally retained.
    }
};
