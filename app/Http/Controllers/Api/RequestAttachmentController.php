<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\EmployeeRequest;
use App\Models\RequestAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RequestAttachmentController extends Controller
{
    /**
     * Ajoute un justificatif tant que l'agent peut modifier sa demande.
     */
    public function store(Request $request, EmployeeRequest $employeeRequest): JsonResponse
    {
        Gate::authorize('update', $employeeRequest);

        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ], [
            'file.mimes' => 'Le justificatif doit être un PDF ou une image (JPG, PNG).',
            'file.max' => 'Le justificatif ne doit pas dépasser 5 Mo.',
        ]);

        $file = $request->file('file');
        $attachment = $employeeRequest->attachments()->create([
            'file_path' => $file->store('request-attachments'),
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            // Le caractère médical est fixé par le type de congé, jamais par l'utilisateur.
            'is_medical' => $employeeRequest->load(EmployeeRequest::detailRelations())->attachmentsAreMedical(),
            'uploaded_by' => $request->user()->id,
        ]);

        return response()->json(['data' => ['id' => $attachment->id, 'original_name' => $attachment->original_name]], 201);
    }

    /**
     * Téléchargement après contrôle des droits ; l'accès à un justificatif médical est journalisé.
     */
    public function show(RequestAttachment $attachment): StreamedResponse
    {
        Gate::authorize('view', $attachment);

        if ($attachment->is_medical && ! $attachment->request->isOwnedBy(auth()->user())) {
            AuditLog::record('attachment.medical_viewed', $attachment->request, newValues: ['attachment_id' => $attachment->id]);
        }

        abort_unless(Storage::exists($attachment->file_path), 404, 'Fichier introuvable.');

        return Storage::download($attachment->file_path, $attachment->original_name);
    }

    public function destroy(RequestAttachment $attachment): JsonResponse
    {
        Gate::authorize('delete', $attachment);

        Storage::delete($attachment->file_path);
        $attachment->delete();

        return response()->json(['message' => 'Justificatif supprimé.']);
    }
}
