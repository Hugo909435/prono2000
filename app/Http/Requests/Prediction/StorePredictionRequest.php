<?php

namespace App\Http\Requests\Prediction;

use App\Models\Game;
use Illuminate\Foundation\Http\FormRequest;

class StorePredictionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $match = Game::with('tournament')->find($this->route('match'));

        if (!$match) {
            return false;
        }

        return $match->canPredict() && $match->tournament->isMember($this->user());
    }

    public function rules(): array
    {
        return [
            'home_score' => ['required', 'integer', 'min:0', 'max:99'],
            'away_score' => ['required', 'integer', 'min:0', 'max:99'],
        ];
    }

    public function messages(): array
    {
        return [
            'home_score.required' => 'Le score domicile est obligatoire.',
            'home_score.min' => 'Le score ne peut pas être négatif.',
            'home_score.max' => 'Le score ne peut pas dépasser 99.',
            'away_score.required' => 'Le score extérieur est obligatoire.',
            'away_score.min' => 'Le score ne peut pas être négatif.',
            'away_score.max' => 'Le score ne peut pas dépasser 99.',
        ];
    }
}
