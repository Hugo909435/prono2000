<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TeamFlag from '@/Components/TeamFlag.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

const props = defineProps({
    tournament: Object,
    members: Array,
    winnerPredictions: Object,
    loserPredictions: Object,
    topScorerPredictions: Object,
    lastPlacePredictions: Object,
    predictionsLocked: Boolean,
});

const currentUserId = usePage().props.auth.user.id;

const rankColors = [
    { bg: 'bg-amber-50 border-amber-200', badge: 'bg-amber-200 text-amber-700' },
    { bg: 'bg-slate-50 border-slate-200', badge: 'bg-slate-200 text-slate-600' },
    { bg: 'bg-orange-50 border-orange-200', badge: 'bg-orange-200 text-orange-700' },
];
const winnerPoints = [30, 20, 10];
const choiceKeys = ['first_choice_team', 'second_choice_team', 'third_choice_team'];
const choiceIdKeys = ['first_choice_team_id', 'second_choice_team_id', 'third_choice_team_id'];

const getWinner = (id) => props.winnerPredictions[id] || null;
const getLoser = (id) => props.loserPredictions[id] || null;
const getTopScorer = (id) => props.topScorerPredictions[id] || null;
const getLastPlace = (id) => props.lastPlacePredictions[id] || null;

const totalBonusPoints = (id) => {
    return (getWinner(id)?.points_earned ?? 0)
         + (getLoser(id)?.points_earned ?? 0)
         + (getTopScorer(id)?.points_earned ?? 0)
         + (getLastPlace(id)?.points_earned ?? 0);
};

const isTopScorerCorrect = (prediction) => {
    if (!props.tournament.top_scorer_name || !prediction?.player_name) return false;
    return prediction.player_name.toLowerCase().trim() === props.tournament.top_scorer_name.toLowerCase().trim();
};
</script>

<template>
    <Head :title="`Pronos Bonus - ${tournament.name}`" />

    <AuthenticatedLayout>
        <div class="py-4 sm:py-8">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 space-y-4">

                <!-- Header -->
                <div class="bg-white rounded-2xl shadow-sm p-4 sm:p-6">
                    <Link
                        :href="route('tournaments.show', tournament.id)"
                        class="text-sm text-indigo-600 hover:text-indigo-800 flex items-center gap-1 mb-3"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                        Retour au tournoi
                    </Link>
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-xl font-bold text-gray-900">Pronos Bonus</h1>
                            <p class="text-sm text-gray-500 mt-0.5">{{ tournament.name }}</p>
                        </div>
                        <div class="text-right">
                            <div class="text-2xl font-bold text-indigo-600">{{ members.length }}</div>
                            <div class="text-xs text-gray-500">joueurs</div>
                        </div>
                    </div>

                    <!-- Avertissement si non verrouillé -->
                    <div v-if="!predictionsLocked" class="mt-4 p-3 bg-amber-50 border border-amber-200 rounded-xl text-sm text-amber-700 flex items-center gap-2">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        Pronostics non verrouillés — seul le vôtre est visible pour l'instant.
                    </div>

                    <!-- Résultats officiels -->
                    <div v-if="tournament.winner_team || tournament.loser_team || tournament.top_scorer_name || tournament.last_place_user" class="mt-4 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-2">
                        <div v-if="tournament.winner_team" class="flex items-center gap-2 p-2.5 bg-amber-50 border border-amber-200 rounded-xl">
                            <span class="text-lg">🏆</span>
                            <div class="min-w-0">
                                <div class="text-[10px] text-amber-600 font-medium uppercase tracking-wide">Champion</div>
                                <div class="flex items-center gap-1">
                                    <TeamFlag :flag="tournament.winner_team.flag" size="sm" />
                                    <span class="text-sm font-bold text-gray-900 truncate">{{ tournament.winner_team.name }}</span>
                                </div>
                            </div>
                        </div>
                        <div v-if="tournament.loser_team" class="flex items-center gap-2 p-2.5 bg-gray-50 border border-gray-200 rounded-xl">
                            <span class="text-lg">💀</span>
                            <div class="min-w-0">
                                <div class="text-[10px] text-gray-500 font-medium uppercase tracking-wide">Gros Loser</div>
                                <div class="flex items-center gap-1">
                                    <TeamFlag :flag="tournament.loser_team.flag" size="sm" />
                                    <span class="text-sm font-bold text-gray-900 truncate">{{ tournament.loser_team.name }}</span>
                                </div>
                            </div>
                        </div>
                        <div v-if="tournament.top_scorer_name" class="flex items-center gap-2 p-2.5 bg-blue-50 border border-blue-200 rounded-xl">
                            <span class="text-lg">⚽</span>
                            <div class="min-w-0">
                                <div class="text-[10px] text-blue-600 font-medium uppercase tracking-wide">Star</div>
                                <span class="text-sm font-bold text-gray-900 truncate block">{{ tournament.top_scorer_name }}</span>
                            </div>
                        </div>
                        <div v-if="tournament.last_place_user" class="flex items-center gap-2 p-2.5 bg-red-50 border border-red-200 rounded-xl">
                            <span class="text-lg">🪦</span>
                            <div class="min-w-0">
                                <div class="text-[10px] text-red-600 font-medium uppercase tracking-wide">Vrai Gros Loser</div>
                                <span class="text-sm font-bold text-gray-900 truncate block">{{ tournament.last_place_user.name }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Carte par membre -->
                <div
                    v-for="member in members"
                    :key="member.id"
                    :class="['bg-white rounded-2xl shadow-sm overflow-hidden', member.id === currentUserId ? 'ring-2 ring-indigo-400' : '']"
                >
                    <!-- Nom + total bonus -->
                    <div class="px-4 py-3 bg-gray-50 border-b flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full overflow-hidden flex-shrink-0 shadow-inner">
                                <img v-if="member.avatar_url" :src="member.avatar_url" class="w-full h-full object-cover" />
                                <div v-else class="w-full h-full bg-gray-200 flex items-center justify-center text-gray-500 font-bold text-xs">
                                    {{ member.name?.charAt(0)?.toUpperCase() }}
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="font-semibold text-gray-800">{{ member.name }}</span>
                                <span v-if="member.id === currentUserId" class="text-xs text-indigo-600 font-medium">(vous)</span>
                            </div>
                        </div>
                        <span
                            v-if="predictionsLocked && (tournament.winner_team_id || tournament.loser_team_id || tournament.top_scorer_name || tournament.last_place_user_id)"
                            :class="['text-sm font-bold px-2 py-0.5 rounded-full', totalBonusPoints(member.id) > 0 ? 'bg-emerald-50 text-emerald-600' : 'bg-gray-100 text-gray-400']"
                        >
                            +{{ totalBonusPoints(member.id) }} pts bonus
                        </span>
                    </div>

                    <!-- Contenu caché si non verrouillé et pas l'utilisateur courant -->
                    <div v-if="!predictionsLocked && member.id !== currentUserId" class="p-4 flex items-center gap-2 text-gray-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        <span class="text-sm">Caché jusqu'au verrouillage</span>
                    </div>

                    <div v-else class="p-4 space-y-4">

                        <!-- Champion du Monde -->
                        <div>
                            <div class="flex items-center gap-1.5 mb-2">
                                <span class="text-sm">🏆</span>
                                <span class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Champion du Monde</span>
                            </div>
                            <div v-if="getWinner(member.id)" class="space-y-1.5">
                                <div
                                    v-for="(c, i) in rankColors"
                                    :key="i"
                                    :class="['flex items-center justify-between p-2.5 rounded-xl border', tournament.winner_team_id && getWinner(member.id)[choiceIdKeys[i]] === tournament.winner_team_id ? 'bg-emerald-50 border-emerald-300' : c.bg]"
                                >
                                    <div class="flex items-center gap-2">
                                        <span :class="['w-6 h-6 rounded-full flex items-center justify-center font-bold text-xs flex-shrink-0', c.badge]">{{ i + 1 }}</span>
                                        <TeamFlag :flag="getWinner(member.id)[choiceKeys[i]]?.flag" size="sm" />
                                        <span class="text-sm font-medium text-gray-800">
                                            {{ getWinner(member.id)[choiceKeys[i]]?.name ?? '—' }}
                                        </span>
                                    </div>
                                    <span
                                        v-if="tournament.winner_team_id && getWinner(member.id)[choiceIdKeys[i]] === tournament.winner_team_id"
                                        class="text-xs font-bold text-emerald-600"
                                    >+{{ winnerPoints[i] }} pts ✓</span>
                                    <span v-else class="text-xs text-gray-400">+{{ winnerPoints[i] }} pts</span>
                                </div>
                            </div>
                            <div v-else class="text-sm text-gray-400 text-center py-2">Pas de pronostic</div>
                        </div>

                        <div class="border-t border-gray-100"></div>

                        <!-- Gros Loser -->
                        <div>
                            <div class="flex items-center gap-1.5 mb-2">
                                <span class="text-sm">💀</span>
                                <span class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Le Gros Loser</span>
                            </div>
                            <div v-if="getLoser(member.id)">
                                <div :class="['flex items-center justify-between p-2.5 rounded-xl border', tournament.loser_team_id && getLoser(member.id).team_id === tournament.loser_team_id ? 'bg-emerald-50 border-emerald-300' : 'bg-gray-50 border-gray-200']">
                                    <div class="flex items-center gap-2">
                                        <TeamFlag :flag="getLoser(member.id).team?.flag" size="sm" />
                                        <span class="text-sm font-medium text-gray-800">{{ getLoser(member.id).team?.name }}</span>
                                    </div>
                                    <span v-if="tournament.loser_team_id && getLoser(member.id).team_id === tournament.loser_team_id" class="text-xs font-bold text-emerald-600">+15 pts ✓</span>
                                    <span v-else class="text-xs text-gray-400">+15 pts</span>
                                </div>
                            </div>
                            <div v-else class="text-sm text-gray-400 text-center py-2">Pas de pronostic</div>
                        </div>

                        <div class="border-t border-gray-100"></div>

                        <!-- Star de la compétition -->
                        <div>
                            <div class="flex items-center gap-1.5 mb-2">
                                <span class="text-sm">⚽</span>
                                <span class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Star de la Compétition</span>
                            </div>
                            <div v-if="getTopScorer(member.id)">
                                <div :class="['flex items-center justify-between p-2.5 rounded-xl border', isTopScorerCorrect(getTopScorer(member.id)) ? 'bg-emerald-50 border-emerald-300' : 'bg-blue-50 border-blue-200']">
                                    <span class="text-sm font-medium text-gray-800">{{ getTopScorer(member.id).player_name }}</span>
                                    <span v-if="isTopScorerCorrect(getTopScorer(member.id))" class="text-xs font-bold text-emerald-600">+15 pts ✓</span>
                                    <span v-else class="text-xs text-gray-400">+15 pts</span>
                                </div>
                            </div>
                            <div v-else class="text-sm text-gray-400 text-center py-2">Pas de pronostic</div>
                        </div>

                        <div class="border-t border-gray-100"></div>

                        <!-- Vrai Gros Loser -->
                        <div>
                            <div class="flex items-center gap-1.5 mb-2">
                                <span class="text-sm">🪦</span>
                                <span class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Le Vrai Gros Loser</span>
                            </div>
                            <div v-if="getLastPlace(member.id)">
                                <div :class="['flex items-center justify-between p-2.5 rounded-xl border', tournament.last_place_user_id && getLastPlace(member.id).predicted_user_id === tournament.last_place_user_id ? 'bg-emerald-50 border-emerald-300' : 'bg-red-50 border-red-200']">
                                    <div class="flex items-center gap-2">
                                        <div class="w-6 h-6 rounded-full overflow-hidden flex-shrink-0 shadow-inner">
                                            <img v-if="getLastPlace(member.id).predicted_user?.avatar_url" :src="getLastPlace(member.id).predicted_user.avatar_url" class="w-full h-full object-cover" />
                                            <div v-else class="w-full h-full bg-red-200 flex items-center justify-center text-red-700 font-bold text-[10px]">
                                                {{ getLastPlace(member.id).predicted_user?.name?.charAt(0).toUpperCase() }}
                                            </div>
                                        </div>
                                        <span class="text-sm font-medium text-gray-800">{{ getLastPlace(member.id).predicted_user?.name }}</span>
                                    </div>
                                    <span v-if="tournament.last_place_user_id && getLastPlace(member.id).predicted_user_id === tournament.last_place_user_id" class="text-xs font-bold text-emerald-600">+15 pts ✓</span>
                                    <span v-else class="text-xs text-gray-400">+15 pts</span>
                                </div>
                            </div>
                            <div v-else class="text-sm text-gray-400 text-center py-2">Pas de pronostic</div>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
