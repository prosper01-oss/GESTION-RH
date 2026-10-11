<?php

namespace App\Http\Controllers\Api;

use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeRequestResource;
use App\Models\EmployeeRequest;
use App\Services\LeaveRequestService;
use App\Services\RequestWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Actions du circuit. Le serveur détermine l'étape : le client ne choisit que l'action (EF-51).
 */
class RequestTransitionController extends Controller
{
    /**
     * Décisions des RH et du Médecin-Chef.
     *
     * @var array<string, RequestStatus>
     */
    private const DECISIONS = [
        'transmettre' => RequestStatus::TransmiseMedecin,
        'approuver' => RequestStatus::Approuvee,
        'refuser' => RequestStatus::Refusee,
        'a_corriger' => RequestStatus::ACorriger,
        'renvoyer_rh' => RequestStatus::RenvoyeeRh,
    ];

    public function __construct(private RequestWorkflow $workflow) {}

    public function submit(Request $request, EmployeeRequest $employeeRequest): EmployeeRequestResource
    {
        return $this->apply($employeeRequest, RequestStatus::Soumise, $request);
    }

    public function cancel(Request $request, EmployeeRequest $employeeRequest): EmployeeRequestResource
    {
        $request->validate(['comment' => ['nullable', 'string', 'max:1000']]);

        return $this->apply($employeeRequest, RequestStatus::Annulee, $request);
    }

    public function decide(Request $request, EmployeeRequest $employeeRequest): EmployeeRequestResource
    {
        $validated = $request->validate([
            'decision' => ['required', Rule::in(array_keys(self::DECISIONS))],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        return $this->apply($employeeRequest, self::DECISIONS[$validated['decision']], $request);
    }

    public function finalize(Request $request, EmployeeRequest $employeeRequest): EmployeeRequestResource
    {
        return $this->apply($employeeRequest, RequestStatus::Finalisee, $request);
    }

    public function complete(Request $request, EmployeeRequest $employeeRequest): EmployeeRequestResource
    {
        return $this->apply($employeeRequest, RequestStatus::Terminee, $request);
    }

    public function interrupt(Request $request, EmployeeRequest $employeeRequest, LeaveRequestService $leaveRequests): EmployeeRequestResource
    {
        Gate::authorize('process', $employeeRequest);

        $validated = $request->validate([
            'actual_end_date' => ['required', 'date'],
            'ends_morning' => ['boolean'],
        ]);

        $leaveRequests->interrupt($employeeRequest, $validated['actual_end_date'], $request->boolean('ends_morning'), $request->user());

        return EmployeeRequestResource::forDisplay($employeeRequest);
    }

    private function apply(EmployeeRequest $employeeRequest, RequestStatus $to, Request $request): EmployeeRequestResource
    {
        Gate::authorize('view', $employeeRequest);

        $this->workflow->transition($employeeRequest, $to, $request->user(), $request->input('comment'));

        return EmployeeRequestResource::forDisplay($employeeRequest);
    }
}
