<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Saisie d'une demande de congé. Le nombre de jours n'est pas accepté : le serveur le calcule.
 */
class SaveLeaveRequestRequest extends FormRequest
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
            'employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')],
            'leave_type_id' => ['required', 'integer', Rule::exists('leave_types', 'id')->where('is_active', true)],
            'start_date' => ['required', 'date', 'after_or_equal:'.now()->subDays(30)->toDateString()],
            'end_date' => ['required', 'date', 'after_or_equal:start_date', 'before_or_equal:'.now()->addYears(2)->toDateString()],
            'starts_afternoon' => ['boolean'],
            'ends_morning' => ['boolean'],
            'is_urgent' => ['boolean'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'leave_type_id.exists' => "Ce type de congé n'est pas proposé.",
            'start_date.after_or_equal' => 'La date de début ne peut pas remonter à plus de 30 jours.',
        ];
    }
}
