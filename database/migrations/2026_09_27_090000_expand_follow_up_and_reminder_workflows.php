<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('follow_ups', 'priority')) {
            Schema::table('follow_ups', function (Blueprint $table) {
                $table->string('status')->default('PLANNED')->change();
                $table->string('priority')->default('NORMAL');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('recurrence_rule')->default('NONE');
                $table->date('recurrence_until')->nullable();
                $table->foreignId('recurrence_parent_id')->nullable()->constrained('follow_ups')->nullOnDelete();
                $table->timestamp('next_occurrence_created_at')->nullable();
                $table->timestamp('due_notified_at')->nullable();
                $table->index(['assigned_to', 'status', 'follow_up_date']);
            });
        }

        DB::table('follow_ups')->where('status', 'PENDING')->update(['status' => 'PLANNED']);

        if (!Schema::hasColumn('reminders', 'customer_id')) {
            Schema::table('reminders', function (Blueprint $table) {
                $table->string('status')->default('PENDING')->change();
                $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
                $table->foreignId('opportunity_id')->nullable()->constrained('opportunities')->nullOnDelete();
                $table->foreignId('follow_up_id')->nullable()->constrained('follow_ups')->cascadeOnDelete();
                $table->nullableMorphs('remindable');
                $table->timestamp('snoozed_until')->nullable();
                $table->timestamp('due_notified_at')->nullable();
                $table->timestamp('overdue_notified_at')->nullable();
                $table->index(['user_id', 'status', 'reminder_date']);
            });
        }

        DB::table('reminders')->where('status', 'DISMISSED')->update(['status' => 'CANCELLED']);

        if (!Schema::hasTable('crm_activity_histories')) {
            Schema::create('crm_activity_histories', function (Blueprint $table) {
                $table->id();
                $table->morphs('activity');
                $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action');
                $table->string('from_status')->nullable();
                $table->string('to_status')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_activity_histories');

        Schema::table('reminders', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'status', 'reminder_date']);
            $table->dropConstrainedForeignId('customer_id');
            $table->dropConstrainedForeignId('opportunity_id');
            $table->dropConstrainedForeignId('follow_up_id');
            $table->dropMorphs('remindable');
            $table->dropColumn(['snoozed_until', 'due_notified_at', 'overdue_notified_at']);
        });

        DB::table('reminders')->whereIn('status', ['CANCELLED', 'OVERDUE'])->update(['status' => 'DISMISSED']);
        DB::table('reminders')->where('status', 'SNOOZED')->update(['status' => 'PENDING']);

        Schema::table('reminders', function (Blueprint $table) {
            $table->enum('status', ['PENDING', 'COMPLETED', 'DISMISSED'])->default('PENDING')->change();
        });

        Schema::table('follow_ups', function (Blueprint $table) {
            $table->dropIndex(['assigned_to', 'status', 'follow_up_date']);
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('recurrence_parent_id');
            $table->dropColumn([
                'priority',
                'recurrence_rule',
                'recurrence_until',
                'next_occurrence_created_at',
                'due_notified_at',
            ]);
        });

        DB::table('follow_ups')->whereIn('status', ['PLANNED', 'IN_PROGRESS', 'OVERDUE'])->update(['status' => 'PENDING']);

        Schema::table('follow_ups', function (Blueprint $table) {
            $table->enum('status', ['PENDING', 'COMPLETED', 'CANCELLED'])->default('PENDING')->change();
        });
    }
};