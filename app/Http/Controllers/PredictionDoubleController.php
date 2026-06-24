<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Prediction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PredictionDoubleController extends Controller
{
    public function toggle(Request $request, int $matchId): JsonResponse
    {
        $game = Game::with('tournament')->findOrFail($matchId);
        $user = $request->user();

        if (!$game->tournament->isMember($user)) {
            return response()->json(['success' => false, 'message' => 'Vous n\'êtes pas membre de ce tournoi.'], 403);
        }

        if ($game->tournament->bonuses_locked) {
            return response()->json(['success' => false, 'message' => 'Les bonus ont été désactivés pour ce tournoi.'], 422);
        }

        if ($game->round !== 'group') {
            return response()->json(['success' => false, 'message' => 'Le prono doublé est réservé à la phase de groupes.'], 422);
        }

        if (!$game->canSpecialProno()) {
            return response()->json(['success' => false, 'message' => 'Les pronos spéciaux ne sont disponibles que lorsque les pronostics sont fermés et le match pas encore commencé.'], 422);
        }

        $prediction = Prediction::where('user_id', $user->id)
            ->where('match_id', $game->id)
            ->first();

        if (!$prediction) {
            return response()->json(['success' => false, 'message' => 'Soumettez d\'abord votre pronostic pour ce match.'], 422);
        }

        $tournamentId = $game->tournament_id;

        if ($prediction->is_doubled) {
            $prediction->update(['is_doubled' => false]);
            $remaining = Prediction::MAX_DOUBLED_PER_TOURNAMENT - Prediction::getDoubledCount($user->id, $tournamentId);

            return response()->json([
                'success' => true,
                'active' => false,
                'remaining' => $remaining,
                'message' => 'Prono doublé désactivé.',
            ]);
        }

        $usedCount = Prediction::getDoubledCount($user->id, $tournamentId);

        if ($usedCount >= Prediction::MAX_DOUBLED_PER_TOURNAMENT) {
            return response()->json([
                'success' => false,
                'message' => 'Vous avez utilisé vos 3 pronos doublés pour ce tournoi.',
            ], 422);
        }

        $prediction->update(['is_doubled' => true]);

        return response()->json([
            'success' => true,
            'active' => true,
            'remaining' => Prediction::MAX_DOUBLED_PER_TOURNAMENT - $usedCount - 1,
            'message' => 'Prono doublé activé !',
        ]);
    }

    public function stats(Request $request, int $tournamentId): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'used' => Prediction::getDoubledCount($user->id, $tournamentId),
            'max' => Prediction::MAX_DOUBLED_PER_TOURNAMENT,
            'remaining' => Prediction::MAX_DOUBLED_PER_TOURNAMENT - Prediction::getDoubledCount($user->id, $tournamentId),
        ]);
    }
}
