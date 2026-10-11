<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Http\Resources\PublicHolidayResource;
use App\Models\AuditLog;
use App\Models\PublicHoliday;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class PublicHolidayController extends Controller
{
    /**
     * Fêtes récurrentes et jours fériés de l'année demandée (année en cours par défaut).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize(PermissionName::ManageLeaveSettings->value);

        $year = $request->validate(['year' => ['nullable', 'integer', 'min:2000', 'max:2100']])['year'] ?? now()->year;

        $holidays = PublicHoliday::query()
            ->where(fn ($query) => $query->where('is_recurring', true)->orWhereYear('date', $year))
            ->get()
            ->sortBy(fn (PublicHoliday $holiday): string => $holiday->date->format('m-d'))
            ->values();

        return PublicHolidayResource::collection($holidays);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize(PermissionName::ManageLeaveSettings->value);

        $holiday = PublicHoliday::create($this->validated($request));
        AuditLog::record('public_holiday.created', $holiday, newValues: $holiday->only('date', 'label', 'is_recurring'));

        return PublicHolidayResource::make($holiday)->response()->setStatusCode(201);
    }

    public function update(Request $request, PublicHoliday $publicHoliday): PublicHolidayResource
    {
        Gate::authorize(PermissionName::ManageLeaveSettings->value);

        $old = $publicHoliday->only('date', 'label', 'is_recurring');
        $publicHoliday->update($this->validated($request, $publicHoliday));
        AuditLog::record('public_holiday.updated', $publicHoliday, $old, $publicHoliday->only('date', 'label', 'is_recurring'));

        return PublicHolidayResource::make($publicHoliday);
    }

    public function destroy(PublicHoliday $publicHoliday): JsonResponse
    {
        Gate::authorize(PermissionName::ManageLeaveSettings->value);

        AuditLog::record('public_holiday.deleted', $publicHoliday, $publicHoliday->only('date', 'label', 'is_recurring'));
        $publicHoliday->delete();

        return response()->json(['message' => 'Jour férié supprimé.']);
    }

    /**
     * @return array{date: string, label: string, is_recurring: bool}
     */
    private function validated(Request $request, ?PublicHoliday $holiday = null): array
    {
        return $request->validate([
            'date' => ['required', 'date', function (string $attribute, mixed $value, \Closure $fail) use ($holiday): void {
                $exists = PublicHoliday::query()
                    ->whereDate('date', $value)
                    ->when($holiday, fn ($query) => $query->whereKeyNot($holiday->id))
                    ->exists();

                if ($exists) {
                    $fail('Un jour férié existe déjà à cette date.');
                }
            }],
            'label' => ['required', 'string', 'max:255'],
            'is_recurring' => ['required', 'boolean'],
        ]);
    }
}
