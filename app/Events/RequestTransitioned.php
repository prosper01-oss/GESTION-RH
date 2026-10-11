<?php

namespace App\Events;

use App\Enums\RequestStatus;
use App\Models\EmployeeRequest;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Émis après chaque changement de statut, une fois la transaction validée.
 */
class RequestTransitioned implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public EmployeeRequest $request,
        public RequestStatus $from,
        public RequestStatus $to,
        public ?int $actorId,
        public ?string $comment,
    ) {}
}
