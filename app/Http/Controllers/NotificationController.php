<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Customer;
use App\Models\FollowUp;
use App\Models\LeaveRequest;
use App\Models\Notification;
use App\Models\Opportunity;
use App\Models\Reminder;
use App\Models\Sale;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class NotificationController extends Controller
{
    use AuthorizesRequests;

    public function index(): View
    {
        $query = auth()->user()->isAdmin()
            ? Notification::query()
            : auth()->user()->notifications();
        $notifications = $query->latest()->paginate(20);

        return view('notifications.index', compact('notifications'));
    }

    public function open(Notification $notification): RedirectResponse
    {
        $this->authorize('view', $notification);
        $related = $this->resolveRelated($notification);

        if ($related instanceof LeaveRequest) {
            $employee = $related->user;
            if ($employee?->is(auth()->user())) {
                $notification->markAsRead();
                return redirect()->route('employee.leave.show', $related);
            }
            abort_unless(
                auth()->user()->isAdmin()
                    || (auth()->user()->isManager() && $employee?->department_id === auth()->user()->department_id)
                    || (auth()->user()->isHR() && auth()->user()->department_id === $employee?->department_id),
                403,
            );
            $notification->markAsRead();
            return redirect()->route('manager.leave.show', $related);
        }

        if ($related instanceof Document) {
            if (Gate::denies('view', $related)) {
                $notification->markAsRead();

                return redirect()->route('documents.shared')
                    ->with('error', 'This document is no longer shared with you. Ask the owner to share it again.');
            }

            $notification->markAsRead();
            return redirect()->route('documents.show', $related);
        }

        if ($related instanceof FollowUp) {
            Gate::authorize('view', $related);
            $notification->markAsRead();
            return redirect()->route('crm.follow-ups.show', $related);
        }

        if ($related instanceof Reminder) {
            Gate::authorize('view', $related);
            $notification->markAsRead();
            return redirect()->route('crm.reminders.show', $related);
        }

        if ($related instanceof Opportunity) {
            Gate::authorize('view', $related);
            $notification->markAsRead();
            return redirect()->route('crm.opportunities.show', $related);
        }

        if ($related instanceof Sale) {
            Gate::authorize('view', $related);
            $notification->markAsRead();
            return redirect()->route('crm.sales.show', $related);
        }

        if ($related instanceof Customer) {
            Gate::authorize('view', $related);
            $notification->markAsRead();
            return redirect()->route('crm.customers.show', $related);
        }

        $notification->markAsRead();
        return redirect()->route('notifications.index');
    }

    public function markRead(Notification $notification): RedirectResponse
    {
        $this->authorize('update', $notification);
        $notification->markAsRead();

        return back()->with('success', 'Notification marked as read.');
    }

    public function markUnread(Notification $notification): RedirectResponse
    {
        $this->authorize('update', $notification);
        $notification->markAsUnread();

        return back()->with('success', 'Notification marked as unread.');
    }

    public function markAllRead(): RedirectResponse
    {
        auth()->user()->unreadNotifications()->update(['is_read' => true, 'read_at' => now()]);

        return back()->with('success', 'All notifications marked as read.');
    }

    public function destroy(Notification $notification): RedirectResponse
    {
        $this->authorize('delete', $notification);
        $notification->delete();

        return back()->with('success', 'Notification deleted.');
    }

    private function resolveRelated(Notification $notification): mixed
    {
        $type = ltrim((string) $notification->related_type, '\\');
        $models = [
            'LeaveRequest' => LeaveRequest::class,
            'Document' => Document::class,
            'FollowUp' => FollowUp::class,
            'Reminder' => Reminder::class,
            'Opportunity' => Opportunity::class,
            'Sale' => Sale::class,
            'Customer' => Customer::class,
        ];
        $model = $models[class_basename($type)] ?? null;

        return $model ? $model::find($notification->related_id) : null;
    }
}