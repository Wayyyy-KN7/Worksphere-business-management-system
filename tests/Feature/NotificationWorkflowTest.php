<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentShare;
use App\Models\FollowUp;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Notification;
use App\Models\Opportunity;
use App\Models\Sale;
use App\Models\User;
use App\Services\CrmNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NotificationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_leave_submission_notifies_department_manager_and_approval_notifies_employee(): void
    {
        [$department, $employee, $manager] = $this->salesUsers();
        $secondManager = $this->user($department, 'SALES_MANAGER', 'second.leave.manager@example.com');
        $leaveType = LeaveType::create([
            'name' => 'Annual Leave',
            'code' => 'ANNUAL',
            'days_allowed' => 12,
            'status' => 'ACTIVE',
        ]);

        $this->actingAs($employee)->post(route('employee.leave.store'), [
            'leave_type_id' => $leaveType->id,
            'start_date' => today()->addDay()->toDateString(),
            'end_date' => today()->addDay()->toDateString(),
            'reason' => 'Personal appointment',
        ])->assertRedirect(route('employee.leave.index'));

        $leaveRequest = LeaveRequest::firstOrFail();
        $managerNotification = Notification::where('user_id', $manager->id)->where('type', 'LEAVE_REQUEST')->firstOrFail();
        $this->assertSame(LeaveRequest::class, $managerNotification->related_type);
        $this->assertSame($leaveRequest->id, $managerNotification->related_id);

        $this->actingAs($manager)->post(route('manager.leave.approve', $leaveRequest), [
            'comment' => 'Approved',
        ])->assertRedirect(route('manager.leave.index'));

        $employeeNotification = Notification::where('user_id', $employee->id)->where('type', 'LEAVE_APPROVED')->firstOrFail();
        $this->assertSame($leaveRequest->id, $employeeNotification->related_id);
        $this->assertDatabaseHas('leave_approvals', [
            'leave_request_id' => $leaveRequest->id,
            'reviewer_id' => $secondManager->id,
            'status' => 'CANCELLED',
        ]);
        $this->actingAs($employee)->get(route('notifications.open', $employeeNotification))
            ->assertRedirect(route('employee.leave.show', $leaveRequest));
        $this->assertTrue($employeeNotification->fresh()->is_read);
    }

    public function test_rejected_leave_request_notifies_its_employee(): void
    {
        [, $employee, $manager] = $this->salesUsers();
        $leaveType = LeaveType::create(['name' => 'Sick Leave', 'code' => 'SICK', 'days_allowed' => 10, 'status' => 'ACTIVE']);

        $this->actingAs($employee)->post(route('employee.leave.store'), [
            'leave_type_id' => $leaveType->id,
            'start_date' => today()->addDay()->toDateString(),
            'end_date' => today()->addDay()->toDateString(),
            'reason' => 'Medical appointment',
        ])->assertRedirect(route('employee.leave.index'));
        $leaveRequest = LeaveRequest::firstOrFail();

        $this->actingAs($manager)->post(route('manager.leave.reject', $leaveRequest), [
            'rejection_reason' => 'Please provide supporting information.',
        ])->assertRedirect(route('manager.leave.index'));

        $this->assertDatabaseHas('notifications', [
            'user_id' => $employee->id,
            'type' => 'LEAVE_REJECTED',
            'related_type' => LeaveRequest::class,
            'related_id' => $leaveRequest->id,
        ]);
    }

    public function test_document_submission_approval_and_sharing_notify_only_the_relevant_users(): void
    {
        Storage::fake('public');
        [$department, $employee, $manager] = $this->salesUsers();
        $colleague = $this->user($department, 'SALES_EMPLOYEE', 'colleague@example.com');

        $this->actingAs($employee)->post(route('documents.store'), [
            'title' => 'Sales Playbook',
            'description' => 'Internal sales process',
            'document_type' => 'POLICY',
            'department_id' => $department->id,
            'access_level' => 'DEPARTMENT',
            'file' => UploadedFile::fake()->create('playbook.pdf', 20, 'application/pdf'),
        ])->assertRedirect(route('documents.index'));

        $document = Document::firstOrFail();
        $this->assertSame('PENDING_REVIEW', $document->status);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $manager->id,
            'type' => 'DOCUMENT_REVIEW_REQUESTED',
            'related_type' => Document::class,
            'related_id' => $document->id,
        ]);
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $colleague->id,
            'type' => 'DOCUMENT_REVIEW_REQUESTED',
        ]);

        $this->actingAs($manager)->post(route('documents.approve', $document), ['comment' => 'Approved'])
            ->assertSessionHas('success');
        $this->assertDatabaseHas('notifications', [
            'user_id' => $employee->id,
            'type' => 'DOCUMENT_APPROVED',
            'related_id' => $document->id,
        ]);

        $this->actingAs($employee)->post(route('documents.share', $document), [
            'user_ids' => [$colleague->id],
            'access_type' => 'VIEW',
        ])->assertSessionHas('success');
        $this->assertDatabaseHas('notifications', [
            'user_id' => $colleague->id,
            'type' => 'DOCUMENT_SHARED',
            'related_id' => $document->id,
        ]);

        $this->put(route('documents.update', $document), [
            'title' => $document->title,
            'description' => $document->description,
            'document_type' => $document->document_type,
            'department_id' => $department->id,
            'access_level' => 'COMPANY_WIDE',
            'status' => 'PUBLISHED',
        ])->assertRedirect(route('documents.show', $document));
        $this->assertDatabaseHas('notifications', [
            'user_id' => $colleague->id,
            'type' => 'DOCUMENT_COMPANY_WIDE',
            'related_id' => $document->id,
        ]);

        $this->actingAs($employee)->post(route('documents.store'), [
            'title' => 'Draft for Rejection',
            'description' => 'Needs correction',
            'document_type' => 'REPORT',
            'department_id' => $department->id,
            'access_level' => 'PRIVATE',
            'file' => UploadedFile::fake()->create('draft.pdf', 10, 'application/pdf'),
        ])->assertRedirect(route('documents.index'));
        $rejectedDocument = Document::where('title', 'Draft for Rejection')->firstOrFail();
        $this->actingAs($manager)->post(route('documents.reject', $rejectedDocument), [
            'comment' => 'Please correct the figures.',
        ])->assertSessionHas('success');
        $this->assertDatabaseHas('notifications', [
            'user_id' => $employee->id,
            'type' => 'DOCUMENT_REJECTED',
            'related_id' => $rejectedDocument->id,
        ]);
    }

    public function test_selected_user_upload_grants_access_and_notifies_only_after_publication(): void
    {
        Storage::fake('public');
        [$department, $employee, $manager] = $this->salesUsers();
        $recipient = $this->user($department, 'SALES_EMPLOYEE', 'selected.recipient@example.com');

        $this->actingAs($employee)->get(route('documents.create'))->assertOk()->assertSee('Specific users');
        $this->post(route('documents.store'), [
            'title' => 'Selected User Procedure',
            'description' => 'Restricted department guide',
            'document_type' => 'POLICY',
            'department_id' => $department->id,
            'access_level' => 'SELECTED_USERS',
            'user_ids' => [$recipient->id],
            'file' => UploadedFile::fake()->create('restricted.pdf', 20, 'application/pdf'),
        ])->assertRedirect(route('documents.index'));

        $document = Document::where('title', 'Selected User Procedure')->firstOrFail();
        $this->assertSame('PENDING_REVIEW', $document->status);
        $this->assertDatabaseHas('document_shares', [
            'document_id' => $document->id,
            'user_id' => $recipient->id,
            'access_type' => 'VIEW',
        ]);
        $this->assertSame(0, Notification::where('user_id', $recipient->id)->where('related_id', $document->id)->count());
        $this->actingAs($recipient)->get(route('documents.show', $document))->assertForbidden();

        $this->actingAs($manager)->post(route('documents.approve', $document), ['comment' => 'Approved'])
            ->assertSessionHas('success');
        $this->assertDatabaseHas('notifications', [
            'user_id' => $recipient->id,
            'type' => 'DOCUMENT_SHARED',
            'related_type' => Document::class,
            'related_id' => $document->id,
        ]);
        $this->actingAs($recipient)->get(route('documents.show', $document))->assertOk();
    }

    public function test_manager_upload_to_selected_users_publishes_and_notifies_immediately(): void
    {
        Storage::fake('public');
        [$department, , $manager] = $this->salesUsers();
        $recipient = $this->user($department, 'SALES_EMPLOYEE', 'manager.selected.recipient@example.com');

        $this->actingAs($manager)->post(route('documents.store'), [
            'title' => 'Manager Shared Guide',
            'description' => 'Published immediately to selected recipients',
            'document_type' => 'POLICY',
            'department_id' => $department->id,
            'access_level' => 'SELECTED_USERS',
            'user_ids' => [$recipient->id],
            'file' => UploadedFile::fake()->create('manager-guide.pdf', 20, 'application/pdf'),
        ])->assertRedirect(route('documents.index'));

        $document = Document::where('title', 'Manager Shared Guide')->firstOrFail();
        $this->assertSame('PUBLISHED', $document->status);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $recipient->id,
            'type' => 'DOCUMENT_SHARED',
            'related_id' => $document->id,
        ]);
        $this->actingAs($recipient)->get(route('documents.show', $document))->assertOk();
    }

    public function test_employee_public_upload_waits_for_approval_before_notifying_company(): void
    {
        Storage::fake('public');
        [$department, $employee, $manager] = $this->salesUsers();
        $otherDepartment = Department::create([
            'name' => 'HR',
            'code' => 'PUBLIC-HR',
            'status' => 'ACTIVE',
        ]);
        $recipient = $this->user($otherDepartment, 'HR_EMPLOYEE', 'public.upload.recipient@example.com');

        $this->actingAs($employee)->post(route('documents.store'), [
            'title' => 'Company Public Procedure',
            'description' => 'Available after review',
            'document_type' => 'POLICY',
            'department_id' => $department->id,
            'access_level' => 'COMPANY_WIDE',
            'file' => UploadedFile::fake()->create('public-procedure.pdf', 20, 'application/pdf'),
        ])->assertRedirect(route('documents.index'));

        $document = Document::where('title', 'Company Public Procedure')->firstOrFail();
        $this->assertSame('PENDING_REVIEW', $document->status);
        $this->assertSame(0, DocumentShare::where('document_id', $document->id)->count());
        $this->assertSame(0, Notification::where('user_id', $recipient->id)
            ->where('type', 'DOCUMENT_COMPANY_WIDE')->count());
        $this->actingAs($recipient)->get(route('documents.show', $document))->assertForbidden();

        $this->actingAs($manager)->post(route('documents.approve', $document), ['comment' => 'Approved'])
            ->assertSessionHas('success');

        $document->refresh();
        $this->assertSame('PUBLISHED', $document->status);
        $this->assertTrue($document->visibleTo($recipient));
        $this->assertDatabaseHas('notifications', [
            'user_id' => $recipient->id,
            'type' => 'DOCUMENT_COMPANY_WIDE',
            'related_type' => Document::class,
            'related_id' => $document->id,
        ]);
        $this->actingAs($recipient)->get(route('documents.show', $document))->assertOk();
    }

    public function test_selected_user_access_requires_at_least_one_active_recipient(): void
    {
        Storage::fake('public');
        [, $employee] = $this->salesUsers();

        $this->actingAs($employee)->post(route('documents.store'), [
            'title' => 'Missing Recipients',
            'document_type' => 'REPORT',
            'access_level' => 'SELECTED_USERS',
            'file' => UploadedFile::fake()->create('missing-recipients.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors('user_ids');

        $this->assertDatabaseMissing('documents', ['title' => 'Missing Recipients']);
    }

    public function test_notifications_index_paginates_and_renders_shared_page_controls(): void
    {
        [, $employee] = $this->salesUsers();

        foreach (range(1, 21) as $number) {
            Notification::create([
                'user_id' => $employee->id,
                'type' => 'GENERAL',
                'title' => "Notification {$number}",
                'message' => "Message {$number}",
            ]);
        }

        $pageOne = $this->actingAs($employee)->get(route('notifications.index'));
        $pageOne->assertOk()
            ->assertSee(route('notifications.index', ['page' => 2]), false)
            ->assertSee('page-link', false)
            ->assertSee('d-sm-none', false)
            ->assertSee('d-sm-flex', false)
            ->assertDontSee('hidden sm:flex', false);

        $this->get(route('notifications.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('Notification 1')
            ->assertViewHas('notifications', function ($notifications) {
                $this->assertSame(2, $notifications->currentPage());
                $this->assertSame(21, $notifications->total());
                $this->assertCount(1, $notifications->items());

                return true;
            });
    }

    public function test_notification_actions_are_limited_to_the_recipient_and_deduplicate_events(): void
    {
        [, $employee, $manager] = $this->salesUsers();
        $service = app(CrmNotificationService::class);

        $service->notifyUser($employee, 'GENERAL', 'Test alert', 'Event message', null, 'test.event.1');
        $service->notifyUser($employee, 'GENERAL', 'Duplicate alert', 'Duplicate message', null, 'test.event.1');
        $notification = Notification::where('user_id', $employee->id)->firstOrFail();

        $this->assertSame(1, Notification::where('user_id', $employee->id)->count());
        $this->actingAs($employee)->get(route('notifications.index'))->assertOk()->assertSee('Test alert');
        $this->patch(route('notifications.read', $notification))->assertRedirect();
        $this->assertTrue($notification->fresh()->is_read);
        $this->patch(route('notifications.unread', $notification))->assertRedirect();
        $this->assertFalse($notification->fresh()->is_read);

        $this->actingAs($manager)->patch(route('notifications.read', $notification))->assertForbidden();

        $admin = User::create([
            'employee_id' => 'EMP-ADMIN-NOTIFY',
            'name' => 'System Admin',
            'email' => 'system-admin@example.com',
            'password' => bcrypt('password'),
            'department_id' => $manager->department_id,
            'role' => 'ADMIN',
            'status' => 'ACTIVE',
        ]);
        $this->actingAs($admin)->get(route('notifications.index'))->assertOk()->assertSee('Test alert');
        $this->assertSame(1, $admin->unreadNotificationsCount());

        $this->actingAs($employee)->post(route('notifications.read-all'))->assertRedirect();
        $this->assertSame(0, $employee->fresh()->unreadNotificationsCount());
    }

    public function test_opportunity_assignment_and_important_stage_changes_notify_the_right_sales_users(): void
    {
        [$department, $employee, $manager] = $this->salesUsers();
        $secondManager = $this->user($department, 'SALES_MANAGER', 'second-manager@example.com');
        $customer = $this->customer($employee);

        $this->actingAs($manager)->post(route('crm.opportunities.store'), [
            'title' => 'New Software Project',
            'customer_id' => $customer->id,
            'estimated_value' => 5000,
            'probability' => 40,
            'expected_close_date' => today()->addMonth()->toDateString(),
            'stage' => 'NEW',
            'status' => 'OPEN',
            'assigned_to' => $employee->id,
            'description' => 'Initial opportunity',
        ])->assertRedirect(route('crm.opportunities.index'));

        $opportunity = Opportunity::firstOrFail();
        $this->assertDatabaseHas('notifications', [
            'user_id' => $employee->id,
            'type' => 'OPPORTUNITY_ASSIGNED',
            'related_id' => $opportunity->id,
        ]);

        $this->put(route('crm.opportunities.updateStage', $opportunity), ['stage' => 'PROPOSAL'])
            ->assertRedirect();
        $this->assertDatabaseHas('notifications', [
            'user_id' => $secondManager->id,
            'type' => 'OPPORTUNITY_STAGE_CHANGED',
            'related_type' => Opportunity::class,
            'related_id' => $opportunity->id,
        ]);

        $newOwner = $this->user($department, 'SALES_EMPLOYEE', 'new-owner@example.com');
        $this->put(route('crm.opportunities.update', $opportunity), [
            'title' => $opportunity->title,
            'customer_id' => $customer->id,
            'estimated_value' => $opportunity->estimated_value,
            'probability' => $opportunity->probability,
            'expected_close_date' => $opportunity->expected_close_date->toDateString(),
            'stage' => $opportunity->stage,
            'status' => 'OPEN',
            'assigned_to' => $newOwner->id,
            'description' => $opportunity->description,
        ])->assertRedirect(route('crm.opportunities.index'));
        $this->assertDatabaseHas('notifications', [
            'user_id' => $newOwner->id,
            'type' => 'OPPORTUNITY_REASSIGNED',
            'related_id' => $opportunity->id,
        ]);
    }

    public function test_assigned_follow_up_notifies_employee_and_completion_notifies_manager(): void
    {
        [$department, $employee, $manager] = $this->salesUsers();
        $customer = $this->customer($employee);

        $this->actingAs($manager)->post(route('crm.follow-ups.store'), [
            'customer_id' => $customer->id,
            'title' => 'Call about proposal',
            'description' => 'Confirm proposal details',
            'follow_up_date' => today()->addDay()->toDateString(),
            'follow_up_time' => '10:00',
            'type' => 'CALL',
            'priority' => 'HIGH',
            'assigned_to' => $employee->id,
            'recurrence_rule' => 'NONE',
        ])->assertRedirect(route('crm.follow-ups.index'));

        $followUp = FollowUp::firstOrFail();
        $this->assertDatabaseHas('notifications', [
            'user_id' => $employee->id,
            'type' => 'FOLLOW_UP_ASSIGNED',
            'related_type' => FollowUp::class,
            'related_id' => $followUp->id,
        ]);

        $newAssignee = $this->user($department, 'SALES_EMPLOYEE', 'follow-up-owner@example.com');
        $this->actingAs($manager)->put(route('crm.follow-ups.update', $followUp), [
            'customer_id' => $customer->id,
            'title' => $followUp->title,
            'description' => $followUp->description,
            'follow_up_date' => $followUp->follow_up_date->toDateString(),
            'follow_up_time' => $followUp->follow_up_time,
            'type' => $followUp->type,
            'priority' => $followUp->priority,
            'assigned_to' => $newAssignee->id,
            'recurrence_rule' => 'NONE',
        ])->assertRedirect(route('crm.follow-ups.show', $followUp));
        $this->assertDatabaseHas('notifications', [
            'user_id' => $newAssignee->id,
            'type' => 'FOLLOW_UP_REASSIGNED',
            'related_id' => $followUp->id,
        ]);

        $this->actingAs($newAssignee)->post(route('crm.follow-ups.start', $followUp))->assertRedirect();
        $this->post(route('crm.follow-ups.complete', $followUp), [
            'completion_notes' => 'Customer requested another quote.',
        ])->assertRedirect();
        $this->assertDatabaseHas('notifications', [
            'user_id' => $manager->id,
            'type' => 'FOLLOW_UP_COMPLETED',
            'related_id' => $followUp->id,
        ]);
    }

    public function test_reminder_assignment_and_rescheduling_notify_assignee(): void
    {
        [$department, $employee, $manager] = $this->salesUsers();
        $customer = $this->customer($employee);

        $this->actingAs($manager)->post(route('crm.reminders.store'), [
            'title' => 'Call about invoice',
            'customer_id' => $customer->id,
            'user_id' => $employee->id,
            'reminder_date' => today()->addDay()->toDateString(),
            'reminder_time' => '10:00',
        ])->assertRedirect(route('crm.reminders.index'));

        $reminder = \App\Models\Reminder::firstOrFail();
        $this->assertDatabaseHas('notifications', [
            'user_id' => $employee->id,
            'type' => 'REMINDER_ASSIGNED',
            'related_id' => $reminder->id,
        ]);

        $this->actingAs($employee)->post(route('crm.reminders.reschedule', $reminder), [
            'reminder_date' => today()->addDays(2)->toDateString(),
            'reminder_time' => '11:00',
            'reschedule_note' => 'Customer requested a later call.',
        ])->assertRedirect();
        $this->assertDatabaseHas('notifications', [
            'user_id' => $employee->id,
            'type' => 'REMINDER_RESCHEDULED',
            'related_id' => $reminder->id,
        ]);
    }

    public function test_sale_creation_payment_change_and_invoice_notify_responsible_sales_users(): void
    {
        Storage::fake('public');
        [$department, $employee, $manager] = $this->salesUsers();
        $customer = $this->customer($employee);

        $this->actingAs($employee)->post(route('crm.sales.store'), [
            'customer_id' => $customer->id,
            'sale_date' => today()->toDateString(),
            'payment_status' => 'PENDING',
            'subtotal' => 100,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'final_amount' => 100,
            'items' => [[
                'product_name' => 'CRM License',
                'quantity' => 1,
                'unit_price' => 100,
                'discount' => 0,
                'tax' => 0,
            ]],
        ])->assertRedirect(route('crm.sales.index'));

        $sale = Sale::firstOrFail();
        $this->assertSame($employee->id, $sale->assigned_to);
        $this->actingAs($manager)->get(route('crm.sales.index'))
            ->assertOk()
            ->assertSee($sale->sale_number);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $manager->id,
            'type' => 'SALE_CREATED',
            'related_id' => $sale->id,
        ]);

        $this->actingAs($manager)->put(route('crm.sales.update', $sale), [
            'customer_id' => $customer->id,
            'assigned_to' => $employee->id,
            'sale_date' => today()->toDateString(),
            'payment_status' => 'PAID',
            'subtotal' => 100,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'final_amount' => 100,
            'items' => [[
                'product_name' => 'CRM License',
                'quantity' => 1,
                'unit_price' => 100,
                'discount' => 0,
                'tax' => 0,
            ]],
        ])->assertRedirect(route('crm.sales.index'));
        $this->assertDatabaseHas('notifications', [
            'user_id' => $employee->id,
            'type' => 'PAYMENT_STATUS_CHANGED',
            'related_id' => $sale->id,
        ]);

        $this->post(route('crm.sales.store-invoice', $sale))->assertSessionHas('success');
        $this->assertDatabaseHas('notifications', [
            'user_id' => $employee->id,
            'type' => 'INVOICE_GENERATED',
            'related_type' => Document::class,
        ]);
    }

    public function test_sale_assignment_notifies_new_employee_and_shows_work_only_to_assignee_and_manager(): void
    {
        [$department, $employee, $manager] = $this->salesUsers();
        $otherEmployee = $this->user($department, 'SALES_EMPLOYEE', 'other.sales.employee@example.com');
        $customer = $this->customer($employee);
        $otherCustomer = $this->customer($otherEmployee);
        $otherSale = Sale::create([
            'sale_number' => 'SALE-OTHER-001',
            'customer_id' => $otherCustomer->id,
            'sale_date' => today()->toDateString(),
            'total_amount' => 50,
            'final_amount' => 50,
            'payment_status' => 'PENDING',
            'assigned_to' => $otherEmployee->id,
        ]);

        $this->actingAs($manager)->post(route('crm.sales.store'), [
            'customer_id' => $customer->id,
            'assigned_to' => $employee->id,
            'sale_date' => today()->toDateString(),
            'payment_status' => 'PENDING',
            'subtotal' => 100,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'final_amount' => 100,
            'items' => [[
                'product_name' => 'CRM License',
                'quantity' => 1,
                'unit_price' => 100,
                'discount' => 0,
                'tax' => 0,
            ]],
        ])->assertRedirect(route('crm.sales.index'));

        $assignedSale = Sale::query()->where('customer_id', $customer->id)->firstOrFail();
        $assignmentNotification = Notification::query()
            ->where('user_id', $employee->id)
            ->where('type', 'SALE_CREATED')
            ->where('related_id', $assignedSale->id)
            ->firstOrFail();

        $this->actingAs($employee)->get(route('notifications.open', $assignmentNotification))
            ->assertRedirect(route('crm.sales.show', $assignedSale));
        $this->get(route('crm.sales.index'))
            ->assertOk()
            ->assertSee($assignedSale->sale_number)
            ->assertDontSee($otherSale->sale_number);

        $this->actingAs($manager)->get(route('crm.sales.index'))
            ->assertOk()
            ->assertSee($assignedSale->sale_number)
            ->assertSee($otherSale->sale_number);

        $this->actingAs($manager)->put(route('crm.sales.update', $assignedSale), [
            'customer_id' => $customer->id,
            'assigned_to' => $otherEmployee->id,
            'sale_date' => today()->toDateString(),
            'payment_status' => 'PENDING',
            'subtotal' => 100,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'final_amount' => 100,
            'items' => [[
                'product_name' => 'CRM License',
                'quantity' => 1,
                'unit_price' => 100,
                'discount' => 0,
                'tax' => 0,
            ]],
        ])->assertRedirect(route('crm.sales.index'));

        $this->assertDatabaseHas('notifications', [
            'user_id' => $otherEmployee->id,
            'type' => 'SALE_ASSIGNED',
            'related_id' => $assignedSale->id,
        ]);
        $this->actingAs($employee)->get(route('crm.sales.index'))
            ->assertOk()
            ->assertViewHas('sales', fn ($sales) => !$sales->contains('id', $assignedSale->id));
        $this->actingAs($otherEmployee)->get(route('crm.sales.index'))
            ->assertOk()
            ->assertViewHas('sales', fn ($sales) => $sales->contains('id', $assignedSale->id));
    }

    public function test_customer_assignment_notifies_assigned_employee_and_notification_opens_customer(): void
    {
        [$department, $employee, $manager] = $this->salesUsers();
        $newEmployee = $this->user($department, 'SALES_EMPLOYEE', 'new.customer.owner@example.com');

        $this->actingAs($manager)->post(route('crm.customers.store'), [
            'name' => 'Assigned Demo Customer',
            'company_name' => 'Assigned Demo Ltd',
            'status' => 'ACTIVE',
            'assigned_to' => $employee->id,
        ])->assertRedirect(route('crm.customers.index'));

        $customer = \App\Models\Customer::query()->where('name', 'Assigned Demo Customer')->firstOrFail();
        $notification = Notification::query()
            ->where('user_id', $employee->id)
            ->where('type', 'CUSTOMER_ASSIGNED')
            ->where('related_id', $customer->id)
            ->firstOrFail();
        $this->actingAs($employee)->get(route('notifications.open', $notification))
            ->assertRedirect(route('crm.customers.show', $customer));

        $this->actingAs($manager)->put(route('crm.customers.update', $customer), [
            'name' => $customer->name,
            'company_name' => $customer->company_name,
            'status' => $customer->status,
            'assigned_to' => $newEmployee->id,
        ])->assertRedirect(route('crm.customers.index'));
        $this->assertDatabaseHas('notifications', [
            'user_id' => $newEmployee->id,
            'type' => 'CUSTOMER_REASSIGNED',
            'related_type' => \App\Models\Customer::class,
            'related_id' => $customer->id,
        ]);
    }

    public function test_sales_manager_sales_list_and_assignments_are_department_scoped(): void
    {
        [$department, $employee, $manager] = $this->salesUsers();
        $otherDepartment = Department::create([
            'name' => 'Other Sales',
            'code' => 'SALES-OTHER',
            'status' => 'ACTIVE',
        ]);
        $otherEmployee = $this->user($otherDepartment, 'SALES_EMPLOYEE', 'other.department.sales@example.com');
        $ownCustomer = $this->customer($employee);
        $otherCustomer = $this->customer($otherEmployee);
        $ownSale = Sale::create([
            'sale_number' => 'SALE-MANAGER-OWN-001',
            'customer_id' => $ownCustomer->id,
            'sale_date' => today()->toDateString(),
            'total_amount' => 100,
            'final_amount' => 100,
            'payment_status' => 'PAID',
            'assigned_to' => $employee->id,
        ]);
        $otherSale = Sale::create([
            'sale_number' => 'SALE-MANAGER-OTHER-001',
            'customer_id' => $otherCustomer->id,
            'sale_date' => today()->toDateString(),
            'total_amount' => 200,
            'final_amount' => 200,
            'payment_status' => 'PENDING',
            'assigned_to' => $otherEmployee->id,
        ]);
        $ownCustomerSaleAssignedElsewhere = Sale::create([
            'sale_number' => 'SALE-MANAGER-CUSTOMER-OWNED-001',
            'customer_id' => $ownCustomer->id,
            'sale_date' => today()->toDateString(),
            'total_amount' => 150,
            'final_amount' => 150,
            'payment_status' => 'PENDING',
            'assigned_to' => $otherEmployee->id,
        ]);

        $this->actingAs($manager)->get(route('crm.sales.index'))
            ->assertOk()
            ->assertSee($ownSale->sale_number)
            ->assertSee($ownCustomerSaleAssignedElsewhere->sale_number)
            ->assertDontSee($otherSale->sale_number)
            ->assertViewHas('stats', function (array $stats): bool {
                $this->assertSame(2, $stats['total_sales']);
                $this->assertSame(250.0, (float) $stats['total_revenue']);
                return true;
            });
        $this->get(route('crm.sales.show', $ownCustomerSaleAssignedElsewhere))
            ->assertOk()
            ->assertSee($ownCustomerSaleAssignedElsewhere->sale_number);

        $this->get(route('crm.sales.create'))
            ->assertOk()
            ->assertViewHas('assignableUsers', fn ($users) => $users->contains('id', $employee->id)
                && $users->contains('id', $manager->id)
                && !$users->contains('id', $otherEmployee->id));

        $this->post(route('crm.sales.store'), [
            'customer_id' => $ownCustomer->id,
            'assigned_to' => $otherEmployee->id,
            'sale_date' => today()->toDateString(),
            'payment_status' => 'PENDING',
            'subtotal' => 100,
            'final_amount' => 100,
            'items' => [[
                'product_name' => 'CRM License',
                'quantity' => 1,
                'unit_price' => 100,
            ]],
        ])->assertSessionHasErrors('assigned_to');

        $this->assertSame(3, Sale::query()->count());
        $this->assertNotSame((int) $manager->department_id, (int) $otherEmployee->department_id);
    }

    public function test_sales_employee_default_assignment_is_visible_to_department_manager(): void
    {
        [$department, $employee, $manager] = $this->salesUsers();
        $customer = $this->customer($employee);

        $this->actingAs($employee)->get(route('crm.sales.create'))
            ->assertOk()
            ->assertViewHas('assignableUsers', fn ($users) => $users->count() === 1
                && $users->contains('id', $employee->id));

        $this->post(route('crm.sales.store'), [
            'customer_id' => $customer->id,
            'sale_date' => today()->toDateString(),
            'payment_status' => 'PENDING',
            'subtotal' => 125,
            'final_amount' => 125,
            'items' => [[
                'product_name' => 'Support Plan',
                'quantity' => 1,
                'unit_price' => 125,
            ]],
        ])->assertRedirect(route('crm.sales.index'));

        $sale = Sale::query()->where('customer_id', $customer->id)->firstOrFail();
        $this->assertSame($employee->id, $sale->assigned_to);
        $this->actingAs($manager)->get(route('crm.sales.index'))
            ->assertOk()
            ->assertSee($sale->sale_number);
        $this->assertSame($department->id, $employee->department_id);
    }

    public function test_sales_pages_keep_archived_customer_details_available(): void
    {
        [, $employee, $manager] = $this->salesUsers();
        $customer = $this->customer($employee);
        $sale = Sale::create([
            'sale_number' => 'SALE-ARCHIVED-CUSTOMER',
            'customer_id' => $customer->id,
            'sale_date' => today()->toDateString(),
            'total_amount' => 100,
            'final_amount' => 100,
            'payment_status' => 'PENDING',
            'assigned_to' => $employee->id,
        ]);
        $customer->delete();

        $this->actingAs($employee)->get(route('crm.sales.index'))
            ->assertOk()
            ->assertSee('SALE-ARCHIVED-CUSTOMER')
            ->assertSee($customer->name);
        $this->actingAs($manager)->get(route('crm.sales.show', $sale))
            ->assertOk()
            ->assertSee($customer->name)
            ->assertSee('Archived');
        $this->get(route('crm.sales.invoice', $sale))
            ->assertOk()
            ->assertSee($customer->name);
        $this->get(route('crm.sales.edit', $sale))
            ->assertOk()
            ->assertSee($customer->name);
    }

    private function salesUsers(): array
    {
        $department = Department::create([
            'name' => 'Sales',
            'code' => 'SALES',
            'description' => 'Sales department',
            'status' => 'ACTIVE',
        ]);

        return [
            $department,
            $this->user($department, 'SALES_EMPLOYEE', 'employee@example.com'),
            $this->user($department, 'SALES_MANAGER', 'manager@example.com'),
        ];
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

    private function customer(User $owner): \App\Models\Customer
    {
        return \App\Models\Customer::create([
            'customer_code' => 'CUS-' . strtoupper(substr(md5($owner->email), 0, 8)),
            'name' => 'Acme Software',
            'company_name' => 'Acme Software Ltd',
            'assigned_to' => $owner->id,
            'status' => 'ACTIVE',
        ]);
    }
}