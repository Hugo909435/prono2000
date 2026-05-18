<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\PointBooster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PointBoosterController extends Controller
{
    public function toggle(Request $request, int $match): JsonResponse
    {
        $game = Game::findOrFail($match);
        $user = $request->user();

        $existingBooster = PointBooster::where('user_id', $user->id)
            ->where('match_id', $game->id)
            ->first();

        if ($existingBooster) {
            $existingBooster->delete();

            return response()->json([
                'success' => true,
                'active' => false,
                'remaining' => PointBooster::getRemainingBoostersCount($user->id, $game->tournament_id),
                'message' => 'Doubleur de points désactivé.',
            ]);
        }

        if ($game->status !== 'scheduled') {
            return response()->json([
                'success' => false,
                'message' => 'Le match a déjà commencé.',
            ], 422);
        }

        if ($game->scheduled_at && $game->scheduled_at->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'Le match a déjà commencé.',
            ], 422);
        }

        $remaining = PointBooster::getRemainingBoostersCount($user->id, $game->tournament_id);

        if ($remaining <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Vous avez utilisé tous vos doubleurs de points pour ce tournoi.',
            ], 422);
        }

        PointBooster::create([
            'user_id' => $user->id,
            'tournament_id' => $game->tournament_id,
            'match_id' => $game->id,
            'activated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'active' => true,
            'remaining' => $remaining - 1,
            'message' => 'Doubleur de points activé !',
        ]);
    }

    public function status(Request $request, int $tournamentId): JsonResponse
    {
        $user = $request->user();

        $boosters = PointBooster::where('user_id', $user->id)
            ->where('tournament_id', $tournamentId)
            ->with('game')
            ->get();

        return response()->json([
            'remaining' => PointBooster::getRemainingBoostersCount($user->id, $tournamentId),
            'max' => PointBooster::MAX_BOOSTERS_PER_TOURNAMENT,
            'boosters' => $boosters,
        ]);
    }
}
