<?php

namespace App\Http\Requests\Pharma;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEntreeIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('pharma.entrees');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'ref_entree' => ['required', 'string', 'max:20', Rule::unique('pharma_entree_indices', 'ref_entree')->ignore($this->route('entreeIndex'))],
            'motif' => ['nullable', 'string', 'max:100'],
            'fournisseur_id' => ['nullable', 'integer', 'exists:pharma_fournisseurs,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['ref_entree.unique' => 'Cette référence existe déjà.'];
    }
}
