<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'action' => 'nullable|in:CREATED,UPDATED,DELETED,VIEWED,APPROVED,REJECTED,LOGGED_IN,LOGGED_OUT,OTHER',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);

        $query = AuditLog::query()->with('user');

        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($matches) use ($search) {
                $matches->where('description', 'like', "%{$search}%")
                    ->orWhere('table_name', 'like', "%{$search}%")
                    ->orWhere('module', 'like', "%{$search}%")
                    ->orWhere('record_id', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($actor) use ($search) {
                        $actor->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $query->when($validated['action'] ?? null, fn ($logs, $action) => $logs->where('action', $action))
            ->when($validated['from'] ?? null, fn ($logs, $date) => $logs->whereDate('created_at', '>=', $date))
            ->when($validated['to'] ?? null, fn ($logs, $date) => $logs->whereDate('created_at', '<=', $date));

        $auditLogs = $query->latest()->paginate(20)->withQueryString();

        return view('admin.audit-logs.index', compact('auditLogs'));
    }

    public function show(AuditLog $auditLog): JsonResponse
    {
        $auditLog->loadMissing('user');
        $oldValues = $auditLog->old_values ?? [];
        $newValues = $auditLog->new_values ?? [];
        $changes = [];

        foreach (array_unique(array_merge(array_keys($oldValues), array_keys($newValues))) as $field) {
            $hasBefore = array_key_exists($field, $oldValues);
            $hasAfter = array_key_exists($field, $newValues);
            $before = $hasBefore ? $oldValues[$field] : null;
            $after = $hasAfter ? $newValues[$field] : null;

            if (!$hasBefore) {
                $type = 'added';
            } elseif (!$hasAfter) {
                $type = 'removed';
            } elseif ($before != $after) {
                $type = 'changed';
            } else {
                continue;
            }

            $changes[] = [
                'field' => $field,
                'type' => $type,
                'before' => $before,
                'after' => $after,
            ];
        }

        return response()->json([
            'id' => $auditLog->id,
            'actor' => $auditLog->user?->name ?? 'System / former user',
            'action' => $auditLog->action,
            'module' => $auditLog->module,
            'table_name' => $auditLog->table_name,
            'record_id' => $auditLog->record_id,
            'description' => $auditLog->description,
            'ip_address' => $auditLog->ip_address,
            'created_at' => $auditLog->created_at?->toDateTimeString(),
            'changes' => $changes,
        ]);
    }
}
