<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\PublicHolidayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

#[Fillable(['date', 'label', 'is_recurring'])]
class PublicHoliday extends Model
{
    /** @use HasFactory<PublicHolidayFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_recurring' => 'boolean',
        ];
    }

    /**
     * Dates fériées (Y-m-d) comprises entre deux dates, fêtes récurrentes reportées sur chaque année.
     *
     * @return Collection<int, string>
     */
    public static function datesBetween(CarbonInterface $start, CarbonInterface $end): Collection
    {
        $fixed = self::query()
            ->where('is_recurring', false)
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->pluck('date')
            ->map(fn (CarbonInterface $date): string => $date->toDateString());

        $recurring = self::query()->where('is_recurring', true)->pluck('date')->flatMap(
            fn (CarbonInterface $date) => collect(range($start->year, $end->year))
                ->filter(fn (int $year): bool => checkdate($date->month, $date->day, $year))
                ->map(fn (int $year): string => sprintf('%04d-%02d-%02d', $year, $date->month, $date->day))
        )->filter(fn (string $date): bool => $date >= $start->toDateString() && $date <= $end->toDateString());

        return $fixed->merge($recurring)->unique()->sort()->values();
    }
}
