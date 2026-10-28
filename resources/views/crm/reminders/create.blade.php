@extends('layouts.app')

@section('content')
<div class="container py-3">
    <div class="row">
        <div class="col-12 col-xl-10 offset-xl-1">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom px-4 py-3">
                    <h4 class="mb-1">Create Sales Reminder</h4>
                    <p class="text-muted mb-0">Schedule a reminder for a customer, opportunity, or sales activity.</p>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="{{ route('crm.reminders.store') }}">
                        @csrf
                        <div class="mb-4">
                            <label for="reminder-title" class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                            <div class="form-text mb-2">Use a clear action, such as “Call customer about the proposal”.</div>
                            <input id="reminder-title" type="text" name="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title') }}" maxlength="255" required>
                            @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-4">
                            <label for="reminder-description" class="form-label fw-semibold">Description <span class="text-muted fw-normal">(Optional)</span></label>
                            <textarea id="reminder-description" name="description" class="form-control" rows="3" placeholder="Add relevant context or the next step.">{{ old('description') }}</textarea>
                        </div>
                        <div class="mb-4">
                            <label for="follow-up-id" class="form-label fw-semibold">Follow-Up <span class="text-muted fw-normal">(Optional)</span></label>
                            <select id="follow-up-id" name="follow_up_id" class="form-select @error('follow_up_id') is-invalid @enderror">
                                <option value="">No linked Follow-Up</option>
                                @foreach($followUps as $followUp)
                                <option value="{{ $followUp->id }}" data-customer-id="{{ $followUp->customer_id }}" data-opportunity-id="{{ $followUp->opportunity_id }}" {{ old('follow_up_id', request('follow_up_id')) == $followUp->id ? 'selected' : '' }}>{{ $followUp->title }} · {{ $followUp->follow_up_date->format('d M Y') }}</option>
                                @endforeach
                            </select>
                            @error('follow_up_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="customer-id" class="form-label fw-semibold">Customer <span class="text-muted fw-normal">(Optional)</span></label>
                                <select id="customer-id" name="customer_id" class="form-select @error('customer_id') is-invalid @enderror">
                                    <option value="">Select Customer</option>
                                    @foreach($customers as $customer)
                                        <option value="{{ $customer->id }}" {{ old('customer_id') == $customer->id ? 'selected' : '' }}>{{ $customer->name }} - {{ $customer->company_name }}</option>
                                    @endforeach
                                </select>
                                @error('customer_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="opportunity-id" class="form-label fw-semibold">Opportunity <span class="text-muted fw-normal">(Optional)</span></label>
                                <select id="opportunity-id" name="opportunity_id" class="form-select @error('opportunity_id') is-invalid @enderror">
                                    <option value="">Select Opportunity</option>
                                    @foreach($opportunities as $opportunity)
                                        <option value="{{ $opportunity->id }}" data-customer-id="{{ $opportunity->customer_id }}" {{ old('opportunity_id') == $opportunity->id ? 'selected' : '' }}>{{ $opportunity->title }}</option>
                                    @endforeach
                                </select>
                                @error('opportunity_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="remindable-type" class="form-label fw-semibold">Other CRM Record <span class="text-muted fw-normal">(Optional)</span></label>
                                <select id="remindable-type" name="remindable_type" class="form-select @error('remindable_type') is-invalid @enderror">
                                    <option value="">No other linked record</option>
                                    @if($linkedRecordOptions['sale']->isNotEmpty())
                                    <option value="sale" {{ old('remindable_type') === 'sale' ? 'selected' : '' }}>Sale / Invoice</option>
                                    @endif
                                    @if($linkedRecordOptions['document']->isNotEmpty())
                                    <option value="document" {{ old('remindable_type') === 'document' ? 'selected' : '' }}>Document</option>
                                    @endif
                                </select>
                                @error('remindable_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="form-text">Customer, opportunity, and follow-up links can be selected above.</div>
                            </div>
                            <div id="linked-record-group" class="col-md-6 mb-3" hidden>
                                <label for="remindable-id" class="form-label fw-semibold">Select Record <span class="text-danger">*</span></label>
                                <select id="remindable-id" name="remindable_id" class="form-select @error('remindable_id') is-invalid @enderror" disabled>
                                    <option value="">Select a record</option>
                                </select>
                                @error('remindable_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        @if(auth()->user()->isManager())
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Assign Reminder To</label>
                                <select name="user_id" class="form-select">
                                    @foreach($users as $user)
                                        <option value="{{ $user->id }}" {{ old('user_id', auth()->id()) == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="reminder-date" class="form-label fw-semibold">Reminder Date <span class="text-danger">*</span></label>
                                <input id="reminder-date" type="date" name="reminder_date" min="{{ today()->toDateString() }}" class="form-control @error('reminder_date') is-invalid @enderror" value="{{ old('reminder_date') }}" required>
                                @error('reminder_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="reminder-time" class="form-label fw-semibold">Reminder Time <span class="text-muted fw-normal">(Optional)</span></label>
                                <input id="reminder-time" type="time" name="reminder_time" class="form-control @error('reminder_time') is-invalid @enderror" value="{{ old('reminder_time') }}">
                                @error('reminder_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="d-flex flex-column flex-sm-row justify-content-end gap-2 pt-2 border-top">
                            <a href="{{ route('crm.reminders.index') }}" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Create Reminder</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const recordsByType = {{ Illuminate\Support\Js::from($linkedRecordOptions) }};
        const customerSelect = document.getElementById('customer-id');
        const opportunitySelect = document.getElementById('opportunity-id');
        const followUpSelect = document.getElementById('follow-up-id');
        const typeSelect = document.getElementById('remindable-type');
        const recordGroup = document.getElementById('linked-record-group');
        const recordSelect = document.getElementById('remindable-id');
        const previousRecordId = @json(old('remindable_id'));
        const opportunityOptions = Array.from(opportunitySelect.options).slice(1).map((option) => ({
            value: option.value,
            label: option.text,
            customerId: option.dataset.customerId,
        }));
        const followUpOptions = Array.from(followUpSelect.options).slice(1).map((option) => ({
            value: option.value,
            label: option.text,
            customerId: option.dataset.customerId,
            opportunityId: option.dataset.opportunityId,
        }));

        const updateRecordOptions = (preferredRecordId = '') => {
            const type = typeSelect.value;
            const customerId = customerSelect.value;
            const opportunityId = opportunitySelect.value;
            const records = (recordsByType[type] || []).filter((record) => {
                if (customerId && String(record.customer_id) !== String(customerId)) {
                    return false;
                }

                return type !== 'sale' || !opportunityId || String(record.opportunity_id) === String(opportunityId);
            });
            recordSelect.replaceChildren(new Option(records.length ? 'Select a record' : 'No records available', ''));

            records.forEach((record) => {
                const option = new Option(record.label, record.id);
                recordSelect.add(option);
            });
            recordSelect.value = records.some((record) => String(record.id) === String(preferredRecordId))
                ? String(preferredRecordId)
                : '';

            recordGroup.hidden = !type;
            recordSelect.disabled = !type || records.length === 0;
            recordSelect.required = Boolean(type);
        };

        const updateCustomerRelatedOptions = () => {
            const customerId = customerSelect.value;
            const selectedOpportunityId = opportunitySelect.value;
            const selectedFollowUpId = followUpSelect.value;
            const matchingOpportunities = opportunityOptions.filter((option) => !customerId || option.customerId === customerId);
            const validOpportunityId = matchingOpportunities.some((option) => option.value === selectedOpportunityId)
                ? selectedOpportunityId
                : '';
            const matchingFollowUps = followUpOptions.filter((option) => {
                const matchesCustomer = !customerId || option.customerId === customerId;
                const matchesOpportunity = !validOpportunityId || option.opportunityId === validOpportunityId;

                return matchesCustomer && matchesOpportunity;
            });

            opportunitySelect.replaceChildren(new Option('Select Opportunity', ''));
            matchingOpportunities.forEach((option) => opportunitySelect.add(new Option(option.label, option.value)));
            opportunitySelect.value = validOpportunityId;

            followUpSelect.replaceChildren(new Option('No linked Follow-Up', ''));
            matchingFollowUps.forEach((option) => followUpSelect.add(new Option(option.label, option.value)));
            followUpSelect.value = matchingFollowUps.some((option) => option.value === selectedFollowUpId)
                ? selectedFollowUpId
                : '';

            updateRecordOptions(recordSelect.value);
        };

        customerSelect.addEventListener('change', updateCustomerRelatedOptions);
        opportunitySelect.addEventListener('change', () => {
            updateCustomerRelatedOptions();
        });
        typeSelect.addEventListener('change', () => updateRecordOptions());
        updateCustomerRelatedOptions();
        updateRecordOptions(previousRecordId);
    });
</script>
@endsection
