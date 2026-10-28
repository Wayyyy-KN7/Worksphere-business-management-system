<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentApproval;
use App\Models\DocumentVersion;
use App\Models\User;
use App\Services\CrmNotificationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $user = Auth::user();
        $query = Document::query()->visibleTo($user)->with(['department', 'uploader', 'latestVersion']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('document_type')) {
            $query->where('document_type', $request->document_type);
        }

        if ($request->filled('access_level')) {
            $query->where('access_level', $request->access_level);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('document_number', 'like', "%{$search}%");
            });
        }

        $documents = $query->latest()->paginate(15);

        return view('documents.index', $this->documentCardCounts($user) + compact('documents'));
    }

    public function myDocuments()
    {
        $user = Auth::user();
        $documents = Document::with(['department', 'uploader', 'latestVersion'])
            ->where('uploaded_by', $user->id)
            ->latest()
            ->paginate(15);

        return view('documents.index', $this->documentCardCounts($user) + compact('documents'));
    }

    public function departmentDocuments()
    {
        $user = Auth::user();
        $query = Document::query()->visibleTo($user)
            ->where('status', 'PUBLISHED')
            ->with(['department', 'uploader', 'latestVersion']);

        if (!$user->isAdmin()) {
            $query->where(function ($department) use ($user) {
                $department->where('department_id', $user->department_id)
                    ->orWhere('access_level', 'COMPANY_WIDE');
            });
        }

        $documents = $query->latest()->paginate(15);

        return view('documents.index', $this->documentCardCounts($user) + compact('documents'));
    }

    public function sharedDocuments()
    {
        $user = Auth::user();
        $documents = Document::query()->visibleTo($user)
            ->whereIn('status', ['PUBLISHED', 'APPROVED'])
            ->with(['department', 'uploader', 'latestVersion'])
            ->whereHas('shares', function ($query) use ($user) {
                $query->where(function ($target) use ($user) {
                    $target->where('user_id', $user->id);
                    if ($user->department_id) {
                        $target->orWhere('department_id', $user->department_id);
                    }
                })->where(function ($expires) {
                    $expires->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })->whereIn('access_type', ['VIEW', 'DOWNLOAD', 'EDIT']);
            })
            ->latest()
            ->paginate(15);

        return view('documents.index', $this->documentCardCounts($user) + compact('documents'));
    }

    public function publicDocuments()
    {
        $documents = Document::query()->visibleTo(Auth::user())
            ->with(['department', 'uploader', 'latestVersion'])
            ->where('access_level', 'COMPANY_WIDE')
            ->where('status', 'PUBLISHED')
            ->latest()
            ->paginate(15);

        return view('documents.index', $this->documentCardCounts(Auth::user()) + compact('documents'));
    }

    public function approvals(Request $request)
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || ($user->isManager() && $user->department_id), 403);

        $documents = Document::query()->visibleTo($user)
            ->where('status', 'PENDING_REVIEW')
            ->with(['department', 'uploader'])
            ->latest()
            ->paginate(15);

        return view('documents.approvals', compact('documents'));
    }

    private function sharedDocumentCount(User $user): int
    {
        return Document::query()
            ->whereIn('status', ['PUBLISHED', 'APPROVED'])
            ->whereHas('shares', function ($query) use ($user) {
                $query->where(function ($target) use ($user) {
                    $target->where('user_id', $user->id);
                    if ($user->department_id) {
                        $target->orWhere('department_id', $user->department_id);
                    }
                })->where(function ($expires) {
                    $expires->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })->whereIn('access_type', ['VIEW', 'DOWNLOAD', 'EDIT']);
            })
            ->count();
    }

    private function documentCardCounts(User $user): array
    {
        $pendingApprovals = Document::where('status', 'PENDING_REVIEW');

        if (!$user->isAdmin()) {
            if (!$user->isManager() || !$user->department_id) {
                $pendingApprovals->whereRaw('1 = 0');
            } else {
                $pendingApprovals->where('department_id', $user->department_id);
            }
        }

        return [
            'documentCountForUser' => Document::query()->visibleTo($user)->count(),
            'pendingApprovalCount' => $pendingApprovals->count(),
            'myDocumentCount' => Document::where('uploaded_by', $user->id)->count(),
            'sharedDocumentCount' => $this->sharedDocumentCount($user),
        ];
    }

    public function create()
    {
        $user = Auth::user();
        $departments = Department::where('status', 'ACTIVE');
        if (!$user->isAdmin()) {
            $departments->whereKey($user->department_id);
        }
        $departments = $departments->get();
        $users = User::where('status', 'ACTIVE')->whereKeyNot($user->id)->orderBy('name')->get();

        return view('documents.create', compact('departments', 'users'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'document_type' => 'required|string',
            'department_id' => 'nullable|exists:departments,id',
            'access_level' => 'required|in:PRIVATE,DEPARTMENT,SELECTED_USERS,COMPANY_WIDE',
            'user_ids' => 'required_if:access_level,SELECTED_USERS|prohibited_unless:access_level,SELECTED_USERS|array|min:1',
            'user_ids.*' => 'required|integer|distinct|exists:users,id,status,ACTIVE',
            'file' => 'required|file|max:10240',
            'change_description' => 'nullable|string',
        ]);

        $user = Auth::user();
        $departmentId = $validated['department_id'] ?? $user->department_id;
        if (!$user->isAdmin() && $departmentId != $user->department_id) {
            abort(403, 'You can only upload documents for your own department.');
        }
        $selectedUserIds = $validated['user_ids'] ?? [];

        $document = DB::transaction(function () use ($validated, $request, $user, $departmentId, $selectedUserIds) {
            $status = Document::defaultStatusForUser($user);

            $document = Document::create([
                'title' => $validated['title'],
                'description' => $validated['description'],
                'document_type' => $validated['document_type'],
                'department_id' => $departmentId,
                'uploaded_by' => $user->id,
                'access_level' => $validated['access_level'],
                'status' => $status,
            ]);

            if ($validated['access_level'] === 'SELECTED_USERS') {
                foreach ($selectedUserIds as $recipientId) {
                    $document->shares()->firstOrCreate(
                        ['user_id' => $recipientId],
                        ['access_type' => 'VIEW'],
                    );
                }
            }

            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $fileName = time() . '_' . $file->getClientOriginalName();
                $filePath = $file->storeAs('documents', $fileName, 'public');

                $document->versions()->create([
                    'version_number' => '1.0',
                    'file_path' => $filePath,
                    'file_name' => $file->getClientOriginalName(),
                    'file_size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    'change_description' => $validated['change_description'] ?? 'Initial upload',
                    'uploaded_by' => Auth::id(),
                ]);
            }

            $document->logActivity('CREATED', 'Document created');

            return $document;
        });

        if ($document->status === 'PENDING_REVIEW') {
            $notificationService = app(CrmNotificationService::class);
            $notificationService->notifyUsers(
                $notificationService->departmentManagerIds($document->department_id, $user->id),
                'DOCUMENT_REVIEW_REQUESTED',
                'Document Waiting for Review',
                "{$user->name} submitted {$document->title} for department review.",
                $document,
                'document.review_requested.' . $document->id,
            );
        } elseif ($document->status === 'PUBLISHED' && $document->access_level === 'COMPANY_WIDE') {
            app(CrmNotificationService::class)->notifyCompanyWideDocument(
                $document,
                'document.company_wide.' . $document->id,
            );
        } elseif ($document->status === 'PUBLISHED' && $document->access_level === 'SELECTED_USERS') {
            app(CrmNotificationService::class)->notifyUsers(
                $selectedUserIds,
                'DOCUMENT_SHARED',
                'Document Shared with You',
                "{$user->name} shared {$document->title} with you.",
                $document,
                'document.upload.selected.' . $document->id,
            );
        }

        $message = ($user->isAdmin() || str_ends_with($user->role, '_MANAGER'))
            ? 'Document uploaded and published successfully.'
            : 'Document uploaded successfully and sent to department manager for approval.';

        return redirect()->route('documents.index')->with('success', $message);
    }

    public function show(Document $document)
    {
        $this->authorize('view', $document);
        $document->load(['department', 'uploader', 'versions.uploader', 'approvals.reviewer', 'activities.user']);
        $document->logActivity('VIEWED', 'Document viewed');
        $departments = Department::where('status', 'ACTIVE')->orderBy('name')->get();

        return view('documents.show', compact('document', 'departments'));
    }

    public function edit(Document $document)
    {
        $this->authorize('update', $document);
        $departments = Department::where('status', 'ACTIVE')
            ->when(!Auth::user()->isAdmin(), fn ($query) => $query->whereKey(Auth::user()->department_id))
            ->get();
        $users = User::where('status', 'ACTIVE')->get();

        return view('documents.edit', compact('document', 'departments', 'users'));
    }

    public function update(Request $request, Document $document)
    {
        $this->authorize('update', $document);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'document_type' => 'required|string',
            'department_id' => 'nullable|exists:departments,id',
            'access_level' => 'required|in:PRIVATE,DEPARTMENT,SELECTED_USERS,COMPANY_WIDE',
        ]);

        $user = Auth::user();
        if (!$user->isAdmin() && (int) ($validated['department_id'] ?? 0) !== (int) $user->department_id) {
            abort(403, 'You can only manage documents for your own department.');
        }

        $oldAccessLevel = $document->access_level;
        $oldDepartmentId = $document->department_id;
        $document->update($validated);
        $document->logActivity('UPDATED', 'Document information updated');

        $notifications = app(CrmNotificationService::class);
        if ($oldAccessLevel !== $document->access_level || $oldDepartmentId !== $document->department_id) {
            if ($document->access_level === 'COMPANY_WIDE' && $oldAccessLevel !== 'COMPANY_WIDE') {
                $notifications->notifyCompanyWideDocument($document, 'document.company_wide.' . $document->id . '.' . $document->updated_at->timestamp);
            } else {
                $notifications->notifyDocumentAccessChanged(
                    $document,
                    $oldAccessLevel,
                    $oldDepartmentId,
                    'document.access_changed.' . $document->id . '.' . $document->updated_at->timestamp,
                );
            }
        }

        return redirect()->route('documents.show', $document)->with('success', 'Document updated successfully');
    }

    public function destroy(Document $document)
    {
        $this->authorize('delete', $document);

        foreach ($document->versions as $version) {
            Storage::disk('public')->delete($version->file_path);
        }

        $document->delete();

        return redirect()->route('documents.index')->with('success', 'Document deleted successfully');
    }

    public function download(Document $document, DocumentVersion $version)
    {
        $this->authorize('download', $document);
        abort_unless((int) $version->document_id === (int) $document->id, 404);

        if (!Storage::disk('public')->exists($version->file_path)) {
            abort(404, 'File not found');
        }

        $document->logActivity('DOWNLOADED', 'Version ' . $version->version_number . ' downloaded');

        return Storage::disk('public')->download($version->file_path, $version->file_name);
    }


    public function uploadVersion(Request $request, Document $document)
    {
        $this->authorize('update', $document);

        $validated = $request->validate([
            'file' => 'required|file|max:10240',
            'version_number' => 'required|string',
            'change_description' => 'required|string',
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('documents', $fileName, 'public');

            $document->versions()->create([
                'version_number' => $validated['version_number'],
                'file_path' => $filePath,
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'change_description' => $validated['change_description'],
                'uploaded_by' => Auth::id(),
            ]);

            $document->logActivity('UPDATED', 'New version ' . $validated['version_number'] . ' uploaded');
        }

        return back()->with('success', 'New version uploaded successfully');
    }

    public function submitForApproval(Document $document)
    {
        $this->authorize('update', $document);

        $document->update(['status' => 'PENDING_REVIEW']);
        $document->logActivity('UPDATED', 'Submitted for approval');

        $notificationService = app(CrmNotificationService::class);
        $notificationService->notifyUsers(
            $notificationService->departmentManagerIds($document->department_id, Auth::id()),
            'DOCUMENT_REVIEW_REQUESTED',
            'Document Waiting for Review',
            Auth::user()->name . " submitted {$document->title} for department review.",
            $document,
            'document.review_requested.' . $document->id . '.' . $document->updated_at->timestamp,
        );

        return back()->with('success', 'Document submitted for approval');
    }

    public function approve(Request $request, Document $document)
    {
        $this->authorize('approve', $document);

        $validated = $request->validate(['comment' => 'nullable|string']);

        DB::transaction(function () use ($document, $validated) {
            DocumentApproval::create([
                'document_id' => $document->id,
                'reviewer_id' => Auth::id(),
                'status' => 'APPROVED',
                'comment' => $validated['comment'] ?? null,
                'reviewed_at' => now(),
            ]);

            $document->update(['status' => 'PUBLISHED']);
            $document->logActivity('APPROVED', 'Document approved and published');
            app(CrmNotificationService::class)->notifyUser(
                $document->uploaded_by,
                'DOCUMENT_APPROVED',
                'Document Approved',
                "{$document->title} was approved by " . Auth::user()->name . '.',
                $document,
                'document.approved.' . $document->id,
            );

            if ($document->access_level === 'COMPANY_WIDE') {
                app(CrmNotificationService::class)->notifyCompanyWideDocument(
                    $document,
                    'document.company_wide.' . $document->id . '.' . $document->updated_at->timestamp,
                );
            } else {
                $notificationService = app(CrmNotificationService::class);
                $recipientIds = $notificationService->documentSharedRecipients($document)
                    ->reject(fn ($userId) => in_array((int) $userId, [(int) $document->uploaded_by, (int) Auth::id()], true))
                    ->values();
                $notificationService->notifyUsers(
                    $recipientIds,
                    'DOCUMENT_SHARED',
                    'Document Shared with You',
                    "{$document->title} is now available to you.",
                    $document,
                    'document.approved.selected.' . $document->id,
                );
            }
        });

        return back()->with('success', 'Document approved and published successfully');
    }

    public function reject(Request $request, Document $document)
    {
        $this->authorize('approve', $document);

        $validated = $request->validate(['comment' => 'required|string']);

        DB::transaction(function () use ($document, $validated) {
            DocumentApproval::create([
                'document_id' => $document->id,
                'reviewer_id' => Auth::id(),
                'status' => 'REJECTED',
                'comment' => $validated['comment'],
                'reviewed_at' => now(),
            ]);

            $document->update(['status' => 'REJECTED']);
            $document->logActivity('REJECTED', 'Document rejected: ' . $validated['comment']);
            app(CrmNotificationService::class)->notifyUser(
                $document->uploaded_by,
                'DOCUMENT_REJECTED',
                'Document Rejected',
                "{$document->title} was rejected by " . Auth::user()->name . ': ' . $validated['comment'],
                $document,
                'document.rejected.' . $document->id,
            );
        });

        return back()->with('success', 'Document rejected and returned to the owner for revision');
    }

    public function share(Request $request, Document $document)
    {
        $this->authorize('share', $document);

        $validated = $request->validate([
            'user_ids' => 'nullable|required_without:department_ids|array|min:1',
            'user_ids.*' => 'exists:users,id,status,ACTIVE',
            'department_ids' => 'nullable|required_without:user_ids|array|min:1',
            'department_ids.*' => 'exists:departments,id,status,ACTIVE',
            'access_type' => 'required|in:VIEW,DOWNLOAD,EDIT',
            'expires_at' => 'nullable|date|after:now',
        ], [
            'expires_at.after' => 'Choose a future expiration time or leave this field blank for no expiration.',
        ]);

        if (isset($validated['user_ids'])) {
            foreach ($validated['user_ids'] as $userId) {
                $document->shares()->where('user_id', $userId)->delete();
                $document->shares()->create([
                    'user_id' => $userId,
                    'access_type' => $validated['access_type'],
                    'expires_at' => $validated['expires_at'] ?? null,
                ]);
            }
        }

        if (isset($validated['department_ids'])) {
            foreach ($validated['department_ids'] as $departmentId) {
                $document->shares()->where('department_id', $departmentId)->delete();
                $document->shares()->create([
                    'department_id' => $departmentId,
                    'access_type' => $validated['access_type'],
                    'expires_at' => $validated['expires_at'] ?? null,
                ]);
            }
        }

        $recipientIds = collect($validated['user_ids'] ?? []);
        $departmentUserIds = User::query()->where('status', 'ACTIVE')
            ->whereIn('department_id', $validated['department_ids'] ?? [])
            ->pluck('id');
        app(CrmNotificationService::class)->notifyUsers(
            $recipientIds->merge($departmentUserIds),
            'DOCUMENT_SHARED',
            'Document Shared with You',
            Auth::user()->name . " shared {$document->title} with you.",
            $document,
            'document.shared.' . $document->id . '.' . Str::uuid(),
        );

        $document->logActivity('SHARED', 'Document shared');

        return back()->with('success', 'Document shared successfully');
    }
}
