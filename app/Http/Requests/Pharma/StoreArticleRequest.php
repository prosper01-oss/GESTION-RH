<?php

namespace App\Http\Requests\Pharma;

use Illuminate\Foundation\Http\FormRequest;

class StoreArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('pharma.articles');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'designation' => ['required', 'string', 'max:255'],
            'presentation' => ['required', 'integer', 'min:1'],
            'unite' => ['required', 'integer', 'exists:pharma_unites,id'],
            'seuil' => ['required', 'integer', 'min:1', 'gte:presentation'],
            'qte_max' => ['nullable', 'integer', 'min:1'],
            'statut' => ['sometimes', 'integer', 'in:0,1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'seuil.gte' => "Le seuil d'alerte ne doit pas être inférieur à la présentation.",
            'presentation.min' => 'La présentation doit être au moins 1.',
        ];
    }
}
