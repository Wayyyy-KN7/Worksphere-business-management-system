@extends('layouts.app')

@section('title', 'Company Settings')
@section('breadcrumb', 'Company Settings')

@push('styles')
<style>
    .company-settings-form { max-width: 920px; }
    .settings-section-title { color: var(--navy); font-size: 1rem; font-weight: 650; }
    .logo-preview { width: 92px; height: 72px; object-fit: contain; border: 1px solid var(--border); border-radius: 6px; background: var(--panel-bg); padding: 6px; }
    .working-days { display: flex; flex-wrap: wrap; gap: 8px; }
    .working-day-option { position: relative; }
    .working-day-option input { position: absolute; opacity: 0; pointer-events: none; }
    .working-day-option label { padding: 7px 12px; border: 1px solid var(--border); border-radius: 6px; color: var(--navy); cursor: pointer; }
    .working-day-option input:checked + label { color: var(--navy); border-color: var(--primary); background: rgba(18, 163, 139, 0.12); }
    .working-day-option input:focus-visible + label { outline: 2px solid var(--primary); outline-offset: 2px; }
</style>
@endpush

@section('content')
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h2>Company Settings</h2>
        <p class="text-muted">Configure company identity, regional formats, and work schedule.</p>
    </div>
    <a href="{{ route('admin.settings.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Settings</a>
</div>

<form class="company-settings-form" method="POST" action="{{ route('admin.settings.company.update') }}" enctype="multipart/form-data">
    @csrf
    @method('PUT')

    <section class="card border-0 shadow-sm mb-3">
        <div class="card-body p-4">
            <h3 class="settings-section-title mb-3">Company identity</h3>
            <div class="mb-3">
                <label class="form-label" for="company_name">Company name</label>
                <input id="company_name" name="company_name" class="form-control @error('company_name') is-invalid @enderror" maxlength="255" value="{{ old('company_name', $settings->company_name) }}" required>
                @error('company_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label" for="logo">Company logo</label>
                <div class="d-flex flex-wrap align-items-center gap-3">
                    @if($settings->logo_path)
                        <img class="logo-preview" src="{{ asset('storage/' . $settings->logo_path) }}" alt="Current company logo">
                    @endif
                    <input id="logo" type="file" name="logo" accept="image/png,image/jpeg,image/webp" class="form-control @error('logo') is-invalid @enderror" style="max-width: 440px">
                    @if($settings->logo_path)
                        <label class="form-check-label"><input class="form-check-input me-1" type="checkbox" name="remove_logo" value="1"> Remove logo</label>
                    @endif
                </div>
                @error('logo')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label" for="address">Company address</label>
                <textarea id="address" name="address" rows="2" maxlength="2000" class="form-control @error('address') is-invalid @enderror">{{ old('address', $settings->address) }}</textarea>
                @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="phone">Phone</label>
                    <input id="phone" name="phone" type="tel" maxlength="50" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $settings->phone) }}">
                    @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="email">Email</label>
                    <input id="email" name="email" type="email" maxlength="255" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $settings->email) }}">
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </section>

    <section class="card border-0 shadow-sm mb-3">
        <div class="card-body p-4">
            <h3 class="settings-section-title mb-3">Regional formats</h3>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="timezone">Time zone</label>
                    <input id="timezone" name="timezone" class="form-control @error('timezone') is-invalid @enderror" list="company-timezones" value="{{ old('timezone', $settings->timezone) }}" required>
                    <datalist id="company-timezones">
                        @foreach($timezones as $timezone)<option value="{{ $timezone }}">@endforeach
                    </datalist>
                    @error('timezone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="currency">Currency</label>
                    <select id="currency" name="currency" class="form-select @error('currency') is-invalid @enderror" required>
                        @foreach(['USD' => 'USD - US Dollar', 'MMK' => 'MMK - Myanmar Kyat', 'THB' => 'THB - Thai Baht', 'SGD' => 'SGD - Singapore Dollar', 'EUR' => 'EUR - Euro', 'GBP' => 'GBP - Pound Sterling', 'JPY' => 'JPY - Japanese Yen', 'AUD' => 'AUD - Australian Dollar', 'CAD' => 'CAD - Canadian Dollar'] as $code => $label)
                            <option value="{{ $code }}" {{ old('currency', $settings->currency) === $code ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('currency')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="date_format">Date format</label>
                    <select id="date_format" name="date_format" class="form-select @error('date_format') is-invalid @enderror" required>
                        @foreach(['Y-m-d' => '2026-09-27', 'd/m/Y' => '27/09/2026', 'm/d/Y' => '09/27/2026', 'd M Y' => '27 Sep 2026'] as $format => $example)
                            <option value="{{ $format }}" {{ old('date_format', $settings->date_format) === $format ? 'selected' : '' }}>{{ $example }}</option>
                        @endforeach
                    </select>
                    @error('date_format')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </section>

    <section class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <h3 class="settings-section-title mb-3">Working schedule</h3>
            @php($selectedDays = old('working_days', $settings->working_days ?? ['MON', 'TUE', 'WED', 'THU', 'FRI']))
            <fieldset class="mb-3">
                <legend class="form-label">Working days</legend>
                <div class="working-days">
                    @foreach(['MON' => 'Mon', 'TUE' => 'Tue', 'WED' => 'Wed', 'THU' => 'Thu', 'FRI' => 'Fri', 'SAT' => 'Sat', 'SUN' => 'Sun'] as $day => $label)
                        <span class="working-day-option">
                            <input id="working_day_{{ strtolower($day) }}" type="checkbox" name="working_days[]" value="{{ $day }}" {{ in_array($day, $selectedDays, true) ? 'checked' : '' }}>
                            <label for="working_day_{{ strtolower($day) }}">{{ $label }}</label>
                        </span>
                    @endforeach
                </div>
                @error('working_days')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                @error('working_days.*')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </fieldset>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="work_start">Working hours start</label>
                    <input id="work_start" type="time" name="work_start" class="form-control @error('work_start') is-invalid @enderror" value="{{ old('work_start', substr((string) $settings->work_start, 0, 5)) }}" required>
                    @error('work_start')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="work_end">Working hours end</label>
                    <input id="work_end" type="time" name="work_end" class="form-control @error('work_end') is-invalid @enderror" value="{{ old('work_end', substr((string) $settings->work_end, 0, 5)) }}" required>
                    @error('work_end')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </section>

    <div class="d-flex justify-content-between mb-4">
        <a href="{{ route('admin.settings.index') }}" class="btn btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check2"></i> Save Company Settings</button>
    </div>
</form>
@endsection