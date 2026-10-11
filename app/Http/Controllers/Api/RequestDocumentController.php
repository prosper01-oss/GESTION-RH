<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmployeeRequest;
use App\Services\Documents\RequestDocument;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class RequestDocumentController extends Controller
{
    public function show(EmployeeRequest $employeeRequest, RequestDocument $document): Response
    {
        Gate::authorize('view', $employeeRequest);

        return $document->download($employeeRequest);
    }
}
