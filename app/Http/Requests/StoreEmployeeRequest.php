<?php

namespace App\Http\Requests;

use App\Enums\ContractType;
use App\Enums\Sexe;
use App\Enums\SituationFamiliale;
use App\Models\Employee;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Employee::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'matricule' => ['required', 'string', 'max:30', Rule::unique('employees', 'matricule')->ignore($this->route('employee'))],
            'nom' => ['required', 'string', 'max:255'],
            'prenom' => ['required', 'string', 'max:255'],
            'cin' => ['nullable', 'string', 'max:30'],
            'date_naissance' => ['nullable', 'date', 'before:today'],
            'numero_cnasp' => ['nullable', 'string', 'max:30'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'sexe' => ['nullable', Rule::enum(Sexe::class)],
            'situation_familiale' => ['nullable', Rule::enum(SituationFamiliale::class)],
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')],
            'position' => ['required', 'string', 'max:255'],
            'hire_date' => ['required', 'date', 'before_or_equal:today'],
            'contract_type' => ['required', Rule::enum(ContractType::class)],
            'phone_pro' => ['nullable', 'string', 'max:30'],
            'email_pro' => ['nullable', 'string', 'email', 'max:255'],
        ];
    }
}
