<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TeamFlag from '@/Components/TeamFlag.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';

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

const hasDoubled = (matchId, memberId) => {
    if (!current.value) return false;
    return !!(current.value.allDoubles?.[matchId]?.[memberId]);
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

// ── Pronos Spéciaux ──────────────────────────────────────────────────────────

const page = usePage();
const csrf = () => document.querySelector('meta[name="csrf-token"]').getAttribute('content');

const localDoubleActive = ref({});
const localDoubleStats = ref({});
const localBlocks = ref({});
const localSwaps = ref({});
const takenBlocks = ref({});
const takenSwaps = ref({});
const localAllSwaps = ref([]);
const localAllBlocks = ref([]);
const doubleLoading = ref({});
const blockLoading = ref({});
const swapLoading = ref({});

// Resynchronise l'état local quand le tournoi actif change
watch(current, (t) => {
    if (!t) return;
    const userId = page.props.auth.user.id;
    const map = {};
    Object.entries(t.allPredictions || {}).forEach(([matchId, preds]) => {
        const myPred = preds.find(p => p.user_id === userId);
        if (myPred) map[matchId] = myPred.is_doubled;
    });
    localDoubleActive.value = map;
    localDoubleStats.value = { ...(t.doubleStats || {}) };
    localBlocks.value = JSON.parse(JSON.stringify(t.myBlocks || {}));
    localSwaps.value = JSON.parse(JSON.stringify(t.mySwaps || {}));
    takenBlocks.value = { ...(t.takenBlocks || {}) };
    takenSwaps.value = { ...(t.takenSwaps || {}) };
    localAllSwaps.value = JSON.parse(JSON.stringify(t.allSwaps || []));
    localAllBlocks.value = JSON.parse(JSON.stringify(t.allBlocks || []));
}, { immediate: true });

// Échange actif pour un membre sur un match
const getSwapForMember = (matchId, memberId) =>
    localAllSwaps.value.find(s =>
        (s.initiator_user_id === memberId && s.initiator_match_id === matchId) ||
        (s.target_user_id === memberId && s.target_match_id === matchId)
    ) || null;

// Bloc actif sur un membre pour un match
const getBlockForMember = (matchId, memberId) =>
    localAllBlocks.value.find(b => b.target_user_id === memberId && b.target_match_id === matchId) || null;

// Nom d'un membre du tournoi courant
const getMemberName = (memberId) =>
    current.value?.members?.find(m => m.id === memberId)?.name || '?';

// Prono effectif après échange
const getEffectivePrediction = (matchId, memberId) => {
    const swap = getSwapForMember(matchId, memberId);
    if (!swap) return getMemberPrediction(matchId, memberId);
    const partnerId = swap.initiator_user_id === memberId ? swap.target_user_id : swap.initiator_user_id;
    return getMemberPrediction(matchId, partnerId);
};

// Confirmation échange
const swapConfirmDialog = ref(null);

const showSwapConfirm = (match, opponentId) => {
    const myPred = myPrediction(match.id);
    const opponentPred = getMemberPrediction(match.id, opponentId);
    const opponentName = current.value?.members?.find(m => m.id === opponentId)?.name || '?';
    swapConfirmDialog.value = { match, opponentId, myPred, opponentPred, opponentName };
};

const confirmSwap = async () => {
    if (!swapConfirmDialog.value) return;
    const { match, opponentId } = swapConfirmDialog.value;
    swapConfirmDialog.value = null;
    await placeSwap(match, opponentId);
};

// Prono de l'utilisateur courant pour un match
const myPrediction = (matchId) => {
    if (!current.value) return null;
    const preds = current.value.allPredictions[matchId] || [];
    return preds.find(p => p.user_id === page.props.auth.user.id) || null;
};

const hasAnyBlockOnMatch = (matchId) => {
    if (!current.value) return false;
    const blocks = localBlocks.value;
    return Object.values(blocks).some(b => b.target_match_id === matchId);
};

const isBlockLoading = (matchId, opponentId) =>
    !!blockLoading.value[`${matchId}-${opponentId}`];

const toggleDouble = async (match) => {
    if (doubleLoading.value[match.id]) return;
    doubleLoading.value[match.id] = true;
    try {
        const res = await fetch(`/doubles/match/${match.id}/toggle`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
        });
        const data = await res.json();
        if (data.success) {
            localDoubleActive.value[match.id] = data.active;
            localDoubleStats.value = { ...localDoubleStats.value, remaining: data.remaining, used: localDoubleStats.value.max - data.remaining };
        } else {
            alert(data.message);
        }
    } finally {
        doubleLoading.value[match.id] = false;
    }
};

const placeBlock = async (match, opponentId) => {
    const key = `${match.id}-${opponentId}`;
    if (blockLoading.value[key]) return;
    blockLoading.value[key] = true;
    try {
        const res = await fetch('/blocks', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
            body: JSON.stringify({ tournament_id: current.value.tournament.id, target_user_id: opponentId, match_id: match.id }),
        });
        const data = await res.json();
        if (data.success) {
            localBlocks.value = {
                ...localBlocks.value,
                [opponentId]: { id: data.block_id, target_user_id: opponentId, target_match_id: match.id },
            };
            localAllBlocks.value = [
                ...localAllBlocks.value.filter(b => !(b.blocker_user_id === page.props.auth.user.id && b.target_user_id === opponentId && b.target_match_id === match.id)),
                { blocker_user_id: page.props.auth.user.id, target_user_id: opponentId, target_match_id: match.id },
            ];
        } else {
            alert(data.message);
        }
    } finally {
        blockLoading.value[key] = false;
    }
};

const removeBlock = async (blockId, opponentId) => {
    const blockEntry = localBlocks.value[opponentId];
    const res = await fetch(`/blocks/${blockId}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
    });
    const data = await res.json();
    if (data.success) {
        const updated = { ...localBlocks.value };
        delete updated[opponentId];
        localBlocks.value = updated;
        if (blockEntry) {
            localAllBlocks.value = localAllBlocks.value.filter(b =>
                !(b.blocker_user_id === page.props.auth.user.id && b.target_user_id === opponentId && b.target_match_id === blockEntry.target_match_id)
            );
        }
    } else {
        alert(data.message);
    }
};

const placeSwap = async (match, opponentId) => {
    const key = `${match.id}-${opponentId}`;
    if (swapLoading.value[key]) return;
    swapLoading.value[key] = true;
    try {
        const res = await fetch('/swaps', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
            body: JSON.stringify({ tournament_id: current.value.tournament.id, target_user_id: opponentId, initiator_match_id: match.id, target_match_id: match.id }),
        });
        const data = await res.json();
        if (data.success) {
            localSwaps.value = {
                ...localSwaps.value,
                [opponentId]: { id: data.swap_id, target_user_id: opponentId, initiator_match_id: match.id, target_match_id: match.id },
            };
            localAllSwaps.value = [
                ...localAllSwaps.value.filter(s => !(s.initiator_user_id === page.props.auth.user.id && s.target_user_id === opponentId && s.initiator_match_id === match.id)),
                { initiator_user_id: page.props.auth.user.id, target_user_id: opponentId, initiator_match_id: match.id, target_match_id: match.id },
            ];
        } else {
            alert(data.message);
        }
    } finally {
        swapLoading.value[key] = false;
    }
};

const removeSwap = async (swapId, opponentId) => {
    const swapEntry = localSwaps.value[opponentId];
    const res = await fetch(`/swaps/${swapId}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
    });
    const data = await res.json();
    if (data.success) {
        const updated = { ...localSwaps.value };
        delete updated[opponentId];
        localSwaps.value = updated;
        if (swapEntry) {
            localAllSwaps.value = localAllSwaps.value.filter(s =>
                !(s.initiator_user_id === page.props.auth.user.id && s.target_user_id === opponentId && s.initiator_match_id === swapEntry.initiator_match_id)
            );
        }
    } else {
        alert(data.message);
    }
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
                            <span class="w-6 h-6 rounded bg-emerald-500 text-white flex items-center justify-center font-bold text-xs">6</span>
                            <span class="text-gray-600">Score exact (+6 pts)</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="w-6 h-6 rounded bg-amber-500 text-white flex items-center justify-center font-bold text-xs">2</span>
                            <span class="text-gray-600">Bon vainqueur (+2 pts)</span>
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
                                            getSwapForMember(match.id, member.id)
                                                ? 'border-purple-300 bg-purple-50'
                                                : getBlockForMember(match.id, member.id)
                                                    ? 'border-red-200 bg-red-50'
                                                    : member.id === $page.props.auth.user.id
                                                        ? 'border-indigo-300 bg-indigo-50'
                                                        : getMemberPrediction(match.id, member.id) && match.status === 'completed'
                                                            ? resultTypeBg(getMemberPrediction(match.id, member.id).result_type)
                                                            : 'border-gray-200 bg-gray-50'
                                        ]"
                                    >
                                        <!-- Nom -->
                                        <div class="text-xs font-medium text-gray-700 truncate mb-0.5">{{ member.name }}</div>
                                        <!-- Badges visibles par tous -->
                                        <div class="flex items-center justify-center gap-1 flex-wrap mb-1">
                                            <span v-if="hasDoubled(match.id, member.id)" class="text-[9px] font-bold text-amber-600 bg-amber-50 px-1 rounded">⚡x2</span>
                                            <span
                                                v-if="getBlockForMember(match.id, member.id)"
                                                class="text-[9px] font-semibold text-red-600 bg-red-100 px-1 rounded"
                                            >🛡 {{ getMemberName(getBlockForMember(match.id, member.id).blocker_user_id) }}</span>
                                            <span
                                                v-if="getSwapForMember(match.id, member.id)"
                                                class="text-[9px] font-semibold text-purple-700 bg-purple-100 px-1 rounded"
                                            >↔ {{ getMemberName(getSwapForMember(match.id, member.id).initiator_user_id === member.id ? getSwapForMember(match.id, member.id).target_user_id : getSwapForMember(match.id, member.id).initiator_user_id) }}</span>
                                        </div>

                                        <div v-if="current.predictionsOpen && member.id !== $page.props.auth.user.id" class="text-sm text-gray-400 py-1">
                                            <svg class="w-4 h-4 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                            </svg>
                                        </div>
                                        <div v-else-if="getEffectivePrediction(match.id, member.id)">
                                            <div
                                                :class="[
                                                    'text-sm font-bold px-2 py-1 rounded',
                                                    getSwapForMember(match.id, member.id)
                                                        ? 'bg-purple-200 text-purple-800'
                                                        : match.status === 'completed'
                                                            ? resultTypeColor(getMemberPrediction(match.id, member.id)?.result_type)
                                                            : 'bg-indigo-100 text-indigo-700'
                                                ]"
                                            >
                                                {{ getEffectivePrediction(match.id, member.id).home_score }}-{{ getEffectivePrediction(match.id, member.id).away_score }}
                                            </div>
                                        </div>
                                        <div v-else class="text-sm text-gray-400 py-1">-</div>

                                        <!-- x2 Doubler sur sa propre carte -->
                                        <div v-if="member.id === $page.props.auth.user.id && match.status === 'scheduled' && !current.predictionsOpen && myPrediction(match.id)" class="mt-1">
                                            <button
                                                @click.stop="toggleDouble(match)"
                                                :disabled="doubleLoading[match.id] || (!localDoubleActive[match.id] && (localDoubleStats.remaining ?? 0) <= 0)"
                                                :class="[
                                                    'w-full text-xs py-0.5 rounded font-semibold transition',
                                                    localDoubleActive[match.id]
                                                        ? 'bg-emerald-500 text-white'
                                                        : (localDoubleStats.remaining ?? 0) > 0
                                                            ? 'bg-white text-emerald-700 border border-emerald-300'
                                                            : 'bg-gray-100 text-gray-400 cursor-not-allowed'
                                                ]"
                                            >
                                                {{ localDoubleActive[match.id] ? '⚡x2 ✓' : `x2 (${localDoubleStats.used ?? 0}/3)` }}
                                            </button>
                                        </div>
                                        <!-- Bloquer / Échanger sur les cartes adversaires -->
                                        <div v-if="member.id !== $page.props.auth.user.id && match.status === 'scheduled' && !current.predictionsOpen" class="mt-1 flex gap-1">
                                            <button
                                                @click.stop="localBlocks[member.id]?.target_match_id === match.id ? removeBlock(localBlocks[member.id].id, member.id) : placeBlock(match, member.id)"
                                                :disabled="isBlockLoading(match.id, member.id) || localSwaps[member.id]?.initiator_match_id === match.id || (takenBlocks[`${member.id}_${match.id}`] && localBlocks[member.id]?.target_match_id !== match.id)"
                                                :class="[
                                                    'flex-1 text-xs py-0.5 rounded font-medium transition',
                                                    localSwaps[member.id]?.initiator_match_id === match.id || (takenBlocks[`${member.id}_${match.id}`] && localBlocks[member.id]?.target_match_id !== match.id)
                                                        ? 'bg-gray-100 text-gray-300 cursor-not-allowed'
                                                        : localBlocks[member.id]?.target_match_id === match.id
                                                            ? 'bg-red-500 text-white'
                                                            : localBlocks[member.id]
                                                                ? 'bg-orange-100 text-orange-700'
                                                                : 'bg-gray-100 text-gray-500 hover:bg-red-50 hover:text-red-600'
                                                ]"
                                                :title="localSwaps[member.id]?.initiator_match_id === match.id ? 'Échange actif — annulez d\'abord' : takenBlocks[`${member.id}_${match.id}`] && localBlocks[member.id]?.target_match_id !== match.id ? 'Déjà bloqué par un autre joueur' : ''"
                                            >
                                                {{ isBlockLoading(match.id, member.id) ? '...' : localBlocks[member.id]?.target_match_id === match.id ? '🛡✓' : '🛡' }}
                                            </button>
                                            <button
                                                @click.stop="localSwaps[member.id]?.initiator_match_id === match.id ? removeSwap(localSwaps[member.id].id, member.id) : showSwapConfirm(match, member.id)"
                                                :disabled="swapLoading[`${match.id}-${member.id}`] || localBlocks[member.id]?.target_match_id === match.id || (takenSwaps[`${member.id}_${match.id}`] && localSwaps[member.id]?.initiator_match_id !== match.id)"
                                                :class="[
                                                    'flex-1 text-xs py-0.5 rounded font-medium transition',
                                                    localBlocks[member.id]?.target_match_id === match.id || (takenSwaps[`${member.id}_${match.id}`] && localSwaps[member.id]?.initiator_match_id !== match.id)
                                                        ? 'bg-gray-100 text-gray-300 cursor-not-allowed'
                                                        : localSwaps[member.id]?.initiator_match_id === match.id
                                                            ? 'bg-purple-500 text-white'
                                                            : localSwaps[member.id]
                                                                ? 'bg-purple-100 text-purple-700'
                                                                : 'bg-purple-50 text-purple-600 hover:bg-purple-100'
                                                ]"
                                                :title="localBlocks[member.id]?.target_match_id === match.id ? 'Bloc actif — annulez d\'abord' : takenSwaps[`${member.id}_${match.id}`] && localSwaps[member.id]?.initiator_match_id !== match.id ? 'Déjà en échange avec un autre joueur' : ''"
                                            >
                                                {{ swapLoading[`${match.id}-${member.id}`] ? '...' : localSwaps[member.id]?.initiator_match_id === match.id ? '↔✓' : '↔' }}
                                            </button>
                                        </div>
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

        <!-- Confirmation échange -->
        <div
            v-if="swapConfirmDialog"
            class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4"
            @click.self="swapConfirmDialog = null"
        >
            <div class="bg-white rounded-2xl p-5 max-w-sm w-full shadow-xl">
                <h3 class="text-sm font-bold text-gray-900 mb-3 text-center">Confirmer l'échange ?</h3>
                <div class="space-y-2 mb-4">
                    <div class="flex items-center justify-between p-2 bg-indigo-50 rounded-lg">
                        <span class="text-xs text-gray-600">Mon prono</span>
                        <span class="text-sm font-bold text-indigo-700">
                            {{ swapConfirmDialog.myPred ? `${swapConfirmDialog.myPred.home_score} - ${swapConfirmDialog.myPred.away_score}` : 'Pas de prono' }}
                        </span>
                    </div>
                    <div class="text-center text-gray-400 text-xs font-medium">↕ échangé avec</div>
                    <div class="flex items-center justify-between p-2 bg-purple-50 rounded-lg">
                        <span class="text-xs text-gray-600">{{ swapConfirmDialog.opponentName }}</span>
                        <span class="text-sm font-bold text-purple-700">
                            {{ swapConfirmDialog.opponentPred ? `${swapConfirmDialog.opponentPred.home_score} - ${swapConfirmDialog.opponentPred.away_score}` : 'Pas de prono' }}
                        </span>
                    </div>
                </div>
                <p class="text-xs text-gray-500 text-center mb-4">
                    Vous jouerez avec le prono de {{ swapConfirmDialog.opponentName }} pour ce match.
                </p>
                <div class="flex gap-2">
                    <button
                        @click="swapConfirmDialog = null"
                        class="flex-1 px-4 py-2 text-sm font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition"
                    >
                        Annuler
                    </button>
                    <button
                        @click="confirmSwap"
                        class="flex-1 px-4 py-2 text-sm font-medium text-white bg-purple-600 rounded-lg hover:bg-purple-700 transition"
                    >
                        Confirmer ↔
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
