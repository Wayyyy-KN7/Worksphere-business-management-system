<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            if (!Schema::hasColumn('documents', 'documentable_type')) {
                $table->string('documentable_type')->nullable()->after('department_id');
            }

            if (!Schema::hasColumn('documents', 'documentable_id')) {
                $table->unsignedBigInteger('documentable_id')->nullable()->after('documentable_type');
            }
        });

        if (!Schema::getConnection()->getSchemaBuilder()->hasIndex('documents', ['documentable_type', 'documentable_id'])) {
            Schema::table('documents', function (Blueprint $table) {
                $table->index(['documentable_type', 'documentable_id']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('documents', 'documentable_type') || Schema::hasColumn('documents', 'documentable_id')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->dropColumn(['documentable_type', 'documentable_id']);
            });
        }
    }
};