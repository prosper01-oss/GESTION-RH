<?php

namespace App\Http\Requests\Admin;

use App\Enums\AccrualRule;
use App\Enums\CountingMode;
use App\Enums\PermissionName;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Type de congé et règles associées, en création comme en modification.
 */
class SaveLeaveTypeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can(PermissionName::ManageLeaveSettings->value);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $leaveType = $this->route('leave_type');

        return [
            'code' => ['required', 'string', 'max:20', Rule::unique('leave_types', 'code')->ignore($leaveType)],
            'name' => ['required', 'string', 'max:255', Rule::unique('leave_types', 'name')->ignore($leaveType)],
            'is_paid' => ['required', 'boolean'],
            'has_balance' => ['required', 'boolean'],
            'requires_attachment' => ['required', 'boolean'],
            'is_medical' => ['required', 'boolean'],
            'allows_half_day' => ['required', 'boolean'],
            'min_notice_days' => ['sometimes', 'integer', 'min:0', 'max:365'],
            'attachment_deadline_hours' => ['nullable', 'integer', 'min:1', 'max:720'],
            'is_active' => ['required', 'boolean'],
            'policy' => ['required', 'array'],
            'policy.accrual_rule' => ['required', Rule::enum(AccrualRule::class)],
            'policy.annual_entitlement' => ['required', 'numeric', 'min:0', 'max:365'],
            'policy.counting_mode' => ['required', Rule::enum(CountingMode::class)],
            'policy.carry_over_max' => ['nullable', 'numeric', 'min:0', 'max:365'],
            'policy.effective_from' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'policy.accrual_rule' => "mode d'acquisition",
            'policy.annual_entitlement' => 'droit annuel',
            'policy.counting_mode' => 'mode de décompte',
            'policy.carry_over_max' => 'report maximal',
            'policy.effective_from' => "date d'effet",
            'min_notice_days' => 'délai de dépôt minimal',
            'attachment_deadline_hours' => 'délai du justificatif',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function typeAttributes(): array
    {
        return $this->safe()->except('policy');
    }

    /**
     * @return array{accrual_rule: string, annual_entitlement: float, counting_mode: string, carry_over_max: ?float}
     */
    public function policyAttributes(): array
    {
        $policy = $this->validated('policy');

        return [
            'accrual_rule' => $policy['accrual_rule'],
            'annual_entitlement' => round((float) $policy['annual_entitlement'], 2),
            'counting_mode' => $policy['counting_mode'],
            'carry_over_max' => isset($policy['carry_over_max']) ? round((float) $policy['carry_over_max'], 2) : null,
        ];
    }
}
