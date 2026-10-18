<?php

namespace App\Http\Requests;

use App\Enums\EmployeeStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'return_date' => ['required', 'date', 'after_or_equal:end_date', 'before_or_equal:'.now()->addYears(2)->addDays(30)->toDateString()],
            'interim_employee_id' => ['required', 'integer', Rule::exists('employees', 'id')->where('status', EmployeeStatus::Actif->value)],
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
            'return_date.required' => 'Indiquez la date de reprise du travail.',
            'return_date.after_or_equal' => 'La date de reprise ne peut pas précéder le dernier jour de congé.',
            'interim_employee_id.required' => "Choisissez l'agent qui assure l'intérim du poste.",
            'interim_employee_id.exists' => "L'intérimaire doit être un agent actif.",
        ];
    }

    /**
     * Reprise le jour même seulement si le congé s'arrête au matin ; l'intérimaire ne peut pas être le demandeur.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['end_date', 'return_date', 'interim_employee_id'])) {
                    return;
                }

                if ($this->input('return_date') === $this->input('end_date') && ! $this->boolean('ends_morning')) {
                    $validator->errors()->add('return_date', "La reprise le dernier jour du congé n'est possible que si le congé s'arrête au matin.");
                }

                $existing = $this->route('leave_request');
                $requesterId = $existing?->employee_id ?? $this->input('employee_id') ?? $this->user()->employee_id;
                if ((int) $this->input('interim_employee_id') === (int) $requesterId) {
                    $validator->errors()->add('interim_employee_id', "L'intérimaire doit être un autre agent que le demandeur.");
                }
            },
        ];
    }
}
