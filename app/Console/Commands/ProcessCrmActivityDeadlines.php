<?php

namespace App\Console\Commands;

use App\Models\FollowUp;
use App\Models\Reminder;
use App\Services\CrmNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessCrmActivityDeadlines extends Command
{
    protected $signature = 'crm:activities:process-deadlines';

    protected $description = 'Mark overdue CRM activities and deliver due reminders';

    public function handle(): int
    {
        $this->processFollowUps();
        $this->processReminders();

        return self::SUCCESS;
    }

    private function processFollowUps(): void
    {
        FollowUp::query()->whereIn('status', ['PLANNED', 'IN_PROGRESS'])->overdue()->chunkById(100, function ($followUps) {
            foreach ($followUps as $followUp) {
                DB::transaction(function () use ($followUp) {
                    $previousStatus = $followUp->status;
                    $followUp->update(['status' => 'OVERDUE']);
                    $followUp->histories()->create([
                        'action' => 'OVERDUE',
                        'from_status' => $previousStatus,
                        'to_status' => 'OVERDUE',
                        'notes' => 'Follow-Up passed its due date.',
                    ]);
                    $this->notify($followUp->assigned_to, 'FOLLOW_UP_OVERDUE', 'Follow-Up overdue', "{$followUp->title} is overdue.", $followUp, 'follow_up.overdue.' . $followUp->id);
                });
            }
        });

        FollowUp::query()->whereIn('status', ['PLANNED', 'IN_PROGRESS'])
            ->whereNull('due_notified_at')
            ->whereDate('follow_up_date', '<=', now()->addHour()->toDateString())
            ->chunkById(100, function ($followUps) {
                foreach ($followUps as $followUp) {
                    if (!$followUp->due_at->betweenIncluded(now(), now()->addHour())) {
                        continue;
                    }
                    DB::transaction(function () use ($followUp) {
                        $followUp->update(['due_notified_at' => now()]);
                        $this->notify($followUp->assigned_to, 'FOLLOW_UP_DUE_SOON', 'Follow-Up due soon', "{$followUp->title} is due within the next hour.", $followUp, 'follow_up.due_soon.' . $followUp->id);
                    });
                }
            });
    }

    private function processReminders(): void
    {
        Reminder::query()->whereIn('status', ['PENDING', 'SNOOZED'])
            ->whereDate('reminder_date', '<=', today())
            ->chunkById(100, function ($reminders) {
                foreach ($reminders as $reminder) {
                    if (!$reminder->due_at->lt(now())) {
                        continue;
                    }
                    DB::transaction(function () use ($reminder) {
                        $previousStatus = $reminder->status;
                        $reminder->update(['status' => 'OVERDUE', 'overdue_notified_at' => now(), 'is_notified' => true]);
                        $reminder->histories()->create([
                            'action' => 'OVERDUE',
                            'from_status' => $previousStatus,
                            'to_status' => 'OVERDUE',
                            'notes' => 'Reminder passed its scheduled time.',
                        ]);
                        $this->notify($reminder->user_id, 'REMINDER_OVERDUE', 'Reminder overdue', "{$reminder->title} is overdue.", $reminder, 'reminder.overdue.' . $reminder->id);
                    });
                }
            });

        Reminder::query()->where('status', 'PENDING')->whereNull('due_notified_at')
            ->whereDate('reminder_date', '<=', now()->addHour()->toDateString())
            ->chunkById(100, function ($reminders) {
                foreach ($reminders as $reminder) {
                    if (!$reminder->due_at->betweenIncluded(now(), now()->addHour())) {
                        continue;
                    }
                    DB::transaction(function () use ($reminder) {
                        $reminder->update(['due_notified_at' => now(), 'is_notified' => true]);
                        $this->notify($reminder->user_id, 'REMINDER_DUE_SOON', 'Reminder due soon', "{$reminder->title} is due within the next hour.", $reminder, 'reminder.due_soon.' . $reminder->id);
                    });
                }
            });
    }

    private function notify(?int $userId, string $type, string $title, string $message, FollowUp|Reminder $activity, string $eventKey): void
    {
        if (!$userId) {
            return;
        }

        app(CrmNotificationService::class)->notifyUser($userId, $type, $title, $message, $activity, $eventKey);
    }
}