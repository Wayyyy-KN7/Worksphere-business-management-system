@extends('layouts.app')

@section('title', 'Create Sale')
@section('breadcrumb', 'CRM / Sales / Create')

@section('content')
<div class="page-header">
    <h2><i class="bi bi-cart-plus me-2"></i>Create Sale</h2>
    <p>Record a new sale transaction</p>
</div>

<form action="{{ route('crm.sales.store') }}" method="POST" id="saleForm">
    @csrf
    
    <div class="row">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3">Sale Information</h6>

                    <div class="row g-3">
                        <!-- Sale Number (Auto-generated) -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Sale Number</label>
                            <input type="text" class="form-control bg-light" value="AUTO-GENERATED" readonly>
                            <small class="text-muted">
                                <i class="bi bi-info-circle me-1"></i>Will be generated on save
                            </small>
                        </div>

                        <!-- Sale Date -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Sale Date <span class="text-danger">*</span></label>
                            <input type="date" name="sale_date" 
                                   class="form-control @error('sale_date') is-invalid @enderror" 
                                   value="{{ old('sale_date', now()->format('Y-m-d')) }}" required>
                            @error('sale_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Customer -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Customer <span class="text-danger">*</span></label>
                            <select name="customer_id" id="customerSelect" 
                                    class="form-select @error('customer_id') is-invalid @enderror" required>
                                <option value="">Select Customer</option>
                                @foreach($customers as $customer)
                                    <option value="{{ $customer->id }}" 
                                            {{ (request('customer_id') == $customer->id || old('customer_id', $selectedOpportunity?->customer_id) == $customer->id) ? 'selected' : '' }}>
                                        {{ $customer->customer_code ?? $customer->id }} - {{$customer->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('customer_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Opportunity (Optional) -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Related Opportunity (Optional)</label>
                            <select name="opportunity_id" class="form-select @error('opportunity_id') is-invalid @enderror">
                                <option value="">None</option>
                                @foreach($opportunities as $opp)
                                    <option value="{{ $opp->id }}" 
                                            {{ old('opportunity_id', $selectedOpportunity?->id) == $opp->id ? 'selected' : '' }}>
                                        {{ $opp->opportunity_number ?? $opp->id }} - {{ $opp->title }} ({{$opp->customer->name ?? '' }})
                                    </option>
                                @endforeach
                            </select>
                            @error('opportunity_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Payment Status -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Payment Status <span class="text-danger">*</span></label>
                            <select name="payment_status" class="form-select @error('payment_status') is-invalid @enderror" required>
                                <option value="PENDING" {{ old('payment_status', 'PENDING') === 'PENDING' ? 'selected' : '' }}>Pending</option>
                                <option value="PARTIAL" {{ old('payment_status') === 'PARTIAL' ? 'selected' : '' }}>Partial</option>
                                <option value="PAID" {{ old('payment_status') === 'PAID' ? 'selected' : '' }}>Paid</option>
                               
                            </select>
                            @error('payment_status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Payment Method -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Payment Method</label>
                            <select name="payment_method" class="form-select @error('payment_method') is-invalid @enderror">
                                <option value="">Select Method</option>
                                <option value="Cash" {{ old('payment_method') === 'Cash' ? 'selected' : '' }}>Cash</option>
                                <option value="Bank Transfer" {{ old('payment_method') === 'Bank Transfer' ? 'selected' : '' }}>Bank Transfer</option>
                                <option value="Credit Card" {{ old('payment_method') === 'Credit Card' ? 'selected' : '' }}>Credit Card</option>
                                <option value="Cheque" {{ old('payment_method') === 'Cheque' ? 'selected' : '' }}>Cheque</option>
                                <option value="Other" {{ old('payment_method') === 'Other' ? 'selected' : '' }}>Other</option>
                            </select>
                            @error('payment_method')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Assigned To -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Assigned To</label>
                            <select name="assigned_to" class="form-select @error('assigned_to') is-invalid @enderror" {{ auth()->user()->isAdmin() ? 'required' : '' }}>
                                @unless(auth()->user()->isAdmin())
                                    <option value="">Assign to me ({{ auth()->user()->name }})</option>
                                @endunless
                                @foreach($assignableUsers as $user)
                                    <option value="{{ $user->id }}" {{ old('assigned_to') == $user->id ? 'selected' : '' }}>
                                        {{ $user->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('assigned_to')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Notes -->
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Notes</label>
                            <textarea name="notes" rows="2" 
                                      class="form-control @error('notes') is-invalid @enderror" 
                                      placeholder="Additional notes...">{{ old('notes') }}</textarea>
                            @error('notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sale Items -->
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold mb-0">Sale Items</h6>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addItem()">
                            <i class="bi bi-plus-circle me-1"></i>Add Item
                        </button>
                    </div>

                    <div id="itemsContainer">
                        <!-- Item rows will be added here -->
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle me-2"></i>Create Sale
                </button>
                <a href="{{ route('crm.sales.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-x-circle me-2"></i>Cancel
                </a>
            </div>
        </div>

        <!-- Summary Sidebar -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm" style="position: sticky; top: 20px;">
                <div class="card-body">
                    <h6 class="fw-bold mb-3">Sale Summary</h6>
                    
                    <table class="table table-sm table-borderless">
                        <tr>
                            <td>Subtotal:</td>
                            <td class="text-end"><strong id="subtotal">$0.00</strong></td>
                        </tr>
                        <tr>
                            <td>Discount:</td>
                            <td class="text-end text-danger">-<span id="totalDiscount">$0.00</span></td>
                        </tr>
                        <tr>
                            <td>Tax:</td>
                            <td class="text-end text-success">+<span id="totalTax">$0.00</span></td>
                        </tr>
                        <tr class="border-top">
                            <td><strong>Total:</strong></td>
                            <td class="text-end"><strong class="fs-5 text-primary" id="grandTotal">$0.00</strong></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
let itemIndex = 0;

document.addEventListener('DOMContentLoaded', function() {
    addItem();
    
    // Dynamic Customer Selection Listener to filter Opportunities
    document.getElementById('customerSelect').addEventListener('change', function() {
        const customerId = this.value;
        if (customerId) {
            window.location.href = "{{ route('crm.sales.create') }}?customer_id=" + customerId;
        } else {
            window.location.href = "{{ route('crm.sales.create') }}";
        }
    });
});

function addItem() {
    itemIndex++;
    const container = document.getElementById('itemsContainer');
    
    const itemHtml = `
        <div class="card mb-3 item-row" id="item-${itemIndex}">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h6 class="mb-0">Item #${itemIndex}</h6>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeItem(${itemIndex})">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
                
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label small">Product Name <span class="text-danger">*</span></label>
                        <input type="text" name="items[${itemIndex}][product_name]" 
                               class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Description</label>
                        <input type="text" name="items[${itemIndex}][description]" 
                               class="form-control form-control-sm">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Quantity <span class="text-danger">*</span></label>
                        <input type="number" name="items[${itemIndex}][quantity]" 
                               class="form-control form-control-sm item-quantity" 
                               step="0.01" min="0.01" value="1" required 
                               oninput="calculateTotals()">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Unit Price <span class="text-danger">*</span></label>
                        <input type="number" name="items[${itemIndex}][unit_price]" 
                               class="form-control form-control-sm item-price" 
                               step="0.01" min="0" value="0" required 
                               oninput="calculateTotals()">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Discount (%)</label>
                        <input type="number" name="items[${itemIndex}][discount]" 
                               class="form-control form-control-sm item-discount" 
                               step="0.01" min="0" max="100" value="0" 
                               oninput="calculateTotals()">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Tax (%)</label>
                        <input type="number" name="items[${itemIndex}][tax]" 
                               class="form-control form-control-sm item-tax" 
                               step="0.01" min="0" value="0" 
                               oninput="calculateTotals()">
                    </div>
                </div>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', itemHtml);
    calculateTotals();
}

function removeItem(index) {
    const itemCount = document.querySelectorAll('.item-row').length;
    if (itemCount <= 1) {
        alert('At least one item is required');
        return;
    }
    
    document.getElementById('item-' + index).remove();
    calculateTotals();
}

function calculateTotals() {
    let subtotal = 0;
    let totalDiscount = 0;
    let totalTax = 0;
    
    document.querySelectorAll('.item-row').forEach(function(item) {
        const quantity = parseFloat(item.querySelector('.item-quantity').value) || 0;
        const price = parseFloat(item.querySelector('.item-price').value) || 0;
        const discountPercent = parseFloat(item.querySelector('.item-discount').value) || 0;
        const taxPercent = parseFloat(item.querySelector('.item-tax').value) || 0;
        
        const lineTotal = quantity * price;
        const lineDiscount = lineTotal * (discountPercent / 100);
        const taxableAmount = lineTotal - lineDiscount;
        const lineTax = taxableAmount * (taxPercent / 100);
        
        subtotal += lineTotal;
        totalDiscount += lineDiscount;
        totalTax += lineTax;
    });
    
    const grandTotal = subtotal - totalDiscount + totalTax;
    
    // Update visual fields
    document.getElementById('subtotal').textContent = '$' + subtotal.toFixed(2);
    document.getElementById('totalDiscount').textContent = '$' + totalDiscount.toFixed(2);
    document.getElementById('totalTax').textContent = '$' + totalTax.toFixed(2);
    document.getElementById('grandTotal').textContent = '$' + grandTotal.toFixed(2);

}
</script>
@endpush