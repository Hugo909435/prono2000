<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TeamFlag from '@/Components/TeamFlag.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    tournamentsData: Array,
});

// Index du tournoi sélectionné
const currentIndex = ref(0);

const current = computed(() => props.tournamentsData[currentIndex.value] ?? null);

const prevTournament = () => { if (currentIndex.value > 0) currentIndex.value--; };
const nextTournament = () => { if (currentIndex.value < props.tournamentsData.length - 1) currentIndex.value++; };

// Onglet poule actif (remis à zéro quand on change de tournoi)
const activeGroup = computed({
    get: () => _activeGroup.value ?? (current.value ? Object.keys(current.value.matchesByGroup)[0] || 'knockout' : null),
    set: (v) => { _activeGroup.value = v; },
});
const _activeGroup = ref(null);

const selectGroup = (g) => { _activeGroup.value = g; };

// Quand on change de tournoi, reset l'onglet
const goTo = (idx) => {
    currentIndex.value = idx;
    _activeGroup.value = null;
};

// Pronostic d'un membre pour un match
const getMemberPrediction = (matchId, memberId) => {
    if (!current.value) return null;
    const predictions = current.value.allPredictions[matchId] || [];
    return predictions.find(p => p.user_id === memberId);
};

const hasBoosted = (matchId, memberId) => {
    if (!current.value) return false;
    return !!(current.value.allBoosters?.[matchId]?.[memberId]);
};

// Couleurs selon le résultat
const resultTypeColor = (type) => {
    const colors = { exact: 'bg-emerald-500 text-white', correct_winner: 'bg-amber-500 text-white', wrong: 'bg-red-500 text-white' };
    return colors[type] || 'bg-gray-200 text-gray-600';
};
const resultTypeBg = (type) => {
    const colors = { exact: 'bg-emerald-50', correct_winner: 'bg-amber-50', wrong: 'bg-red-50' };
    return colors[type] || 'bg-gray-50';
};

// Labels des rounds
const roundLabels = { round_of_32: '32èmes', round_of_16: '8èmes', quarter: 'Quarts', semi: 'Demis', final: 'Finale' };

// Grouper les matchs éliminatoires par round
const knockoutByRound = computed(() => {
    if (!current.value) return {};
    const rounds = {};
    current.value.knockoutMatches.forEach(match => {
        if (!rounds[match.round]) rounds[match.round] = [];
        rounds[match.round].push(match);
    });
    return rounds;
});

const formatDate = (d) => {
    if (!d) return '';
    return new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
};
</script>

<template>
    <Head title="Pronostics des joueurs" />

    <AuthenticatedLayout>
        <div class="py-4 sm:py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

                <!-- Aucun tournoi -->
                <div v-if="tournamentsData.length === 0" class="bg-white rounded-2xl shadow-sm p-10 text-center">
                    <div class="text-4xl mb-3">🏆</div>
                    <p class="text-gray-500 text-sm">Tu ne participes à aucun tournoi pour l'instant.</p>
                    <Link :href="route('tournaments.index')" class="mt-4 inline-block text-sm font-medium text-indigo-600 hover:text-indigo-800">
                        Rejoindre un tournoi →
                    </Link>
                </div>

                <template v-else-if="current">
                    <!-- Sélecteur de tournoi (style Dashboard) -->
                    <div class="bg-white rounded-2xl shadow-sm overflow-hidden mb-6">
                        <div class="flex items-center justify-between p-3">
                            <button
                                @click="prevTournament"
                                :disabled="currentIndex === 0"
                                :class="[
                                    'w-10 h-10 rounded-xl flex items-center justify-center transition',
                                    currentIndex === 0 ? 'text-gray-300' : 'text-gray-600 hover:bg-gray-100 active:bg-gray-200'
                                ]"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                                </svg>
                            </button>

                            <div class="text-center flex-1">
                                <h1 class="text-base font-bold text-gray-900">{{ current.tournament.name }}</h1>
                                <p class="text-xs text-gray-500">{{ current.members.length }} joueur{{ current.members.length > 1 ? 's' : '' }}</p>
                            </div>

                            <button
                                @click="nextTournament"
                                :disabled="currentIndex === tournamentsData.length - 1"
                                :class="[
                                    'w-10 h-10 rounded-xl flex items-center justify-center transition',
                                    currentIndex === tournamentsData.length - 1 ? 'text-gray-300' : 'text-gray-600 hover:bg-gray-100 active:bg-gray-200'
                                ]"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                        </div>

                        <!-- Points de navigation -->
                        <div v-if="tournamentsData.length > 1" class="flex justify-center gap-1.5 pb-3">
                            <button
                                v-for="(_, idx) in tournamentsData"
                                :key="idx"
                                @click="goTo(idx)"
                                :class="['w-2 h-2 rounded-full transition', idx === currentIndex ? 'bg-indigo-600' : 'bg-gray-300']"
                            />
                        </div>
                    </div>

                    <!-- Avertissement pronostics ouverts -->
                    <div v-if="current.predictionsOpen" class="mb-6 p-3 bg-amber-50 border border-amber-200 rounded-xl">
                        <div class="flex items-center gap-2 text-amber-700">
                            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <div>
                                <p class="font-medium text-sm">Pronostics encore ouverts</p>
                                <p class="text-xs">Seuls vos pronostics sont visibles pour l'instant.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Légende -->
                    <div class="bg-white rounded-2xl shadow-sm p-4 mb-6 flex flex-wrap gap-4 text-xs">
                        <div class="flex items-center gap-2">
                            <span class="w-6 h-6 rounded bg-emerald-500 text-white flex items-center justify-center font-bold text-xs">3</span>
                            <span class="text-gray-600">Score exact (+3 pts)</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="w-6 h-6 rounded bg-amber-500 text-white flex items-center justify-center font-bold text-xs">1</span>
                            <span class="text-gray-600">Bon vainqueur (+1 pt)</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="w-6 h-6 rounded bg-red-500 text-white flex items-center justify-center font-bold text-xs">0</span>
                            <span class="text-gray-600">Incorrect</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="w-6 h-6 rounded bg-gray-200 text-gray-600 flex items-center justify-center font-bold text-xs">-</span>
                            <span class="text-gray-600">Pas de prono</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-amber-500 font-semibold text-sm">⚡ x2</span>
                            <span class="text-gray-600">Points doublés</span>
                        </div>
                    </div>

                    <!-- Navigation par poule -->
                    <div class="bg-white rounded-2xl shadow-sm mb-6 overflow-hidden">
                        <div class="overflow-x-auto">
                            <nav class="flex border-b border-gray-200">
                                <button
                                    v-for="(matches, groupName) in current.matchesByGroup"
                                    :key="groupName"
                                    @click="selectGroup(groupName)"
                                    :class="[
                                        'px-4 py-3 text-sm font-medium border-b-2 transition whitespace-nowrap',
                                        activeGroup === groupName
                                            ? 'border-indigo-500 text-indigo-600 bg-indigo-50'
                                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50'
                                    ]"
                                >
                                    {{ groupName }}
                                    <span class="ml-1 text-xs text-gray-400">({{ matches.length }})</span>
                                </button>
                                <button
                                    v-if="current.knockoutMatches.length > 0"
                                    @click="selectGroup('knockout')"
                                    :class="[
                                        'px-4 py-3 text-sm font-medium border-b-2 transition whitespace-nowrap',
                                        activeGroup === 'knockout'
                                            ? 'border-amber-500 text-amber-600 bg-amber-50'
                                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50'
                                    ]"
                                >
                                    Phases finales
                                    <span class="ml-1 text-xs text-gray-400">({{ current.knockoutMatches.length }})</span>
                                </button>
                            </nav>
                        </div>
                    </div>

                    <!-- Matchs de poule -->
                    <div v-if="activeGroup !== 'knockout' && current.matchesByGroup[activeGroup]" class="space-y-4">
                        <div
                            v-for="match in current.matchesByGroup[activeGroup]"
                            :key="match.id"
                            class="bg-white rounded-2xl shadow-sm overflow-hidden"
                        >
                            <div class="p-4 bg-gradient-to-r from-indigo-500 to-indigo-600">
                                <div class="flex items-center justify-between text-white">
                                    <div class="flex items-center gap-3">
                                        <TeamFlag :flag="match.home_team?.flag" size="lg" />
                                        <span class="font-semibold">{{ match.home_team?.name || 'TBD' }}</span>
                                    </div>
                                    <div class="text-center">
                                        <div v-if="match.status === 'completed'" class="bg-white/20 px-4 py-1 rounded-lg">
                                            <span class="font-bold text-xl">{{ match.home_score }} - {{ match.away_score }}</span>
                                        </div>
                                        <div v-else class="text-white/80 text-sm">
                                            {{ formatDate(match.scheduled_at) || 'VS' }}
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span class="font-semibold">{{ match.away_team?.name || 'TBD' }}</span>
                                        <TeamFlag :flag="match.away_team?.flag" size="lg" />
                                    </div>
                                </div>
                            </div>

                            <div class="p-4">
                                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-2">
                                    <div
                                        v-for="member in current.members"
                                        :key="member.id"
                                        :class="[
                                            'p-2 rounded-xl border text-center',
                                            member.id === $page.props.auth.user.id
                                                ? 'border-indigo-300 bg-indigo-50'
                                                : getMemberPrediction(match.id, member.id) && match.status === 'completed'
                                                    ? resultTypeBg(getMemberPrediction(match.id, member.id).result_type)
                                                    : 'border-gray-200 bg-gray-50'
                                        ]"
                                    >
                                        <div class="text-xs font-medium text-gray-700 truncate mb-1">{{ member.name }}</div>
                                        <div v-if="current.predictionsOpen && member.id !== $page.props.auth.user.id" class="text-sm text-gray-400 py-1">
                                            <svg class="w-4 h-4 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                            </svg>
                                        </div>
                                        <div v-else-if="getMemberPrediction(match.id, member.id)">
                                            <div
                                                :class="[
                                                    'text-sm font-bold px-2 py-1 rounded',
                                                    match.status === 'completed'
                                                        ? resultTypeColor(getMemberPrediction(match.id, member.id).result_type)
                                                        : 'bg-indigo-100 text-indigo-700'
                                                ]"
                                            >
                                                {{ getMemberPrediction(match.id, member.id).home_score }}-{{ getMemberPrediction(match.id, member.id).away_score }}
                                            </div>
                                            <div v-if="hasBoosted(match.id, member.id)" class="text-xs text-amber-500 font-semibold mt-0.5">⚡ x2</div>
                                        </div>
                                        <div v-else class="text-sm text-gray-400 py-1">-</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Matchs éliminatoires -->
                    <div v-if="activeGroup === 'knockout'" class="space-y-6">
                        <div v-for="(matches, round) in knockoutByRound" :key="round">
                            <h3 class="text-lg font-semibold text-gray-800 mb-3 flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                                {{ roundLabels[round] || round }}
                            </h3>
                            <div class="space-y-4">
                                <div
                                    v-for="match in matches"
                                    :key="match.id"
                                    class="bg-white rounded-2xl shadow-sm overflow-hidden"
                                >
                                    <div class="p-4 bg-gradient-to-r from-amber-500 to-orange-500">
                                        <div class="flex items-center justify-between text-white">
                                            <div class="flex items-center gap-3">
                                                <TeamFlag :flag="match.home_team?.flag" size="lg" />
                                                <span class="font-semibold">{{ match.home_team?.name || match.placeholder_home || 'TBD' }}</span>
                                            </div>
                                            <div class="text-center">
                                                <div v-if="match.status === 'completed'" class="bg-white/20 px-4 py-1 rounded-lg">
                                                    <span class="font-bold text-xl">{{ match.home_score }} - {{ match.away_score }}</span>
                                                </div>
                                                <div v-else class="text-white/80 text-sm">{{ formatDate(match.scheduled_at) || 'VS' }}</div>
                                            </div>
                                            <div class="flex items-center gap-3">
                                                <span class="font-semibold">{{ match.away_team?.name || match.placeholder_away || 'TBD' }}</span>
                                                <TeamFlag :flag="match.away_team?.flag" size="lg" />
                                            </div>
                                        </div>
                                    </div>
                                    <div class="p-4">
                                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-2">
                                            <div
                                                v-for="member in current.members"
                                                :key="member.id"
                                                :class="[
                                                    'p-2 rounded-xl border text-center',
                                                    member.id === $page.props.auth.user.id
                                                        ? 'border-indigo-300 bg-indigo-50'
                                                        : getMemberPrediction(match.id, member.id) && match.status === 'completed'
                                                            ? resultTypeBg(getMemberPrediction(match.id, member.id).result_type)
                                                            : 'border-gray-200 bg-gray-50'
                                                ]"
                                            >
                                                <div class="text-xs font-medium text-gray-700 truncate mb-1">{{ member.name }}</div>
                                                <div v-if="current.predictionsOpen && member.id !== $page.props.auth.user.id" class="text-sm text-gray-400 py-1">
                                                    <svg class="w-4 h-4 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                                    </svg>
                                                </div>
                                                <div
                                                    v-else-if="getMemberPrediction(match.id, member.id)"
                                                    :class="[
                                                        'text-sm font-bold px-2 py-1 rounded',
                                                        match.status === 'completed'
                                                            ? resultTypeColor(getMemberPrediction(match.id, member.id).result_type)
                                                            : 'bg-indigo-100 text-indigo-700'
                                                    ]"
                                                >
                                                    {{ getMemberPrediction(match.id, member.id).home_score }}-{{ getMemberPrediction(match.id, member.id).away_score }}
                                                </div>
                                                <div v-else class="text-sm text-gray-400 py-1">-</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Aucun match -->
                    <div v-if="Object.keys(current.matchesByGroup).length === 0 && current.knockoutMatches.length === 0" class="bg-white rounded-2xl shadow-sm p-8 text-center">
                        <p class="text-gray-500">Aucun match disponible pour ce tournoi.</p>
                    </div>
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
