<?php

namespace App\Http\Controllers\Api\Pharma;

use App\Http\Controllers\Api\Pharma\Concerns\RepondEnJson;
use App\Http\Controllers\Controller;
use App\Services\Pharma\Docteurs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DocteurController extends Controller
{
    use RepondEnJson;

    /** Docteurs du personnel RH (id, nom, fonction, contact), filtrés par type de consultation. */
    public function index(Request $request): JsonResponse
    {
        abort_unless(Gate::any(['pharma.caisse', 'pharma.gestion']), 403);

        $request->validate(['type_docteur' => ['nullable', 'in:generaliste,dentiste']]);

        return $this->ok(['data' => Docteurs::liste($request->input('type_docteur'))]);
    }
}
