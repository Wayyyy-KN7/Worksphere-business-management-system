@extends('layouts.app')

@section('title', 'Edit Sale')
@section('breadcrumb', 'CRM / Sales / Edit')

@section('content')
<div class="page-header">
    <h2><i class="bi bi-pencil me-2"></i>Edit Sale</h2>
    <p>{{ $sale->sale_number }} - {{ $sale->customer?->name ?? 'Customer unavailable' }}</p>
</div>

<form action="{{ route('crm.sales.update', $sale) }}" method="POST">
    @csrf
    @method('PUT')
    <input type="hidden" name="customer_id" value="{{ $sale->customer_id }}">
    
    <div class="row">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <div class="row g-3">
                        <!-- Sale Number (Read-only) -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Sale Number</label>
                            <input type="text" class="form-control bg-light" 
                                   value="{{ $sale->sale_number }}" readonly>
                        </div>

                        <!-- Customer (Read-only) -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Customer</label>
                            <input type="text" class="form-control bg-light" 
                                   value="{{ $sale->customer?->name ?? 'Customer unavailable' }}" readonly>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Assigned To <span class="text-danger">*</span></label>
                            <select name="assigned_to" class="form-select @error('assigned_to') is-invalid @enderror" required>
                                @foreach($assignableUsers as $assignableUser)
                                    <option value="{{ $assignableUser->id }}" {{ (string) old('assigned_to', $sale->assigned_to) === (string) $assignableUser->id ? 'selected' : '' }}>
                                        {{ $assignableUser->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('assigned_to')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Sale Date -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Sale Date <span class="text-danger">*</span></label>
                            <input type="date" name="sale_date" 
                                   class="form-control @error('sale_date') is-invalid @enderror" 
                                   value="{{ old('sale_date', $sale->sale_date->format('Y-m-d')) }}" required>
                            @error('sale_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Payment Status -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Payment Status <span class="text-danger">*</span></label>
                            <select name="payment_status" class="form-select @error('payment_status') is-invalid @enderror" required>
                                <option value="PENDING" {{ old('payment_status', $sale->payment_status) === 'PENDING' ? 'selected' : '' }}>Pending</option>
                                <option value="PARTIAL" {{ old('payment_status', $sale->payment_status) === 'PARTIAL' ? 'selected' : '' }}>Partial</option>
                                <option value="PAID" {{ old('payment_status', $sale->payment_status) === 'PAID' ? 'selected' : '' }}>Paid</option>
                                <option value="CANCELLED" {{ old('payment_status', $sale->payment_status) === 'CANCELLED' ? 'selected' : '' }}>Cancelled</option>
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
                                <option value="Cash" {{ old('payment_method', $sale->payment_method) === 'Cash' ? 'selected' : '' }}>Cash</option>
                                <option value="Bank Transfer" {{ old('payment_method', $sale->payment_method) === 'Bank Transfer' ? 'selected' : '' }}>Bank Transfer</option>
                                <option value="Credit Card" {{ old('payment_method', $sale->payment_method) === 'Credit Card' ? 'selected' : '' }}>Credit Card</option>
                                <option value="Cheque" {{ old('payment_method', $sale->payment_method) === 'Cheque' ? 'selected' : '' }}>Cheque</option>
                                <option value="Other" {{ old('payment_method', $sale->payment_method) === 'Other' ? 'selected' : '' }}>Other</option>
                            </select>
                            @error('payment_method')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Notes -->
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Notes</label>
                            <textarea name="notes" rows="3" 
                                      class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $sale->notes) }}</textarea>
                            @error('notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm mt-3">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold mb-0">Sale Items</h6>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="addSaleItem">
                                    <i class="bi bi-plus-circle me-1"></i>Add Item
                                </button>
                            </div>
                            <div id="saleItems">
                                @foreach(old('items', $sale->items->toArray()) as $index => $item)
                                    <div class="row g-2 align-items-end mb-3 sale-item">
                                        <div class="col-md-3">
                                            <label class="form-label small">Product</label>
                                            <input name="items[{{ $index }}][product_name]" class="form-control" value="{{ $item['product_name'] }}" required>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small">Description</label>
                                            <input name="items[{{ $index }}][description]" class="form-control" value="{{ $item['description'] ?? '' }}">
                                        </div>
                                        <div class="col-md-1">
                                            <label class="form-label small">Qty</label>
                                            <input name="items[{{ $index }}][quantity]" type="number" step="0.01" min="0.01" class="form-control" value="{{ $item['quantity'] }}" required>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small">Unit Price</label>
                                            <input name="items[{{ $index }}][unit_price]" type="number" step="0.01" min="0" class="form-control" value="{{ $item['unit_price'] }}" required>
                                        </div>
                                        <div class="col-md-1">
                                            <label class="form-label small">Discount %</label>
                                            <input name="items[{{ $index }}][discount]" type="number" step="0.01" min="0" max="100" class="form-control" value="{{ $item['discount'] ?? 0 }}">
                                        </div>
                                        <div class="col-md-1">
                                            <label class="form-label small">Tax %</label>
                                            <input name="items[{{ $index }}][tax]" type="number" step="0.01" min="0" class="form-control" value="{{ $item['tax'] ?? 0 }}">
                                        </div>
                                        <div class="col-md-2">
                                            <button type="button" class="btn btn-outline-danger w-100 remove-sale-item">Remove</button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle me-2"></i>Update Sale
                        </button>
                        <a href="{{ route('crm.sales.show', $sale) }}" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle me-2"></i>Cancel
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary (Read-only) -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="fw-bold mb-3">Sale Summary</h6>
                    
                    <table class="table table-sm table-borderless">
                        <tr>
                            <td>Subtotal:</td>
                            <td class="text-end"><strong>${{ number_format($sale->total_amount, 2) }}</strong></td>
                        </tr>
                        <tr>
                            <td>Discount:</td>
                            <td class="text-end text-danger">-${{ number_format($sale->discount_amount, 2) }}</td>
                        </tr>
                        <tr>
                            <td>Tax:</td>
                            <td class="text-end text-success">+${{ number_format($sale->tax_amount, 2) }}</td>
                        </tr>
                        <tr class="border-top">
                            <td><strong>Total:</strong></td>
                            <td class="text-end"><strong class="fs-5 text-primary">${{ number_format($sale->final_amount, 2) }}</strong></td>
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
(() => {
    const container = document.getElementById('saleItems');
    let nextIndex = {{ count(old('items', $sale->items->toArray())) }};

    document.getElementById('addSaleItem').addEventListener('click', () => {
        const index = nextIndex++;
        container.insertAdjacentHTML('beforeend', `
            <div class="row g-2 align-items-end mb-3 sale-item">
                <div class="col-md-3"><label class="form-label small">Product</label><input name="items[${index}][product_name]" class="form-control" required></div>
                <div class="col-md-2"><label class="form-label small">Description</label><input name="items[${index}][description]" class="form-control"></div>
                <div class="col-md-1"><label class="form-label small">Qty</label><input name="items[${index}][quantity]" type="number" step="0.01" min="0.01" value="1" class="form-control" required></div>
                <div class="col-md-2"><label class="form-label small">Unit Price</label><input name="items[${index}][unit_price]" type="number" step="0.01" min="0" value="0" class="form-control" required></div>
                <div class="col-md-1"><label class="form-label small">Discount %</label><input name="items[${index}][discount]" type="number" step="0.01" min="0" max="100" value="0" class="form-control"></div>
                <div class="col-md-1"><label class="form-label small">Tax %</label><input name="items[${index}][tax]" type="number" step="0.01" min="0" value="0" class="form-control"></div>
                <div class="col-md-2"><button type="button" class="btn btn-outline-danger w-100 remove-sale-item">Remove</button></div>
            </div>
        `);
    });

    container.addEventListener('click', (event) => {
        if (event.target.closest('.remove-sale-item')) {
            if (container.querySelectorAll('.sale-item').length === 1) {
                alert('At least one item is required');
                return;
            }
            event.target.closest('.sale-item').remove();
        }
    });
})();
</script>
@endpush