<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_approval_queue_is_scoped_to_authorized_managers(): void
    {
        $sales = Department::create(['name' => 'Sales', 'code' => 'SALES', 'status' => 'ACTIVE']);
        $hr = Department::create(['name' => 'HR', 'code' => 'HR', 'status' => 'ACTIVE']);

        $admin = User::create([
            'employee_id' => 'APPROVAL-ADMIN',
            'name' => 'Admin Reviewer',
            'email' => 'approvals.admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'ADMIN',
            'status' => 'ACTIVE',
        ]);
        $salesManager = User::create([
            'employee_id' => 'APPROVAL-SALES',
            'name' => 'Sales Reviewer',
            'email' => 'approvals.sales@example.com',
            'password' => bcrypt('password'),
            'department_id' => $sales->id,
            'role' => 'SALES_MANAGER',
            'status' => 'ACTIVE',
        ]);
        $salesEmployee = User::create([
            'employee_id' => 'APPROVAL-EMPLOYEE',
            'name' => 'Sales Employee',
            'email' => 'approvals.employee@example.com',
            'password' => bcrypt('password'),
            'department_id' => $sales->id,
            'role' => 'SALES_EMPLOYEE',
            'status' => 'ACTIVE',
        ]);
        $hrEmployee = User::create([
            'employee_id' => 'APPROVAL-HR',
            'name' => 'HR Employee',
            'email' => 'approvals.hr@example.com',
            'password' => bcrypt('password'),
            'department_id' => $hr->id,
            'role' => 'HR_EMPLOYEE',
            'status' => 'ACTIVE',
        ]);

        $salesDocument = Document::create([
            'title' => 'Sales policy awaiting approval',
            'document_type' => 'POLICY',
            'department_id' => $sales->id,
            'uploaded_by' => $salesEmployee->id,
            'access_level' => 'DEPARTMENT',
            'status' => 'PENDING_REVIEW',
        ]);
        $hrDocument = Document::create([
            'title' => 'HR policy awaiting approval',
            'document_type' => 'POLICY',
            'department_id' => $hr->id,
            'uploaded_by' => $hrEmployee->id,
            'access_level' => 'DEPARTMENT',
            'status' => 'PENDING_REVIEW',
        ]);

        $this->actingAs($salesManager)->get(route('documents.approvals'))
            ->assertOk()
            ->assertSee($salesDocument->title)
            ->assertDontSee($hrDocument->title);

        $this->actingAs($admin)->get(route('documents.approvals'))
            ->assertOk()
            ->assertSee($salesDocument->title)
            ->assertSee($hrDocument->title);

        $this->actingAs($salesEmployee)->get(route('documents.approvals'))->assertForbidden();
    }

    public function test_document_upload_form_renders_with_compact_layout_and_existing_fields(): void
    {
        $department = Department::create(['name' => 'Sales', 'code' => 'SALES', 'status' => 'ACTIVE']);
        $employee = User::create([
            'employee_id' => 'UPLOAD-EMPLOYEE',
            'name' => 'Sales Employee',
            'email' => 'documents.upload.form@example.com',
            'password' => bcrypt('password'),
            'department_id' => $department->id,
            'role' => 'SALES_EMPLOYEE',
            'status' => 'ACTIVE',
        ]);

        $this->actingAs($employee)->get(route('documents.create'))
            ->assertOk()
            ->assertSee('Upload New Document')
            ->assertSee('Document Title *')
            ->assertSee('Access Level *')
            ->assertSee('Upload File *')
            ->assertSee('max-width: 760px', false)
            ->assertSee(route('documents.store'), false);
    }

    public function test_employee_document_requires_manager_approval(): void
    {
        $department = Department::create([
            'name' => 'Sales',
            'code' => 'SALES',
            'description' => 'Sales department',
            'status' => 'ACTIVE',
        ]);

        $employee = User::create([
            'employee_id' => 'EMP-1001',
            'name' => 'Alex Employee',
            'email' => 'alex@example.com',
            'password' => bcrypt('password'),
            'department_id' => $department->id,
            'role' => 'SALES_EMPLOYEE',
            'status' => 'ACTIVE',
        ]);

        $manager = User::create([
            'employee_id' => 'EMP-1002',
            'name' => 'Morgan Manager',
            'email' => 'morgan@example.com',
            'password' => bcrypt('password'),
            'department_id' => $department->id,
            'role' => 'SALES_MANAGER',
            'status' => 'ACTIVE',
        ]);

        $document = Document::create([
            'document_number' => 'DOC-2026-00001',
            'title' => 'Quarterly sales report',
            'description' => 'Pending manager review',
            'document_type' => 'REPORT',
            'department_id' => $department->id,
            'uploaded_by' => $employee->id,
            'access_level' => 'DEPARTMENT',
            'status' => 'PENDING_REVIEW',
        ]);

        $this->assertTrue($document->canBeApprovedBy($manager));
        $this->assertFalse($document->canBeApprovedBy($employee));
        $this->assertTrue($document->visibleTo($employee));
        $this->assertEquals('PENDING_REVIEW', $document->status);
    }

    public function test_manager_upload_is_published_immediately(): void
    {
        $department = Department::create([
            'name' => 'Sales',
            'code' => 'SALES',
            'description' => 'Sales department',
            'status' => 'ACTIVE',
        ]);

        $manager = User::create([
            'employee_id' => 'EMP-2001',
            'name' => 'Sales Manager',
            'email' => 'sales.manager@example.com',
            'password' => bcrypt('password'),
            'department_id' => $department->id,
            'role' => 'SALES_MANAGER',
            'status' => 'ACTIVE',
        ]);

        $document = Document::create([
            'document_number' => 'DOC-2026-00002',
            'title' => 'Sales policy',
            'document_type' => 'POLICY',
            'department_id' => $department->id,
            'uploaded_by' => $manager->id,
            'access_level' => 'COMPANY_WIDE',
            'status' => 'PUBLISHED',
        ]);

        $this->assertTrue($document->visibleTo($manager));
        $this->assertEquals('PUBLISHED', $document->status);
    }

    public function test_document_owner_cannot_publish_or_move_a_pending_document_by_updating_it(): void
    {
        Storage::fake('public');
        $sales = Department::create(['name' => 'Sales', 'code' => 'SALES', 'status' => 'ACTIVE']);
        $hr = Department::create(['name' => 'Human Resources', 'code' => 'HR', 'status' => 'ACTIVE']);
        $employee = User::create([
            'employee_id' => 'OWNER-REVIEW-EMP',
            'name' => 'Document Owner',
            'email' => 'document.owner@example.test',
            'password' => bcrypt('password'),
            'department_id' => $sales->id,
            'role' => 'SALES_EMPLOYEE',
            'status' => 'ACTIVE',
        ]);
        $manager = User::create([
            'employee_id' => 'OWNER-REVIEW-MANAGER',
            'name' => 'Document Manager',
            'email' => 'document.manager@example.test',
            'password' => bcrypt('password'),
            'department_id' => $sales->id,
            'role' => 'SALES_MANAGER',
            'status' => 'ACTIVE',
        ]);
        $admin = User::create([
            'employee_id' => 'OWNER-REVIEW-ADMIN',
            'name' => 'Document Admin',
            'email' => 'document.admin@example.test',
            'password' => bcrypt('password'),
            'role' => 'ADMIN',
            'status' => 'ACTIVE',
        ]);

        $this->actingAs($employee)->post(route('documents.store'), [
            'title' => 'Review required document',
            'description' => 'Must remain pending until manager review.',
            'document_type' => 'REPORT',
            'department_id' => $sales->id,
            'access_level' => 'DEPARTMENT',
            'file' => UploadedFile::fake()->create('review.pdf', 10, 'application/pdf'),
        ])->assertRedirect(route('documents.index'));

        $document = Document::query()->where('title', 'Review required document')->firstOrFail();
        $this->put(route('documents.update', $document), [
            'title' => $document->title,
            'description' => $document->description,
            'document_type' => $document->document_type,
            'department_id' => $sales->id,
            'access_level' => 'COMPANY_WIDE',
            'status' => 'PUBLISHED',
        ])->assertRedirect(route('documents.show', $document));
        $this->assertSame('PENDING_REVIEW', $document->fresh()->status);
        $this->assertSame('COMPANY_WIDE', $document->fresh()->access_level);
        $this->get(route('documents.edit', $document))
            ->assertOk()
            ->assertDontSee('name="status"', false);

        $this->put(route('documents.update', $document), [
            'title' => $document->title,
            'description' => $document->description,
            'document_type' => $document->document_type,
            'department_id' => $hr->id,
            'access_level' => 'COMPANY_WIDE',
            'status' => 'PUBLISHED',
        ])->assertForbidden();
        $this->assertSame($sales->id, $document->fresh()->department_id);
        $this->post(route('documents.approve', $document), ['comment' => 'Owner must not approve'])->assertForbidden();

        $this->actingAs($manager)->post(route('documents.approve', $document), ['comment' => 'Reviewed'])->assertSessionHas('success');
        $this->assertSame('PUBLISHED', $document->fresh()->status);
        $this->actingAs($admin)->post(route('documents.approve', $document), ['comment' => 'Already published'])->assertForbidden();
    }

    public function test_shared_documents_lists_only_viewable_direct_and_department_shares(): void
    {
        $salesDepartment = Department::create([
            'name' => 'Sales',
            'code' => 'SHARE-SALES',
            'status' => 'ACTIVE',
        ]);
        $hrDepartment = Department::create([
            'name' => 'HR',
            'code' => 'SHARE-HR',
            'status' => 'ACTIVE',
        ]);
        $uploader = User::create([
            'employee_id' => 'SHARE-001',
            'name' => 'HR Manager',
            'email' => 'share.hr.manager@example.com',
            'password' => bcrypt('password'),
            'department_id' => $hrDepartment->id,
            'role' => 'HR_MANAGER',
            'status' => 'ACTIVE',
        ]);
        $recipient = User::create([
            'employee_id' => 'SHARE-002',
            'name' => 'Sales Manager',
            'email' => 'share.sales.manager@example.com',
            'password' => bcrypt('password'),
            'department_id' => $salesDepartment->id,
            'role' => 'SALES_MANAGER',
            'status' => 'ACTIVE',
        ]);

        $directDocument = Document::create([
            'title' => 'Direct share',
            'document_type' => 'REPORT',
            'department_id' => $hrDepartment->id,
            'uploaded_by' => $uploader->id,
            'access_level' => 'PRIVATE',
            'status' => 'PUBLISHED',
        ]);
        $departmentDocument = Document::create([
            'title' => 'Department share',
            'document_type' => 'POLICY',
            'department_id' => $hrDepartment->id,
            'uploaded_by' => $uploader->id,
            'access_level' => 'PRIVATE',
            'status' => 'PUBLISHED',
        ]);
        $pendingDocument = Document::create([
            'title' => 'Pending shared document',
            'document_type' => 'REPORT',
            'department_id' => $hrDepartment->id,
            'uploaded_by' => $uploader->id,
            'access_level' => 'PRIVATE',
            'status' => 'PENDING_REVIEW',
        ]);

        $this->actingAs($uploader)->post(route('documents.share', $directDocument), [
            'user_ids' => [$recipient->id],
            'access_type' => 'VIEW',
        ])->assertRedirect();
        $shareNotification = Notification::query()
            ->where('user_id', $recipient->id)
            ->where('type', 'DOCUMENT_SHARED')
            ->where('related_id', $directDocument->id)
            ->firstOrFail();
        $this->post(route('documents.share', $departmentDocument), [
            'department_ids' => [$salesDepartment->id],
            'access_type' => 'VIEW',
        ])->assertRedirect();
        $this->assertDatabaseMissing('document_shares', ['document_id' => $pendingDocument->id]);

        $sharedResponse = $this->actingAs($recipient)->get(route('documents.shared'));
        $sharedResponse->assertOk()
            ->assertViewHas('sharedDocumentCount', 2)
            ->assertViewHas('documents', function ($documents) use ($directDocument, $departmentDocument) {
                $documentIds = $documents->getCollection()->modelKeys();
                sort($documentIds);
                $expectedIds = [$directDocument->id, $departmentDocument->id];
                sort($expectedIds);

                return $documentIds === $expectedIds;
            })
            ->assertSee('Direct share')
            ->assertSee('Department share')
            ->assertDontSee('Pending shared document');
        $this->actingAs($uploader)->get(route('documents.show', $directDocument))
            ->assertOk()
            ->assertSee('Sales');
        $this->actingAs($recipient)->get(route('documents.show', $directDocument))->assertOk();
        $this->get(route('notifications.open', $shareNotification))
            ->assertRedirect(route('documents.show', $directDocument));
        $this->get(route('documents.show', $departmentDocument))->assertOk();
        $this->get(route('documents.show', $pendingDocument))->assertForbidden();
    }

    public function test_total_documents_count_reflects_user_visible_documents(): void
    {
        $salesDepartment = Department::create([
            'name' => 'Sales',
            'code' => 'SALES',
            'description' => 'Sales department',
            'status' => 'ACTIVE',
        ]);

        $hrDepartment = Department::create([
            'name' => 'HR',
            'code' => 'HR',
            'description' => 'Human resources',
            'status' => 'ACTIVE',
        ]);

        $employee = User::create([
            'employee_id' => 'EMP-3001',
            'name' => 'Sales Employee',
            'email' => 'sales.employee@example.com',
            'password' => bcrypt('password'),
            'department_id' => $salesDepartment->id,
            'role' => 'SALES_EMPLOYEE',
            'status' => 'ACTIVE',
        ]);

        $otherUser = User::create([
            'employee_id' => 'EMP-3002',
            'name' => 'Other Employee',
            'email' => 'other.employee@example.com',
            'password' => bcrypt('password'),
            'department_id' => $hrDepartment->id,
            'role' => 'HR_EMPLOYEE',
            'status' => 'ACTIVE',
        ]);

        $salesManager = User::create([
            'employee_id' => 'EMP-3003',
            'name' => 'Sales Manager',
            'email' => 'sales.manager.counts@example.com',
            'password' => bcrypt('password'),
            'department_id' => $salesDepartment->id,
            'role' => 'SALES_MANAGER',
            'status' => 'ACTIVE',
        ]);

        Document::create([
            'document_number' => 'DOC-2026-00003',
            'title' => 'My own document',
            'document_type' => 'REPORT',
            'department_id' => $salesDepartment->id,
            'uploaded_by' => $employee->id,
            'access_level' => 'PRIVATE',
            'status' => 'PUBLISHED',
        ]);

        Document::create([
            'document_number' => 'DOC-2026-00004',
            'title' => 'Same department doc',
            'document_type' => 'REPORT',
            'department_id' => $salesDepartment->id,
            'uploaded_by' => $otherUser->id,
            'access_level' => 'DEPARTMENT',
            'status' => 'PUBLISHED',
        ]);

        Document::create([
            'document_number' => 'DOC-2026-00005',
            'title' => 'Company wide doc',
            'document_type' => 'POLICY',
            'department_id' => $hrDepartment->id,
            'uploaded_by' => $otherUser->id,
            'access_level' => 'COMPANY_WIDE',
            'status' => 'PUBLISHED',
        ]);

        Document::create([
            'document_number' => 'DOC-2026-00006',
            'title' => 'HR private doc',
            'document_type' => 'POLICY',
            'department_id' => $hrDepartment->id,
            'uploaded_by' => $otherUser->id,
            'access_level' => 'PRIVATE',
            'status' => 'PUBLISHED',
        ]);

        Document::create([
            'document_number' => 'DOC-2026-00007',
            'title' => 'Sales pending review',
            'document_type' => 'REPORT',
            'department_id' => $salesDepartment->id,
            'uploaded_by' => $otherUser->id,
            'access_level' => 'DEPARTMENT',
            'status' => 'PENDING_REVIEW',
        ]);

        Document::create([
            'document_number' => 'DOC-2026-00008',
            'title' => 'HR pending review',
            'document_type' => 'REPORT',
            'department_id' => $hrDepartment->id,
            'uploaded_by' => $otherUser->id,
            'access_level' => 'DEPARTMENT',
            'status' => 'PENDING_REVIEW',
        ]);

        $this->actingAs($employee);

        $response = $this->get('/documents');

        $response->assertOk();
        $response->assertViewHas('documentCountForUser', 3);
        $this->assertEquals(3, Document::query()->visibleTo($employee)->count());
        $response->assertViewHas('pendingApprovalCount', 0);
        $response->assertViewHas('myDocumentCount', 1);

        $managerResponse = $this->actingAs($salesManager)->get('/documents');

        $managerResponse->assertOk();
        $managerResponse->assertViewHas('pendingApprovalCount', 1);
        $managerResponse->assertViewHas('myDocumentCount', 0);
    }
}
