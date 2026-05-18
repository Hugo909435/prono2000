<?php

namespace App\Http\Requests\Tournament;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTournamentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'format' => ['required', Rule::in(['elimination', 'groups_elimination'])],
            'team_count' => ['required', 'integer', Rule::in([8, 16, 32, 48])],
            'group_count' => ['required_if:format,groups_elimination', 'nullable', 'integer', 'min:2', 'max:12'],
            'teams_per_group' => ['required_if:format,groups_elimination', 'nullable', 'integer', 'min:3', 'max:8'],
            'qualified_per_group' => ['required_if:format,groups_elimination', 'nullable', 'integer', 'min:1', 'max:4'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Le nom du tournoi est obligatoire.',
            'format.required' => 'Le format du tournoi est obligatoire.',
            'format.in' => 'Le format doit être "éliminatoire" ou "poules + éliminatoires".',
            'team_count.required' => 'Le nombre d\'équipes est obligatoire.',
            'team_count.in' => 'Le nombre d\'équipes doit être 8, 16, 32 ou 48.',
            'group_count.required_if' => 'Le nombre de poules est obligatoire pour ce format.',
            'teams_per_group.required_if' => 'Le nombre d\'équipes par poule est obligatoire.',
            'qualified_per_group.required_if' => 'Le nombre de qualifiés par poule est obligatoire.',
        ];
    }
}
