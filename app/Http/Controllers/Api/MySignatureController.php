<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Signature électronique du compte connecté, utilisée sur les documents imprimables.
 */
class MySignatureController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'has_signature' => $request->user()->signature_path !== null,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'signature' => ['required', 'image', 'mimes:png,jpg,jpeg', 'max:2048'],
        ], [
            'signature.image' => 'Le fichier doit être une image.',
            'signature.mimes' => 'Formats acceptés : PNG, JPG.',
            'signature.max' => 'La taille maximale est 2 Mo.',
        ]);

        $user = $request->user();

        if ($user->signature_path) {
            Storage::disk('local')->delete($user->signature_path);
        }

        $path = $request->file('signature')->store('signatures', 'local');
        $user->signature_path = $path;
        $user->save();

        return response()->json(['message' => 'Signature enregistrée.']);
    }

    public function image(Request $request): Response
    {
        $path = $request->user()->signature_path;
        if ($path === null || ! Storage::disk('local')->exists($path)) {
            throw new NotFoundHttpException('Aucune signature enregistrée.');
        }

        return Storage::disk('local')->response($path);
    }

    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->signature_path) {
            Storage::disk('local')->delete($user->signature_path);
            $user->signature_path = null;
            $user->save();
        }

        return response()->json(['message' => 'Signature supprimée.']);
    }
}
