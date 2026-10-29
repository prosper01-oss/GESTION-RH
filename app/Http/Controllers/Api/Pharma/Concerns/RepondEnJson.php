<?php

namespace App\Http\Controllers\Api\Pharma\Concerns;

use Illuminate\Http\JsonResponse;

/**
 * Réponses JSON des contrôleurs Pharmacie : le payload tel quel en cas de succès,
 * { message } avec un code d'erreur en cas d'échec métier.
 */
trait RepondEnJson
{
    /**
     * @param  array<string, mixed>  $payload
     */
    protected function ok(array $payload = [], int $status = 200): JsonResponse
    {
        return response()->json($payload, $status);
    }

    protected function echec(string $message, int $status = 422): JsonResponse
    {
        return response()->json(['message' => $message], $status);
    }
}
