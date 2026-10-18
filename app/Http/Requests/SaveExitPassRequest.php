<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveExitPassRequest extends FormRequest
{
    /**
     * L'autorisation est vérifiée par la politique dans le contrôleur.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'departure_at' => ['required', 'date', 'after_or_equal:'.now()->startOfDay()->toDateTimeString()],
            'expected_return_at' => ['required', 'date', 'after:departure_at', 'before_or_equal:'.now()->addMonth()->toDateString()],
            'destination' => ['required', 'string', 'max:255'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * Règle R7 : une sortie dure au plus OMIT_EXIT_PASS_MAX_HOURS heures.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $maxHours = (float) config('omit.exit_pass_max_hours');
                if ($maxHours <= 0 || $validator->errors()->hasAny(['departure_at', 'expected_return_at'])) {
                    return;
                }

                $minutes = CarbonImmutable::parse($this->input('departure_at'))->diffInMinutes(CarbonImmutable::parse($this->input('expected_return_at')));
                if ($minutes > $maxHours * 60) {
                    $validator->errors()->add('expected_return_at', sprintf('Une sortie dure au plus %s h.', rtrim(rtrim(number_format($maxHours, 2, ',', ''), '0'), ',')));
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'departure_at.after_or_equal' => 'Le départ ne peut pas être dans le passé.',
            'expected_return_at.after' => "L'heure de retour prévue doit suivre l'heure de départ.",
        ];
    }
}
