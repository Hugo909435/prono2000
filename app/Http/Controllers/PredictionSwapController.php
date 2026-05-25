<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Prediction;
use App\Models\PredictionSwap;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PredictionSwapController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'tournament_id' => 'required|integer|exists:tournaments,id',
            'target_user_id' => 'required|integer|exists:users,id',
            'initiator_match_id' => 'required|integer|exists:matches,id',
            'target_match_id' => 'required|integer|exists:matches,id',
        ]);

        $user = $request->user();
        $tournament = Tournament::findOrFail($request->tournament_id);
        $initiatorMatch = Game::findOrFail($request->initiator_match_id);
        $targetMatch = Game::findOrFail($request->target_match_id);

        if (!$tournament->isMember($user)) {
            return response()->json(['success' => false, 'message' => 'Vous n\'êtes pas membre de ce tournoi.'], 403);
        }

        if ($request->target_user_id === $user->id) {
            return response()->json(['success' => false, 'message' => 'Vous ne pouvez pas échanger avec vous-même.'], 422);
        }

        $targetUser = User::findOrFail($request->target_user_id);
        if (!$tournament->isMember($targetUser)) {
            return response()->json(['success' => false, 'message' => 'Ce joueur n\'est pas membre du tournoi.'], 422);
        }

        if ($initiatorMatch->round !== 'group' || $targetMatch->round !== 'group') {
            return response()->json(['success' => false, 'message' => 'Les pronos échangés sont réservés à la phase de groupes.'], 422);
        }

        if ($initiatorMatch->tournament_id !== $tournament->id || $targetMatch->tournament_id !== $tournament->id) {
            return response()->json(['success' => false, 'message' => 'Ces matchs n\'appartiennent pas à ce tournoi.'], 422);
        }

        if (!$initiatorMatch->canSpecialProno()) {
            return response()->json(['success' => false, 'message' => 'Les pronos spéciaux ne sont disponibles que lorsque les pronostics sont fermés et le match pas encore commencé.'], 422);
        }

        if (!$targetMatch->canSpecialProno()) {
            return response()->json(['success' => false, 'message' => 'Les pronos spéciaux ne sont disponibles que lorsque les pronostics sont fermés et le match adverse pas encore commencé.'], 422);
        }

        // Vérifier que l'initiateur a bien un pronostic pour son match
        $initiatorPrediction = Prediction::where('user_id', $user->id)
            ->where('match_id', $initiatorMatch->id)
            ->first();

        if (!$initiatorPrediction) {
            return response()->json(['success' => false, 'message' => 'Vous devez d\'abord soumettre votre pronostic pour le match que vous donnez.'], 422);
        }

        // Quota : 1 seul échange par tournoi — vérifier qu'il n'y a pas d'échange sur un AUTRE adversaire
        $swapOnOtherTarget = PredictionSwap::where('initiator_user_id', $user->id)
            ->where('tournament_id', $tournament->id)
            ->where('target_user_id', '!=', $request->target_user_id)
            ->exists();
        if ($swapOnOtherTarget) {
            return response()->json(['success' => false, 'message' => 'Vous avez déjà utilisé votre échange avec un autre adversaire. Annulez-le d\'abord.'], 422);
        }

        // On ne peut pas échanger et bloquer en même temps avec le même adversaire pour ce match
        $conflictBlock = \App\Models\PredictionBlock::where('blocker_user_id', $user->id)
            ->where('target_user_id', $request->target_user_id)
            ->where('tournament_id', $tournament->id)
            ->where('target_match_id', $initiatorMatch->id)
            ->first();
        if ($conflictBlock) {
            return response()->json(['success' => false, 'message' => 'Vous avez déjà un bloc actif sur ce joueur pour ce match. Annulez-le d\'abord.'], 422);
        }

        // Un seul joueur peut échanger avec un adversaire donné pour un match donné
        $alreadySwappedByOther = PredictionSwap::where('target_user_id', $request->target_user_id)
            ->where('tournament_id', $tournament->id)
            ->where('initiator_match_id', $initiatorMatch->id)
            ->where('initiator_user_id', '!=', $user->id)
            ->exists();
        if ($alreadySwappedByOther) {
            return response()->json(['success' => false, 'message' => 'Ce joueur est déjà en échange avec un autre joueur pour ce match.'], 422);
        }

        // Si un échange existant cible des matchs déjà commencés, on ne peut pas le remplacer
        $existingSwap = PredictionSwap::getSwapByInitiator($user->id, $request->target_user_id, $tournament->id);
        if ($existingSwap) {
            $existingInitiatorMatch = Game::find($existingSwap->initiator_match_id);
            $existingTargetMatch = Game::find($existingSwap->target_match_id);
            $initiatorMatchLocked = $existingInitiatorMatch && !$existingInitiatorMatch->isScheduled();
            $targetMatchLocked = $existingTargetMatch && !$existingTargetMatch->isScheduled();

            if ($initiatorMatchLocked || $targetMatchLocked) {
                return response()->json([
                    'success' => false,
                    'message' => 'Votre échange actuel ne peut plus être modifié (un des matchs a commencé).',
                ], 422);
            }
            $existingSwap->delete();
        }

        $swap = PredictionSwap::create([
            'tournament_id' => $tournament->id,
            'initiator_user_id' => $user->id,
            'target_user_id' => $request->target_user_id,
            'initiator_match_id' => $initiatorMatch->id,
            'target_match_id' => $targetMatch->id,
        ]);

        return response()->json([
            'success' => true,
            'swap_id' => $swap->id,
            'message' => 'Échange enregistré !',
        ]);
    }

    public function destroy(int $swapId): JsonResponse
    {
        $swap = PredictionSwap::findOrFail($swapId);

        if ($swap->initiator_user_id !== auth()->id()) {
            abort(403);
        }

        $initiatorMatch = Game::find($swap->initiator_match_id);
        $targetMatch = Game::find($swap->target_match_id);

        if (($initiatorMatch && !$initiatorMatch->isScheduled()) || ($targetMatch && !$targetMatch->isScheduled())) {
            return response()->json(['success' => false, 'message' => 'L\'échange ne peut plus être annulé (un des matchs a commencé).'], 422);
        }

        $swap->delete();

        return response()->json(['success' => true, 'message' => 'Échange annulé.']);
    }
}
