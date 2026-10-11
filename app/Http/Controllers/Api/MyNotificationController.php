<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Alertes internes de l'utilisateur connecté et préférences d'envoi (EF-60, EF-63).
 */
class MyNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()->notifications()->limit(30)->get();

        return response()->json([
            'data' => $notifications->map(fn (DatabaseNotification $notification): array => [
                'id' => $notification->id,
                'title' => $notification->data['title'] ?? '',
                'message' => $notification->data['message'] ?? '',
                'link' => $notification->data['link'] ?? (isset($notification->data['request_id']) ? "/demandes/{$notification->data['request_id']}" : null),
                'read_at' => $notification->read_at,
                'created_at' => $notification->created_at,
            ]),
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markAsRead(Request $request, string $notification): JsonResponse
    {
        $request->user()->notifications()->whereKey($notification)->firstOrFail()->markAsRead();

        return response()->json(['message' => 'Notification lue.']);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['message' => 'Toutes les notifications sont lues.']);
    }

    public function preferences(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()->only('notify_by_email', 'notify_by_sms')]);
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'notify_by_email' => ['required', 'boolean'],
            'notify_by_sms' => ['required', 'boolean'],
        ]);

        $request->user()->update($validated);

        return response()->json(['data' => $request->user()->only('notify_by_email', 'notify_by_sms')]);
    }
}
