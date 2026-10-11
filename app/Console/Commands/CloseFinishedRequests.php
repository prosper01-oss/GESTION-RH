<?php

namespace App\Console\Commands;

use App\Enums\RequestStatus;
use App\Models\EmployeeRequest;
use App\Services\RequestWorkflow;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('omit:close-finished-requests')]
#[Description('Clôture les congés et permissions finalisés dont la période est passée ; pour un congé, la réservation devient consommation')]
class CloseFinishedRequests extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(RequestWorkflow $workflow): int
    {
        $closed = 0;

        EmployeeRequest::query()
            ->where('status', RequestStatus::Finalisee)
            ->where(fn ($query) => $query
                ->whereHas('leaveDetail', fn ($query) => $query->whereDate('end_date', '<', now()->toDateString()))
                ->orWhereHas('permissionDetail', fn ($query) => $query->where('end_at', '<', now())))
            ->orderBy('id')
            ->each(function (EmployeeRequest $request) use ($workflow, &$closed): void {
                try {
                    $workflow->transition($request, RequestStatus::Terminee, null, 'Clôture automatique en fin de période');
                    $closed++;
                } catch (Throwable $exception) {
                    report($exception);
                    $this->error("{$request->reference} : {$exception->getMessage()}");
                }
            });

        $this->info("{$closed} demande(s) clôturée(s).");

        return self::SUCCESS;
    }
}
