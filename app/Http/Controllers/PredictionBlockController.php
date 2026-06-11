<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\PredictionBlock;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PredictionBlockController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'tournament_id' => 'required|integer|exists:tournaments,id',
            'target_user_id' => 'required|integer|exists:users,id',
            'match_id' => 'required|integer|exists:matches,id',
        ]);

        $user = $request->user();
        $tournament = Tournament::findOrFail($request->tournament_id);
        $match = Game::findOrFail($request->match_id);

        if (!$tournament->isMember($user)) {
            return response()->json(['success' => false, 'message' => 'Vous n\'êtes pas membre de ce tournoi.'], 403);
        }

        if ($request->target_user_id === $user->id) {
            return response()->json(['success' => false, 'message' => 'Vous ne pouvez pas bloquer votre propre pronostic.'], 422);
        }

        $targetUser = User::findOrFail($request->target_user_id);
        if (!$tournament->isMember($targetUser)) {
            return response()->json(['success' => false, 'message' => 'Ce joueur n\'est pas membre du tournoi.'], 422);
        }

        if ($match->round !== 'group') {
            return response()->json(['success' => false, 'message' => 'Le prono bloqué est réservé à la phase de groupes.'], 422);
        }

        if ($match->tournament_id !== $tournament->id) {
            return response()->json(['success' => false, 'message' => 'Ce match n\'appartient pas à ce tournoi.'], 422);
        }

        if (!$match->canSpecialProno()) {
            return response()->json(['success' => false, 'message' => 'Les pronos spéciaux ne sont disponibles que lorsque les pronostics sont fermés et le match pas encore commencé.'], 422);
        }

        // Quota : 1 seul bloc par tournoi — vérifier qu'il n'y a pas de bloc sur un AUTRE adversaire
        $blockOnOtherTarget = PredictionBlock::where('blocker_user_id', $user->id)
            ->where('tournament_id', $tournament->id)
            ->where('target_user_id', '!=', $request->target_user_id)
            ->exists();
        if ($blockOnOtherTarget) {
            return response()->json(['success' => false, 'message' => 'Vous avez déjà utilisé votre bloc sur un autre adversaire. Annulez-le d\'abord.'], 422);
        }

        // On ne peut pas bloquer et échanger en même temps avec le même adversaire pour ce match
        $conflictSwap = \App\Models\PredictionSwap::where('initiator_user_id', $user->id)
            ->where('target_user_id', $request->target_user_id)
            ->where('tournament_id', $tournament->id)
            ->where('initiator_match_id', $match->id)
            ->first();
        if ($conflictSwap) {
            return response()->json(['success' => false, 'message' => 'Vous avez déjà un échange actif avec ce joueur pour ce match. Annulez-le d\'abord.'], 422);
        }

        // Un seul joueur peut bloquer un adversaire donné pour un match donné
        $alreadyBlockedByOther = PredictionBlock::where('target_user_id', $request->target_user_id)
            ->where('target_match_id', $match->id)
            ->where('blocker_user_id', '!=', $user->id)
            ->exists();
        if ($alreadyBlockedByOther) {
            return response()->json(['success' => false, 'message' => 'Ce joueur est déjà bloqué par un autre joueur pour ce match.'], 422);
        }

        // Si un bloc existant cible un match déjà commencé, on ne peut pas le remplacer
        $existingBlock = PredictionBlock::getBlockByBlocker($user->id, $request->target_user_id, $tournament->id);
        if ($existingBlock) {
            $existingMatch = Game::find($existingBlock->target_match_id);
            if ($existingMatch && $existingMatch->hasStarted()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Votre bloc actuel ne peut plus être modifié (le match a commencé).',
                ], 422);
            }
            $existingBlock->delete();
        }

        $block = PredictionBlock::create([
            'tournament_id' => $tournament->id,
            'blocker_user_id' => $user->id,
            'target_user_id' => $request->target_user_id,
            'target_match_id' => $match->id,
        ]);

        return response()->json([
            'success' => true,
            'block_id' => $block->id,
            'message' => 'Prono bloqué !',
        ]);
    }

    public function destroy(int $blockId): JsonResponse
    {
        $block = PredictionBlock::findOrFail($blockId);

        if ($block->blocker_user_id !== auth()->id()) {
            abort(403);
        }

        $match = Game::find($block->target_match_id);
        if ($match && $match->hasStarted()) {
            return response()->json(['success' => false, 'message' => 'Le match a déjà commencé, le bloc ne peut plus être annulé.'], 422);
        }

        $block->delete();

        return response()->json(['success' => true, 'message' => 'Bloc annulé.']);
    }
}
