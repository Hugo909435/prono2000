<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TeamFlag from '@/Components/TeamFlag.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    myTournaments: Array,
    matchesOfDay: Array,
    selectedDate: String,
    availableDates: Array,
    userPredictions: Object,
    userWinnerPredictions: Object,
    membersWinnerPredictions: Object,
    userBoosters: Object,
    boosterStats: Object,
});

// Index du tournoi selectionne
const currentTournamentIndex = ref(0);

// Tournoi actuellement selectionne
const currentTournament = computed(() => {
    if (props.myTournaments.length === 0) return null;
    return props.myTournaments[currentTournamentIndex.value];
});

// Navigation entre les tournois
const prevTournament = () => {
    if (currentTournamentIndex.value > 0) {
        currentTournamentIndex.value--;
    }
};

const nextTournament = () => {
    if (currentTournamentIndex.value < props.myTournaments.length - 1) {
        currentTournamentIndex.value++;
    }
};

// Navigation par date
const currentDateIndex = computed(() => {
    return props.availableDates.indexOf(props.selectedDate);
});

const prevDate = () => {
    const idx = currentDateIndex.value;
    if (idx > 0) {
        router.get(route('dashboard'), { date: props.availableDates[idx - 1] }, { preserveState: true });
    }
};

const nextDate = () => {
    const idx = currentDateIndex.value;
    if (idx < props.availableDates.length - 1) {
        router.get(route('dashboard'), { date: props.availableDates[idx + 1] }, { preserveState: true });
    }
};

// Matchs du tournoi courant pour le jour sélectionné
const currentTournamentMatches = computed(() => {
    if (!currentTournament.value) return [];
    return props.matchesOfDay.filter(m => m.tournament_id === currentTournament.value.id);
});

// Formater la date pour l'affichage
const formatDate = (dateString) => {
    const date = new Date(dateString);
    const today = new Date();
    const tomorrow = new Date(today);
    tomorrow.setDate(tomorrow.getDate() + 1);
    const yesterday = new Date(today);
    yesterday.setDate(yesterday.getDate() - 1);

    if (date.toDateString() === today.toDateString()) {
        return "Aujourd'hui";
    } else if (date.toDateString() === tomorrow.toDateString()) {
        return 'Demain';
    } else if (date.toDateString() === yesterday.toDateString()) {
        return 'Hier';
    }

    return date.toLocaleDateString('fr-FR', {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
    });
};

// Formater l'heure
const formatTime = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleTimeString('fr-FR', {
        hour: '2-digit',
        minute: '2-digit',
    });
};

// Match selectionne pour voir les pronostics
const selectedMatch = ref(null);

const toggleMatchDetails = (match) => {
    if (selectedMatch.value?.id === match.id) {
        selectedMatch.value = null;
    } else {
        selectedMatch.value = match;
    }
};

// Obtenir le pronostic d'un membre pour un match
const getMemberPrediction = (match, memberId) => {
    return match.predictions.find(p => p.user_id === memberId);
};

// Couleur selon le resultat
const resultTypeColor = (type) => {
    const colors = {
        exact: 'bg-emerald-500 text-white',
        correct_winner: 'bg-amber-500 text-white',
        wrong: 'bg-red-500 text-white',
    };
    return colors[type] || 'bg-gray-200 text-gray-700';
};

const resultTypeBg = (type) => {
    const colors = {
        exact: 'bg-emerald-50 border-emerald-200',
        correct_winner: 'bg-amber-50 border-amber-200',
        wrong: 'bg-red-50 border-red-200',
    };
    return colors[type] || 'bg-gray-50 border-gray-200';
};

// Poules qui ont des matchs aujourd'hui (pour le tournoi courant)
const todaysGroups = computed(() => {
    if (!currentTournament.value?.tournament_groups) return [];

    const groupIdsWithMatches = new Set(
        currentTournamentMatches.value
            .filter(m => m.round === 'group' && m.tournament_group_id)
            .map(m => m.tournament_group_id)
    );

    if (groupIdsWithMatches.size === 0) return [];

    return currentTournament.value.tournament_groups.filter(
        g => groupIdsWithMatches.has(g.id)
    );
});

// Vérifier si on a des matchs de poule aujourd'hui
const hasGroupMatchesToday = computed(() => todaysGroups.value.length > 0);

// Pronostic vainqueur du tournoi actuel
const currentWinnerPrediction = computed(() => {
    if (!currentTournament.value?.id) return null;
    return props.userWinnerPredictions[currentTournament.value.id] || null;
});

// Équipes du tournoi actuel
const tournamentTeams = computed(() => {
    return currentTournament.value?.teams || [];
});

// Form pour le pronostic vainqueur
const winnerForm = ref({
    first_choice_team_id: null,
    second_choice_team_id: null,
    third_choice_team_id: null,
});

const showWinnerForm = ref(false);

const initWinnerForm = () => {
    if (currentWinnerPrediction.value) {
        winnerForm.value.first_choice_team_id = currentWinnerPrediction.value.first_choice_team_id;
        winnerForm.value.second_choice_team_id = currentWinnerPrediction.value.second_choice_team_id;
        winnerForm.value.third_choice_team_id = currentWinnerPrediction.value.third_choice_team_id;
    } else {
        winnerForm.value = {
            first_choice_team_id: null,
            second_choice_team_id: null,
            third_choice_team_id: null,
        };
    }
    showWinnerForm.value = true;
};

const submitWinnerPrediction = () => {
    if (!currentTournament.value?.id) return;

    router.post(route('tournaments.winner-prediction.store', currentTournament.value.id), winnerForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            showWinnerForm.value = false;
        },
    });
};

// Équipes disponibles pour chaque choix
const availableForFirst = computed(() => tournamentTeams.value);
const availableForSecond = computed(() => tournamentTeams.value.filter(t => t.id !== winnerForm.value.first_choice_team_id));
const availableForThird = computed(() => tournamentTeams.value.filter(t =>
    t.id !== winnerForm.value.first_choice_team_id &&
    t.id !== winnerForm.value.second_choice_team_id
));

// Pronostics vainqueur des membres du tournoi actuel (keyed by tournament_id)
const currentTournamentMembersWinnerPredictions = computed(() => {
    if (!currentTournament.value) return {};
    return props.membersWinnerPredictions[currentTournament.value.id] || {};
});

// Boosters
const localBoosters = ref(props.userBoosters || {});
const localBoosterStats = ref(props.boosterStats || {});
const boosterLoading = ref({});

const hasBooster = (matchId) => {
    return !!localBoosters.value[matchId];
};

const canToggleBooster = (match) => {
    if (match.status !== 'scheduled') return false;

    if (match.scheduled_at) {
        const matchDate = new Date(match.scheduled_at);
        if (matchDate <= new Date()) return false;
    }

    if (hasBooster(match.id)) return true;

    const tournamentId = match.tournament_id;
    const stats = localBoosterStats.value[tournamentId];

    return stats && stats.remaining > 0;
};

const toggleBooster = async (match) => {
    if (boosterLoading.value[match.id]) return;

    boosterLoading.value[match.id] = true;

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        const response = await fetch(route('boosters.toggle', match.id), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            console.error('Erreur HTTP:', response.status);
            return;
        }

        const data = await response.json();

        if (data.success) {
            if (data.active) {
                localBoosters.value[match.id] = true;
            } else {
                delete localBoosters.value[match.id];
            }
            if (localBoosterStats.value[match.tournament_id]) {
                localBoosterStats.value[match.tournament_id].remaining = data.remaining;
            }
        } else {
            alert(data.message || 'Erreur lors de l\'activation du booster');
        }
    } catch (error) {
        console.error('Erreur lors du toggle du booster:', error);
        alert('Une erreur est survenue');
    } finally {
        boosterLoading.value[match.id] = false;
    }
};

const getBoosterStats = (tournamentId) => {
    return localBoosterStats.value[tournamentId] || { remaining: 0, max: 5 };
};

// Rejoindre un tournoi par code
const joinCode = ref('');
const joinTournament = () => {
    router.post(route('tournaments.join'), { access_code: joinCode.value.toUpperCase() });
};
</script>

<template>
    <Head title="Accueil" />

    <AuthenticatedLayout>
        <div class="px-4 py-4 space-y-4 max-w-2xl mx-auto">

            <!-- Message si pas de tournoi -->
            <div v-if="myTournaments.length === 0" class="bg-white rounded-2xl p-6 text-center shadow-sm">
                <div class="w-16 h-16 mx-auto mb-4 bg-indigo-100 rounded-full flex items-center justify-center">
                    <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Rejoins un tournoi !</h3>
                <p class="text-gray-500 text-sm mb-4">Entre le code d'acces partagé par le créateur du tournoi.</p>
                <form @submit.prevent="joinTournament" class="flex flex-col items-center gap-3">
                    <input
                        v-model="joinCode"
                        type="text"
                        maxlength="8"
                        placeholder="Ex: ABCD1234"
                        class="w-48 text-center uppercase font-mono text-xl tracking-widest rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <button
                        type="submit"
                        class="inline-flex items-center px-5 py-2.5 bg-indigo-600 text-white rounded-xl font-medium text-sm hover:bg-indigo-700 transition"
                    >
                        Rejoindre
                    </button>
                </form>
                <div class="mt-4">
                    <Link
                        :href="route('tournaments.create')"
                        class="text-sm text-indigo-600 hover:text-indigo-800"
                    >
                        Ou créer un nouveau tournoi
                    </Link>
                </div>
            </div>

            <!-- Contenu principal -->
            <template v-else>
                <!-- Selecteur de tournoi -->
                <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
                    <div class="flex items-center justify-between p-3">
                        <button
                            @click="prevTournament"
                            :disabled="currentTournamentIndex === 0"
                            :class="[
                                'w-10 h-10 rounded-xl flex items-center justify-center transition',
                                currentTournamentIndex === 0
                                    ? 'text-gray-300'
                                    : 'text-gray-600 hover:bg-gray-100 active:bg-gray-200'
                            ]"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                            </svg>
                        </button>

                        <Link :href="route('tournaments.show', currentTournament.id)" class="text-center flex-1">
                            <h2 class="text-base font-bold text-gray-900">{{ currentTournament?.name }}</h2>
                            <p class="text-xs text-gray-500">{{ currentTournament?.members_count }} membre(s)</p>
                        </Link>

                        <button
                            @click="nextTournament"
                            :disabled="currentTournamentIndex === myTournaments.length - 1"
                            :class="[
                                'w-10 h-10 rounded-xl flex items-center justify-center transition',
                                currentTournamentIndex === myTournaments.length - 1
                                    ? 'text-gray-300'
                                    : 'text-gray-600 hover:bg-gray-100 active:bg-gray-200'
                            ]"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                    </div>

                    <!-- Indicateur de tournoi -->
                    <div v-if="myTournaments.length > 1" class="flex justify-center gap-1.5 pb-3">
                        <button
                            v-for="(tournament, idx) in myTournaments"
                            :key="tournament.id"
                            @click="currentTournamentIndex = idx"
                            :class="[
                                'w-2 h-2 rounded-full transition',
                                idx === currentTournamentIndex ? 'bg-indigo-600' : 'bg-gray-300'
                            ]"
                        />
                    </div>
                </div>

                <!-- Classement moderne -->
                <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
                    <!-- Header -->
                    <div class="px-4 py-3 border-b border-gray-100">
                        <h3 class="text-sm font-bold text-gray-800 text-center">Classement</h3>
                    </div>

                    <!-- Podium - Top 3 -->
                    <div v-if="currentTournament?.members?.length >= 3" class="p-4 bg-gradient-to-b from-slate-50 to-white">
                        <div class="flex items-end justify-center gap-2">
                            <!-- 2ème place -->
                            <div class="flex-1 max-w-[110px]">
                                <div class="bg-gradient-to-b from-slate-100 to-slate-50 rounded-xl p-3 border border-slate-200 text-center">
                                    <div class="w-10 h-10 mx-auto mb-2 rounded-full bg-gradient-to-br from-slate-200 to-slate-300 flex items-center justify-center text-slate-600 font-bold text-sm shadow-inner">
                                        {{ currentTournament.members[1]?.name?.charAt(0)?.toUpperCase() }}
                                    </div>
                                    <div class="text-xs font-semibold text-slate-700 truncate mb-1">
                                        {{ currentTournament.members[1]?.name }}
                                    </div>
                                    <div class="text-lg font-bold text-slate-600">
                                        {{ currentTournament.members[1]?.pivot?.total_points ?? 0 }}
                                    </div>
                                    <div class="text-[10px] text-slate-400">points</div>
                                    <div class="mt-2 flex justify-center gap-1">
                                        <span class="text-[10px] bg-emerald-100 text-emerald-600 px-1.5 py-0.5 rounded">{{ currentTournament.members[1]?.pivot?.exact_scores ?? 0 }}</span>
                                        <span class="text-[10px] bg-amber-100 text-amber-600 px-1.5 py-0.5 rounded">{{ currentTournament.members[1]?.pivot?.correct_results ?? 0 }}</span>
                                        <span class="text-[10px] bg-red-100 text-red-500 px-1.5 py-0.5 rounded">{{ currentTournament.members[1]?.pivot?.wrong_predictions ?? 0 }}</span>
                                    </div>
                                </div>
                                <div class="text-center mt-2">
                                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-slate-200 text-slate-600 text-xs font-bold">2</span>
                                </div>
                            </div>

                            <!-- 1ère place -->
                            <div class="flex-1 max-w-[120px] -mt-4">
                                <div class="bg-gradient-to-b from-amber-50 to-yellow-50 rounded-xl p-4 border border-amber-200 text-center shadow-sm">
                                    <div class="w-12 h-12 mx-auto mb-2 rounded-full bg-gradient-to-br from-amber-200 to-yellow-300 flex items-center justify-center text-amber-700 font-bold text-lg shadow-inner">
                                        {{ currentTournament.members[0]?.name?.charAt(0)?.toUpperCase() }}
                                    </div>
                                    <div class="text-sm font-bold text-amber-800 truncate mb-1">
                                        {{ currentTournament.members[0]?.name }}
                                    </div>
                                    <div class="text-2xl font-bold text-amber-600">
                                        {{ currentTournament.members[0]?.pivot?.total_points ?? 0 }}
                                    </div>
                                    <div class="text-[10px] text-amber-500">points</div>
                                    <div class="mt-2 flex justify-center gap-1">
                                        <span class="text-[10px] bg-emerald-100 text-emerald-600 px-1.5 py-0.5 rounded">{{ currentTournament.members[0]?.pivot?.exact_scores ?? 0 }}</span>
                                        <span class="text-[10px] bg-amber-100 text-amber-600 px-1.5 py-0.5 rounded">{{ currentTournament.members[0]?.pivot?.correct_results ?? 0 }}</span>
                                        <span class="text-[10px] bg-red-100 text-red-500 px-1.5 py-0.5 rounded">{{ currentTournament.members[0]?.pivot?.wrong_predictions ?? 0 }}</span>
                                    </div>
                                </div>
                                <div class="text-center mt-2">
                                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-amber-200 text-amber-700 text-sm font-bold shadow-sm">1</span>
                                </div>
                            </div>

                            <!-- 3ème place -->
                            <div class="flex-1 max-w-[110px]">
                                <div class="bg-gradient-to-b from-orange-50 to-amber-50 rounded-xl p-3 border border-orange-200 text-center">
                                    <div class="w-10 h-10 mx-auto mb-2 rounded-full bg-gradient-to-br from-orange-200 to-orange-300 flex items-center justify-center text-orange-700 font-bold text-sm shadow-inner">
                                        {{ currentTournament.members[2]?.name?.charAt(0)?.toUpperCase() }}
                                    </div>
                                    <div class="text-xs font-semibold text-orange-800 truncate mb-1">
                                        {{ currentTournament.members[2]?.name }}
                                    </div>
                                    <div class="text-lg font-bold text-orange-600">
                                        {{ currentTournament.members[2]?.pivot?.total_points ?? 0 }}
                                    </div>
                                    <div class="text-[10px] text-orange-400">points</div>
                                    <div class="mt-2 flex justify-center gap-1">
                                        <span class="text-[10px] bg-emerald-100 text-emerald-600 px-1.5 py-0.5 rounded">{{ currentTournament.members[2]?.pivot?.exact_scores ?? 0 }}</span>
                                        <span class="text-[10px] bg-amber-100 text-amber-600 px-1.5 py-0.5 rounded">{{ currentTournament.members[2]?.pivot?.correct_results ?? 0 }}</span>
                                        <span class="text-[10px] bg-red-100 text-red-500 px-1.5 py-0.5 rounded">{{ currentTournament.members[2]?.pivot?.wrong_predictions ?? 0 }}</span>
                                    </div>
                                </div>
                                <div class="text-center mt-2">
                                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-orange-200 text-orange-700 text-xs font-bold">3</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Légende -->
                    <div v-if="currentTournament?.members?.length > 3" class="px-4 py-2 bg-gray-50 border-y border-gray-100">
                        <div class="flex items-center justify-between text-[10px] text-gray-400">
                            <span>Autres joueurs</span>
                            <div class="flex gap-3">
                                <span class="flex items-center gap-1">
                                    <span class="w-2 h-2 rounded-full bg-emerald-400"></span> Exact
                                </span>
                                <span class="flex items-center gap-1">
                                    <span class="w-2 h-2 rounded-full bg-amber-400"></span> Bon
                                </span>
                                <span class="flex items-center gap-1">
                                    <span class="w-2 h-2 rounded-full bg-red-400"></span> Raté
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Reste du classement (à partir du 4ème) -->
                    <div v-if="currentTournament?.members?.length > 3" class="divide-y divide-gray-100">
                        <div
                            v-for="(member, idx) in currentTournament.members.slice(3)"
                            :key="member.id"
                            :class="[
                                'flex items-center gap-3 px-4 py-3 transition-colors',
                                member.id === $page.props.auth.user.id ? 'bg-indigo-50 border-l-2 border-indigo-400' : 'hover:bg-gray-50'
                            ]"
                        >
                            <!-- Position -->
                            <div class="w-6 text-center">
                                <span class="text-sm font-semibold text-gray-400">{{ idx + 4 }}</span>
                            </div>

                            <!-- Nom -->
                            <div class="flex-1 min-w-0 flex items-center gap-2">
                                <div class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 font-medium text-sm">
                                    {{ member.name?.charAt(0)?.toUpperCase() }}
                                </div>
                                <span class="font-medium text-sm text-gray-700 truncate">{{ member.name }}</span>
                                <span
                                    v-if="member.id === $page.props.auth.user.id"
                                    class="text-[10px] bg-indigo-100 text-indigo-600 px-1.5 py-0.5 rounded font-medium"
                                >
                                    Toi
                                </span>
                            </div>

                            <!-- Stats compactes -->
                            <div class="flex items-center gap-1.5">
                                <span class="text-[10px] bg-emerald-50 text-emerald-600 px-1.5 py-0.5 rounded font-medium">{{ member.pivot?.exact_scores ?? 0 }}</span>
                                <span class="text-[10px] bg-amber-50 text-amber-600 px-1.5 py-0.5 rounded font-medium">{{ member.pivot?.correct_results ?? 0 }}</span>
                                <span class="text-[10px] bg-red-50 text-red-500 px-1.5 py-0.5 rounded font-medium">{{ member.pivot?.wrong_predictions ?? 0 }}</span>
                            </div>

                            <!-- Score -->
                            <div class="w-12 text-right">
                                <span class="text-sm font-bold text-gray-700">{{ member.pivot?.total_points ?? 0 }}</span>
                                <span class="text-[10px] text-gray-400 ml-0.5">pts</span>
                            </div>
                        </div>
                    </div>

                    <!-- Si moins de 3 membres, affichage simplifié -->
                    <div v-else-if="currentTournament?.members?.length > 0 && currentTournament?.members?.length < 3" class="p-4 space-y-2">
                        <div
                            v-for="(member, idx) in currentTournament.members"
                            :key="member.id"
                            :class="[
                                'flex items-center gap-3 p-3 rounded-xl',
                                idx === 0 ? 'bg-amber-50 border border-amber-200' :
                                idx === 1 ? 'bg-slate-50 border border-slate-200' :
                                'bg-orange-50 border border-orange-200'
                            ]"
                        >
                            <span :class="[
                                'w-8 h-8 rounded-full flex items-center justify-center font-bold',
                                idx === 0 ? 'bg-amber-200 text-amber-700' :
                                idx === 1 ? 'bg-slate-200 text-slate-600' :
                                'bg-orange-200 text-orange-700'
                            ]">
                                {{ idx + 1 }}
                            </span>
                            <span class="flex-1 font-medium text-gray-800">{{ member.name }}</span>
                            <span class="font-bold text-gray-700">{{ member.pivot?.total_points ?? 0 }} pts</span>
                        </div>
                    </div>

                    <!-- Message si pas de membres -->
                    <div v-if="!currentTournament?.members?.length" class="p-8 text-center text-gray-500 text-sm">
                        Aucun membre dans ce tournoi
                    </div>
                </div>

                <!-- Matchs du jour -->
                <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
                    <!-- Liens vers les matchs et pronostics -->
                    <div v-if="currentTournament" class="px-3 pt-3 flex justify-between items-center">
                        <!-- Bouton voir tous les pronostics -->
                        <Link
                            :href="route('tournaments.allPredictions', currentTournament.id)"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-white bg-purple-600 hover:bg-purple-700 rounded-lg transition"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            Tous les pronos
                        </Link>

                        <Link
                            :href="route('tournaments.show', currentTournament.id) + '?tab=matches'"
                            class="inline-flex items-center gap-1 text-xs font-medium text-indigo-600 hover:text-indigo-800"
                        >
                            Voir tous les matchs
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </Link>
                    </div>

                    <!-- Indicateur de boosters restants -->
                    <div v-if="currentTournament?.id && getBoosterStats(currentTournament.id).max > 0" class="px-3 pt-3">
                        <div class="flex items-center justify-center gap-2 py-2 px-3 bg-gradient-to-r from-purple-50 to-indigo-50 rounded-xl border border-purple-100">
                            <svg class="w-4 h-4 text-purple-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd" />
                            </svg>
                            <span class="text-xs font-medium text-purple-700">
                                Doubleurs x2 :
                                <span class="font-bold">{{ getBoosterStats(currentTournament.id).remaining }}</span>
                                / {{ getBoosterStats(currentTournament.id).max }}
                            </span>
                        </div>
                    </div>

                    <!-- Header avec navigation -->
                    <div class="flex items-center justify-between p-3 border-b border-gray-100">
                        <button
                            @click="prevDate"
                            :disabled="currentDateIndex <= 0"
                            :class="[
                                'w-10 h-10 rounded-xl flex items-center justify-center transition',
                                currentDateIndex <= 0
                                    ? 'text-gray-300'
                                    : 'text-gray-600 hover:bg-gray-100 active:bg-gray-200'
                            ]"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                            </svg>
                        </button>

                        <div class="text-center">
                            <h3 class="text-sm font-bold text-gray-900 capitalize">
                                {{ formatDate(selectedDate) }}
                            </h3>
                            <p class="text-xs text-gray-500">{{ currentTournamentMatches.length }} match(s)</p>
                        </div>

                        <button
                            @click="nextDate"
                            :disabled="currentDateIndex >= availableDates.length - 1"
                            :class="[
                                'w-10 h-10 rounded-xl flex items-center justify-center transition',
                                currentDateIndex >= availableDates.length - 1
                                    ? 'text-gray-300'
                                    : 'text-gray-600 hover:bg-gray-100 active:bg-gray-200'
                            ]"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                    </div>

                    <!-- Liste des matchs -->
                    <div v-if="currentTournamentMatches.length === 0" class="p-8 text-center">
                        <div class="w-12 h-12 mx-auto mb-3 bg-gray-100 rounded-full flex items-center justify-center">
                            <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <p class="text-gray-500 text-sm">Aucun match ce jour pour ce tournoi</p>
                    </div>

                    <div v-else class="divide-y divide-gray-100">
                        <div
                            v-for="match in currentTournamentMatches"
                            :key="match.id"
                        >
                            <!-- Carte du match -->
                            <div
                                @click="toggleMatchDetails(match)"
                                class="p-4 active:bg-gray-50 transition cursor-pointer"
                            >
                                <!-- Heure et statut -->
                                <div class="flex items-center justify-between mb-3">
                                    <span class="text-xs font-medium text-gray-500">
                                        {{ formatTime(match.scheduled_at) }}
                                    </span>
                                    <div class="flex items-center gap-2">
                                        <!-- Badge x2 si booster actif -->
                                        <span
                                            v-if="hasBooster(match.id)"
                                            class="flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-gradient-to-r from-purple-500 to-indigo-500 text-white"
                                        >
                                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd" />
                                            </svg>
                                            <span>x2</span>
                                        </span>
                                        <span
                                            v-if="match.status === 'completed'"
                                            class="text-[10px] font-medium text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full"
                                        >
                                            Terminé
                                        </span>
                                        <span
                                            v-else
                                            class="text-[10px] font-medium text-blue-600 bg-blue-50 px-2 py-0.5 rounded-full"
                                        >
                                            À venir
                                        </span>
                                        <svg
                                            :class="[
                                                'w-4 h-4 text-gray-400 transition-transform',
                                                selectedMatch?.id === match.id ? 'rotate-180' : ''
                                            ]"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </div>
                                </div>

                                <!-- Match principal -->
                                <div class="flex items-center justify-between gap-3">
                                    <!-- Equipe domicile -->
                                    <div class="flex-1 text-center">
                                        <div class="flex flex-col items-center gap-1">
                                            <TeamFlag :flag="match.home_team?.flag" size="lg" />
                                            <span class="font-semibold text-gray-900 text-sm">
                                                {{ match.home_team?.short_name || match.home_team?.name || 'TBD' }}
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Score central -->
                                    <div class="flex flex-col items-center gap-1 px-3">
                                        <!-- Score réel -->
                                        <div
                                            v-if="match.status === 'completed'"
                                            class="bg-gray-900 text-white px-4 py-2 rounded-lg"
                                        >
                                            <span class="font-bold text-lg">{{ match.home_score }} - {{ match.away_score }}</span>
                                        </div>
                                        <div
                                            v-else
                                            class="bg-gray-100 text-gray-400 px-4 py-2 rounded-lg"
                                        >
                                            <span class="font-medium text-sm">VS</span>
                                        </div>

                                        <!-- Mon pronostic -->
                                        <div class="mt-1">
                                            <div
                                                v-if="userPredictions[match.id]"
                                                :class="[
                                                    'px-3 py-1 rounded-md text-xs font-semibold',
                                                    match.status === 'completed'
                                                        ? resultTypeColor(userPredictions[match.id].result_type)
                                                        : 'bg-indigo-50 text-indigo-600 border border-indigo-200'
                                                ]"
                                            >
                                                <span class="text-[10px] opacity-75">Mon prono : </span>
                                                {{ userPredictions[match.id].home_score }} - {{ userPredictions[match.id].away_score }}
                                            </div>
                                            <Link
                                                v-else-if="match.status === 'scheduled'"
                                                :href="route('predictions.show', match.id)"
                                                class="inline-block px-3 py-1 text-xs font-medium text-indigo-600 bg-indigo-50 rounded-md border border-indigo-200 hover:bg-indigo-100 transition"
                                                @click.stop
                                            >
                                                Pronostiquer
                                            </Link>
                                            <span v-else class="text-[10px] text-gray-400">Pas de prono</span>
                                        </div>
                                    </div>

                                    <!-- Equipe extérieur -->
                                    <div class="flex-1 text-center">
                                        <div class="flex flex-col items-center gap-1">
                                            <TeamFlag :flag="match.away_team?.flag" size="lg" />
                                            <span class="font-semibold text-gray-900 text-sm">
                                                {{ match.away_team?.short_name || match.away_team?.name || 'TBD' }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Bouton Booster x2 (gros bouton) -->
                                <div
                                    v-if="match.status === 'scheduled'"
                                    class="mt-3 flex flex-col items-center gap-1"
                                    @click.stop
                                >
                                    <button
                                        @click="toggleBooster(match)"
                                        :disabled="boosterLoading[match.id] || !canToggleBooster(match)"
                                        :class="[
                                            'flex items-center gap-2 px-4 py-2 rounded-xl font-bold text-sm transition-all',
                                            hasBooster(match.id)
                                                ? 'bg-gradient-to-r from-purple-500 to-indigo-500 text-white shadow-lg hover:shadow-xl hover:scale-105'
                                                : canToggleBooster(match)
                                                    ? 'bg-purple-50 text-purple-600 border-2 border-purple-200 hover:border-purple-400 hover:bg-purple-100'
                                                    : 'bg-gray-100 text-gray-400 border-2 border-gray-200 cursor-not-allowed',
                                            boosterLoading[match.id] ? 'opacity-50 cursor-wait' : ''
                                        ]"
                                    >
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd" />
                                        </svg>
                                        <span v-if="hasBooster(match.id)">Doubleur actif !</span>
                                        <span v-else>Doubler mes points</span>
                                    </button>
                                    <span v-if="!canToggleBooster(match) && !hasBooster(match.id)" class="text-[10px] text-gray-400">
                                        {{ getBoosterStats(match.tournament_id).remaining === 0 ? 'Plus de doubleurs disponibles' : 'Match déjà commencé' }}
                                    </span>
                                </div>
                                <!-- Indicateur booster actif (si match terminé avec booster) -->
                                <div
                                    v-else-if="hasBooster(match.id)"
                                    class="mt-3 flex justify-center"
                                >
                                    <span class="flex items-center gap-2 px-4 py-2 rounded-xl font-bold text-sm bg-gradient-to-r from-purple-500 to-indigo-500 text-white">
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd" />
                                        </svg>
                                        <span>Points doublés !</span>
                                    </span>
                                </div>
                            </div>

                            <!-- Pronostics du tournoi (expanded) -->
                            <div
                                v-if="selectedMatch?.id === match.id && currentTournament"
                                class="bg-gray-50 px-3 pb-3"
                            >
                                <!-- Si pronostics ouverts, on ne peut pas voir les autres -->
                                <div v-if="match.tournament.predictions_open" class="text-center py-4">
                                    <div class="w-10 h-10 mx-auto mb-2 bg-amber-100 rounded-full flex items-center justify-center">
                                        <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m0 0v2m0-2h2m-2 0H9m3-10V6a3 3 0 00-3-3H6a3 3 0 00-3 3v2m0 0h14m-9 4h4" />
                                        </svg>
                                    </div>
                                    <p class="text-sm text-gray-600 font-medium">Pronos caches</p>
                                    <p class="text-xs text-gray-400">Visibles apres fermeture des pronostics</p>
                                </div>

                                <!-- Si pronostics fermes, on affiche tous les pronos -->
                                <template v-else>
                                    <div class="text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide">
                                        Pronos du tournoi
                                    </div>

                                    <div class="grid grid-cols-2 gap-2">
                                        <div
                                            v-for="member in currentTournament.members"
                                            :key="member.id"
                                            :class="[
                                                'p-2 rounded-xl border',
                                                member.id === $page.props.auth.user.id
                                                    ? 'bg-indigo-50 border-indigo-200'
                                                    : getMemberPrediction(match, member.id) && match.status === 'completed'
                                                        ? resultTypeBg(getMemberPrediction(match, member.id).result_type)
                                                        : 'bg-white border-gray-200'
                                            ]"
                                        >
                                            <div class="flex items-center justify-between">
                                                <span class="text-xs font-medium text-gray-700 truncate">{{ member.name }}</span>
                                                <span
                                                    v-if="getMemberPrediction(match, member.id)"
                                                    :class="[
                                                        'text-xs font-bold px-1.5 py-0.5 rounded',
                                                        match.status === 'completed'
                                                            ? resultTypeColor(getMemberPrediction(match, member.id).result_type)
                                                            : 'bg-gray-200 text-gray-600'
                                                    ]"
                                                >
                                                    {{ getMemberPrediction(match, member.id).home_score }}-{{ getMemberPrediction(match, member.id).away_score }}
                                                </span>
                                                <span v-else class="text-xs text-gray-400">-</span>
                                            </div>
                                        </div>
                                    </div>

                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
            <!-- Classement des poules du jour (si matchs de poule aujourd'hui) -->
            <div
                v-if="hasGroupMatchesToday"
                class="bg-white rounded-2xl shadow-sm overflow-hidden"
            >
                <div class="p-3 border-b border-gray-100 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-emerald-100 flex items-center justify-center">
                            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-gray-900">Poules du jour</h3>
                            <p class="text-[10px] text-gray-500">{{ todaysGroups.length }} poule(s) en jeu</p>
                        </div>
                    </div>
                    <Link
                        :href="route('tournaments.show', currentTournament.id)"
                        class="text-xs font-medium text-indigo-600 hover:text-indigo-800 flex items-center gap-1"
                    >
                        Voir tout
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </Link>
                </div>

                <!-- Classements des poules du jour -->
                <div class="p-3 space-y-3">
                    <div
                        v-for="group in todaysGroups"
                        :key="group.id"
                        class="bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl overflow-hidden"
                    >
                        <!-- Header de la poule -->
                        <div class="bg-gradient-to-r from-emerald-600 to-emerald-500 px-3 py-2">
                            <h4 class="text-sm font-bold text-white text-center">{{ group.name }}</h4>
                        </div>

                        <!-- Tableau classement -->
                        <div class="p-2">
                            <table class="w-full text-xs">
                                <thead>
                                <tr class="text-[10px] text-gray-500 uppercase">
                                    <th class="text-left py-1 px-1 w-6">#</th>
                                    <th class="text-left py-1 px-1">Equipe</th>
                                    <th class="text-center py-1 px-1 w-6">J</th>
                                    <th class="text-center py-1 px-1 w-6">G</th>
                                    <th class="text-center py-1 px-1 w-6">N</th>
                                    <th class="text-center py-1 px-1 w-6">P</th>
                                    <th class="text-center py-1 px-1 w-8">+/-</th>
                                    <th class="text-center py-1 px-1 w-8 font-bold">Pts</th>
                                </tr>
                                </thead>
                                <tbody>
                                <tr
                                    v-for="(team, idx) in group.teams"
                                    :key="team.id"
                                    :class="[
                                                'border-t border-gray-200',
                                                idx < 2 ? 'bg-emerald-50' : 'bg-white'
                                            ]"
                                >
                                    <td class="py-1.5 px-1">
                                                <span :class="[
                                                    'w-5 h-5 rounded flex items-center justify-center text-[10px] font-bold',
                                                    idx < 2 ? 'bg-emerald-500 text-white' : 'bg-gray-200 text-gray-600'
                                                ]">
                                                    {{ idx + 1 }}
                                                </span>
                                    </td>
                                    <td class="py-1.5 px-1">
                                        <div class="flex items-center gap-1.5">
                                            <TeamFlag :flag="team.flag" size="sm" />
                                            <span class="font-medium text-gray-800 truncate">
                                                        {{ team.short_name || team.name }}
                                                    </span>
                                        </div>
                                    </td>
                                    <td class="text-center py-1.5 px-1 text-gray-600">{{ team.pivot?.played ?? 0 }}</td>
                                    <td class="text-center py-1.5 px-1 text-gray-600">{{ team.pivot?.won ?? 0 }}</td>
                                    <td class="text-center py-1.5 px-1 text-gray-600">{{ team.pivot?.drawn ?? 0 }}</td>
                                    <td class="text-center py-1.5 px-1 text-gray-600">{{ team.pivot?.lost ?? 0 }}</td>
                                    <td class="text-center py-1.5 px-1">
                                                <span :class="[
                                                    'font-medium',
                                                    (team.pivot?.goal_difference ?? 0) > 0 ? 'text-emerald-600' :
                                                    (team.pivot?.goal_difference ?? 0) < 0 ? 'text-red-500' : 'text-gray-500'
                                                ]">
                                                    {{ (team.pivot?.goal_difference ?? 0) > 0 ? '+' : '' }}{{ team.pivot?.goal_difference ?? 0 }}
                                                </span>
                                    </td>
                                    <td class="text-center py-1.5 px-1 font-bold text-gray-900">{{ team.pivot?.points ?? 0 }}</td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Accès rapide aux poules (si PAS de matchs de poule aujourd'hui) -->
            <div
                v-else-if="currentTournament?.tournament_groups?.length > 0"
                class="bg-white rounded-2xl shadow-sm overflow-hidden"
            >
                <div class="p-3 border-b border-gray-100 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center">
                            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                            </svg>
                        </div>
                        <h3 class="text-sm font-bold text-gray-900">Classement des poules</h3>
                    </div>
                    <Link
                        :href="route('tournaments.show', currentTournament.id)"
                        class="text-xs font-medium text-indigo-600 hover:text-indigo-800 flex items-center gap-1"
                    >
                        Voir tout
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </Link>
                </div>

                <!-- Liste des poules en scroll horizontal -->
                <div class="p-3 overflow-x-auto">
                    <div class="flex gap-3" style="min-width: max-content;">
                        <div
                            v-for="group in currentTournament.tournament_groups.slice(0, 4)"
                            :key="group.id"
                            class="w-44 flex-shrink-0 bg-gray-50 rounded-xl p-3"
                        >
                            <!-- Nom du groupe -->
                            <div class="text-xs font-bold text-gray-700 mb-2 text-center">
                                {{ group.name }}
                            </div>

                            <!-- Mini classement -->
                            <div class="space-y-1">
                                <div
                                    v-for="(team, idx) in group.teams.slice(0, 4)"
                                    :key="team.id"
                                    :class="[
                                            'flex items-center justify-between px-2 py-1.5 rounded-lg text-xs',
                                            idx < 2 ? 'bg-emerald-100' : 'bg-white'
                                        ]"
                                >
                                    <div class="flex items-center gap-1.5">
                                            <span :class="[
                                                'w-4 h-4 rounded flex items-center justify-center text-[10px] font-bold',
                                                idx < 2 ? 'bg-emerald-500 text-white' : 'bg-gray-200 text-gray-600'
                                            ]">
                                                {{ idx + 1 }}
                                            </span>
                                        <TeamFlag :flag="team.flag" size="sm" />
                                        <span class="font-medium text-gray-800 truncate max-w-[60px]">
                                                {{ team.short_name || team.name }}
                                            </span>
                                    </div>
                                    <span class="font-bold text-gray-700">
                                            {{ team.pivot?.points ?? 0 }}
                                        </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Indicateur de scroll si plus de 4 groupes -->
                <div
                    v-if="currentTournament.tournament_groups.length > 4"
                    class="text-center pb-3"
                >
                        <span class="text-xs text-gray-400">
                            Glissez pour voir les {{ currentTournament.tournament_groups.length - 4 }} autres poules
                        </span>
                </div>
            </div>

            <!-- Pronostic Vainqueur du Tournoi -->
            <div v-if="currentTournament?.teams?.length > 0" class="bg-white rounded-2xl shadow-sm overflow-hidden">
                <!-- Header -->
                <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-yellow-100 flex items-center justify-center">
                            <svg class="w-4 h-4 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                            </svg>
                        </div>
                        <h3 class="text-sm font-bold text-gray-800">Pronostic Vainqueur</h3>
                    </div>
                    <!-- Bouton modifier (si pronostics ouverts et non verrouillés définitivement) -->
                    <button
                        v-if="currentTournament?.predictions_open && !currentTournament?.winner_predictions_locked && !showWinnerForm"
                        @click="initWinnerForm"
                        class="text-xs font-medium text-indigo-600 hover:text-indigo-800"
                    >
                        {{ currentWinnerPrediction ? 'Modifier' : 'Pronostiquer' }}
                    </button>
                </div>

                <!-- Vainqueur déjà désigné -->
                <div v-if="currentTournament?.winner_team_id" class="p-4">
                    <div class="text-center mb-3">
                        <span class="text-xs font-medium text-emerald-600 bg-emerald-50 px-2 py-1 rounded-full">
                            Tournoi terminé
                        </span>
                    </div>
                    <div class="bg-gradient-to-b from-yellow-50 to-amber-50 rounded-xl p-4 border border-yellow-200 text-center">
                        <div class="text-xs text-yellow-600 font-medium mb-2">Vainqueur</div>
                        <div class="flex items-center justify-center gap-2 mb-2">
                            <TeamFlag :flag="currentTournament.winner_team?.flag" size="lg" />
                            <span class="font-bold text-lg text-gray-900">
                                {{ currentTournament.winner_team?.name }}
                            </span>
                        </div>
                        <!-- Afficher les points gagnés si l'utilisateur avait un pronostic -->
                        <div v-if="currentWinnerPrediction" class="mt-3 pt-3 border-t border-yellow-200">
                            <div v-if="currentWinnerPrediction.points_earned > 0" class="text-emerald-600 font-bold">
                                +{{ currentWinnerPrediction.points_earned }} points
                            </div>
                            <div v-else class="text-gray-500 text-sm">
                                Pas de points bonus
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Formulaire de pronostic (si pronostics ouverts et non verrouillés) -->
                <div v-else-if="showWinnerForm && currentTournament?.predictions_open && !currentTournament?.winner_predictions_locked" class="p-4">
                    <div class="space-y-3">
                        <!-- 1er choix -->
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">
                                1er choix <span class="text-amber-500">(+30 pts)</span>
                            </label>
                            <select
                                v-model="winnerForm.first_choice_team_id"
                                class="w-full rounded-lg border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500"
                            >
                                <option :value="null">Sélectionner...</option>
                                <option v-for="team in availableForFirst" :key="team.id" :value="team.id">
                                    {{ team.name }}
                                </option>
                            </select>
                        </div>

                        <!-- 2ème choix -->
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">
                                2ème choix <span class="text-gray-400">(+20 pts)</span>
                            </label>
                            <select
                                v-model="winnerForm.second_choice_team_id"
                                class="w-full rounded-lg border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500"
                                :disabled="!winnerForm.first_choice_team_id"
                            >
                                <option :value="null">Sélectionner...</option>
                                <option v-for="team in availableForSecond" :key="team.id" :value="team.id">
                                    {{ team.name }}
                                </option>
                            </select>
                        </div>

                        <!-- 3ème choix -->
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">
                                3ème choix <span class="text-orange-400">(+10 pts)</span>
                            </label>
                            <select
                                v-model="winnerForm.third_choice_team_id"
                                class="w-full rounded-lg border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500"
                                :disabled="!winnerForm.second_choice_team_id"
                            >
                                <option :value="null">Sélectionner...</option>
                                <option v-for="team in availableForThird" :key="team.id" :value="team.id">
                                    {{ team.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Boutons -->
                        <div class="flex gap-2 pt-2">
                            <button
                                @click="showWinnerForm = false"
                                class="flex-1 px-4 py-2 text-sm font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition"
                            >
                                Annuler
                            </button>
                            <button
                                @click="submitWinnerPrediction"
                                :disabled="!winnerForm.first_choice_team_id || !winnerForm.second_choice_team_id || !winnerForm.third_choice_team_id"
                                :class="[
                                    'flex-1 px-4 py-2 text-sm font-medium rounded-lg transition',
                                    winnerForm.first_choice_team_id && winnerForm.second_choice_team_id && winnerForm.third_choice_team_id
                                        ? 'bg-indigo-600 text-white hover:bg-indigo-700'
                                        : 'bg-gray-200 text-gray-400 cursor-not-allowed'
                                ]"
                            >
                                Valider
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Affichage du pronostic existant (si pas en mode édition) -->
                <div v-else-if="currentWinnerPrediction && !showWinnerForm" class="p-4">
                    <div class="space-y-2">
                        <!-- 1er choix -->
                        <div class="flex items-center justify-between p-3 bg-gradient-to-r from-amber-50 to-yellow-50 rounded-xl border border-amber-200">
                            <div class="flex items-center gap-2">
                                <span class="w-6 h-6 rounded-full bg-amber-200 text-amber-700 flex items-center justify-center text-xs font-bold">1</span>
                                <TeamFlag :flag="currentWinnerPrediction.first_choice_team?.flag" size="md" />
                                <span class="font-medium text-gray-800">
                                    {{ currentWinnerPrediction.first_choice_team?.name }}
                                </span>
                            </div>
                            <span class="text-xs font-medium text-amber-600">+30 pts</span>
                        </div>

                        <!-- 2ème choix -->
                        <div class="flex items-center justify-between p-3 bg-gradient-to-r from-slate-50 to-gray-50 rounded-xl border border-slate-200">
                            <div class="flex items-center gap-2">
                                <span class="w-6 h-6 rounded-full bg-slate-200 text-slate-600 flex items-center justify-center text-xs font-bold">2</span>
                                <TeamFlag :flag="currentWinnerPrediction.second_choice_team?.flag" size="md" />
                                <span class="font-medium text-gray-800">
                                    {{ currentWinnerPrediction.second_choice_team?.name }}
                                </span>
                            </div>
                            <span class="text-xs font-medium text-slate-500">+20 pts</span>
                        </div>

                        <!-- 3ème choix -->
                        <div class="flex items-center justify-between p-3 bg-gradient-to-r from-orange-50 to-amber-50 rounded-xl border border-orange-200">
                            <div class="flex items-center gap-2">
                                <span class="w-6 h-6 rounded-full bg-orange-200 text-orange-700 flex items-center justify-center text-xs font-bold">3</span>
                                <TeamFlag :flag="currentWinnerPrediction.third_choice_team?.flag" size="md" />
                                <span class="font-medium text-gray-800">
                                    {{ currentWinnerPrediction.third_choice_team?.name }}
                                </span>
                            </div>
                            <span class="text-xs font-medium text-orange-500">+10 pts</span>
                        </div>
                    </div>

                    <!-- Message si pronostics fermés ou verrouillés définitivement -->
                    <div v-if="currentTournament?.winner_predictions_locked || !currentTournament?.predictions_open" class="mt-3 text-center">
                        <span class="text-xs text-gray-400">
                            {{ currentTournament?.winner_predictions_locked ? 'Pronostic vainqueur verrouillé' : 'Pronostics fermés' }}
                        </span>
                    </div>
                </div>

                <!-- Pas de pronostic et pronostics fermés/verrouillés -->
                <div v-else-if="(currentTournament?.winner_predictions_locked || !currentTournament?.predictions_open) && !currentWinnerPrediction" class="p-6 text-center">
                    <div class="w-12 h-12 mx-auto mb-3 bg-gray-100 rounded-full flex items-center justify-center">
                        <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m0 0v2m0-2h2m-2 0H9m3-10V6a3 3 0 00-3-3H6a3 3 0 00-3 3v2m0 0h14m-9 4h4" />
                        </svg>
                    </div>
                    <p class="text-sm text-gray-500">Pas de pronostic vainqueur</p>
                    <p class="text-xs text-gray-400">
                        {{ currentTournament?.winner_predictions_locked ? 'Les pronostics vainqueur sont définitivement verrouillés' : 'Les pronostics sont fermés' }}
                    </p>
                </div>

                <!-- Pas de pronostic et pronostics ouverts (non verrouillés) -->
                <div v-else-if="currentTournament?.predictions_open && !currentTournament?.winner_predictions_locked && !currentWinnerPrediction && !showWinnerForm" class="p-6 text-center">
                    <div class="w-12 h-12 mx-auto mb-3 bg-yellow-100 rounded-full flex items-center justify-center">
                        <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                        </svg>
                    </div>
                    <p class="text-sm text-gray-600 font-medium mb-2">Qui va gagner ?</p>
                    <p class="text-xs text-gray-400 mb-3">Choisis tes 3 favoris pour gagner des points bonus</p>
                    <button
                        @click="initWinnerForm"
                        class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition"
                    >
                        Faire mon pronostic
                    </button>
                </div>

                <!-- Lien vers tous les pronostics vainqueur -->
                <div class="px-4 pb-4 pt-2 border-t border-gray-100 text-center">
                    <Link
                        :href="route('tournaments.allWinnerPredictions', currentTournament.id)"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-amber-500 text-white rounded-lg text-sm font-medium hover:bg-amber-600 transition"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        Voir tous les pronostics vainqueur
                    </Link>
                </div>
            </div>

            <!-- Pronostics Vainqueur du Tournoi (si verrouillés) -->
            <div v-if="currentTournament?.winner_predictions_locked" class="bg-white rounded-2xl shadow-sm overflow-hidden">
                <!-- Header -->
                <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-purple-100 flex items-center justify-center">
                            <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-gray-800">Pronos Vainqueur du Tournoi</h3>
                            <p class="text-[10px] text-gray-500">Qui a choisi quelle équipe ?</p>
                        </div>
                    </div>
                    <Link
                        :href="route('tournaments.show', currentTournament.id)"
                        class="text-xs font-medium text-indigo-600 hover:text-indigo-800 flex items-center gap-1"
                    >
                        Voir tout
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </Link>
                </div>

                <!-- Vainqueur officiel si désigné -->
                <div v-if="currentTournament?.winner_team_id" class="mx-4 mt-4 p-3 bg-gradient-to-r from-yellow-50 to-amber-50 rounded-xl border border-yellow-200 text-center">
                    <div class="text-[10px] text-yellow-600 font-medium mb-1 uppercase tracking-wide">Vainqueur</div>
                    <div class="flex items-center justify-center gap-2">
                        <TeamFlag :flag="currentTournament.winner_team?.flag" size="md" />
                        <span class="font-bold text-gray-900">
                            {{ currentTournament.winner_team?.name }}
                        </span>
                    </div>
                </div>

                <!-- Liste des pronostics des membres -->
                <div class="p-4 space-y-2">
                    <div
                        v-for="member in currentTournament.members"
                        :key="member.id"
                        :class="[
                            'p-3 rounded-xl border',
                            member.id === $page.props.auth.user.id
                                ? 'bg-indigo-50 border-indigo-200'
                                : 'bg-gray-50 border-gray-200'
                        ]"
                    >
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium text-gray-800">{{ member.name }}</span>
                            <span
                                v-if="currentTournamentMembersWinnerPredictions[member.id]?.points_earned"
                                class="text-xs font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full"
                            >
                                +{{ currentTournamentMembersWinnerPredictions[member.id].points_earned }} pts
                            </span>
                        </div>

                        <!-- Choix du membre -->
                        <div v-if="currentTournamentMembersWinnerPredictions[member.id]" class="flex gap-2">
                            <!-- 1er choix -->
                            <div :class="[
                                'flex-1 flex items-center gap-1.5 px-2 py-1.5 rounded-lg text-xs',
                                currentTournamentMembersWinnerPredictions[member.id].first_choice_team_id === currentTournament.winner_team_id
                                    ? 'bg-emerald-100 border border-emerald-300'
                                    : 'bg-amber-50 border border-amber-200'
                            ]">
                                <span class="w-4 h-4 rounded-full bg-amber-200 text-amber-700 flex items-center justify-center text-[10px] font-bold flex-shrink-0">1</span>
                                <TeamFlag :flag="currentTournamentMembersWinnerPredictions[member.id].first_choice_team?.flag" size="sm" />
                                <span class="truncate font-medium" :class="{
                                    'text-emerald-700': currentTournamentMembersWinnerPredictions[member.id].first_choice_team_id === currentTournament.winner_team_id
                                }">
                                    {{ currentTournamentMembersWinnerPredictions[member.id].first_choice_team?.short_name || currentTournamentMembersWinnerPredictions[member.id].first_choice_team?.name }}
                                </span>
                            </div>

                            <!-- 2ème choix -->
                            <div :class="[
                                'flex-1 flex items-center gap-1.5 px-2 py-1.5 rounded-lg text-xs',
                                currentTournamentMembersWinnerPredictions[member.id].second_choice_team_id === currentTournament.winner_team_id
                                    ? 'bg-emerald-100 border border-emerald-300'
                                    : 'bg-slate-50 border border-slate-200'
                            ]">
                                <span class="w-4 h-4 rounded-full bg-slate-200 text-slate-600 flex items-center justify-center text-[10px] font-bold flex-shrink-0">2</span>
                                <TeamFlag :flag="currentTournamentMembersWinnerPredictions[member.id].second_choice_team?.flag" size="sm" />
                                <span class="truncate font-medium" :class="{
                                    'text-emerald-700': currentTournamentMembersWinnerPredictions[member.id].second_choice_team_id === currentTournament.winner_team_id
                                }">
                                    {{ currentTournamentMembersWinnerPredictions[member.id].second_choice_team?.short_name || currentTournamentMembersWinnerPredictions[member.id].second_choice_team?.name }}
                                </span>
                            </div>

                            <!-- 3ème choix -->
                            <div :class="[
                                'flex-1 flex items-center gap-1.5 px-2 py-1.5 rounded-lg text-xs',
                                currentTournamentMembersWinnerPredictions[member.id].third_choice_team_id === currentTournament.winner_team_id
                                    ? 'bg-emerald-100 border border-emerald-300'
                                    : 'bg-orange-50 border border-orange-200'
                            ]">
                                <span class="w-4 h-4 rounded-full bg-orange-200 text-orange-700 flex items-center justify-center text-[10px] font-bold flex-shrink-0">3</span>
                                <TeamFlag :flag="currentTournamentMembersWinnerPredictions[member.id].third_choice_team?.flag" size="sm" />
                                <span class="truncate font-medium" :class="{
                                    'text-emerald-700': currentTournamentMembersWinnerPredictions[member.id].third_choice_team_id === currentTournament.winner_team_id
                                }">
                                    {{ currentTournamentMembersWinnerPredictions[member.id].third_choice_team?.short_name || currentTournamentMembersWinnerPredictions[member.id].third_choice_team?.name }}
                                </span>
                            </div>
                        </div>

                        <!-- Pas de pronostic -->
                        <div v-else class="text-xs text-gray-400 text-center py-2">
                            Pas de pronostic
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </AuthenticatedLayout>
</template>
