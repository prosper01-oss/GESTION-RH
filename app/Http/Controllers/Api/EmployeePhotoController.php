<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeePhotoController extends Controller
{
    /**
     * Photo stockée sur le disque privé, servie après contrôle des droits.
     */
    public function show(Employee $employee): StreamedResponse
    {
        Gate::authorize('view', $employee);

        abort_if($employee->photo_path === null || ! Storage::exists($employee->photo_path), 404, "Cet employé n'a pas de photo.");

        return Storage::response($employee->photo_path);
    }

    public function store(Request $request, Employee $employee): JsonResponse
    {
        Gate::authorize('update', $employee);

        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $oldPath = $employee->photo_path;
        $employee->photo_path = $request->file('photo')->store('employee-photos');
        $employee->save();

        if ($oldPath !== null) {
            Storage::delete($oldPath);
        }

        AuditLog::record('employee.photo_updated', $employee);

        return response()->json(['message' => 'Photo enregistrée.']);
    }
}
