<?php

namespace App\Http\Requests\Pharma;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSortieIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('pharma.sorties');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'ref_sortie' => ['required', 'string', 'max:255', Rule::unique('pharma_sortie_indices', 'ref_sortie')->ignore($this->route('sortieIndex'))],
            'motif' => ['required', 'string', 'in:ajustement,perte,casse,perime'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'motif.in' => 'Le motif doit être « ajustement », « perte », « casse » ou « périmé ».',
            'ref_sortie.unique' => 'Cette référence existe déjà.',
        ];
    }
}
