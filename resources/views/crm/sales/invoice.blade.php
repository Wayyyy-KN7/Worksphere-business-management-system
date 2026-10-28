<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $sale->sale_number }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --navy: #24323B;
            --primary: #0F766E;
            --surface: #FFFFFF;
            --page: #F4F6F8;
        }

        body {
            background-color: var(--page);
            color: var(--navy);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .btn-primary,
        .btn-success {
            --bs-btn-bg: var(--primary);
            --bs-btn-border-color: var(--primary);
            --bs-btn-hover-bg: var(--navy);
            --bs-btn-hover-border-color: var(--navy);
        }

        .text-primary { color: var(--primary) !important; }

        .invoice-container {
            max-width: 800px;
            margin: 40px auto;
            background: var(--surface);
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0,0,0,0.05);
        }
        .invoice-header {
            border-bottom: 2px solid rgba(36, 50, 59, 0.16);
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .table th {
            background-color: var(--page) !important;
        }
        @media print {
            body {
                background-color: var(--surface);
            }
            .invoice-container {
                box-shadow: none;
                margin: 0;
                padding: 20px;
                max-width: 100%;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="invoice-container">
        
        <!-- Action Buttons (Hidden on Print) -->
        <div class="d-flex justify-content-between align-items-center mb-4 no-print">
            <a href="{{ route('crm.sales.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Back to Sales
            </a>
            
            <div class="d-flex gap-2">
                <!-- Save to Documents Button (No print popup) -->
                <button type="button" onclick="storeDocumentOnly()" id="saveDocBtn" class="btn btn-success btn-sm">
                    <i class="bi bi-folder-plus me-1"></i> Save to Documents
                </button>

                <!-- Pure Print / Download Button -->
                <button type="button" onclick="window.print()" class="btn btn-primary btn-sm">
                    <i class="bi bi-printer me-1"></i> Print / PDF
                </button>
            </div>
        </div>

        <!-- Notification Banner Area -->
        <div id="alertContainer" class="no-print"></div>

        <!-- Hidden form for automatic document storage -->
        <form id="autoStoreForm" action="{{ route('crm.sales.store-invoice', $sale->id) }}" method="POST" class="d-none">
            @csrf
        </form>

        <!-- Header -->
        <div class="invoice-header d-flex justify-content-between align-items-start">
            <div>
                <h2 class="fw-bold text-primary mb-1">INVOICE</h2>
                <h5 class="text-muted">{{ $sale->sale_number }}</h5>
            </div>
            <div class="text-end">
                <h4 class="fw-bold mb-0">{{ config('app.name', 'CRM System') }}</h4>
                <p class="text-muted small mb-0">123 Business Rd, Suite 100<br>City, State, ZIP</p>
            </div>
        </div>

        <!-- Client & Sale Meta -->
        <div class="row mb-4">
            <div class="col-sm-6">
                <h6 class="fw-bold text-muted text-uppercase small">Billed To:</h6>
                <h5 class="mb-1">{{ $sale->customer?->name ?? 'Customer unavailable' }}</h5>
                <p class="text-muted mb-0 small">
                    {{ $sale->customer?->email ?? '' }}<br>
                    {{ $sale->customer?->phone ?? '' }}<br>
                    {{ $sale->customer?->address ?? '' }}
                </p>
            </div>
            <div class="col-sm-6 text-sm-end">
                <h6 class="fw-bold text-muted text-uppercase small">Invoice Details:</h6>
                <p class="mb-1"><strong>Sale Date:</strong> {{ optional($sale->sale_date)->format('M d, Y') ?? $sale->sale_date }}</p>
                <p class="mb-1"><strong>Payment Status:</strong> 
                    <span class="badge {{ $sale->payment_status === 'PAID' ? 'bg-success' : 'bg-warning text-dark' }}">
                        {{ $sale->payment_status }}
                    </span>
                </p>
                <p class="mb-0"><strong>Payment Method:</strong> {{ $sale->payment_method ?? 'N/A' }}</p>
            </div>
        </div>

        <!-- Items Table -->
        <div class="table-responsive mb-4">
            <table class="table table-bordered align-middle">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Item Description</th>
                        <th class="text-center">Qty</th>
                        <th class="text-end">Unit Price</th>
                        <th class="text-end">Discount</th>
                        <th class="text-end">Tax</th>
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($sale->items as $index =>$item)
                        @php
                            $lineBase = $item->quantity * $item->unit_price;
                            $lineDiscAmount = $lineBase * (($item->discount ?? 0) / 100);
                            $taxable = $lineBase -$lineDiscAmount;
                            $lineTaxAmount = $taxable * (($item->tax ?? 0) / 100);
                            $lineTotal = $taxable +$lineTaxAmount;
                        @endphp
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>
                                <strong>{{ $item->title ?? $item->product_name }}</strong>
                                @if($item->description)
                                    <br><small class="text-muted">{{ $item->description }}</small>
                                @endif
                            </td>
                            <td class="text-center">{{ $item->quantity }}</td>
                            <td class="text-end">${{ number_format($item->unit_price, 2) }}</td>
                            <td class="text-end text-danger">-${{ number_format($lineDiscAmount, 2) }}</td>
                            <td class="text-end text-success">+${{ number_format($lineTaxAmount, 2) }}</td>
                            <td class="text-end"><strong>${{ number_format($lineTotal, 2) }}</strong></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Summary Totals -->
        <div class="row justify-content-end">
            <div class="col-md-5">
                <table class="table table-sm table-borderless">
                    <tr>
                        <td>Subtotal:</td>
                        <td class="text-end">${{ number_format($sale->total_amount, 2) }}</td>
                    </tr>
                    <tr>
                        <td>Total Discount:</td>
                        <td class="text-end text-danger">-${{ number_format($sale->discount_amount, 2) }}</td>
                    </tr>
                    <tr>
                        <td>Total Tax:</td>
                        <td class="text-end text-success">+${{ number_format($sale->tax_amount, 2) }}</td>
                    </tr>
                    <tr class="border-top border-2">
                        <td><strong>Grand Total:</strong></td>
                        <td class="text-end"><strong class="fs-5 text-primary">${{ number_format($sale->final_amount, 2) }}</strong></td>
                    </tr>
                </table>
            </div>
        </div>

        @if($sale->notes)
        <div class="mt-4 pt-3 border-top">
            <h6 class="fw-bold text-muted small uppercase">Notes / Terms:</h6>
            <p class="text-muted small mb-0">{{ $sale->notes }}</p>
        </div>
        @endif

        <!-- Footer Note -->
        <div class="text-center text-muted mt-5 pt-3 border-top small">
            <p class="mb-0">Thank you for your business!</p>
        </div>

    </div>
</div>

<!-- Script to handle saving to documents via AJAX without print popup -->
<script>
    function storeDocumentOnly() {
        let btn = document.getElementById('saveDocBtn');
        let alertContainer = document.getElementById('alertContainer');
        let form = document.getElementById('autoStoreForm');
        let formData = new FormData(form);

        // Disable button and show loading state
        btn.disabled = true;
        btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Saving...`;
        alertContainer.innerHTML = '';

        fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (response.ok) {
                alertContainer.innerHTML = `
                    <div class="alert alert-success alert-dismissible fade show py-2 small" role="alert">
                        <i class="bi bi-check-circle-fill me-1"></i> Invoice successfully generated and stored in your documents!
                        <button type="button" class="btn-close py-2" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>`;
            } else {
                throw new Error('Failed to store document');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alertContainer.innerHTML = `
                <div class="alert alert-danger alert-dismissible fade show py-2 small" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i> Error saving invoice to documents. Please try again.
                    <button type="button" class="btn-close py-2" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>`;
        })
        .finally(() => {
            // Restore button state
            btn.disabled = false;
            btn.innerHTML = `<i class="bi bi-folder-plus me-1"></i> Save to Documents`;
        });
    }
</script>

</body>
</html>