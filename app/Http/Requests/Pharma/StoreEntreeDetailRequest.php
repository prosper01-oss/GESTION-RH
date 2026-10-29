<?php

namespace App\Http\Requests\Pharma;

use Illuminate\Foundation\Http\FormRequest;

class StoreEntreeDetailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('pharma.entrees');
    }

    /**
     * Le numéro de lot est généré côté serveur à la création.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'article_id' => ['required', 'integer', 'exists:pharma_articles,id'],
            'qte_entree' => ['required', 'integer', 'min:1'],
            'prix_achat_boite' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'date_peremption' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['qte_entree.min' => 'La quantité entrée doit être au moins 1.'];
    }
}
