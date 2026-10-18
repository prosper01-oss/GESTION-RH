<?php

namespace App\Services\Documents;

use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Models\EmployeeRequest;
use App\Models\LeaveRequestDetail;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Documents imprimables des demandes approuvées (EF-28, EF-43) : autorisation de congé
 * ou de permission, billet de sortie, avec en-tête et référence de l'OMIT.
 */
class RequestDocument
{
    /**
     * @var list<RequestStatus>
     */
    private const PRINTABLE = [RequestStatus::Approuvee, RequestStatus::Finalisee, RequestStatus::Terminee];

    public function download(EmployeeRequest $request): Response
    {
        if (! in_array($request->status, self::PRINTABLE, true)) {
            throw new ConflictHttpException("Le document n'est disponible qu'une fois la demande approuvée.");
        }

        $request->load(['employee.department', 'history.actor', 'history.onBehalfOf', ...EmployeeRequest::detailRelations()]);
        $title = match ($request->request_type) {
            RequestType::Conge => 'Autorisation de congé',
            RequestType::Permission => 'Autorisation de permission',
            RequestType::Sortie => 'Billet de sortie',
        };

        return Pdf::loadView('pdf.request', ['request' => $request, 'title' => $title, 'rows' => $this->rows($request)])
            ->setPaper('a4')
            ->download("{$request->reference}.pdf");
    }

    /**
     * @return array<string, string>
     */
    private function rows(EmployeeRequest $request): array
    {
        $employee = $request->employee;
        $rows = [
            'Agent' => "{$employee->prenom} {$employee->nom}",
            'Matricule' => $employee->matricule,
            'Service' => $employee->department?->name ?? '—',
        ];

        if ($detail = $request->leaveDetail) {
            $rows += [
                'Nature du congé' => $detail->leaveType->name,
                'Du' => $detail->start_date->format('d/m/Y').($detail->starts_afternoon ? ' (après-midi)' : ''),
                'Au' => $detail->end_date->format('d/m/Y').($detail->ends_morning ? ' (matin)' : ''),
                'Nombre de jours' => $this->number((float) $detail->requested_days).' jour(s)',
                'Reprise du travail' => $this->returnDate($detail),
                'Intérim assuré par' => $detail->interim
                    ? "{$detail->interim->prenom} {$detail->interim->nom} ({$detail->interim->matricule}, {$detail->interim->position})"
                    : '—',
            ];
            if ($detail->consumed_days !== null) {
                $rows['Jours réellement pris'] = $this->number((float) $detail->consumed_days).' jour(s)';
            }
        }

        if ($detail = $request->permissionDetail) {
            $rows += [
                'Catégorie' => $detail->permissionType->name.($detail->permissionType->is_paid ? '' : ' (non rémunérée)'),
                'Du' => $detail->start_at->format('d/m/Y à H:i'),
                'Au' => $detail->end_at->format('d/m/Y à H:i'),
                'Durée' => $this->number((float) $detail->duration_hours).' h',
            ];
        }

        if ($detail = $request->exitDetail) {
            $rows += [
                'Destination' => $detail->destination,
                'Motif' => $detail->reason,
                'Départ prévu' => $detail->departure_at->format('d/m/Y à H:i'),
                'Retour prévu' => $detail->expected_return_at->format('d/m/Y à H:i'),
                'Départ réel' => $detail->actual_departure_at?->format('d/m/Y à H:i') ?? '……………………',
                'Retour réel' => $detail->actual_return_at?->format('d/m/Y à H:i') ?? '……………………',
            ];
        }

        $approval = $request->history->sortByDesc('id')->first(fn ($entry): bool => $entry->to_status === RequestStatus::Approuvee);
        if ($approval) {
            $rows['Approuvé par'] = ($approval->actor?->name ?? '—')
                .($approval->onBehalfOf ? " (en suppléance de {$approval->onBehalfOf->name})" : '')
                .', le '.$approval->created_at->format('d/m/Y');
        }

        return $rows;
    }

    /**
     * Date de reprise ; « après-midi » quand le congé s'arrête au matin du même jour.
     */
    private function returnDate(LeaveRequestDetail $detail): string
    {
        if ($detail->return_date === null) {
            return '—';
        }

        $sameDay = $detail->ends_morning && $detail->return_date->equalTo($detail->end_date);

        return $detail->return_date->format('d/m/Y').($sameDay ? ' (après-midi)' : '');
    }

    private function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', ' '), '0'), ',');
    }
}
