<?php

namespace App\Http\Controllers\Api;

use App\Enums\EmployeeStatus;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Recherche d'un collègue (choix de l'intérimaire d'un congé), ouverte à tout agent connecté.
 * Ne renvoie que l'identité professionnelle d'agents actifs, jamais l'annuaire complet.
 */
class ColleagueController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = $request->validate(['search' => ['required', 'string', 'min:2', 'max:100']])['search'];

        $colleagues = Employee::query()
            ->where('status', EmployeeStatus::Actif)
            ->when($request->user()->employee_id, fn ($query, int $employeeId) => $query->whereKeyNot($employeeId))
            ->search($search)
            ->with('department')
            ->orderBy('nom')
            ->orderBy('prenom')
            ->limit(15)
            ->get();

        return response()->json([
            'data' => $colleagues->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'matricule' => $employee->matricule,
                'nom' => $employee->nom,
                'prenom' => $employee->prenom,
                'position' => $employee->position,
                'department' => $employee->department ? ['name' => $employee->department->name] : null,
            ]),
        ]);
    }
}
