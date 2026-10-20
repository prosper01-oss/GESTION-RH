<?php

namespace App\Http\Controllers\Api;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\PermissionQuota;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Quota annuel de permissions : jours accordés, engagés et restants sur l'année civile.
 */
class PermissionQuotaController extends Controller
{
    public function __construct(private PermissionQuota $quota) {}

    /**
     * Quota de l'agent connecté.
     */
    public function mine(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;
        abort_if($employee === null, 404, "Ce compte n'est rattaché à aucune fiche du personnel.");

        return response()->json(['data' => $this->quota->summary($employee, $this->year($request))]);
    }

    /**
     * Quota d'un agent, pour les RH et les personnes habilitées.
     */
    public function show(Request $request, Employee $employee): JsonResponse
    {
        abort_unless(
            $request->user()->hasPermission(PermissionName::ViewLeaveBalances) || $request->user()->hasPermission(PermissionName::ReviewRequests),
            403,
        );

        return response()->json(['data' => $this->quota->summary($employee, $this->year($request))]);
    }

    private function year(Request $request): int
    {
        return (int) ($request->validate(['year' => ['nullable', 'integer', 'min:2000', 'max:2100']])['year'] ?? now()->year);
    }
}
