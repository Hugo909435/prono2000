<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TeamFlag from '@/Components/TeamFlag.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    tournament: Object,
    groupMatches: Array,
    myPredictions: Object,
    doubleStats: Object,
    myBlocks: Object,
    mySwaps: Object,
    receivedSwaps: Object,
    receivedBlocks: Object,
    opponents: Array,
    predictionsOpen: Boolean,
});

// ── Helpers ──────────────────────────────────────────────────────────────────

const csrf = () => document.querySelector('meta[name="csrf-token"]').getAttribute('content');

const formatMatchLabel = (match) => {
    if (!match) return '?';
    const home = match.home_team?.short_name || match.home_team?.name || 'TBD';
    const away = match.away_team?.short_name || match.away_team?.name || 'TBD';
    return `${home} - ${away}`;
};

const openMatches = computed(() =>
    props.groupMatches.filter(m => m.status === 'scheduled')
);

const formatDate = (d) => {
    if (!d) return '';
    return new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
};

// ── Prono Doublé ─────────────────────────────────────────────────────────────

const doubleStats = ref({ ...props.doubleStats });
const doubleLoading = ref({});

const isMatchDoubled = (matchId) => {
    return !!props.myPredictions?.[matchId]?.is_doubled;
};

const doubledMatches = ref(
    Object.fromEntries(
        Object.entries(props.myPredictions || {}).map(([k, v]) => [k, v.is_doubled])
    )
);

const canDouble = (matchId) => {
    const m = props.groupMatches.find(m => m.id === matchId);
    if (!m || m.status !== 'scheduled') return false;
    if (!props.myPredictions?.[matchId]) return false;
    return doubledMatches.value[matchId] || doubleStats.value.remaining > 0;
};

const toggleDouble = async (matchId) => {
    if (doubleLoading.value[matchId]) return;
    doubleLoading.value[matchId] = true;

    try {
        const res = await fetch(route('doubles.toggle', matchId), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
        });
        const data = await res.json();
        if (data.success) {
            doubledMatches.value[matchId] = data.active;
            doubleStats.value.remaining = data.remaining;
            doubleStats.value.used = props.doubleStats.max - data.remaining;
        } else {
            alert(data.message);
        }
    } catch (e) {
        console.error(e);
    } finally {
        doubleLoading.value[matchId] = false;
    }
};

// ── Prono Bloqué ─────────────────────────────────────────────────────────────

const blockLoading = ref({});
const myBlocks = ref({ ...props.myBlocks });

// selectedBlockMatch[opponentId] = matchId chosen in select
const selectedBlockMatch = ref({});
props.opponents.forEach(o => {
    selectedBlockMatch.value[o.id] = myBlocks.value[o.id]?.target_match_id ?? '';
});

const setBlock = async (opponentId) => {
    const matchId = selectedBlockMatch.value[opponentId];
    if (!matchId) return;

    blockLoading.value[opponentId] = true;
    try {
        const res = await fetch(route('blocks.store'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
            body: JSON.stringify({
                tournament_id: props.tournament.id,
                target_user_id: opponentId,
                match_id: matchId,
            }),
        });
        const data = await res.json();
        if (data.success) {
            myBlocks.value[opponentId] = { target_match_id: parseInt(matchId) };
        } else {
            alert(data.message);
        }
    } catch (e) {
        console.error(e);
    } finally {
        blockLoading.value[opponentId] = false;
    }
};

const removeBlock = async (opponentId) => {
    const block = myBlocks.value[opponentId];
    if (!block?.id) {
        // No id means it was set in this session, just clear locally after checking
        blockLoading.value[opponentId] = true;
        try {
            const res = await fetch(route('blocks.store'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
                body: JSON.stringify({ tournament_id: props.tournament.id, target_user_id: opponentId, match_id: 0 }),
            });
        } catch {}
        myBlocks.value[opponentId] = null;
        selectedBlockMatch.value[opponentId] = '';
        blockLoading.value[opponentId] = false;
        return;
    }

    blockLoading.value[opponentId] = true;
    try {
        const res = await fetch(route('blocks.destroy', block.id), {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrf() },
        });
        const data = await res.json();
        if (data.success) {
            myBlocks.value[opponentId] = null;
            selectedBlockMatch.value[opponentId] = '';
        } else {
            alert(data.message);
        }
    } catch (e) {
        console.error(e);
    } finally {
        blockLoading.value[opponentId] = false;
    }
};

// ── Prono Échangé ─────────────────────────────────────────────────────────────

const swapLoading = ref({});
const mySwaps = ref({ ...props.mySwaps });

const selectedSwap = ref({});
props.opponents.forEach(o => {
    selectedSwap.value[o.id] = {
        myMatch: mySwaps.value[o.id]?.initiator_match_id ?? '',
        theirMatch: mySwaps.value[o.id]?.target_match_id ?? '',
    };
});

const setSwap = async (opponentId) => {
    const { myMatch, theirMatch } = selectedSwap.value[opponentId];
    if (!myMatch || !theirMatch) return;

    swapLoading.value[opponentId] = true;
    try {
        const res = await fetch(route('swaps.store'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
            body: JSON.stringify({
                tournament_id: props.tournament.id,
                target_user_id: opponentId,
                initiator_match_id: myMatch,
                target_match_id: theirMatch,
            }),
        });
        const data = await res.json();
        if (data.success) {
            mySwaps.value[opponentId] = {
                initiator_match_id: parseInt(myMatch),
                target_match_id: parseInt(theirMatch),
            };
        } else {
            alert(data.message);
        }
    } catch (e) {
        console.error(e);
    } finally {
        swapLoading.value[opponentId] = false;
    }
};

const removeSwap = async (opponentId) => {
    const swap = mySwaps.value[opponentId];
    if (!swap?.id) {
        mySwaps.value[opponentId] = null;
        selectedSwap.value[opponentId] = { myMatch: '', theirMatch: '' };
        return;
    }

    swapLoading.value[opponentId] = true;
    try {
        const res = await fetch(route('swaps.destroy', swap.id), {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrf() },
        });
        const data = await res.json();
        if (data.success) {
            mySwaps.value[opponentId] = null;
            selectedSwap.value[opponentId] = { myMatch: '', theirMatch: '' };
        } else {
            alert(data.message);
        }
    } catch (e) {
        console.error(e);
    } finally {
        swapLoading.value[opponentId] = false;
    }
};

// Mes matchs de poule où j'ai un prono ET qui sont encore ouverts
const myAvailableMatches = computed(() =>
    props.groupMatches.filter(m => props.myPredictions?.[m.id] && m.status === 'scheduled')
);

// Quota : 1 bloc et 1 échange par tournoi
const hasAnyBlock = computed(() => Object.values(myBlocks.value).some(b => b !== null));
const hasAnySwap = computed(() => Object.values(mySwaps.value).some(s => s !== null));
</script>

<template>
    <Head :title="`Pronos Spéciaux - ${tournament.name}`" />

    <AuthenticatedLayout>
        <div class="py-4 sm:py-8">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 space-y-6">

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
                    <h1 class="text-xl font-bold text-gray-900">Pronos Spéciaux</h1>
                    <p class="text-sm text-gray-500 mt-1">{{ tournament.name }} · Phase de groupes uniquement</p>

                    <div class="mt-4 grid grid-cols-3 gap-3 text-center">
                        <div class="bg-green-50 rounded-xl p-3">
                            <div class="text-2xl font-bold text-green-600">{{ doubleStats.remaining }}</div>
                            <div class="text-xs text-green-700 font-medium">Doubles restants</div>
                            <div class="text-xs text-gray-400">sur {{ doubleStats.max }}</div>
                        </div>
                        <div class="bg-red-50 rounded-xl p-3">
                            <div class="text-2xl font-bold" :class="hasAnyBlock ? 'text-gray-400' : 'text-red-600'">
                                {{ hasAnyBlock ? '0' : '1' }}
                            </div>
                            <div class="text-xs text-red-700 font-medium">Bloc disponible</div>
                            <div class="text-xs text-gray-400">1 au total</div>
                        </div>
                        <div class="bg-blue-50 rounded-xl p-3">
                            <div class="text-2xl font-bold" :class="hasAnySwap ? 'text-gray-400' : 'text-blue-600'">
                                {{ hasAnySwap ? '0' : '1' }}
                            </div>
                            <div class="text-xs text-blue-700 font-medium">Échange disponible</div>
                            <div class="text-xs text-gray-400">1 au total</div>
                        </div>
                    </div>
                </div>

                <!-- ── Section 1: Pronos Doublés ─────────────────────────── -->
                <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
                    <div class="p-4 bg-gradient-to-r from-green-500 to-emerald-600">
                        <h2 class="text-white font-bold text-lg flex items-center gap-2">
                            <span class="text-xl">x2</span>
                            Pronos Doublés
                            <span class="ml-auto text-sm font-normal opacity-90">{{ doubleStats.remaining }}/{{ doubleStats.max }} restants</span>
                        </h2>
                        <p class="text-green-100 text-sm mt-1">Doublez les points de 3 de vos pronostics (12, 4 ou 0 pts)</p>
                    </div>

                    <div class="p-4">
                        <div v-if="groupMatches.length === 0" class="text-gray-400 text-sm text-center py-4">
                            Aucun match de poule disponible.
                        </div>
                        <div class="space-y-2">
                            <div
                                v-for="match in groupMatches"
                                :key="match.id"
                                class="flex items-center justify-between p-3 rounded-xl border"
                                :class="doubledMatches[match.id] ? 'border-green-300 bg-green-50' : 'border-gray-200'"
                            >
                                <div class="flex items-center gap-2 min-w-0">
                                    <TeamFlag :flag="match.home_team?.flag" size="sm" />
                                    <span class="text-sm font-medium truncate">{{ formatMatchLabel(match) }}</span>
                                    <TeamFlag :flag="match.away_team?.flag" size="sm" />
                                    <span v-if="!myPredictions?.[match.id]" class="text-xs text-gray-400">(pas de prono)</span>
                                </div>

                                <div class="flex items-center gap-2 ml-2 flex-shrink-0">
                                    <span v-if="doubledMatches[match.id]" class="text-xs font-bold text-green-600">x2</span>
                                    <button
                                        v-if="canDouble(match.id)"
                                        @click="toggleDouble(match.id)"
                                        :disabled="doubleLoading[match.id]"
                                        :class="[
                                            'text-xs px-3 py-1.5 rounded-lg font-semibold transition-all',
                                            doubledMatches[match.id]
                                                ? 'bg-green-500 text-white hover:bg-green-600'
                                                : 'bg-white border-2 border-green-400 text-green-600 hover:border-green-600',
                                            doubleLoading[match.id] ? 'opacity-50 cursor-wait' : ''
                                        ]"
                                    >
                                        {{ doubledMatches[match.id] ? 'Actif ✓' : 'Doubler' }}
                                    </button>
                                    <span v-else-if="match.status !== 'scheduled'" class="text-xs text-gray-400">
                                        {{ match.status === 'completed' ? 'Terminé' : 'En cours' }}
                                    </span>
                                    <span v-else-if="!myPredictions?.[match.id]" class="text-xs text-gray-300">—</span>
                                    <span v-else class="text-xs text-gray-400">Quota plein</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── Section 2: Pronos Bloqués ──────────────────────────── -->
                <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
                    <div class="p-4 bg-gradient-to-r from-red-500 to-rose-600">
                        <h2 class="text-white font-bold text-lg flex items-center gap-2">
                            <span>🚫</span>
                            Pronos Bloqués
                        </h2>
                        <p class="text-red-100 text-sm mt-1">Bloquez 1 prono par adversaire → 0 point quoi qu'il arrive</p>
                    </div>

                    <div class="p-4 space-y-4">
                        <div v-if="opponents.length === 0" class="text-gray-400 text-sm text-center py-4">
                            Aucun adversaire dans ce tournoi.
                        </div>

                        <div
                            v-for="opponent in opponents"
                            :key="opponent.id"
                            class="border rounded-xl p-4"
                            :class="myBlocks[opponent.id] ? 'border-red-300 bg-red-50' : 'border-gray-200'"
                        >
                            <div class="flex items-center justify-between mb-3">
                                <div class="font-semibold text-gray-900">{{ opponent.name }}</div>
                                <div v-if="myBlocks[opponent.id]" class="text-xs text-red-600 font-medium">
                                    Bloc actif
                                </div>
                                <div v-else-if="hasAnyBlock" class="text-xs text-gray-400">
                                    Quota utilisé
                                </div>
                            </div>

                            <!-- Bloc actif sur cet adversaire -->
                            <div v-if="myBlocks[opponent.id]" class="flex items-center gap-3">
                                <div class="flex-1 text-sm text-gray-700 bg-red-100 rounded-lg px-3 py-2">
                                    🚫 {{ formatMatchLabel(myBlocks[opponent.id]?.target_match) }}
                                </div>
                                <button
                                    @click="removeBlock(opponent.id)"
                                    :disabled="blockLoading[opponent.id]"
                                    class="text-xs px-3 py-2 rounded-lg bg-white border border-red-300 text-red-600 hover:bg-red-50 transition disabled:opacity-50"
                                >
                                    Annuler
                                </button>
                            </div>

                            <!-- Quota déjà utilisé sur un autre adversaire -->
                            <div v-else-if="hasAnyBlock" class="text-sm text-gray-400 italic">
                                Vous avez déjà posé votre bloc sur un autre adversaire.
                            </div>

                            <!-- Sélecteur disponible -->
                            <div v-else class="flex items-center gap-2">
                                <select
                                    v-model="selectedBlockMatch[opponent.id]"
                                    class="flex-1 text-sm border-gray-300 rounded-lg"
                                >
                                    <option value="">— Choisir un match à bloquer —</option>
                                    <option
                                        v-for="match in openMatches"
                                        :key="match.id"
                                        :value="match.id"
                                    >
                                        {{ formatMatchLabel(match) }} ({{ formatDate(match.scheduled_at) }})
                                    </option>
                                </select>
                                <button
                                    @click="setBlock(opponent.id)"
                                    :disabled="!selectedBlockMatch[opponent.id] || blockLoading[opponent.id]"
                                    class="text-xs px-3 py-2 rounded-lg bg-red-500 text-white hover:bg-red-600 disabled:opacity-50 disabled:cursor-not-allowed transition"
                                >
                                    Bloquer
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Blocs reçus (info) -->
                <div v-if="Object.keys(receivedBlocks).length > 0" class="bg-orange-50 border border-orange-200 rounded-2xl p-4">
                    <h3 class="font-semibold text-orange-800 mb-2">Blocs que vous subissez</h3>
                    <div class="space-y-1">
                        <div v-for="(block, blockerId) in receivedBlocks" :key="blockerId" class="text-sm text-orange-700">
                            <span class="font-medium">{{ block.blocker?.name }}</span> bloque votre prono :
                            <span class="font-medium">{{ formatMatchLabel(block.target_match) }}</span>
                        </div>
                    </div>
                </div>

                <!-- ── Section 3: Pronos Échangés ─────────────────────────── -->
                <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
                    <div class="p-4 bg-gradient-to-r from-blue-500 to-indigo-600">
                        <h2 class="text-white font-bold text-lg flex items-center gap-2">
                            <span>🔄</span>
                            Pronos Échangés
                        </h2>
                        <p class="text-blue-100 text-sm mt-1">Échangez 1 prono par adversaire · Vous récupérez ses points, il récupère les vôtres</p>
                    </div>

                    <div class="p-4 space-y-4">
                        <div v-if="opponents.length === 0" class="text-gray-400 text-sm text-center py-4">
                            Aucun adversaire dans ce tournoi.
                        </div>

                        <div
                            v-for="opponent in opponents"
                            :key="opponent.id"
                            class="border rounded-xl p-4"
                            :class="mySwaps[opponent.id] ? 'border-blue-300 bg-blue-50' : 'border-gray-200'"
                        >
                            <div class="flex items-center justify-between mb-3">
                                <div class="font-semibold text-gray-900">{{ opponent.name }}</div>
                                <div v-if="mySwaps[opponent.id]" class="text-xs text-blue-600 font-medium">
                                    Échange actif
                                </div>
                                <div v-else-if="hasAnySwap" class="text-xs text-gray-400">
                                    Quota utilisé
                                </div>
                            </div>

                            <!-- Échange actif sur cet adversaire -->
                            <div v-if="mySwaps[opponent.id]" class="space-y-2">
                                <div class="text-sm text-gray-700 bg-blue-100 rounded-lg px-3 py-2 flex items-center gap-2">
                                    <span class="text-gray-500">Vous donnez :</span>
                                    <span class="font-medium">{{ formatMatchLabel(mySwaps[opponent.id]?.initiator_match) }}</span>
                                    <span class="mx-1">🔄</span>
                                    <span class="text-gray-500">Vous prenez :</span>
                                    <span class="font-medium">{{ formatMatchLabel(mySwaps[opponent.id]?.target_match) }}</span>
                                </div>
                                <button
                                    @click="removeSwap(opponent.id)"
                                    :disabled="swapLoading[opponent.id]"
                                    class="text-xs px-3 py-2 rounded-lg bg-white border border-blue-300 text-blue-600 hover:bg-blue-50 transition disabled:opacity-50"
                                >
                                    Annuler l'échange
                                </button>
                            </div>

                            <!-- Quota déjà utilisé sur un autre adversaire -->
                            <div v-else-if="hasAnySwap" class="text-sm text-gray-400 italic">
                                Vous avez déjà posé votre échange avec un autre adversaire.
                            </div>

                            <!-- Sélecteur disponible -->
                            <div v-else class="space-y-2">
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="text-xs text-gray-500 mb-1 block">Votre prono à donner</label>
                                        <select
                                            v-model="selectedSwap[opponent.id].myMatch"
                                            class="w-full text-sm border-gray-300 rounded-lg"
                                        >
                                            <option value="">— Mon match —</option>
                                            <option
                                                v-for="match in myAvailableMatches"
                                                :key="match.id"
                                                :value="match.id"
                                            >
                                                {{ formatMatchLabel(match) }}
                                            </option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="text-xs text-gray-500 mb-1 block">Prono adverse à prendre</label>
                                        <select
                                            v-model="selectedSwap[opponent.id].theirMatch"
                                            class="w-full text-sm border-gray-300 rounded-lg"
                                        >
                                            <option value="">— Son match —</option>
                                            <option
                                                v-for="match in openMatches"
                                                :key="match.id"
                                                :value="match.id"
                                            >
                                                {{ formatMatchLabel(match) }}
                                            </option>
                                        </select>
                                    </div>
                                </div>
                                <button
                                    @click="setSwap(opponent.id)"
                                    :disabled="!selectedSwap[opponent.id].myMatch || !selectedSwap[opponent.id].theirMatch || swapLoading[opponent.id]"
                                    class="w-full text-sm py-2 rounded-lg bg-blue-500 text-white hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed transition"
                                >
                                    Confirmer l'échange
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Échanges reçus (info) -->
                <div v-if="Object.keys(receivedSwaps).length > 0" class="bg-purple-50 border border-purple-200 rounded-2xl p-4">
                    <h3 class="font-semibold text-purple-800 mb-2">Échanges que vous subissez</h3>
                    <div class="space-y-1">
                        <div v-for="(swap, initiatorId) in receivedSwaps" :key="initiatorId" class="text-sm text-purple-700">
                            <span class="font-medium">{{ swap.initiator?.name }}</span> prend votre prono
                            <span class="font-medium">{{ formatMatchLabel(swap.target_match) }}</span>
                            et vous donne
                            <span class="font-medium">{{ formatMatchLabel(swap.initiator_match) }}</span>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
