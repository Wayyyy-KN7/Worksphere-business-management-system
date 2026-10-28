@extends('layouts.app')

@section('title', 'Audit Logs')
@section('breadcrumb', 'Admin / Audit Logs')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div>
        <h2><i class="bi bi-journal-text me-2"></i>Audit Logs</h2>
        <p>Review administrative and business activity recorded across the CRM.</p>
    </div>
    <span class="badge rounded-pill text-bg-light border px-3 py-2">
        {{ number_format($auditLogs->total()) }} records
    </span>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.audit-logs.index') }}" class="row g-3 align-items-end">
            <div class="col-lg-5 col-md-6">
                <label for="audit-search" class="form-label small fw-semibold">Search records</label>
                <input id="audit-search" type="search" name="search" class="form-control"
                       placeholder="Actor, module, record ID, or details" value="{{ request('search') }}">
            </div>
            <div class="col-lg-2 col-md-6">
                <label for="audit-action" class="form-label small fw-semibold">Action</label>
                <select id="audit-action" name="action" class="form-select">
                    <option value="">All actions</option>
                    @foreach(['CREATED', 'UPDATED', 'DELETED', 'VIEWED', 'APPROVED', 'REJECTED', 'LOGGED_IN', 'LOGGED_OUT', 'OTHER'] as $action)
                        <option value="{{ $action }}" @selected(request('action') === $action)>{{ str_replace('_', ' ', $action) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2 col-md-6">
                <label for="audit-from" class="form-label small fw-semibold">From</label>
                <input id="audit-from" type="date" name="from" class="form-control" value="{{ request('from') }}">
            </div>
            <div class="col-lg-2 col-md-6">
                <label for="audit-to" class="form-label small fw-semibold">To</label>
                <input id="audit-to" type="date" name="to" class="form-control" value="{{ request('to') }}">
            </div>
            <div class="col-lg-1 col-md-6 d-grid">
                <button type="submit" class="btn btn-outline-primary" aria-label="Filter audit logs">
                    <i class="bi bi-search"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Actor</th>
                        <th>Action</th>
                        <th>Module / Record</th>
                        <th>Details</th>
                        <th>Timestamp</th>
                        <th class="text-end pe-4">More</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($auditLogs as $auditLog)
                        <tr>
                            <td class="ps-4">
                                <div class="fw-semibold">{{ $auditLog->user?->name ?? 'System / former user' }}</div>
                                @if($auditLog->user?->email)
                                    <small class="text-muted">{{ $auditLog->user->email }}</small>
                                @endif
                            </td>
                            <td>
                                @php
                                    $actionClass = match($auditLog->action) {
                                        'CREATED', 'APPROVED' => 'text-bg-success',
                                        'UPDATED', 'VIEWED' => 'text-bg-primary',
                                        'DELETED', 'REJECTED' => 'text-bg-danger',
                                        default => 'text-bg-secondary',
                                    };
                                @endphp
                                <span class="badge {{ $actionClass }}">{{ str_replace('_', ' ', $auditLog->action) }}</span>
                            </td>
                            <td>
                                <div class="fw-medium">{{ $auditLog->module ?: \Illuminate\Support\Str::headline($auditLog->table_name ?? 'Activity') }}</div>
                                <small class="text-muted">{{ $auditLog->table_name ?? '—' }}{{ $auditLog->record_id ? ' · #' . $auditLog->record_id : '' }}</small>
                            </td>
                            <td class="text-wrap" style="min-width: 220px; max-width: 360px;">
                                {{ \Illuminate\Support\Str::limit($auditLog->description, 110) ?: 'No description available' }}
                            </td>
                            <td class="text-nowrap">
                                <span>{{ $auditLog->created_at?->format('d M Y, H:i') ?? '—' }}</span>
                                @if($auditLog->created_at)
                                    <small class="d-block text-muted">{{ $auditLog->created_at->diffForHumans() }}</small>
                                @endif
                            </td>
                            <td class="text-end pe-4">
                                <button type="button" class="btn btn-sm btn-outline-primary audit-details"
                                        data-details-url="{{ route('admin.audit-logs.show', $auditLog) }}"
                                        data-bs-toggle="modal" data-bs-target="#auditDetailsModal">
                                    <i class="bi bi-eye me-1"></i>Details
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-5">
                                <i class="bi bi-journal-x fs-3 d-block mb-2"></i>
                                No audit records match these filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($auditLogs->hasPages())
        <div class="card-footer bg-white border-0 px-4">{{ $auditLogs->links() }}</div>
    @endif
</div>

<div class="modal fade" id="auditDetailsModal" tabindex="-1" aria-labelledby="auditDetailsTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title fw-semibold" id="auditDetailsTitle">Audit record details</h5>
                    <div class="small text-muted" id="auditDetailsMeta"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="auditDetailsBody">
                <div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-2"></span>Loading details…</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('auditDetailsModal');
    const body = document.getElementById('auditDetailsBody');
    const meta = document.getElementById('auditDetailsMeta');

    const element = (tag, className, text) => {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    };

    const valueBlock = (value) => {
        const pre = element('pre', 'small bg-light border rounded p-2 mb-0 text-wrap');
        pre.textContent = value === null || value === undefined ? '—' :
            (typeof value === 'object' ? JSON.stringify(value, null, 2) : String(value));
        return pre;
    };

    modal.addEventListener('show.bs.modal', async (event) => {
        body.replaceChildren(element('div', 'text-center text-muted py-4', 'Loading details…'));
        meta.textContent = '';
        try {
            const response = await fetch(event.relatedTarget.dataset.detailsUrl, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (!response.ok) throw new Error('Could not load this audit record.');
            const data = await response.json();

            document.getElementById('auditDetailsTitle').textContent = `${data.action.replaceAll('_', ' ')} · #${data.id}`;
            meta.textContent = `${data.actor} · ${data.module || data.table_name || 'Activity'} · ${data.created_at || ''}`;
            body.replaceChildren();
            body.append(element('p', 'mb-2', data.description || 'No description available.'));
            const recordInfo = [data.table_name, data.record_id ? `Record #${data.record_id}` : null, data.ip_address].filter(Boolean).join(' · ');
            if (recordInfo) body.append(element('div', 'small text-muted mb-3', recordInfo));

            const heading = element('h6', 'fw-semibold mt-3', 'Field changes');
            body.append(heading);
            if (!data.changes.length) {
                body.append(element('div', 'alert alert-light border mb-0', 'No before-and-after field values were recorded.'));
                return;
            }

            const tableWrap = element('div', 'table-responsive');
            const table = element('table', 'table table-sm table-bordered align-middle');
            const thead = document.createElement('thead');
            const headerRow = document.createElement('tr');
            ['Field', 'Change', 'Before', 'After'].forEach((label) => headerRow.append(element('th', 'small', label)));
            thead.append(headerRow);
            const tbody = document.createElement('tbody');

            data.changes.forEach((change) => {
                const row = document.createElement('tr');
                row.append(element('th', 'small text-break', change.field));
                const badge = element('span', `badge ${change.type === 'added' ? 'text-bg-success' : change.type === 'removed' ? 'text-bg-danger' : 'text-bg-primary'}`, change.type);
                const typeCell = document.createElement('td');
                typeCell.append(badge);
                row.append(typeCell);
                const beforeCell = document.createElement('td');
                beforeCell.append(valueBlock(change.before));
                const afterCell = document.createElement('td');
                afterCell.append(valueBlock(change.after));
                row.append(beforeCell, afterCell);
                tbody.append(row);
            });

            table.append(thead, tbody);
            tableWrap.append(table);
            body.append(tableWrap);
        } catch (error) {
            body.replaceChildren(element('div', 'alert alert-danger mb-0', error.message || 'Could not load audit details.'));
        }
    });
});
</script>
@endpush
