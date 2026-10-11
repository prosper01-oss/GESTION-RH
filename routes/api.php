<?php

use App\Enums\RequestType;
use App\Http\Controllers\Api\Admin\AuditLogController;
use App\Http\Controllers\Api\Admin\LeaveTypeController as AdminLeaveTypeController;
use App\Http\Controllers\Api\Admin\PublicHolidayController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Admin\UserPasswordResetController;
use App\Http\Controllers\Api\Admin\UserRoleController;
use App\Http\Controllers\Api\ApprovalController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\EmployeePhotoController;
use App\Http\Controllers\Api\EmployeeStatusController;
use App\Http\Controllers\Api\LeaveBalanceAdjustmentController;
use App\Http\Controllers\Api\LeaveBalanceController;
use App\Http\Controllers\Api\LeaveDayCountController;
use App\Http\Controllers\Api\LeaveRequestController;
use App\Http\Controllers\Api\LeaveTypeController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\MyPasswordController;
use App\Http\Controllers\Api\RequestAttachmentController;
use App\Http\Controllers\Api\RequestTransitionController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Middleware\EnsureAccountIsUsable;
use App\Models\EmployeeRequest;
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

    Route::apiResource('employees', EmployeeController::class)->except('destroy');
    Route::patch('/employees/{employee}/status', [EmployeeStatusController::class, 'update'])->name('employees.status.update');
    Route::get('/employees/{employee}/photo', [EmployeePhotoController::class, 'show'])->name('employees.photo.show');
    Route::post('/employees/{employee}/photo', [EmployeePhotoController::class, 'store'])->name('employees.photo.store');

    Route::apiResource('departments', DepartmentController::class)->except('destroy');

    Route::get('/leave-types', [LeaveTypeController::class, 'index'])->name('leave-types.index');
    Route::get('/leave-days/count', [LeaveDayCountController::class, 'show'])->name('leave-days.count');
    Route::get('/leave-balances/me', [LeaveBalanceController::class, 'mine'])->name('leave-balances.mine');
    Route::get('/leave-balances/{employee}', [LeaveBalanceController::class, 'show'])->name('leave-balances.show');
    Route::apiResource('leave-requests', LeaveRequestController::class);
    Route::post('/leave-requests/{employeeRequest}/submit', [RequestTransitionController::class, 'submit'])->name('leave-requests.submit');
    Route::post('/leave-requests/{employeeRequest}/cancel', [RequestTransitionController::class, 'cancel'])->name('leave-requests.cancel');
    Route::post('/leave-requests/{employeeRequest}/interrupt', [RequestTransitionController::class, 'interrupt'])->name('leave-requests.interrupt');
    Route::post('/leave-requests/{employeeRequest}/attachments', [RequestAttachmentController::class, 'store'])->name('leave-requests.attachments.store');

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
    });
});
