<?php

namespace App\Http\Requests\Admin;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Employee;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can(PermissionName::ManageUsers->value);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'matricule' => ['nullable', 'string', Rule::exists('employees', 'matricule')],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['distinct', Rule::enum(RoleName::class)],
        ];
    }

    /**
     * L'administrateur rattache le compte par le matricule, sans accès à l'annuaire du personnel.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('matricule') || ! $this->filled('matricule')) {
                    return;
                }

                if (Employee::where('matricule', $this->string('matricule'))->whereHas('user')->exists()) {
                    $validator->errors()->add('matricule', 'Cet employé a déjà un compte.');
                }
            },
        ];
    }

    public function employee(): ?Employee
    {
        return $this->filled('matricule')
            ? Employee::where('matricule', $this->string('matricule'))->first()
            : null;
    }
}
