<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class CrmNotificationService
{
    public function notifyUser(
        User|int $recipient,
        string $type,
        string $title,
        string $message,
        ?Model $related = null,
        ?string $eventKey = null,
    ): ?Notification {
        $userId = $recipient instanceof User ? $recipient->id : $recipient;
        if (!User::query()->whereKey($userId)->where('status', 'ACTIVE')->exists()) {
            return null;
        }
        $values = [
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'related_type' => $related ? $related::class : null,
            'related_id' => $related?->getKey(),
        ];

        if ($eventKey === null) {
            return Notification::create(['user_id' => $userId] + $values);
        }

        return Notification::firstOrCreate(
            ['user_id' => $userId, 'event_key' => $eventKey . ':' . $userId],
            $values,
        );
    }

    public function notifyUsers(
        iterable $recipients,
        string $type,
        string $title,
        string $message,
        ?Model $related = null,
        ?string $eventKey = null,
    ): Collection {
        $notifications = collect();
        $recipientIds = collect($recipients)->map(fn ($recipient) => $recipient instanceof User ? $recipient->id : $recipient)->filter()->unique();

        foreach ($recipientIds as $recipientId) {
            $notification = $this->notifyUser(
                (int) $recipientId,
                $type,
                $title,
                $message,
                $related,
                $eventKey,
            );
            if ($notification) {
                $notifications->push($notification);
            }
        }

        return $notifications;
    }

    public function departmentManagerIds(?int $departmentId, ?int $exceptUserId = null): Collection
    {
        if (!$departmentId) {
            return collect();
        }

        return User::query()->where('status', 'ACTIVE')
            ->where('department_id', $departmentId)
            ->when($exceptUserId, fn ($query) => $query->whereKeyNot($exceptUserId))
            ->get(['id', 'role'])
            ->filter(fn (User $user) => str_ends_with($user->role, '_MANAGER'))
            ->pluck('id')->values();
    }

    public function documentAudience(Document $document, ?string $accessLevel = null, ?int $departmentId = null): Collection
    {
        $accessLevel ??= $document->access_level;
        $departmentId ??= $document->department_id;
        $userIds = collect([$document->uploaded_by])->filter();

        if ($accessLevel === 'COMPANY_WIDE') {
            $userIds = $userIds->merge(User::where('status', 'ACTIVE')->pluck('id'));
        } elseif ($accessLevel === 'DEPARTMENT' && $departmentId) {
            $userIds = $userIds->merge(User::where('status', 'ACTIVE')->where('department_id', $departmentId)->pluck('id'));
        }

        return $userIds->merge($this->documentSharedRecipients($document))->unique()->values();
    }

    public function documentSharedRecipients(Document $document): Collection
    {
        $activeShares = $document->shares()->where(function ($shares) {
            $shares->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
        $userIds = $activeShares->whereNotNull('user_id')->pluck('user_id');
        $departmentIds = $document->shares()->whereNotNull('department_id')->where(function ($shares) {
            $shares->whereNull('expires_at')->orWhere('expires_at', '>', now());
        })->pluck('department_id');

        if ($departmentIds->isNotEmpty()) {
            $userIds = $userIds->merge(User::where('status', 'ACTIVE')->whereIn('department_id', $departmentIds)->pluck('id'));
        }

        return $userIds->unique()->values();
    }

    public function notifyDocumentAccessChanged(Document $document, string $oldAccessLevel, ?int $oldDepartmentId, string $eventKey): Collection
    {
        $recipients = $this->documentAudience($document, $oldAccessLevel, $oldDepartmentId)
            ->merge($this->documentAudience($document));

        return $this->notifyUsers(
            $recipients,
            'DOCUMENT_ACCESS_CHANGED',
            'Document Access Changed',
            "Access to {$document->title} has changed.",
            $document,
            $eventKey,
        );
    }

    public function notifyCompanyWideDocument(Document $document, string $eventKey): Collection
    {
        return $this->notifyUsers(
            User::query()->where('status', 'ACTIVE')->pluck('id'),
            'DOCUMENT_COMPANY_WIDE',
            'Company-Wide Document Published',
            "{$document->title} is now available company-wide.",
            $document,
            $eventKey,
        );
    }
}