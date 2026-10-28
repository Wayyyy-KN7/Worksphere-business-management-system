<?php

namespace Tests\Feature;

use App\Models\CrmActivityHistory;
use App\Models\Customer;
use App\Models\Department;
use App\Models\Document;
use App\Models\FollowUp;
use App\Models\Notification;
use App\Models\Opportunity;
use App\Models\Reminder;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CrmActivityWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_follow_up_creates_a_linked_reminder_for_the_assigned_employee(): void
    {
        [$department, $employee] = $this->salesEmployee();
        $customer = $this->customer($employee);
        $otherEmployee = User::create([
            'employee_id' => 'EMP-4103',
            'name' => 'Second Sales Employee',
            'email' => 'second.sales@example.com',
            'password' => bcrypt('password'),
            'department_id' => $department->id,
            'role' => 'SALES_EMPLOYEE',
            'status' => 'ACTIVE',
        ]);

        $response = $this->actingAs($employee)->post(route('crm.follow-ups.store'), [
            'customer_id' => $customer->id,
            'title' => 'Call about quotation',
            'description' => 'Confirm proposal details',
            'follow_up_date' => today()->addDay()->toDateString(),
            'follow_up_time' => '10:00',
            'type' => 'CALL',
            'priority' => 'HIGH',
            'recurrence_rule' => 'NONE',
            'assigned_to' => $otherEmployee->id,
        ]);

        $followUp = FollowUp::with('reminders')->firstOrFail();
        $response->assertRedirect(route('crm.follow-ups.index'));
        $this->assertSame('PLANNED', $followUp->status);
        $this->assertSame($employee->id, $followUp->assigned_to);
        $this->assertSame($employee->id, $followUp->created_by);
        $this->assertCount(1, $followUp->reminders);
        $this->assertSame($followUp->id, $followUp->reminders->first()->follow_up_id);
        $this->assertSame('PENDING', $followUp->reminders->first()->status);
        $this->assertDatabaseHas('crm_activity_histories', [
            'activity_type' => FollowUp::class,
            'activity_id' => $followUp->id,
            'action' => 'CREATED',
        ]);
        $this->get(route('crm.follow-ups.show', $followUp))->assertOk();
        $this->get(route('crm.reminders.show', $followUp->reminders->first()))->assertOk();
    }

    public function test_add_follow_up_from_opportunity_prefills_opportunity_and_related_customer(): void
    {
        [, $employee] = $this->salesEmployee();
        $customer = $this->customer($employee);
        $opportunity = $this->createOpportunity($customer, $employee, 'OPP-4101', 14000, 55, 'QUALIFIED');

        $this->actingAs($employee)
            ->get(route('crm.follow-ups.create', ['opportunity_id' => $opportunity->id]))
            ->assertOk()
            ->assertSee('value="' . $opportunity->id . '" data-customer-id="' . $customer->id . '" selected', false)
            ->assertSee('value="' . $customer->id . '" selected', false);

        $this->post(route('crm.follow-ups.store'), [
            'opportunity_id' => $opportunity->id,
            'title' => 'Follow up on opportunity',
            'follow_up_date' => today()->addDay()->toDateString(),
            'type' => 'CALL',
            'priority' => 'NORMAL',
        ])->assertRedirect(route('crm.follow-ups.index'));

        $followUp = FollowUp::query()->firstOrFail();
        $this->assertSame($opportunity->id, $followUp->opportunity_id);
        $this->assertSame($customer->id, $followUp->customer_id);
    }

    public function test_assigned_employee_starts_and_completes_follow_up_with_outcome_history(): void
    {
        [, $employee] = $this->salesEmployee();
        $customer = $this->customer($employee);
        $followUp = $this->followUp($employee, $customer);
        Reminder::create([
            'user_id' => $employee->id,
            'follow_up_id' => $followUp->id,
            'title' => $followUp->title,
            'reminder_date' => $followUp->follow_up_date,
            'reminder_time' => $followUp->follow_up_time,
            'status' => 'PENDING',
        ]);

        $this->actingAs($employee)->post(route('crm.follow-ups.start', $followUp))->assertSessionHasNoErrors();
        $this->assertSame('IN_PROGRESS', $followUp->fresh()->status);

        $this->post(route('crm.follow-ups.complete', $followUp), [
            'completion_notes' => 'Customer requested another quotation.',
        ])->assertSessionHasNoErrors();

        $followUp->refresh();
        $this->assertSame('COMPLETED', $followUp->status);
        $this->assertNotNull($followUp->completed_at);
        $this->assertSame('Customer requested another quotation.', $followUp->notes);
        $this->assertSame('COMPLETED', $followUp->reminders()->first()->status);
        $this->assertDatabaseHas('crm_activity_histories', [
            'activity_type' => FollowUp::class,
            'activity_id' => $followUp->id,
            'action' => 'COMPLETED',
            'notes' => 'Customer requested another quotation.',
        ]);
    }

    public function test_employee_cannot_view_another_employees_follow_up(): void
    {
        [, $employee] = $this->salesEmployee();
        [, $otherEmployee] = $this->salesEmployee('EMP-4102', 'other@example.com');
        $customer = $this->customer($otherEmployee, 'CUS-4102');
        $followUp = $this->followUp($otherEmployee, $customer);

        $this->actingAs($employee)->get(route('crm.follow-ups.show', $followUp))->assertForbidden();
    }

    public function test_recurring_follow_up_creates_only_one_next_occurrence_on_completion(): void
    {
        [, $employee] = $this->salesEmployee();
        $customer = $this->customer($employee);
        $followUp = $this->followUp($employee, $customer, [
            'status' => 'IN_PROGRESS',
            'recurrence_rule' => 'WEEKLY',
            'recurrence_until' => today()->addMonth()->toDateString(),
        ]);

        $response = $this->actingAs($employee)->post(route('crm.follow-ups.complete', $followUp), [
            'completion_notes' => 'Call completed.',
        ]);
        $response->assertRedirect();

        $this->assertSame(2, FollowUp::count());
        $next = FollowUp::where('recurrence_parent_id', $followUp->id)->firstOrFail();
        $this->assertSame('PLANNED', $next->status);
        $this->assertSame($followUp->follow_up_date->copy()->addWeek()->toDateString(), $next->follow_up_date->toDateString());
        $this->assertSame(1, $next->reminders()->count());

        $this->post(route('crm.follow-ups.complete', $followUp), ['completion_notes' => 'Duplicate request'])->assertStatus(422);
        $this->assertSame(2, FollowUp::count());
    }

    public function test_reminder_can_be_snoozed_and_history_is_retained(): void
    {
        [, $employee] = $this->salesEmployee();
        $reminder = Reminder::create([
            'user_id' => $employee->id,
            'title' => 'Call customer',
            'reminder_date' => today()->toDateString(),
            'reminder_time' => '15:00',
            'status' => 'PENDING',
        ]);

        $this->actingAs($employee)->post(route('crm.reminders.snooze', $reminder), [
            'reminder_date' => today()->addDay()->toDateString(),
            'reminder_time' => '10:00',
            'snooze_note' => 'Customer asked for tomorrow.',
        ])->assertSessionHasNoErrors();

        $this->assertSame('SNOOZED', $reminder->fresh()->status);
        $this->assertSame(today()->addDay()->toDateString(), $reminder->fresh()->reminder_date->toDateString());
        $this->assertDatabaseHas('crm_activity_histories', [
            'activity_type' => Reminder::class,
            'activity_id' => $reminder->id,
            'action' => 'SNOOZED',
            'notes' => 'Customer asked for tomorrow.',
        ]);
    }

    public function test_reminder_can_link_to_a_customer_as_a_polymorphic_crm_record(): void
    {
        [, $employee] = $this->salesEmployee();
        $customer = $this->customer($employee);

        $this->actingAs($employee)->post(route('crm.reminders.store'), [
            'title' => 'Review customer account',
            'customer_id' => $customer->id,
            'remindable_type' => 'customer',
            'remindable_id' => $customer->id,
            'reminder_date' => today()->addDay()->toDateString(),
            'reminder_time' => '10:00',
        ])->assertRedirect(route('crm.reminders.index'));

        $reminder = Reminder::firstOrFail();
        $this->assertSame(Customer::class, $reminder->remindable_type);
        $this->assertSame($customer->id, $reminder->remindable->id);
        $this->assertSame($customer->id, $reminder->customer_id);
        $this->get(route('crm.reminders.show', $reminder))->assertOk()->assertSee('Linked CRM Record')->assertSee($customer->name);
    }

    public function test_reminder_form_lists_visible_sales_and_uses_a_dynamic_record_select(): void
    {
        [, $employee] = $this->salesEmployee();
        $customer = $this->customer($employee);
        $otherCustomer = $this->customer($employee, 'CUS-4102');
        $opportunity = $this->createOpportunity($customer, $employee, 'OPP-REMINDER-001', 1200, 70, 'PROPOSAL');
        $otherOpportunity = $this->createOpportunity($otherCustomer, $employee, 'OPP-REMINDER-002', 1200, 70, 'PROPOSAL');
        $followUp = $this->followUp($employee, $customer, ['opportunity_id' => $opportunity->id]);
        $otherFollowUp = $this->followUp($employee, $otherCustomer, ['opportunity_id' => $otherOpportunity->id]);
        $sale = $this->createSale($customer, $employee, 'SALE-REMINDER-001', 1200);
        $sale->update(['opportunity_id' => $opportunity->id]);
        $otherSale = $this->createSale($otherCustomer, $employee, 'SALE-REMINDER-002', 1200);
        $otherSale->update(['opportunity_id' => $otherOpportunity->id]);

        $this->actingAs($employee)->get(route('crm.reminders.create'))
            ->assertOk()
            ->assertSee('Create Sales Reminder')
            ->assertSee($sale->sale_number)
            ->assertSee($otherSale->sale_number)
            ->assertSee('value="' . $opportunity->id . '" data-customer-id="' . $customer->id . '"', false)
            ->assertSee('value="' . $otherOpportunity->id . '" data-customer-id="' . $otherCustomer->id . '"', false)
            ->assertSee('value="' . $followUp->id . '" data-customer-id="' . $customer->id . '" data-opportunity-id="' . $opportunity->id . '"', false)
            ->assertSee('value="' . $otherFollowUp->id . '" data-customer-id="' . $otherCustomer->id . '" data-opportunity-id="' . $otherOpportunity->id . '"', false)
            ->assertSee('String(record.customer_id)', false)
            ->assertSee('record.opportunity_id', false)
            ->assertSee('name="remindable_type"', false)
            ->assertSee('id="remindable-id" name="remindable_id"', false)
            ->assertDontSee('type="number" min="1" name="remindable_id"', false);
    }

    public function test_reminder_can_link_to_a_sale_and_derives_its_customer_and_opportunity(): void
    {
        [, $employee] = $this->salesEmployee();
        $customer = $this->customer($employee);
        $opportunity = $this->createOpportunity($customer, $employee, 'OPP-REMINDER-001', 1200, 70, 'PROPOSAL');
        $sale = $this->createSale($customer, $employee, 'SALE-REMINDER-002', 1200);
        $sale->update(['opportunity_id' => $opportunity->id]);

        $this->actingAs($employee)->post(route('crm.reminders.store'), [
            'title' => 'Follow up on paid sale',
            'remindable_type' => 'sale',
            'remindable_id' => $sale->id,
            'reminder_date' => today()->addDay()->toDateString(),
        ])->assertRedirect(route('crm.reminders.index'));

        $reminder = Reminder::firstOrFail();
        $this->assertSame(Sale::class, $reminder->remindable_type);
        $this->assertSame($sale->id, $reminder->remindable_id);
        $this->assertSame($customer->id, $reminder->customer_id);
        $this->assertSame($opportunity->id, $reminder->opportunity_id);
        $this->assertNull($reminder->reminder_time);
    }

    public function test_reminder_can_link_to_a_visible_document_from_the_dynamic_record_selector(): void
    {
        [, $employee] = $this->salesEmployee();
        $document = Document::create([
            'document_number' => 'DOC-REMINDER-001',
            'title' => 'Sales proposal',
            'uploaded_by' => $employee->id,
            'access_level' => 'PRIVATE',
            'status' => 'DRAFT',
        ]);

        $this->actingAs($employee)->get(route('crm.reminders.create'))
            ->assertOk()
            ->assertSee($document->document_number);

        $this->post(route('crm.reminders.store'), [
            'title' => 'Review the sales proposal',
            'remindable_type' => 'document',
            'remindable_id' => $document->id,
            'reminder_date' => today()->addDay()->toDateString(),
        ])->assertRedirect(route('crm.reminders.index'));

        $reminder = Reminder::firstOrFail();
        $this->assertSame(Document::class, $reminder->remindable_type);
        $this->assertSame($document->id, $reminder->remindable_id);
    }

    public function test_reminder_rejects_missing_or_conflicting_linked_sales(): void
    {
        [, $employee] = $this->salesEmployee();
        $customer = $this->customer($employee);
        $otherCustomer = $this->customer($employee, 'CUS-4104');
        $sale = $this->createSale($customer, $employee, 'SALE-REMINDER-003', 1200);
        $this->actingAs($employee);

        $this->from(route('crm.reminders.create'))->post(route('crm.reminders.store'), [
            'title' => 'Invalid linked sale',
            'remindable_type' => 'sale',
            'remindable_id' => 999999,
            'reminder_date' => today()->addDay()->toDateString(),
        ])->assertRedirect(route('crm.reminders.create'))->assertSessionHasErrors('remindable_id');

        $this->from(route('crm.reminders.create'))->post(route('crm.reminders.store'), [
            'title' => 'Conflicting linked sale',
            'customer_id' => $otherCustomer->id,
            'remindable_type' => 'sale',
            'remindable_id' => $sale->id,
            'reminder_date' => today()->addDay()->toDateString(),
        ])->assertRedirect(route('crm.reminders.create'))->assertSessionHasErrors('remindable_id');
    }

    public function test_deadline_processor_marks_overdue_and_notifies_assigned_users_once(): void
    {
        [, $employee] = $this->salesEmployee();
        $customer = $this->customer($employee);
        $followUp = $this->followUp($employee, $customer, [
            'follow_up_date' => today()->subDay()->toDateString(),
        ]);
        $reminder = Reminder::create([
            'user_id' => $employee->id,
            'follow_up_id' => $followUp->id,
            'title' => 'Overdue call',
            'reminder_date' => today()->subDay()->toDateString(),
            'status' => 'PENDING',
        ]);

        Artisan::call('crm:activities:process-deadlines');
        Artisan::call('crm:activities:process-deadlines');

        $this->assertSame('OVERDUE', $followUp->fresh()->status);
        $this->assertSame('OVERDUE', $reminder->fresh()->status);
        $this->assertSame(2, Notification::where('user_id', $employee->id)->count());
        $this->assertDatabaseHas('notifications', ['user_id' => $employee->id, 'type' => 'FOLLOW_UP_OVERDUE', 'related_id' => $followUp->id]);
        $this->assertDatabaseHas('notifications', ['user_id' => $employee->id, 'type' => 'REMINDER_OVERDUE', 'related_id' => $reminder->id]);
        $this->assertSame(1, CrmActivityHistory::where('activity_type', FollowUp::class)->where('activity_id', $followUp->id)->where('action', 'OVERDUE')->count());
    }

    public function test_deadline_processor_notifies_for_upcoming_follow_up_and_reminder_once(): void
    {
        [, $employee] = $this->salesEmployee();
        $customer = $this->customer($employee);
        $dueAt = now()->addMinutes(30);
        $followUp = $this->followUp($employee, $customer, [
            'follow_up_date' => $dueAt->toDateString(),
            'follow_up_time' => $dueAt->format('H:i'),
        ]);
        $reminder = Reminder::create([
            'user_id' => $employee->id,
            'title' => 'Upcoming call reminder',
            'reminder_date' => $dueAt->toDateString(),
            'reminder_time' => $dueAt->format('H:i'),
            'status' => 'PENDING',
        ]);

        Artisan::call('crm:activities:process-deadlines');
        Artisan::call('crm:activities:process-deadlines');

        $this->assertSame(1, Notification::where('user_id', $employee->id)->where('type', 'FOLLOW_UP_DUE_SOON')->where('related_type', FollowUp::class)->where('related_id', $followUp->id)->count());
        $this->assertSame(1, Notification::where('user_id', $employee->id)->where('type', 'REMINDER_DUE_SOON')->where('related_type', Reminder::class)->where('related_id', $reminder->id)->count());
    }

    public function test_employee_can_open_follow_up_and_reminder_screens(): void
    {
        [, $employee] = $this->salesEmployee();

        $this->actingAs($employee)->get(route('crm.follow-ups.create'))->assertOk();
        $this->get(route('crm.reminders.create'))->assertOk();
        $this->get(route('crm.reminders.index'))
            ->assertOk()
            ->assertSee(route('crm.reminders.index'), false)
            ->assertSee('> Reminders', false);
    }

    public function test_employee_can_edit_a_reminder_using_the_registered_crm_routes(): void
    {
        [, $employee] = $this->salesEmployee();
        $reminder = Reminder::create([
            'user_id' => $employee->id,
            'title' => 'Reminder before edit',
            'reminder_date' => today()->addDay()->toDateString(),
            'reminder_time' => '10:00',
            'status' => 'PENDING',
        ]);

        $this->actingAs($employee)->get(route('crm.reminders.edit', $reminder))
            ->assertOk()
            ->assertSee(route('crm.reminders.update', $reminder), false);

        $this->put(route('crm.reminders.update', $reminder), [
            'title' => 'Reminder after edit',
            'reminder_date' => today()->addDays(2)->toDateString(),
            'reminder_time' => '11:30',
        ])->assertSessionHas('success', 'Reminder updated successfully.');

        $this->assertSame('Reminder after edit', $reminder->fresh()->title);
        $this->assertSame('11:30', $reminder->fresh()->reminder_time);
    }

    public function test_manager_dashboard_includes_department_employee_activity(): void
    {
        [$department, $employee] = $this->salesEmployee();
        $customer = $this->customer($employee);
        $this->followUp($employee, $customer, ['status' => 'OVERDUE']);
        $manager = User::create([
            'employee_id' => 'EMP-4201',
            'name' => 'Sales Manager',
            'email' => 'sales.manager@example.com',
            'password' => bcrypt('password'),
            'department_id' => $department->id,
            'role' => 'SALES_MANAGER',
            'status' => 'ACTIVE',
        ]);
        Notification::create([
            'user_id' => $manager->id,
            'type' => 'GENERAL',
            'title' => 'Activity review needed',
            'message' => 'Review an overdue activity.',
        ]);

        $response = $this->actingAs($manager)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Good morning, Sales Manager')
            ->assertSee(route('crm.reminders.index'), false)
            ->assertSee('Upcoming schedule')
            ->assertSee('id="dashboardRefresh"', false)
            ->assertSee('id="dashboardCalendarToggle"', false)
            ->assertSee('data-calendar-month-shift="-1"', false)
            ->assertSee('View calendar')
            ->assertDontSee('<button type="button" class="dashboard-btn">', false)
            ->assertViewHas('data', function (array $data) use ($employee) {
            $this->assertSame(1, $data['crmActivity']['overdueFollowUps']);
            $this->assertSame($employee->id, $data['crmActivity']['employeeActivity'][0]['user']->id);
            $this->assertSame(1, $data['crmActivity']['employeeActivity'][0]['overdue']);
            $salesCards = collect($data['managerDashboard']['cards'])->keyBy('label');
            $this->assertSame(1, $salesCards['Customer Count']['value']);
            $this->assertSame(1, $salesCards['Overdue Follow-ups']['value']);
            $this->assertSame(1, $data['notificationPanel']['unreadCount']);
            $this->assertSame('Activity review needed', $data['notificationPanel']['recent']->first()->title);
            return true;
        });
    }

    public function test_sales_dashboard_charts_are_weighted_and_scoped_to_department(): void
    {
        [$salesDepartment, $employee] = $this->salesEmployee();
        $salesCustomer = $this->customer($employee);
        $manager = User::create([
            'employee_id' => 'EMP-4301',
            'name' => 'Sales Manager',
            'email' => 'charts.manager@example.com',
            'password' => bcrypt('password'),
            'department_id' => $salesDepartment->id,
            'role' => 'SALES_MANAGER',
            'status' => 'ACTIVE',
        ]);
        $this->createSale($salesCustomer, $employee, 'SALE-CHART-001', 250);
        $this->createOpportunity($salesCustomer, $employee, 'OPP-CHART-001', 1000, 50, 'PROPOSAL');

        $otherDepartment = Department::create([
            'name' => 'Marketing',
            'code' => 'MKT',
            'status' => 'ACTIVE',
        ]);
        $otherEmployee = User::create([
            'employee_id' => 'EMP-4302',
            'name' => 'Marketing Employee',
            'email' => 'charts.marketing@example.com',
            'password' => bcrypt('password'),
            'department_id' => $otherDepartment->id,
            'role' => 'MARKETING_EMPLOYEE',
            'status' => 'ACTIVE',
        ]);
        $otherCustomer = $this->customer($otherEmployee, 'CUS-CHART-MKT');
        $this->createSale($otherCustomer, $otherEmployee, 'SALE-CHART-002', 9000);
        $this->createOpportunity($otherCustomer, $otherEmployee, 'OPP-CHART-002', 20000, 100, 'NEGOTIATION');

        $this->assertDatabaseHas('opportunities', [
            'opportunity_number' => 'OPP-CHART-001',
            'status' => 'OPEN',
            'assigned_to' => $employee->id,
        ]);
        $this->assertSame(2, Opportunity::query()->where('status', 'OPEN')->count());
        $this->assertSame(1, Opportunity::query()->whereHas('assignedUser', fn ($assigned) => $assigned->where('department_id', $salesDepartment->id))->count());
        $this->assertSame(2, Opportunity::query()
            ->whereDate('expected_close_date', '>=', now()->startOfMonth()->subMonths(5)->toDateString())
            ->whereDate('expected_close_date', '<=', now()->endOfMonth()->toDateString())
            ->count());

        $response = $this->actingAs($manager)->get(route('dashboard'));
        $response->assertOk()->assertSee('Performance overview')->assertSee('Booked Sales vs Forecast');
        $response->assertViewHas('data', function (array $data) {
            $charts = $data['salesCharts'];
            $this->assertCount(6, $charts['labels']);
            $this->assertSame(250.0, $charts['bookedSales'][5]);
            $this->assertSame(500.0, $charts['weightedForecast'][5]);
            $this->assertSame(500.0, $charts['weightedPipelineTotal']);
            $this->assertSame(500.0, $charts['weightedByStage'][2]);
            return true;
        });

        $employeeDashboard = $this->actingAs($employee)->get(route('dashboard'));
        $employeeDashboard->assertOk()
            ->assertDontSee('Won Sales')
            ->assertSee('Monthly Sales')
            ->assertSee('My Customers')
            ->assertSee('Open Opportunities');
    }

    private function salesEmployee(string $employeeId = 'EMP-4101', string $email = 'sales@example.com'): array
    {
        $department = Department::firstOrCreate(['code' => 'SALES'], [
            'name' => 'Sales',
            'description' => 'Sales team',
            'status' => 'ACTIVE',
        ]);

        $employee = User::create([
            'employee_id' => $employeeId,
            'name' => 'Sales Employee',
            'email' => $email,
            'password' => bcrypt('password'),
            'department_id' => $department->id,
            'role' => 'SALES_EMPLOYEE',
            'status' => 'ACTIVE',
        ]);

        return [$department, $employee];
    }

    private function customer(User $employee, string $code = 'CUS-4101'): Customer
    {
        return Customer::create([
            'customer_code' => $code,
            'name' => 'ABC Company',
            'company_name' => 'ABC Company Ltd',
            'assigned_to' => $employee->id,
            'status' => 'ACTIVE',
        ]);
    }

    private function followUp(User $employee, Customer $customer, array $overrides = []): FollowUp
    {
        return FollowUp::create(array_merge([
            'customer_id' => $customer->id,
            'title' => 'Call about proposal',
            'follow_up_date' => today()->addDay()->toDateString(),
            'follow_up_time' => '10:00',
            'type' => 'CALL',
            'priority' => 'NORMAL',
            'status' => 'PLANNED',
            'assigned_to' => $employee->id,
            'created_by' => $employee->id,
            'recurrence_rule' => 'NONE',
        ], $overrides));
    }

    private function createSale(Customer $customer, User $owner, string $number, float $amount): Sale
    {
        return Sale::create([
            'sale_number' => $number,
            'customer_id' => $customer->id,
            'sale_date' => today()->toDateString(),
            'total_amount' => $amount,
            'final_amount' => $amount,
            'payment_status' => 'PAID',
            'assigned_to' => $owner->id,
        ]);
    }

    private function createOpportunity(Customer $customer, User $owner, string $number, float $amount, int $probability, string $stage): Opportunity
    {
        return Opportunity::create([
            'opportunity_number' => $number,
            'customer_id' => $customer->id,
            'title' => $number,
            'estimated_value' => $amount,
            'probability' => $probability,
            'expected_close_date' => today()->endOfMonth()->toDateString(),
            'stage' => $stage,
            'status' => 'OPEN',
            'assigned_to' => $owner->id,
        ]);
    }
}