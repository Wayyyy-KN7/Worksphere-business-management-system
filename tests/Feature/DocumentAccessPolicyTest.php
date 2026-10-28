<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentShare;
use App\Models\DocumentVersion;
use App\Models\Notification;
use App\Models\User;
use App\Services\CrmNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentAccessPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_company_wide_document_is_visible_without_a_share_record(): void
    {
        [$ownerDepartment, $recipientDepartment] = $this->departments();
        $owner = $this->user($ownerDepartment, 'HR_MANAGER', 'company.owner@example.com');
        $recipient = $this->user($recipientDepartment, 'SALES_EMPLOYEE', 'company.recipient@example.com');
        $document = $this->document($owner, 'COMPANY_WIDE');

        $this->assertDatabaseCount('document_shares', 0);
        $this->assertTrue($document->visibleTo($recipient));
        $this->assertTrue(Document::query()->visibleTo($recipient)->whereKey($document->id)->exists());
        $this->actingAs($recipient)->get(route('documents.index'))->assertOk()->assertSee($document->title);
        $this->get(route('documents.show', $document))->assertOk();
        $this->get(route('dashboard'))->assertOk()->assertViewHas('data', function ($data) use ($document) {
            return $data['companyDocumentsList']->contains('id', $document->id);
        });
    }

    public function test_dashboard_recent_documents_preview_five_documents(): void
    {
        [$ownerDepartment, $recipientDepartment] = $this->departments();
        $owner = $this->user($ownerDepartment, 'HR_MANAGER', 'dashboard.docs.owner@example.com');
        $recipient = $this->user($recipientDepartment, 'SALES_EMPLOYEE', 'dashboard.docs.recipient@example.com');

        foreach (range(1, 5) as $number) {
            Document::create([
                'title' => 'Dashboard document ' . $number,
                'document_type' => 'POLICY',
                'department_id' => $ownerDepartment->id,
                'uploaded_by' => $owner->id,
                'access_level' => 'COMPANY_WIDE',
                'status' => 'PUBLISHED',
            ]);
        }

        $response = $this->actingAs($recipient)->get(route('dashboard'));
        $response->assertOk()
            ->assertSee(route('documents.index'), false)
            ->assertSee(route('documents.company'), false);
        preg_match_all('/class="doc-row"/', $response->getContent(), $previewRows);
        $this->assertSame(5, count($previewRows[0]));
    }

    public function test_published_selected_user_document_is_visible_to_its_recipient(): void
    {
        [$department] = $this->departments();
        $owner = $this->user($department, 'SALES_MANAGER', 'selected.owner@example.com');
        $recipient = $this->user($department, 'SALES_EMPLOYEE', 'selected.recipient@example.com');
        $document = $this->document($owner, 'SELECTED_USERS');
        $this->share($document, $recipient, null, 'VIEW');

        $this->actingAs($recipient)->get(route('documents.index'))->assertOk()->assertSee($document->title);
        $this->get(route('documents.show', $document))->assertOk();
    }

    public function test_unselected_department_manager_cannot_see_selected_user_document(): void
    {
        [$department] = $this->departments();
        $owner = $this->user($department, 'SALES_EMPLOYEE', 'selected.manager.owner@example.com');
        $selectedUser = $this->user($department, 'SALES_EMPLOYEE', 'selected.manager.grantee@example.com');
        $unselectedManager = $this->user($department, 'SALES_MANAGER', 'selected.unselected.manager@example.com');
        $document = $this->document($owner, 'SELECTED_USERS');
        $this->share($document, $selectedUser, null, 'VIEW');

        $this->assertFalse($document->visibleTo($unselectedManager));
        $this->actingAs($unselectedManager)->get(route('documents.index'))->assertOk()->assertDontSee($document->title);
        $this->get(route('documents.show', $document))->assertForbidden();
    }

    public function test_published_department_document_is_visible_to_its_department(): void
    {
        [$department] = $this->departments();
        $owner = $this->user($department, 'SALES_EMPLOYEE', 'dept.owner@example.com');
        $recipient = $this->user($department, 'SALES_EMPLOYEE', 'dept.recipient@example.com');
        $document = $this->document($owner, 'DEPARTMENT');

        $this->assertTrue($document->visibleTo($recipient));
        $this->actingAs($recipient)->get(route('documents.index'))->assertOk()->assertSee($document->title);
        $this->get(route('documents.show', $document))->assertOk();
    }

    public function test_published_department_document_is_not_visible_outside_its_department(): void
    {
        [$department, $otherDepartment] = $this->departments();
        $owner = $this->user($department, 'SALES_EMPLOYEE', 'dept.outside.owner@example.com');
        $outsider = $this->user($otherDepartment, 'HR_EMPLOYEE', 'dept.outside.recipient@example.com');
        $document = $this->document($owner, 'DEPARTMENT');

        $this->assertFalse($document->visibleTo($outsider));
        $this->actingAs($outsider)->get(route('documents.index'))->assertOk()->assertDontSee($document->title);
        $this->get(route('documents.show', $document))->assertForbidden();
    }

    public function test_private_document_is_visible_to_its_owner_and_authorized_department_manager(): void
    {
        [$department, $otherDepartment] = $this->departments();
        $owner = $this->user($department, 'SALES_EMPLOYEE', 'private.owner@example.com');
        $manager = $this->user($department, 'SALES_MANAGER', 'private.manager@example.com');
        $otherManager = $this->user($otherDepartment, 'HR_MANAGER', 'private.other.manager@example.com');
        $document = $this->document($owner, 'PRIVATE');

        $this->assertTrue($document->visibleTo($owner));
        $this->assertTrue($document->visibleTo($manager));
        $this->assertFalse($document->visibleTo($otherManager));
        $this->actingAs($manager)->get(route('documents.show', $document))->assertOk();
        $this->actingAs($otherManager)->get(route('documents.show', $document))->assertForbidden();
    }

    public function test_direct_user_share_grants_view_and_download_follows_access_type(): void
    {
        Storage::fake('public');
        [$department] = $this->departments();
        $owner = $this->user($department, 'SALES_MANAGER', 'direct.owner@example.com');
        $recipient = $this->user($department, 'SALES_EMPLOYEE', 'direct.recipient@example.com');
        $downloadRecipient = $this->user($department, 'SALES_EMPLOYEE', 'direct.download@example.com');
        $editRecipient = $this->user($department, 'SALES_EMPLOYEE', 'direct.edit@example.com');
        $document = $this->document($owner, 'PRIVATE');
        $this->share($document, $recipient, null, 'EDIT');
        $this->share($document, $downloadRecipient, null, 'DOWNLOAD');
        $this->share($document, $editRecipient, null, 'EDIT');
        $version = DocumentVersion::create([
            'document_id' => $document->id,
            'version_number' => '1.0',
            'file_path' => 'documents/access-policy.pdf',
            'file_name' => 'access-policy.pdf',
            'file_size' => 4,
            'mime_type' => 'application/pdf',
            'uploaded_by' => $owner->id,
        ]);
        Storage::disk('public')->put($version->file_path, 'test');

        $this->actingAs($owner)->post(route('documents.share', $document), [
            'user_ids' => [$recipient->id],
            'access_type' => 'VIEW',
        ])->assertRedirect();
        $this->assertDatabaseHas('document_shares', [
            'document_id' => $document->id,
            'user_id' => $recipient->id,
            'access_type' => 'VIEW',
        ]);

        $this->actingAs($recipient)->get(route('documents.shared'))->assertOk()->assertSee($document->title);
        $this->get(route('documents.show', $document))->assertOk();
        $this->assertFalse(Gate::forUser($recipient)->allows('download', $document));
        $this->assertFalse(Gate::forUser($recipient)->allows('update', $document));
        $this->get(route('documents.versions.download', [$document, $version]))->assertForbidden();
        $this->assertTrue(Gate::forUser($downloadRecipient)->allows('download', $document));
        $this->assertFalse(Gate::forUser($downloadRecipient)->allows('update', $document));
        $this->actingAs($downloadRecipient)->get(route('documents.versions.download', [$document, $version]))->assertOk();
        $this->assertTrue(Gate::forUser($editRecipient)->allows('download', $document));
        $this->assertTrue(Gate::forUser($editRecipient)->allows('update', $document));
    }

    public function test_view_only_shared_document_is_visible_to_authorized_recipient_but_not_unauthorized_users(): void
    {
        [$ownerDepartment, $otherDepartment] = $this->departments();
        $owner = $this->user($ownerDepartment, 'SALES_MANAGER', 'shared.view.owner@example.com');
        $recipient = $this->user($ownerDepartment, 'SALES_EMPLOYEE', 'shared.view.recipient@example.com');
        $otherUser = $this->user($otherDepartment, 'HR_EMPLOYEE', 'shared.view.other@example.com');
        $document = $this->document($owner, 'PRIVATE');
        $this->share($document, $recipient, null, 'VIEW');

        $this->assertTrue($document->visibleTo($recipient));
        $this->assertFalse($document->visibleTo($otherUser));
        $this->actingAs($recipient)->get(route('documents.shared'))->assertOk()->assertSee($document->title);
        $this->actingAs($recipient)->get(route('documents.show', $document))->assertOk();
        $this->actingAs($otherUser)->get(route('documents.show', $document))->assertForbidden();
        $this->assertFalse(Gate::forUser($otherUser)->allows('view', $document));
        $this->assertFalse(Gate::forUser($otherUser)->allows('download', $document));
    }

    public function test_document_detail_displays_view_only_message_and_preview_routes_are_disabled(): void
    {
        Storage::fake('public');
        [$department, $otherDepartment] = $this->departments();
        $owner = $this->user($department, 'SALES_MANAGER', 'preview.owner@example.com');
        $recipient = $this->user($department, 'SALES_EMPLOYEE', 'preview.recipient@example.com');
        $outsider = $this->user($otherDepartment, 'HR_EMPLOYEE', 'preview.outsider@example.com');
        $document = $this->document($owner, 'PRIVATE');
        $this->share($document, $recipient, null, 'VIEW');

        $version = DocumentVersion::create([
            'document_id' => $document->id,
            'version_number' => '1.0',
            'file_path' => 'documents/preview.pdf',
            'file_name' => 'preview.pdf',
            'file_size' => 10,
            'mime_type' => 'application/pdf',
            'uploaded_by' => $owner->id,
        ]);
        Storage::disk('public')->put($version->file_path, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");

        $this->actingAs($recipient)->get(route('documents.show', $document))
            ->assertOk()
            ->assertSee('View only:')
            ->assertSee('downloading is not allowed');

        $this->get('/documents/' . $document->id . '/preview')->assertNotFound();
        $this->get('/documents/' . $document->id . '/versions/' . $version->id . '/preview')->assertNotFound();

        $this->actingAs($outsider)->get('/documents/' . $document->id . '/preview')->assertNotFound();
        $this->actingAs($outsider)->get(route('documents.show', $document))->assertForbidden();
    }

    public function test_later_sharing_requires_a_published_document_and_manage_permission(): void
    {
        [$department, $otherDepartment] = $this->departments();
        $owner = $this->user($department, 'SALES_MANAGER', 'later.share.owner@example.com');
        $recipient = $this->user($otherDepartment, 'HR_EMPLOYEE', 'later.share.recipient@example.com');
        $unauthorizedManager = $this->user($otherDepartment, 'HR_MANAGER', 'later.share.unauthorized@example.com');
        $pending = $this->document($owner, 'PRIVATE', 'PENDING_REVIEW');
        $rejected = $this->document($owner, 'PRIVATE', 'REJECTED');
        $published = $this->document($owner, 'PRIVATE');
        $minimumExpiry = now()->addMinute()->startOfMinute()->format('Y-m-d\\TH:i');
        $this->actingAs($owner)->get(route('documents.show', $published))
            ->assertOk()
            ->assertSee('min="' . $minimumExpiry . '"', false);

        $this->actingAs($owner)->post(route('documents.share', $pending), [
            'user_ids' => [$recipient->id],
            'access_type' => 'VIEW',
        ])->assertForbidden();
        $this->post(route('documents.share', $rejected), [
            'user_ids' => [$recipient->id],
            'access_type' => 'VIEW',
        ])->assertForbidden();
        $this->actingAs($unauthorizedManager);
        $this->post(route('documents.share', $published), [
            'user_ids' => [$recipient->id],
            'access_type' => 'VIEW',
        ])->assertForbidden();

        $this->assertDatabaseMissing('document_shares', ['document_id' => $pending->id]);
        $this->assertDatabaseMissing('document_shares', ['document_id' => $rejected->id]);
        $this->assertDatabaseMissing('document_shares', ['document_id' => $published->id]);
    }

    public function test_later_published_share_requires_a_recipient_and_replaces_existing_access(): void
    {
        [$department] = $this->departments();
        $owner = $this->user($department, 'SALES_MANAGER', 'later.share.update.owner@example.com');
        $recipient = $this->user($department, 'SALES_EMPLOYEE', 'later.share.update.recipient@example.com');
        $document = $this->document($owner, 'PRIVATE');
        $this->share($document, $recipient, null, 'EDIT');

        $this->actingAs($owner)->from(route('documents.show', $document))->post(route('documents.share', $document), [
            'access_type' => 'VIEW',
        ])->assertRedirect(route('documents.show', $document))->assertSessionHasErrors('user_ids');
        $this->get(route('documents.show', $document))
            ->assertOk()
            ->assertSee('Document was not shared.')
            ->assertSee('bootstrap.Modal.getOrCreateInstance(shareModal).show()', false);
        $this->post(route('documents.share', $document), [
            'user_ids' => [$recipient->id],
            'access_type' => 'VIEW',
        ])->assertSessionHas('success');

        $this->assertSame(1, $document->shares()->where('user_id', $recipient->id)->count());
        $this->assertSame('VIEW', $document->shares()->where('user_id', $recipient->id)->value('access_type'));
        $this->assertSame('PRIVATE', $document->fresh()->access_level);
        $this->assertFalse(Gate::forUser($recipient)->allows('download', $document));
        $this->assertDatabaseHas('notifications', [
            'user_id' => $recipient->id,
            'type' => 'DOCUMENT_SHARED',
            'related_type' => Document::class,
            'related_id' => $document->id,
        ]);
    }

    public function test_department_share_grants_view_to_members_of_that_department(): void
    {
        [, $sharedDepartment] = $this->departments();
        $owner = $this->user($this->department('IT'), 'IT_MANAGER', 'department.share.owner@example.com');
        $recipient = $this->user($sharedDepartment, 'HR_EMPLOYEE', 'department.share.recipient@example.com');
        $document = $this->document($owner, 'PRIVATE');
        $this->share($document, null, $sharedDepartment, 'VIEW');

        $this->assertTrue($document->visibleTo($recipient));
        $this->actingAs($recipient)->get(route('documents.shared'))->assertOk()->assertSee($document->title);
        $this->get(route('documents.show', $document))->assertOk();
    }

    public function test_pending_shared_document_is_hidden_until_manager_approval(): void
    {
        [$department, $otherDepartment] = $this->departments();
        $owner = $this->user($department, 'SALES_EMPLOYEE', 'pending.share.owner@example.com');
        $manager = $this->user($department, 'SALES_MANAGER', 'pending.share.manager@example.com');
        $recipient = $this->user($otherDepartment, 'HR_EMPLOYEE', 'pending.share.recipient@example.com');
        $document = $this->document($owner, 'SELECTED_USERS', 'PENDING_REVIEW');
        $this->share($document, $recipient, null, 'DOWNLOAD');

        $this->actingAs($recipient)->get(route('documents.show', $document))->assertForbidden();
        $this->actingAs($manager)->post(route('documents.approve', $document), ['comment' => 'Approved'])
            ->assertSessionHas('success');
        $this->actingAs($recipient)->get(route('documents.show', $document))->assertOk();
    }

    public function test_expired_share_does_not_grant_visibility_or_view(): void
    {
        [$department] = $this->departments();
        $owner = $this->user($department, 'SALES_MANAGER', 'expired.owner@example.com');
        $recipient = $this->user($department, 'SALES_EMPLOYEE', 'expired.recipient@example.com');
        $document = $this->document($owner, 'PRIVATE');
        $this->share($document, $recipient, null, 'EDIT', now()->subMinute());

        $this->assertFalse($document->visibleTo($recipient));
        $this->assertFalse(Gate::forUser($recipient)->allows('update', $document));
        $this->actingAs($recipient)->get(route('documents.shared'))->assertOk()->assertDontSee($document->title);
        $this->get(route('documents.show', $document))->assertForbidden();
    }

    public function test_share_submission_rejects_an_expiration_in_the_past(): void
    {
        [$department] = $this->departments();
        $owner = $this->user($department, 'SALES_MANAGER', 'past.expiry.owner@example.com');
        $recipient = $this->user($department, 'SALES_EMPLOYEE', 'past.expiry.recipient@example.com');
        $document = $this->document($owner, 'PRIVATE');

        $this->actingAs($owner)->post(route('documents.share', $document), [
            'user_ids' => [$recipient->id],
            'access_type' => 'DOWNLOAD',
            'expires_at' => now()->subMinute()->format('Y-m-d\\TH:i'),
        ])->assertSessionHasErrors([
            'expires_at' => 'Choose a future expiration time or leave this field blank for no expiration.',
        ]);

        $this->assertDatabaseMissing('document_shares', [
            'document_id' => $document->id,
            'user_id' => $recipient->id,
        ]);
    }

    public function test_document_shared_notification_opens_only_while_recipient_has_valid_access(): void
    {
        [$department] = $this->departments();
        $owner = $this->user($department, 'SALES_MANAGER', 'notification.share.owner@example.com');
        $recipient = $this->user($department, 'SALES_EMPLOYEE', 'notification.share.recipient@example.com');
        $document = $this->document($owner, 'SELECTED_USERS');
        $this->share($document, $recipient, null, 'VIEW');
        $notification = app(CrmNotificationService::class)->notifyUser(
            $recipient,
            'DOCUMENT_SHARED',
            'Document Shared with You',
            'A document was shared with you.',
            $document,
            'document.share.test.' . $document->id,
        );

        $this->actingAs($recipient)->get(route('notifications.open', $notification))
            ->assertRedirect(route('documents.show', $document));
        $this->assertTrue($notification->fresh()->is_read);

        $document->shares()->where('user_id', $recipient->id)->firstOrFail()->update([
            'expires_at' => now()->subMinute(),
        ]);
        $this->get(route('notifications.open', $notification))
            ->assertRedirect(route('documents.shared'))
            ->assertSessionHas('error', 'This document is no longer shared with you. Ask the owner to share it again.');
    }

    private function departments(): array
    {
        return [$this->department('SALES'), $this->department('HR')];
    }

    private function department(string $code): Department
    {
        return Department::create([
            'name' => $code,
            'code' => $code,
            'status' => 'ACTIVE',
        ]);
    }

    private function user(Department $department, string $role, string $email): User
    {
        return User::create([
            'employee_id' => strtoupper(substr(md5($email), 0, 10)),
            'name' => ucfirst(strstr($email, '@', true)),
            'email' => $email,
            'password' => bcrypt('password'),
            'department_id' => $department->id,
            'role' => $role,
            'status' => 'ACTIVE',
        ]);
    }

    private function document(User $owner, string $accessLevel, string $status = 'PUBLISHED'): Document
    {
        return Document::create([
            'title' => 'Test ' . $accessLevel . ' ' . $status,
            'document_type' => 'REPORT',
            'department_id' => $owner->department_id,
            'uploaded_by' => $owner->id,
            'access_level' => $accessLevel,
            'status' => $status,
        ]);
    }

    private function share(Document $document, ?User $user, ?Department $department, string $accessType, $expiresAt = null): DocumentShare
    {
        return DocumentShare::create([
            'document_id' => $document->id,
            'user_id' => $user?->id,
            'department_id' => $department?->id,
            'access_type' => $accessType,
            'expires_at' => $expiresAt,
        ]);
    }
}
