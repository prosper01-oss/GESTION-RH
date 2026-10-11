<?php

use App\Http\Controllers\Api\Admin\AuditLogController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Admin\UserPasswordResetController;
use App\Http\Controllers\Api\Admin\UserRoleController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\EmployeePhotoController;
use App\Http\Controllers\Api\EmployeeStatusController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\MyPasswordController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Middleware\EnsureAccountIsUsable;
use Illuminate\Support\Facades\Route;

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

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::apiResource('users', UserController::class)->only(['index', 'store']);
        Route::patch('/users/{user}/roles', [UserRoleController::class, 'update'])->name('users.roles.update');
        Route::post('/users/{user}/reset-password', [UserPasswordResetController::class, 'store'])->name('users.password.reset');
        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    });
});
