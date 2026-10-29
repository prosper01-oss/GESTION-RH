<?php

namespace App\Http\Requests\Pharma;

use Illuminate\Foundation\Http\FormRequest;

class StoreDetailKitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('pharma.gestion');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'id_kit' => ['required', 'integer', 'exists:pharma_kits,id'],
            'id_article' => ['required', 'integer', 'exists:pharma_articles,id'],
            'qte_kit' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'qte_kit.min' => 'La quantité dans le kit doit être au moins 1.',
            'id_kit.exists' => 'Le kit sélectionné est introuvable.',
            'id_article.exists' => "L'article sélectionné est introuvable.",
        ];
    }
}
