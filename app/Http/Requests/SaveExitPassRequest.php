<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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
