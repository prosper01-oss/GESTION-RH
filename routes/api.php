<?php

use App\Enums\RequestType;
use App\Http\Controllers\Api\Admin\AuditLogController;
use App\Http\Controllers\Api\Admin\BackupController;
use App\Http\Controllers\Api\Admin\LeaveTypeController as AdminLeaveTypeController;
use App\Http\Controllers\Api\Admin\PermissionTypeController;
use App\Http\Controllers\Api\Admin\PublicHolidayController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Admin\UserPasswordResetController;
use App\Http\Controllers\Api\Admin\UserRoleController;
use App\Http\Controllers\Api\ApprovalController;
use App\Http\Controllers\Api\ColleagueController;
use App\Http\Controllers\Api\DelegationController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\EmployeePhotoController;
use App\Http\Controllers\Api\EmployeeStatusController;
use App\Http\Controllers\Api\ExitPassController;
use App\Http\Controllers\Api\ImportController;
use App\Http\Controllers\Api\LeaveBalanceAdjustmentController;
use App\Http\Controllers\Api\LeaveBalanceController;
use App\Http\Controllers\Api\LeaveDayCountController;
use App\Http\Controllers\Api\LeaveRequestController;
use App\Http\Controllers\Api\LeaveTypeController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\MyNotificationController;
use App\Http\Controllers\Api\MyPasswordController;
use App\Http\Controllers\Api\PermissionRequestController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\RequestAttachmentController;
use App\Http\Controllers\Api\RequestController;
use App\Http\Controllers\Api\RequestDocumentController;
use App\Http\Controllers\Api\RequestTransitionController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Middleware\EnsureAccountIsUsable;
use App\Models\EmployeeRequest;
use App\Models\PermissionType;
use Illuminate\Support\Facades\Route;

// Une demande de congé n'est accessible que par les routes des congés.
Route::bind('leave_request', fn (string $value) => EmployeeRequest::where('request_type', RequestType::Conge)->findOrFail($value));
Route::bind('employeeRequest', fn (string $value) => EmployeeRequest::findOrFail($value));

Route::get('/health', fn () => response()->json(['status' => 'ok']))->name('health');

Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:login')->name('login');

Route::middleware(['auth:sanctum', EnsureAccountIsUsable::class])->group(function () {
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');

    Route::get('/me', [MeController::class, 'show'])->name('me.show');
    Route::patch('/me/password', [MyPasswordController::class, 'update'])->name('me.password.update');
    Route::get('/me/notifications', [MyNotificationController::class, 'index'])->name('me.notifications.index');
    Route::patch('/me/notifications/{notification}/read', [MyNotificationController::class, 'markAsRead'])->name('me.notifications.read');
    Route::post('/me/notifications/read-all', [MyNotificationController::class, 'markAllAsRead'])->name('me.notifications.read-all');
    Route::get('/me/notification-preferences', [MyNotificationController::class, 'preferences'])->name('me.notification-preferences.show');
    Route::put('/me/notification-preferences', [MyNotificationController::class, 'updatePreferences'])->name('me.notification-preferences.update');

    Route::get('/imports/templates/{type}', [ImportController::class, 'template'])->name('imports.template');
    Route::post('/employees/import', [ImportController::class, 'employees'])->name('employees.import');
    Route::post('/leave-balances/import', [ImportController::class, 'balances'])->name('leave-balances.import');
    Route::apiResource('employees', EmployeeController::class)->except('destroy');
    Route::patch('/employees/{employee}/status', [EmployeeStatusController::class, 'update'])->name('employees.status.update');
    Route::get('/employees/{employee}/photo', [EmployeePhotoController::class, 'show'])->name('employees.photo.show');
    Route::post('/employees/{employee}/photo', [EmployeePhotoController::class, 'store'])->name('employees.photo.store');

    Route::apiResource('departments', DepartmentController::class)->except('destroy');

    Route::get('/colleagues', [ColleagueController::class, 'index'])->name('colleagues.index');
    Route::get('/leave-types', [LeaveTypeController::class, 'index'])->name('leave-types.index');
    Route::get('/leave-days/count', [LeaveDayCountController::class, 'show'])->name('leave-days.count');
    Route::get('/leave-balances/me', [LeaveBalanceController::class, 'mine'])->name('leave-balances.mine');
    Route::get('/leave-balances/me/years', [LeaveBalanceController::class, 'myYearsSummary'])->name('leave-balances.mine.years');
    Route::get('/leave-balances/me/monthly', [LeaveBalanceController::class, 'myMonthly'])->name('leave-balances.mine.monthly');
    Route::get('/leave-balances/{employee}', [LeaveBalanceController::class, 'show'])->name('leave-balances.show');
    Route::get('/leave-balances/{employee}/years', [LeaveBalanceController::class, 'yearsSummary'])->name('leave-balances.years');
    Route::get('/leave-balances/{employee}/monthly', [LeaveBalanceController::class, 'monthly'])->name('leave-balances.monthly');
    Route::get('/requests', [RequestController::class, 'index'])->name('requests.index');
    Route::get('/requests/{employeeRequest}', [RequestController::class, 'show'])->name('requests.show');
    Route::post('/requests/{employeeRequest}/submit', [RequestTransitionController::class, 'submit'])->name('requests.submit');
    Route::post('/requests/{employeeRequest}/cancel', [RequestTransitionController::class, 'cancel'])->name('requests.cancel');
    Route::post('/requests/{employeeRequest}/attachments', [RequestAttachmentController::class, 'store'])->name('requests.attachments.store');
    Route::delete('/requests/{employeeRequest}', [RequestController::class, 'destroy'])->name('requests.destroy');
    Route::get('/requests/{employeeRequest}/pdf', [RequestDocumentController::class, 'show'])->name('requests.pdf');
    Route::get('/leave-requests/{employeeRequest}/pdf', [RequestDocumentController::class, 'show'])->name('leave-requests.pdf');
    Route::get('/permission-requests/{employeeRequest}/pdf', [RequestDocumentController::class, 'show'])->name('permission-requests.pdf');
    Route::get('/exit-passes/{employeeRequest}/pdf', [RequestDocumentController::class, 'show'])->name('exit-passes.pdf');

    Route::get('/leave-requests', [RequestController::class, 'index'])->defaults('type', 'conge')->name('leave-requests.index');
    Route::apiResource('leave-requests', LeaveRequestController::class)->except(['index', 'destroy']);
    Route::delete('/leave-requests/{employeeRequest}', [RequestController::class, 'destroy'])->name('leave-requests.destroy');
    Route::post('/leave-requests/{employeeRequest}/submit', [RequestTransitionController::class, 'submit'])->name('leave-requests.submit');
    Route::post('/leave-requests/{employeeRequest}/cancel', [RequestTransitionController::class, 'cancel'])->name('leave-requests.cancel');
    Route::post('/leave-requests/{employeeRequest}/interrupt', [RequestTransitionController::class, 'interrupt'])->name('leave-requests.interrupt');
    Route::post('/leave-requests/{employeeRequest}/attachments', [RequestAttachmentController::class, 'store'])->name('leave-requests.attachments.store');

    Route::get('/permission-types', fn () => response()->json(['data' => PermissionType::query()->active()->orderBy('requires_details')->orderBy('name')->get()]))->name('permission-types.index');
    Route::get('/permission-requests', [RequestController::class, 'index'])->defaults('type', 'permission')->name('permission-requests.index');
    Route::post('/permission-requests', [PermissionRequestController::class, 'store'])->name('permission-requests.store');
    Route::put('/permission-requests/{employeeRequest}', [PermissionRequestController::class, 'update'])->name('permission-requests.update');

    Route::get('/exit-passes', [RequestController::class, 'index'])->defaults('type', 'sortie')->name('exit-passes.index');
    Route::post('/exit-passes', [ExitPassController::class, 'store'])->name('exit-passes.store');
    Route::put('/exit-passes/{employeeRequest}', [ExitPassController::class, 'update'])->name('exit-passes.update');
    Route::post('/exit-passes/{employeeRequest}/record-departure', [ExitPassController::class, 'recordDeparture'])->name('exit-passes.record-departure');
    Route::post('/exit-passes/{employeeRequest}/record-return', [ExitPassController::class, 'recordReturn'])->name('exit-passes.record-return');

    Route::get('/delegations', [DelegationController::class, 'index'])->name('delegations.index');
    Route::get('/delegations/candidates', [DelegationController::class, 'candidates'])->name('delegations.candidates');
    Route::post('/delegations', [DelegationController::class, 'store'])->name('delegations.store');
    Route::delete('/delegations/{delegation}', [DelegationController::class, 'destroy'])->name('delegations.destroy');

    Route::get('/reports/dashboard', [ReportController::class, 'dashboard'])->name('reports.dashboard');
    Route::get('/reports/absences', [ReportController::class, 'absences'])->name('reports.absences');
    Route::get('/reports/absences/export', [ReportController::class, 'exportAbsences'])->name('reports.absences.export');
    Route::get('/reports/staff/export', [ReportController::class, 'exportStaff'])->name('reports.staff.export');

    Route::get('/approvals/pending', [ApprovalController::class, 'pending'])->name('approvals.pending');
    Route::post('/requests/{employeeRequest}/decision', [RequestTransitionController::class, 'decide'])->name('requests.decision');
    Route::post('/requests/{employeeRequest}/finalize', [RequestTransitionController::class, 'finalize'])->name('requests.finalize');
    Route::post('/requests/{employeeRequest}/complete', [RequestTransitionController::class, 'complete'])->name('requests.complete');

    Route::get('/attachments/{attachment}', [RequestAttachmentController::class, 'show'])->name('attachments.show');
    Route::delete('/attachments/{attachment}', [RequestAttachmentController::class, 'destroy'])->name('attachments.destroy');

    Route::post('/leave-balances/{employee}/adjustments', [LeaveBalanceAdjustmentController::class, 'store'])->name('leave-balances.adjustments.store');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::apiResource('users', UserController::class)->only(['index', 'store']);
        Route::patch('/users/{user}/roles', [UserRoleController::class, 'update'])->name('users.roles.update');
        Route::post('/users/{user}/reset-password', [UserPasswordResetController::class, 'store'])->name('users.password.reset');
        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
        Route::apiResource('leave-types', AdminLeaveTypeController::class)->only(['index', 'store', 'update']);
        Route::apiResource('public-holidays', PublicHolidayController::class)->except('show');
        Route::apiResource('permission-types', PermissionTypeController::class)->only(['index', 'store', 'update']);
        Route::get('/backups', [BackupController::class, 'index'])->name('backups.index');
        Route::post('/backups', [BackupController::class, 'store'])->middleware('throttle:3,10')->name('backups.store');
    });
});
