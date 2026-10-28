<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Customer;
use App\Models\Opportunity;
use App\Models\User;
use App\Models\Document; // <-- Added Document model import
use App\Models\AuditLog;
use App\Services\CrmNotificationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class SaleController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $user = auth()->user();
        $this->authorize('viewAny', Sale::class);

        $query = Sale::with(['customer', 'opportunity', 'assignedUser']);

        // Access Control
        if (!$user->isAdmin()) {
            if ($user->isManager() && $user->isSales()) {
                $query->where(function ($departmentSales) use ($user) {
                    $departmentSales->whereHas('assignedUser', function ($assigned) use ($user) {
                        $assigned->where('department_id', $user->department_id);
                    })->orWhereHas('customer.assignedUser', function ($customerOwner) use ($user) {
                        $customerOwner->where('department_id', $user->department_id);
                    });
                });
            } else {
                $query->where('assigned_to', $user->id);
            }
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('sale_number', 'like', "%{$search}%")
                  ->orWhereHas('customer', function($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // Filter by payment status
        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        // Filter by date range
        if ($request->filled('start_date')) {
            $query->whereDate('sale_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('sale_date', '<=', $request->end_date);
        }

        $sales = $query->latest('sale_date')->paginate(15);

        // Statistics
        $statsQuery = clone $query;
        $stats = [
            'total_sales' => (clone $statsQuery)->count(),
            'total_revenue' => (clone $statsQuery)->sum('final_amount'),
            'pending_payment' => (clone $statsQuery)->where('payment_status', 'PENDING')->sum('final_amount'),
            'paid' => (clone $statsQuery)->where('payment_status', 'PAID')->count(),
        ];

        return view('crm.sales.index', compact('sales', 'stats'));
    }

    public function create(Request $request)
    {
        $user = auth()->user();
        $this->authorize('create', Sale::class);
        $customers = $this->manageableCustomersQuery($user)->where('status', 'ACTIVE')
            ->orderBy('name')
            ->get();

        // Filter opportunities: Only unbilled ones, and filtered by customer if a customer is selected
        $query = $this->visibleOpportunitiesQuery($user)->whereDoesntHave('sales');

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        $opportunities = $query->with('customer')->get();

        // If opportunity_id is provided, pre-select it
        $selectedOpportunity = null;
        if ($request->filled('opportunity_id')) {
            $selectedOpportunity = $opportunities->firstWhere('id', $request->opportunity_id);
            abort_unless($selectedOpportunity, 404);
        }

        // Get assignable users
        $assignableUsers = $this->assignableSalesUsers(auth()->user());

        return view('crm.sales.create', compact('customers', 'opportunities', 'selectedOpportunity', 'assignableUsers'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Sale::class);
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'opportunity_id' => 'nullable|exists:opportunities,id',
            'assigned_to' => [auth()->user()->isAdmin() ? 'required' : 'nullable', $this->assignedUserRule(auth()->user())],
            'sale_date' => 'required|date',
            'payment_status' => 'required|in:PENDING,PARTIAL,PAID,CANCELLED',
            'payment_method' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_name' => 'required|string|max:255',
            'items.*.description' => 'nullable|string',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0|max:100',
            'items.*.tax' => 'nullable|numeric|min:0',
        ]);
        $validated['assigned_to'] = $validated['assigned_to'] ?? auth()->id();
        $customer = Customer::findOrFail($validated['customer_id']);
        $this->authorize('manageActivities', $customer);
        if (!empty($validated['opportunity_id'])) {
            $opportunity = Opportunity::findOrFail($validated['opportunity_id']);
            $this->authorize('view', $opportunity);
            abort_unless((int) $opportunity->customer_id === (int) $customer->id, 422, 'The opportunity must belong to the selected customer.');
        }
        $totals = $this->calculateLineItems($validated['items']);

        DB::beginTransaction();

        try {
            // Generate unique sale number
            $saleNumber = 'SALE-' . date('Y') . '-' . str_pad(Sale::count() + 1, 5, '0', STR_PAD_LEFT);

            $sale = Sale::create([
                'sale_number' => $saleNumber,
                'customer_id' => $validated['customer_id'],
                'opportunity_id' => $validated['opportunity_id'] ?? null,
                'assigned_to' => $validated['assigned_to'],
                'sale_date' => $validated['sale_date'],
                'payment_status' => $validated['payment_status'],
                'payment_method' => $validated['payment_method'] ?? null,
                'total_amount' => $totals['subtotal'],
                'discount_amount' => $totals['discount'],
                'tax_amount' => $totals['tax'],
                'final_amount' => $totals['total'],
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($totals['items'] as $itemData) {
                $itemData['sale_id'] = $sale->id;
                SaleItem::create($itemData);
            }

            DB::commit();

            $notificationService = app(CrmNotificationService::class);
            $recipients = $notificationService->departmentManagerIds(
                $sale->assignedUser?->department_id,
                auth()->id(),
            );
            if ((int) $sale->assigned_to !== (int) auth()->id()) {
                $recipients->push($sale->assigned_to);
            }
            $notificationService->notifyUsers(
                $recipients->unique(),
                'SALE_CREATED',
                'Sale Created',
                "Sale {$sale->sale_number} was created for {$sale->customer->name}.",
                $sale,
                'sale.created.' . $sale->id,
            );

            return redirect()->route('crm.sales.index')->with('success', 'Sale created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Failed to create sale: ' . $e->getMessage());
        }
    }

    public function show(Sale $sale)
    {
        $this->authorize('view', $sale);
        $sale->load(['customer', 'opportunity', 'assignedUser', 'items']);
        return view('crm.sales.show', compact('sale'));
    }

    public function invoice(Sale $sale)
    {
        $this->authorize('view', $sale);
        $sale->load(['customer', 'opportunity', 'assignedUser', 'items']);
        return view('crm.sales.invoice', compact('sale'));
    }

    public function edit(Sale $sale)
    {
        $this->authorize('update', $sale);
        $customers = $this->manageableCustomersQuery(auth()->user())->where('status', 'ACTIVE')->orderBy('name')->get();
        $assignableUsers = $this->assignableSalesUsers(auth()->user());
            
        $sale->load('items');

        return view('crm.sales.edit', compact('sale', 'customers', 'assignableUsers'));
    }

    public function update(Request $request, Sale $sale)
    {
        $this->authorize('update', $sale);
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'assigned_to' => ['required', $this->assignedUserRule(auth()->user())],
            'sale_date' => 'required|date',
            'payment_status' => 'required|in:PENDING,PARTIAL,PAID,CANCELLED',
            'payment_method' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_name' => 'required|string|max:255',
            'items.*.description' => 'nullable|string',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0|max:100',
            'items.*.tax' => 'nullable|numeric|min:0',
        ]);
        $customer = Customer::findOrFail($validated['customer_id']);
        $this->authorize('manageActivities', $customer);
        if ($sale->opportunity_id) {
            abort_unless((int) $sale->opportunity?->customer_id === (int) $customer->id, 422, 'The opportunity must belong to the selected customer.');
        }
        $totals = $this->calculateLineItems($validated['items']);

        $oldPaymentStatus = $sale->payment_status;
        $oldAssignedTo = (int) $sale->assigned_to;
        $oldValues = $sale->only(['customer_id', 'opportunity_id', 'assigned_to', 'sale_date', 'payment_status', 'total_amount', 'discount_amount', 'tax_amount', 'final_amount', 'payment_method']);
        DB::beginTransaction();

        try {
            $sale->update([
                'customer_id' => $validated['customer_id'],
                'assigned_to' => $validated['assigned_to'],
                'sale_date' => $validated['sale_date'],
                'payment_status' => $validated['payment_status'],
                'payment_method' => $validated['payment_method'] ?? null,
                'total_amount' => $totals['subtotal'],
                'discount_amount' => $totals['discount'],
                'tax_amount' => $totals['tax'],
                'final_amount' => $totals['total'],
                'notes' => $validated['notes'] ?? null,
            ]);

            $sale->items()->delete();
            
            foreach ($totals['items'] as $item) {
                $item['sale_id'] = $sale->id;
                SaleItem::create($item);
            }

            AuditLog::log(
                'UPDATED',
                'sales',
                $sale->id,
                "Updated sale {$sale->sale_number}.",
                $oldValues,
                $sale->only(['customer_id', 'opportunity_id', 'assigned_to', 'sale_date', 'payment_status', 'total_amount', 'discount_amount', 'tax_amount', 'final_amount', 'payment_method']),
            );

            DB::commit();

            if ($oldAssignedTo !== (int) $sale->assigned_to
                && (int) $sale->assigned_to !== (int) auth()->id()) {
                $notificationService = app(CrmNotificationService::class);
                $recipients = $notificationService->departmentManagerIds(
                    $sale->assignedUser?->department_id,
                    auth()->id(),
                );
                $recipients->push($sale->assigned_to);
                $notificationService->notifyUsers(
                    $recipients->unique(),
                    'SALE_ASSIGNED',
                    'Sale Assigned to You',
                    "Sale {$sale->sale_number} for {$sale->customer->name} has been assigned to you.",
                    $sale,
                    'sale.assigned.' . $sale->id . '.' . $sale->assigned_to . '.' . $sale->updated_at->timestamp,
                );
            }

            if ($oldPaymentStatus !== $sale->payment_status) {
                $notificationService = app(CrmNotificationService::class);
                $responsibleIds = $notificationService->departmentManagerIds($sale->assignedUser?->department_id, auth()->id())
                    ->merge($sale->assigned_to !== auth()->id() ? [$sale->assigned_to] : [])
                    ->unique();
                $notificationService->notifyUsers(
                    $responsibleIds,
                    'PAYMENT_STATUS_CHANGED',
                    'Sale Payment Status Changed',
                    "Payment status for {$sale->sale_number} changed from {$oldPaymentStatus} to {$sale->payment_status}.",
                    $sale,
                    'sale.payment_status.' . $sale->id . '.' . $sale->updated_at->timestamp,
                );
            }

            return redirect()->route('crm.sales.index')->with('success', 'Sale updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Failed to update sale: ' . $e->getMessage());
        }
    }

    public function destroy(Sale $sale)
    {
        $this->authorize('delete', $sale);
        try {
            DB::beginTransaction();
            $sale->items()->delete();
            $sale->delete();
            DB::commit();

            return redirect()->route('crm.sales.index')->with('success', 'Sale deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to delete sale: ' . $e->getMessage());
        }
    }
public function generateAndStoreInvoice(Sale $sale)
    {
        $this->authorize('view', $sale);
        $sale->load(['customer', 'opportunity', 'assignedUser', 'items']);

        // 1. Load the invoice view with the sale data into PDF
        $pdf = Pdf::loadView('crm.sales.invoice', compact('sale'));

        // 2. Build unique storage names and paths
        $fileName = 'invoice_' . $sale->sale_number . '_' . time() . '.pdf';
        $filePath = 'documents/invoices/' . $fileName;

        // 3. Store the PDF file onto the public disk
        Storage::disk('public')->put($filePath, $pdf->output());

        DB::beginTransaction();

        try {
            // Generate a unique document number matching your document numbering scheme
            $documentNumber = 'DOC-' . date('Y') . '-' . str_pad(Document::count() + 1, 5, '0', STR_PAD_LEFT);
            $currentUser = auth()->user();
            $departmentId = $currentUser?->department_id;

            // 4. Create the central Document record with the correct default access for sales invoice documents
            $document = Document::create([
                'document_number' => $documentNumber,
                'title' => 'Sale Invoice #' . $sale->sale_number,
                'description' => 'Auto-generated invoice for customer: ' . optional($sale->customer)->name,
                'document_type' => 'INVOICE',
                'department_id' => $departmentId,
                'uploaded_by' => $currentUser?->id,
                'access_level' => 'DEPARTMENT',
                'status' => 'PUBLISHED',
                'documentable_type' => Sale::class,
                'documentable_id' => $sale->id,
            ]);

            // 5. Attach the file version
            $document->versions()->create([
                'version_number' => '1.0',
                'file_path' => $filePath,
                'file_name' => $fileName,
                'file_size' => strlen($pdf->output()),
                'mime_type' => 'application/pdf',
                'change_description' => 'Auto-generated invoice PDF copy',
                'uploaded_by' => auth()->id(),
            ]);

            // 6. Log document activity safely
            if (method_exists($document, 'logActivity')) {
                try {
                    $document->logActivity('CREATED', 'Invoice generated and stored as document');
                } catch (\Exception $ex) {
                    // Fail gracefully
                }
            }

            DB::commit();

            $notificationService = app(CrmNotificationService::class);
            $recipients = $notificationService->departmentManagerIds($sale->assignedUser?->department_id, auth()->id());
            if ($sale->assigned_to !== auth()->id()) {
                $recipients->push($sale->assigned_to);
            }
            $notificationService->notifyUsers(
                $recipients->unique(),
                'INVOICE_GENERATED',
                'Invoice Generated',
                "An invoice for {$sale->sale_number} was generated and saved as a department document.",
                $document,
                'invoice.generated.' . $document->id,
            );

            return back()->with('success', 'Invoice generated and successfully stored into documents!');
        } catch (\Exception $e) {
            DB::rollBack();
            Storage::disk('public')->delete($filePath);

            return back()->with('error', 'Failed to store invoice as document: ' . $e->getMessage());
        }
    }

    private function assignableSalesUsers(User $user)
    {
        $query = User::query()
            ->where('status', 'ACTIVE')
            ->whereIn('role', ['SALES_MANAGER', 'SALES_EMPLOYEE'])
            ->orderBy('name');

        if ($user->isAdmin()) {
            return $query->get();
        }

        if ($user->isDepartmentManager() && $user->isSales()) {
            return $query->where('department_id', $user->department_id)->get();
        }

        return $query->whereKey($user->id)->get();
    }

    private function assignedUserRule(User $user): Exists
    {
        $rule = Rule::exists('users', 'id')
            ->where('status', 'ACTIVE')
            ->whereIn('role', ['SALES_MANAGER', 'SALES_EMPLOYEE']);

        if (!$user->isAdmin()) {
            if ($user->isDepartmentManager() && $user->isSales()) {
                $rule->where('department_id', $user->department_id);
            } else {
                $rule->where('id', $user->id);
            }
        }

        return $rule;
    }

    private function manageableCustomersQuery(User $user)
    {
        return Customer::query()->manageableBy($user);
    }

    private function visibleOpportunitiesQuery(User $user)
    {
        $query = Opportunity::query();
        if ($user->isAdmin()) {
            return $query;
        }
        if ($user->isSales() || $user->isMarketing()) {
            return $query->whereHas('assignedUser', function ($assigned) use ($user) {
                $assigned->where('department_id', $user->department_id);
            });
        }
        return $query->where('assigned_to', $user->id);
    }

    private function calculateLineItems(array $items): array
    {
        $subtotal = 0;
        $discount = 0;
        $tax = 0;
        $total = 0;
        $processed = [];

        foreach ($items as $item) {
            $base = round((float) $item['quantity'] * (float) $item['unit_price'], 2);
            $lineDiscount = round($base * ((float) ($item['discount'] ?? 0) / 100), 2);
            $lineTax = round(($base - $lineDiscount) * ((float) ($item['tax'] ?? 0) / 100), 2);
            $lineTotal = round($base - $lineDiscount + $lineTax, 2);

            $subtotal += $base;
            $discount += $lineDiscount;
            $tax += $lineTax;
            $total += $lineTotal;
            $processed[] = [
                'product_name' => $item['product_name'],
                'description' => $item['description'] ?? null,
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'discount' => $item['discount'] ?? 0,
                'tax' => $item['tax'] ?? 0,
                'total' => $lineTotal,
            ];
        }

        return [
            'subtotal' => round($subtotal, 2),
            'discount' => round($discount, 2),
            'tax' => round($tax, 2),
            'total' => round($total, 2),
            'items' => $processed,
        ];
    }
}