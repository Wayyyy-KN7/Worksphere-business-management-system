<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CompanySettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CompanySettingsController extends Controller
{
    private const WORKING_DAYS = ['MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN'];

    public function edit(): View
    {
        $settings = CompanySettings::current();
        $timezones = [
            'Asia/Yangon', 'Asia/Bangkok', 'Asia/Singapore', 'Asia/Tokyo',
            'Australia/Sydney', 'Europe/London', 'Europe/Paris', 'UTC',
            'America/Chicago', 'America/Los_Angeles', 'America/New_York',
        ];

        return view('admin.settings.company', compact('settings', 'timezones'));
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'remove_logo' => 'nullable|boolean',
            'address' => 'nullable|string|max:2000',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'timezone' => 'required|timezone',
            'currency' => ['required', 'regex:/^[A-Z]{3}$/'],
            'date_format' => 'required|in:Y-m-d,d/m/Y,m/d/Y,d M Y',
            'working_days' => 'required|array|min:1',
            'working_days.*' => 'required|in:MON,TUE,WED,THU,FRI,SAT,SUN',
            'work_start' => 'required|date_format:H:i',
            'work_end' => 'required|date_format:H:i|after:work_start',
        ]);

        $settings = CompanySettings::query()->firstOrNew(['id' => 1], CompanySettings::defaults());
        $oldValues = $this->settingsValues($settings);
        $oldLogoPath = $settings->logo_path;
        $newLogoPath = null;

        if ($request->hasFile('logo')) {
            $newLogoPath = $request->file('logo')->store('company', 'public');
            $validated['logo_path'] = $newLogoPath;
        } elseif ($request->boolean('remove_logo')) {
            $validated['logo_path'] = null;
        } else {
            $validated['logo_path'] = $oldLogoPath;
        }

        unset($validated['logo'], $validated['remove_logo']);
        $validated['working_days'] = array_values(array_unique($validated['working_days']));

        try {
            DB::transaction(function () use ($settings, $validated, $oldValues) {
                $settings->fill($validated);
                $settings->save();

                AuditLog::log(
                    'UPDATED',
                    'company_settings',
                    $settings->id,
                    'Company configuration updated.',
                    $oldValues,
                    $this->settingsValues($settings),
                    'Company Settings',
                );
            });
        } catch (\Throwable $exception) {
            if ($newLogoPath) {
                Storage::disk('public')->delete($newLogoPath);
            }

            throw $exception;
        }

        if ($oldLogoPath && $oldLogoPath !== $settings->logo_path) {
            Storage::disk('public')->delete($oldLogoPath);
        }

        return redirect()->route('admin.settings.company')->with('success', 'Company settings saved.');
    }

    public function security(): View
    {
        $settings = CompanySettings::current();

        return view('admin.settings.security', compact('settings'));
    }

    public function updateSecurity(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'max_failed_login_attempts' => 'required|integer|min:1|max:20',
            'account_lockout_minutes' => 'required|integer|min:1|max:1440',
            'session_timeout_minutes' => 'required|integer|min:5|max:1440',
        ]);

        $settings = CompanySettings::query()->firstOrNew(['id' => 1], CompanySettings::defaults());
        $oldValues = $this->securityValues($settings);

        DB::transaction(function () use ($settings, $validated, $oldValues) {
            $settings->fill($validated);
            $settings->save();

            AuditLog::log(
                'UPDATED',
                'company_settings',
                $settings->id,
                'Login protection settings updated.',
                $oldValues,
                $this->securityValues($settings),
                'Login Protection Settings',
            );
        });

        return redirect()->route('admin.settings.security')->with('success', 'Security settings saved.');
    }

    private function securityValues(CompanySettings $settings): array
    {
        return [
            'max_failed_login_attempts' => $settings->max_failed_login_attempts,
            'account_lockout_minutes' => $settings->account_lockout_minutes,
            'session_timeout_minutes' => $settings->session_timeout_minutes,
        ];
    }

    private function settingsValues(CompanySettings $settings): array
    {
        return [
            'company_name' => $settings->company_name,
            'logo_path' => $settings->logo_path,
            'address' => $settings->address,
            'phone' => $settings->phone,
            'email' => $settings->email,
            'timezone' => $settings->timezone,
            'currency' => $settings->currency,
            'date_format' => $settings->date_format,
            'working_days' => $settings->working_days,
            'work_start' => $settings->work_start,
            'work_end' => $settings->work_end,
        ];
    }
}