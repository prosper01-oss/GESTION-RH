<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Saisie d'une permission ; la durée est calculée par le serveur.
 */
class SavePermissionRequestRequest extends FormRequest
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
            'permission_type_id' => ['required', 'integer', Rule::exists('permission_types', 'id')->where('is_active', true)],
            'start_at' => ['required', 'date', 'after_or_equal:'.now()->subDays(30)->toDateString()],
            'end_at' => ['required', 'date', 'after:start_at', 'before_or_equal:'.now()->addYear()->toDateString()],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'permission_type_id.exists' => "Cette catégorie de permission n'est pas proposée.",
            'end_at.after' => 'La fin doit suivre le début.',
        ];
    }
}
