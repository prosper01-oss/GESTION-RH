<?php

namespace App\Http\Requests\Pharma;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePanierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('pharma.caisse');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'sex_patient' => $this->boolean('sex_patient'),
            'unite_age' => $this->boolean('unite_age'),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $pageType = $this->input('page_type');
        $isConsultation = $pageType === 'consultation';
        $required = $isConsultation ? 'required' : 'nullable';

        return [
            'page_type' => ['required', 'string', 'in:consultation,service,article,analyse,kit'],
            'id_panier' => ['nullable', 'integer'],
            'article_id' => [$pageType === 'article' ? 'required' : 'nullable', 'integer'],
            'analyse_id' => [$pageType === 'analyse' ? 'required' : 'nullable', 'array', 'min:1'],
            'analyse_id.*' => ['integer'],
            'service_id' => [$pageType === 'service' ? 'required' : 'nullable', 'integer'],
            'kit_id' => [$pageType === 'kit' ? 'required' : 'nullable', 'integer'],
            'qte' => [in_array($pageType, ['article', 'service', 'kit'], true) ? 'required' : 'nullable', 'integer', 'min:1', 'max:9999'],
            // Fiche patient : obligatoire pour une consultation
            'nom_patient' => [$required, 'string', 'max:255'],
            'sex_patient' => [$isConsultation ? 'required' : 'nullable', 'boolean'],
            'age_patient' => [$required, 'integer', 'min:0', 'max:150'],
            'unite_age' => [$isConsultation ? 'required' : 'nullable', 'boolean'],
            'type_docteur' => [$required, Rule::in(['generaliste', 'dentiste'])],
            'docteur' => [$required, 'integer', Rule::exists('employees', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nom_patient.required' => 'Le nom du patient est obligatoire.',
            'age_patient.required' => "L'âge du patient est obligatoire.",
            'type_docteur.required' => 'Le type de consultation est obligatoire.',
            'type_docteur.in' => 'Type de consultation invalide (généraliste ou dentiste).',
            'docteur.required' => 'Le choix du docteur est obligatoire.',
            'qte.min' => 'La quantité doit être au moins 1.',
            'analyse_id.min' => 'Sélectionnez au moins une analyse.',
        ];
    }
}
