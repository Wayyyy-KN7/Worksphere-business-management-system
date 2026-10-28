<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Employee\AttendanceController as EmployeeAttendanceController;
use App\Http\Controllers\Employee\LeaveController as EmployeeLeaveController;
use App\Http\Controllers\HR\AttendanceController as HRAttendanceController;
use App\Http\Controllers\HR\EmployeeController as HREmployeeController;
use App\Http\Controllers\Manager\LeaveController as ManagerLeaveController;
use App\Http\Controllers\CRM\CustomerController;
use App\Http\Controllers\CRM\OpportunityController;
use App\Http\Controllers\CRM\SaleController;
use App\Http\Controllers\CRM\ReminderController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\CompanySettingsController;
use App\Http\Controllers\Admin\AuditLogController;


Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

// ── AUTHENTICATED ──
Route::middleware(['auth', 'active', 'company.settings', 'session.timeout'])->group(function () {

    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/company-structure', [DepartmentController::class, 'structure'])->name('company.structure');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::get('/notifications/{notification}/open', [NotificationController::class, 'open'])->name('notifications.open');
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::patch('/notifications/{notification}/unread', [NotificationController::class, 'markUnread'])->name('notifications.unread');
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/photo', [ProfileController::class, 'updatePhoto'])->name('profile.photo.update');
    Route::delete('/profile/photo', [ProfileController::class, 'destroyPhoto'])->name('profile.photo.destroy');

    // ── ADMIN ONLY ──
    Route::middleware('role:ADMIN')->prefix('admin')->name('admin.')->group(function () {
        Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('audit-logs/{auditLog}', [AuditLogController::class, 'show'])->name('audit-logs.show');
        Route::resource('departments', DepartmentController::class);
        Route::resource('users', UserController::class)->except(['destroy']);
        Route::patch('users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
        
        Route::get('settings', function () {
            return view('admin.settings.index');
        })->name('settings.index');
        Route::get('settings/security', [CompanySettingsController::class, 'security'])->name('settings.security');
        Route::put('settings/security', [CompanySettingsController::class, 'updateSecurity'])->name('settings.security.update');
        Route::get('settings/company', [CompanySettingsController::class, 'edit'])->name('settings.company');
        Route::put('settings/company', [CompanySettingsController::class, 'update'])->name('settings.company.update');
    });

    // ── HR ROUTES ──
    Route::middleware('role:ADMIN,HR_MANAGER,HR_EMPLOYEE')->prefix('hr')->name('hr.')->group(function () {
        Route::get('employees', [HREmployeeController::class, 'index'])->name('employees.index');
        Route::get('attendance', [HRAttendanceController::class, 'index'])->name('attendance.index');
        Route::get('attendance/history', [HRAttendanceController::class, 'history'])
            ->middleware('role:ADMIN,HR_MANAGER')
            ->name('attendance.history');
        Route::post('attendance/manual-check-in', [HRAttendanceController::class, 'manualCheckIn'])->name('attendance.manual-check-in');
        Route::post('attendance/manual-check-out', [HRAttendanceController::class, 'manualCheckOut'])->name('attendance.manual-check-out');
        Route::get('attendance/report', [HRAttendanceController::class, 'report'])
            ->middleware('role:ADMIN,HR_MANAGER')
            ->name('attendance.report');
        Route::put('attendance/{id}', [HRAttendanceController::class, 'update'])->name('attendance.update');
        Route::delete('attendance/{id}', [HRAttendanceController::class, 'destroy'])->name('attendance.destroy');
        Route::get('leaves', [ManagerLeaveController::class, 'hrIndex'])->name('leaves.index');
    });

    // ── MANAGER ROUTES ──
    Route::middleware('role:ADMIN,*_MANAGER')->prefix('manager')->name('manager.')->group(function () {
        Route::get('attendance/history', [App\Http\Controllers\Manager\AttendanceController::class, 'history'])
            ->middleware('role:ADMIN,HR_MANAGER')
            ->name('attendance.history');
        Route::get('leave', [ManagerLeaveController::class, 'index'])->name('leave.index');
        Route::get('leave/calendar', [ManagerLeaveController::class, 'calendar'])->name('leave.calendar');
        Route::get('leave/{leaveRequest}', [ManagerLeaveController::class, 'show'])->name('leave.show');
        Route::post('leave/{leaveRequest}/approve', [ManagerLeaveController::class, 'approve'])->name('leave.approve');
        Route::post('leave/{leaveRequest}/reject', [ManagerLeaveController::class, 'reject'])->name('leave.reject');

        Route::get('leave/{leaveRequest}/approve', function () {
            return redirect()->route('manager.leave.index');
        })->name('leave.approve.redirect');
        Route::get('leave/{leaveRequest}/reject', function () {
            return redirect()->route('manager.leave.index');
        })->name('leave.reject.redirect');
    });

    // ── EMPLOYEE ROUTES ──
    Route::prefix('employee')->name('employee.')->group(function () {
        Route::get('attendance', [EmployeeAttendanceController::class, 'index'])->name('attendance.index');
        Route::post('attendance/report-late', [EmployeeAttendanceController::class, 'reportLate'])->name('attendance.report-late');
        Route::post('attendance/check-in', [EmployeeAttendanceController::class, 'checkIn'])->name('attendance.check-in');
        Route::post('attendance/check-out', [EmployeeAttendanceController::class, 'checkOut'])->name('attendance.check-out');
        Route::get('attendance/check-out', function () {
            return redirect()->route('employee.attendance.index');
        })->name('attendance.check-out.redirect');
        Route::get('attendance/history', [EmployeeAttendanceController::class, 'history'])
            ->middleware('role:ADMIN,HR_MANAGER')
            ->name('attendance.history');

        Route::get('leave', [EmployeeLeaveController::class, 'index'])->name('leave.index');
        Route::get('leave/create', [EmployeeLeaveController::class, 'create'])->name('leave.create');
        Route::post('leave', [EmployeeLeaveController::class, 'store'])->name('leave.store');
        Route::get('leave/{leaveRequest}', [EmployeeLeaveController::class, 'show'])->name('leave.show');
        Route::post('leave/{leaveRequest}/cancel', [EmployeeLeaveController::class, 'cancel'])->name('leave.cancel');
        
        Route::get('leave/{leaveRequest}/cancel', function () {
            return redirect()->route('employee.leave.index');
        })->name('leave.cancel.redirect');
    });

  // ── CRM ROUTES ──
    Route::prefix('crm')->name('crm.')->group(function () {
        Route::get('customers/archived', [CustomerController::class, 'archived'])->name('customers.archived');
        Route::post('customers/{customerId}/restore', [CustomerController::class, 'restore'])->name('customers.restore');
        Route::resource('customers', CustomerController::class)->withTrashed(['show']);
        
        Route::resource('opportunities', OpportunityController::class);
        Route::put('opportunities/{opportunity}/stage', [OpportunityController::class, 'updateStage'])->name('opportunities.updateStage');
        
        Route::resource('follow-ups', App\Http\Controllers\CRM\FollowUpController::class);
        Route::post('follow-ups/{followUp}/start', [App\Http\Controllers\CRM\FollowUpController::class, 'start'])->name('follow-ups.start');
        Route::post('follow-ups/{followUp}/complete', [App\Http\Controllers\CRM\FollowUpController::class, 'complete'])->name('follow-ups.complete');
        Route::post('follow-ups/{followUp}/cancel', [App\Http\Controllers\CRM\FollowUpController::class, 'cancel'])->name('follow-ups.cancel');
        Route::post('follow-ups/{followUp}/reschedule', [App\Http\Controllers\CRM\FollowUpController::class, 'reschedule'])->name('follow-ups.reschedule');
        
        Route::resource('reminders', ReminderController::class);
        Route::post('reminders/{reminder}/complete', [ReminderController::class, 'complete'])->name('reminders.complete');
        Route::post('reminders/{reminder}/snooze', [ReminderController::class, 'snooze'])->name('reminders.snooze');
        Route::post('reminders/{reminder}/reschedule', [ReminderController::class, 'reschedule'])->name('reminders.reschedule');
        Route::post('reminders/{reminder}/cancel', [ReminderController::class, 'cancel'])->name('reminders.cancel');

        // Invoice routes
        Route::get('sales/{sale}/invoice', [SaleController::class, 'invoice'])->name('sales.invoice');
        Route::post('sales/{sale}/store-invoice', [SaleController::class, 'generateAndStoreInvoice'])->name('sales.store-invoice'); // <-- Added this route
        
        Route::resource('sales', SaleController::class);
    });

    // ── DOCUMENT MANAGEMENT ROUTES ──
    Route::prefix('documents')->group(function () {
        // Specific Named Custom Views for Sidebar
        Route::get('/my', [DocumentController::class, 'myDocuments'])->name('documents.my');
        Route::get('/department', [DocumentController::class, 'departmentDocuments'])->name('documents.department');
        Route::get('/company', [DocumentController::class, 'publicDocuments'])->name('documents.company');
        Route::get('/public', [DocumentController::class, 'publicDocuments'])->name('documents.public');
        Route::get('/shared', [DocumentController::class, 'sharedDocuments'])->name('documents.shared');
        Route::get('/approvals', [DocumentController::class, 'approvals'])->name('documents.approvals');

        // Standard Resource Management Views
        Route::get('/', [DocumentController::class, 'index'])->name('documents.index');
        Route::get('/create', [DocumentController::class, 'create'])->name('documents.create');
        Route::post('/', [DocumentController::class, 'store'])->name('documents.store');
        Route::get('/{document}', [DocumentController::class, 'show'])->name('documents.show');
        Route::get('/{document}/edit', [DocumentController::class, 'edit'])->name('documents.edit');
        Route::put('/{document}', [DocumentController::class, 'update'])->name('documents.update');
        Route::delete('/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');
        
        Route::post('{document}/versions', [DocumentController::class, 'uploadVersion'])->name('documents.versions.upload');
        Route::get('{document}/versions/{version}/download', [DocumentController::class, 'download'])->name('documents.versions.download');
        
        Route::post('{document}/submit-for-approval', [DocumentController::class, 'submitForApproval'])->name('documents.submit-approval');
        Route::post('{document}/approve', [DocumentController::class, 'approve'])->name('documents.approve');
        Route::post('{document}/reject', [DocumentController::class, 'reject'])->name('documents.reject');
        Route::post('{document}/share', [DocumentController::class, 'share'])->name('documents.share');
    });
});

Route::get('/', function () {
    return redirect()->route('login');
});