<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TeamFlag from '@/Components/TeamFlag.vue';
import StatsChart from '@/Components/Tournament/StatsChart.vue';
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    tournament: Object,
    isOwner: Boolean,
    isMember: Boolean,
    isAdmin: Boolean,
    predefinedTeams: Array,
    userWinnerPrediction: Object,
    userPredictions: Object,
    membersWinnerPredictions: Object,
    myTournaments: Array,
    userLoserPrediction: Object,
    userTopScorerPrediction: Object,
    userLastPlacePrediction: Object,
    statsData: Object,
});

// Navigation entre tournois (comme le Dashboard)
const currentIdx = computed(() => (props.myTournaments ?? []).findIndex(t => t.id === props.tournament.id));
const goPrev = () => {
    const idx = currentIdx.value;
    if (idx > 0) router.visit(route('tournaments.show', props.myTournaments[idx - 1].id));
};
const goNext = () => {
    const idx = currentIdx.value;
    if (idx < (props.myTournaments?.length ?? 0) - 1) router.visit(route('tournaments.show', props.myTournaments[idx + 1].id));
};

// Récupérer le paramètre tab de l'URL si présent
const urlParams = new URLSearchParams(window.location.search);
const tabParam = urlParams.get('tab');

// Par défaut, afficher les poules si le tournoi en a, sinon les équipes
const getDefaultTab = () => {
    if (tabParam && ['teams', 'poules', 'matches', 'members', 'winner', 'stats'].includes(tabParam)) {
        return tabParam;
    }
    return props.tournament.format === 'groups_elimination' ? 'poules' : 'teams';
};
const activeTab = ref(getDefaultTab());
const showAddTeamModal = ref(false);
const showPredefinedTeamsModal = ref(false);
const showAddMatchModal = ref(false);
const showCreateGroupModal = ref(false);
const showAddTeamToGroupModal = ref(false);
const selectedGroupForTeam = ref(null);
const searchQuery = ref('');
const selectedConfederation = ref('');
const selectedTeams = ref([]);

const teamForm = useForm({
    name: '',
    short_name: '',
    flag: '',
});

const matchForm = useForm({
    home_team_id: '',
    away_team_id: '',
    round: 'group',
    scheduled_at: '',
});

const groupForm = useForm({
    name: '',
});

const selectedTeamsForGroup = ref([]);

// Confederations list
const confederations = [
    { value: '', label: 'Toutes' },
    { value: 'UEFA', label: 'Europe (UEFA)' },
    { value: 'CONMEBOL', label: 'Amerique du Sud (CONMEBOL)' },
    { value: 'CONCACAF', label: 'Amerique Nord/Centre (CONCACAF)' },
    { value: 'CAF', label: 'Afrique (CAF)' },
    { value: 'AFC', label: 'Asie (AFC)' },
    { value: 'OFC', label: 'Oceanie (OFC)' },
];

// Filter predefined teams
const filteredPredefinedTeams = computed(() => {
    let teams = props.predefinedTeams || [];

    if (selectedConfederation.value) {
        teams = teams.filter(t => t.confederation === selectedConfederation.value);
    }

    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        teams = teams.filter(t =>
            t.name.toLowerCase().includes(query) ||
            t.short_name.toLowerCase().includes(query)
        );
    }

    return teams;
});

// Get existing team names for disabling already added teams
const existingTeamNames = computed(() => {
    return props.tournament.teams.map(t => t.name);
});

// Teams not assigned to any group
const unassignedTeams = computed(() => {
    const assignedTeamIds = new Set();
    props.tournament.tournament_groups?.forEach(group => {
        group.teams?.forEach(team => {
            assignedTeamIds.add(team.id);
        });
    });
    return props.tournament.teams.filter(t => !assignedTeamIds.has(t.id));
});

const toggleTeamSelection = (team) => {
    const index = selectedTeams.value.findIndex(t => t.name === team.name);
    if (index === -1) {
        selectedTeams.value.push(team);
    } else {
        selectedTeams.value.splice(index, 1);
    }
};

const isTeamSelected = (team) => {
    return selectedTeams.value.some(t => t.name === team.name);
};

const isTeamAlreadyAdded = (team) => {
    return existingTeamNames.value.includes(team.name);
};

const importSelectedTeams = () => {
    if (selectedTeams.value.length === 0) return;

    router.post(route('tournaments.teams.import', props.tournament.id), {
        teams: selectedTeams.value.map(t => ({
            name: t.name,
            short_name: t.short_name,
            flag: t.flag,
        })),
    }, {
        onSuccess: () => {
            showPredefinedTeamsModal.value = false;
            selectedTeams.value = [];
            searchQuery.value = '';
            selectedConfederation.value = '';
        },
    });
};

const submitTeam = () => {
    teamForm.post(route('tournaments.teams.store', props.tournament.id), {
        onSuccess: () => {
            showAddTeamModal.value = false;
            teamForm.reset();
        },
    });
};

const submitMatch = () => {
    matchForm.post(route('tournaments.matches.store', props.tournament.id), {
        onSuccess: () => {
            showAddMatchModal.value = false;
            matchForm.reset();
        },
    });
};

const deleteTeam = (teamId) => {
    if (confirm('Supprimer cette equipe ?')) {
        router.delete(route('tournaments.teams.destroy', [props.tournament.id, teamId]));
    }
};

const activateTournament = () => {
    router.post(route('tournaments.activate', props.tournament.id));
};

const togglePredictions = () => {
    router.post(route('tournaments.togglePredictions', props.tournament.id));
};

const snapshotProcessing = ref(false);
const snapshotDone = ref(false);
const takeSnapshot = () => {
    router.post(route('tournaments.snapshot', props.tournament.id), {}, {
        preserveScroll: true,
        onStart: () => { snapshotProcessing.value = true; snapshotDone.value = false; },
        onSuccess: () => {
            snapshotDone.value = true;
            setTimeout(() => { snapshotDone.value = false; }, 4000);
        },
        onFinish: () => { snapshotProcessing.value = false; },
    });
};

const backfillProcessing = ref(false);
const backfillStats = () => {
    if (!confirm("Reconstruire l'historique des statistiques depuis le début du tournoi à partir des matchs terminés ?\n\nLes relevés déjà enregistrés ne seront pas modifiés. Les points bonus spéciaux (vainqueur, buteur…) ne sont pas inclus dans l'historique reconstruit.")) {
        return;
    }
    router.post(route('tournaments.backfillStats', props.tournament.id), {}, {
        preserveScroll: true,
        onStart: () => { backfillProcessing.value = true; },
        onFinish: () => { backfillProcessing.value = false; },
    });
};

const recapProcessing = ref(false);
const recapDone = ref(false);
const publishRecap = () => {
    if (!confirm("Publier le récap du jour ?\n\nAssure-toi d'avoir saisi tous les scores du jour. Les matchs terminés pas encore inclus dans un récap seront ajoutés, et les joueurs verront le récap à leur prochaine visite.")) {
        return;
    }
    router.post(route('tournaments.publishRecap', props.tournament.id), {}, {
        preserveScroll: true,
        onStart: () => { recapProcessing.value = true; recapDone.value = false; },
        onSuccess: () => {
            recapDone.value = true;
            setTimeout(() => { recapDone.value = false; }, 4000);
        },
        onFinish: () => { recapProcessing.value = false; },
    });
};

const initRecapProcessing = ref(false);
const initRecap = () => {
    if (!confirm("Initialiser la baseline du récap ?\n\nÀ faire UNE SEULE FOIS : tous les matchs déjà terminés seront marqués comme « déjà vus » (sans popup). Ensuite, chaque récap ne contiendra que les nouveaux matchs du jour. Rien n'est supprimé.")) {
        return;
    }
    router.post(route('tournaments.initRecap', props.tournament.id), {}, {
        preserveScroll: true,
        onStart: () => { initRecapProcessing.value = true; },
        onFinish: () => { initRecapProcessing.value = false; },
    });
};

// Schedule management
const showScheduleModal = ref(false);
const selectedMatchForSchedule = ref(null);
const scheduleForm = useForm({
    scheduled_at: '',
});

const formatDateForInput = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    return `${year}-${month}-${day}T${hours}:${minutes}`;
};

const openScheduleModal = (match) => {
    selectedMatchForSchedule.value = match;
    scheduleForm.scheduled_at = formatDateForInput(match.scheduled_at);
    showScheduleModal.value = true;
};

const submitSchedule = () => {
    scheduleForm.patch(route('tournaments.matches.schedule', [props.tournament.id, selectedMatchForSchedule.value.id]), {
        preserveScroll: true,
        onSuccess: () => {
            showScheduleModal.value = false;
            selectedMatchForSchedule.value = null;
        },
    });
};

// Result management
const showResultModal = ref(false);
const selectedMatchForResult = ref(null);
const resultForm = useForm({
    home_score: 0,
    away_score: 0,
});

const openResultModal = (match) => {
    selectedMatchForResult.value = match;
    resultForm.home_score = match.home_score ?? 0;
    resultForm.away_score = match.away_score ?? 0;
    showResultModal.value = true;
};

const submitResult = () => {
    resultForm.post(route('tournaments.matches.result', [props.tournament.id, selectedMatchForResult.value.id]), {
        onSuccess: () => {
            showResultModal.value = false;
            selectedMatchForResult.value = null;
            resultForm.reset();
        },
    });
};

// Classement des equipes par poule (utilise les donnees pivot de la BDD)
const getGroupStandings = (group) => {
    // Les equipes sont deja triees par la relation Eloquent (points, goal_difference, goals_for)
    // et les stats sont stockees dans le pivot
    return group.teams.map(team => ({
        team: team,
        played: team.pivot?.played ?? 0,
        won: team.pivot?.won ?? 0,
        drawn: team.pivot?.drawn ?? 0,
        lost: team.pivot?.lost ?? 0,
        goals_for: team.pivot?.goals_for ?? 0,
        goals_against: team.pivot?.goals_against ?? 0,
        goal_difference: team.pivot?.goal_difference ?? 0,
        points: team.pivot?.points ?? 0,
    }));
};

// Group management
const createGroup = () => {
    groupForm.post(route('tournaments.groups.store', props.tournament.id), {
        onSuccess: () => {
            showCreateGroupModal.value = false;
            groupForm.reset();
        },
    });
};

const deleteGroup = (groupId) => {
    if (confirm('Supprimer ce groupe et retirer toutes les equipes ?')) {
        router.delete(route('tournaments.groups.destroy', [props.tournament.id, groupId]));
    }
};

const openAddTeamToGroup = (group) => {
    selectedGroupForTeam.value = group;
    selectedTeamsForGroup.value = [];
    showAddTeamToGroupModal.value = true;
};

const toggleTeamForGroup = (teamId) => {
    const index = selectedTeamsForGroup.value.indexOf(teamId);
    if (index === -1) {
        selectedTeamsForGroup.value.push(teamId);
    } else {
        selectedTeamsForGroup.value.splice(index, 1);
    }
};

const addTeamToGroup = () => {
    if (selectedTeamsForGroup.value.length === 0) return;

    router.post(route('tournaments.groups.addTeam', [props.tournament.id, selectedGroupForTeam.value.id]), {
        team_ids: selectedTeamsForGroup.value,
    }, {
        onSuccess: () => {
            showAddTeamToGroupModal.value = false;
            selectedTeamsForGroup.value = [];
        },
    });
};

const removeTeamFromGroup = (groupId, teamId) => {
    router.delete(route('tournaments.groups.removeTeam', [props.tournament.id, groupId, teamId]));
};

const showGenerateMatchesModal = ref(false);
const generateMatchesForm = useForm({
    scheduled_at: '',
});

const generateGroupMatches = () => {
    showGenerateMatchesModal.value = true;
};

const submitGenerateMatches = () => {
    generateMatchesForm.post(route('tournaments.groups.generateMatches', props.tournament.id), {
        onSuccess: () => {
            showGenerateMatchesModal.value = false;
            generateMatchesForm.reset();
        },
    });
};

const createDefaultGroups = () => {
    const groupNames = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L'];

    router.post(route('tournaments.groups.storeMultiple', props.tournament.id), {
        groups: groupNames.map(letter => 'Groupe ' + letter),
    }, { preserveScroll: true });
};

const formatLabel = computed(() => {
    return props.tournament.format === 'elimination' ? 'Eliminatoire direct' : 'Poules + Eliminatoires';
});

const statusLabel = computed(() => {
    const labels = { draft: 'Brouillon', active: 'En cours', completed: 'Termine' };
    return labels[props.tournament.status];
});

const roundLabels = {
    group: 'Phase de poules',
    round_of_32: '32emes',
    round_of_16: '8emes',
    quarter: 'Quarts',
    semi: 'Demis',
    final: 'Finale',
};

const groupedMatches = computed(() => {
    const groups = {};
    props.tournament.matches.forEach(match => {
        const key = match.round;
        if (!groups[key]) groups[key] = [];
        groups[key].push(match);
    });
    return groups;
});

// Matchs de poule regroupés par groupe (Groupe A, Groupe B, etc.)
const matchesByPoule = computed(() => {
    const poules = {};
    const groupMatches = props.tournament.matches.filter(m => m.round === 'group');

    groupMatches.forEach(match => {
        const groupId = match.tournament_group_id;
        const group = props.tournament.tournament_groups?.find(g => g.id === groupId);
        const groupName = group?.name || 'Non assigné';

        if (!poules[groupName]) {
            poules[groupName] = {
                id: groupId,
                name: groupName,
                matches: []
            };
        }
        poules[groupName].matches.push(match);
    });

    // Trier par nom de groupe (Groupe A, Groupe B, etc.)
    return Object.values(poules).sort((a, b) => a.name.localeCompare(b.name));
});

// Matchs éliminatoires (non phase de poules)
const knockoutMatches = computed(() => {
    const rounds = {};
    const knockoutRounds = ['round_of_32', 'round_of_16', 'quarter', 'semi', 'final'];

    props.tournament.matches
        .filter(m => knockoutRounds.includes(m.round))
        .forEach(match => {
            const key = match.round;
            if (!rounds[key]) rounds[key] = [];
            rounds[key].push(match);
        });

    return rounds;
});

// Count group stage matches
const groupMatchesCount = computed(() => {
    return props.tournament.matches.filter(m => m.round === 'group').length;
});

// Total expected group matches
const expectedGroupMatches = computed(() => {
    let total = 0;
    props.tournament.tournament_groups?.forEach(group => {
        const n = group.teams?.length || 0;
        // Round-robin: n*(n-1)/2 matches per group
        total += (n * (n - 1)) / 2;
    });
    return total;
});

// Pronostic Vainqueur
const showWinnerForm = ref(false);
const winnerForm = useForm({
    first_choice_team_id: null,
    second_choice_team_id: null,
    third_choice_team_id: null,
});

const initWinnerForm = () => {
    if (props.userWinnerPrediction) {
        winnerForm.first_choice_team_id = props.userWinnerPrediction.first_choice_team_id;
        winnerForm.second_choice_team_id = props.userWinnerPrediction.second_choice_team_id;
        winnerForm.third_choice_team_id = props.userWinnerPrediction.third_choice_team_id;
    } else {
        winnerForm.first_choice_team_id = null;
        winnerForm.second_choice_team_id = null;
        winnerForm.third_choice_team_id = null;
    }
    showWinnerForm.value = true;
};

const submitWinnerPrediction = () => {
    winnerForm.post(route('tournaments.winner-prediction.store', props.tournament.id), {
        preserveScroll: true,
        onSuccess: () => {
            showWinnerForm.value = false;
        },
    });
};

// Équipes disponibles pour chaque choix
const availableForFirst = computed(() => props.tournament.teams || []);
const availableForSecond = computed(() => (props.tournament.teams || []).filter(t => t.id !== winnerForm.first_choice_team_id));
const availableForThird = computed(() => (props.tournament.teams || []).filter(t =>
    t.id !== winnerForm.first_choice_team_id && t.id !== winnerForm.second_choice_team_id
));

// Désigner le vainqueur (admin)
const showSetWinnerModal = ref(false);
const setWinnerForm = useForm({
    winner_team_id: null,
});

const submitSetWinner = () => {
    setWinnerForm.post(route('tournaments.set-winner', props.tournament.id), {
        preserveScroll: true,
        onSuccess: () => {
            showSetWinnerModal.value = false;
        },
    });
};

// Loser Prediction
const showLoserForm = ref(false);
const showSetLoserModal = ref(false);
const loserForm = useForm({ team_id: null });
const setLoserForm = useForm({ loser_team_id: null });

const initLoserForm = () => {
    loserForm.team_id = props.userLoserPrediction?.team_id ?? null;
    showLoserForm.value = true;
};

const submitLoserPrediction = () => {
    loserForm.post(route('tournaments.loser-prediction.store', props.tournament.id), {
        preserveScroll: true,
        onSuccess: () => { showLoserForm.value = false; },
    });
};

const submitSetLoser = () => {
    setLoserForm.post(route('tournaments.set-loser', props.tournament.id), {
        preserveScroll: true,
        onSuccess: () => { showSetLoserModal.value = false; },
    });
};

// Top Scorer Prediction
const showTopScorerForm = ref(false);
const showSetTopScorerModal = ref(false);
const topScorerForm = useForm({ player_name: '' });
const setTopScorerForm = useForm({ top_scorer_name: '' });

const initTopScorerForm = () => {
    topScorerForm.player_name = props.userTopScorerPrediction?.player_name ?? '';
    showTopScorerForm.value = true;
};

const submitTopScorerPrediction = () => {
    topScorerForm.post(route('tournaments.top-scorer-prediction.store', props.tournament.id), {
        preserveScroll: true,
        onSuccess: () => { showTopScorerForm.value = false; },
    });
};

const submitSetTopScorer = () => {
    setTopScorerForm.post(route('tournaments.set-top-scorer', props.tournament.id), {
        preserveScroll: true,
        onSuccess: () => { showSetTopScorerModal.value = false; },
    });
};

// Last Place Prediction (Vrai Gros Loser)
const showLastPlaceForm = ref(false);
const showSetLastPlaceModal = ref(false);
const lastPlaceForm = useForm({ predicted_user_id: null });
const setLastPlaceForm = useForm({ last_place_user_id: null });

const initLastPlaceForm = () => {
    lastPlaceForm.predicted_user_id = props.userLastPlacePrediction?.predicted_user_id ?? null;
    showLastPlaceForm.value = true;
};

const submitLastPlacePrediction = () => {
    lastPlaceForm.post(route('tournaments.last-place-prediction.store', props.tournament.id), {
        preserveScroll: true,
        onSuccess: () => { showLastPlaceForm.value = false; },
    });
};

const submitSetLastPlace = () => {
    setLastPlaceForm.post(route('tournaments.set-last-place', props.tournament.id), {
        preserveScroll: true,
        onSuccess: () => { showSetLastPlaceModal.value = false; },
    });
};
</script>

<template>
    <Head :title="tournament.name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        {{ tournament.name }}
                    </h2>
                    <p class="text-sm text-gray-500 mt-1">
                        {{ formatLabel }} - {{ tournament.team_count }} equipes
                        <span
                            :class="[
                                'ml-2 px-2 py-0.5 text-xs rounded-full',
                                tournament.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'
                            ]"
                        >
                            {{ statusLabel }}
                        </span>
                        <span
                            v-if="tournament.status === 'active'"
                            :class="[
                                'ml-2 px-2 py-0.5 text-xs rounded-full',
                                tournament.predictions_open ? 'bg-blue-100 text-blue-800' : 'bg-red-100 text-red-800'
                            ]"
                        >
                            {{ tournament.predictions_open ? 'Pronostics ouverts' : 'Pronostics fermes' }}
                        </span>
                    </p>
                </div>
                <div class="flex gap-2" v-if="isAdmin || $page.props.auth.user.is_admin">
                    <Link :href="route('tournaments.edit', tournament.id)">
                        <SecondaryButton>Modifier</SecondaryButton>
                    </Link>
                    <PrimaryButton
                        v-if="tournament.status === 'draft'"
                        @click="activateTournament"
                    >
                        Activer
                    </PrimaryButton>
                    <button
                        v-if="tournament.status === 'active'"
                        @click="togglePredictions"
                        :class="[
                            'px-4 py-2 text-sm font-medium rounded-md transition',
                            tournament.predictions_open
                                ? 'bg-red-600 text-white hover:bg-red-700'
                                : 'bg-emerald-600 text-white hover:bg-emerald-700'
                        ]"
                    >
                        {{ tournament.predictions_open ? 'Fermer les pronostics' : 'Ouvrir les pronostics' }}
                    </button>
                </div>
            </div>
        </template>

        <div class="py-4 sm:py-12">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

                <!-- Sélecteur de tournoi (style Dashboard) -->
                <div v-if="myTournaments && myTournaments.length > 1" class="bg-white rounded-2xl shadow-sm overflow-hidden mb-4">
                    <div class="flex items-center justify-between p-3">
                        <button
                            @click="goPrev"
                            :disabled="currentIdx === 0"
                            :class="['w-10 h-10 rounded-xl flex items-center justify-center transition', currentIdx === 0 ? 'text-gray-300' : 'text-gray-600 hover:bg-gray-100 active:bg-gray-200']"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                            </svg>
                        </button>
                        <div class="text-center flex-1">
                            <p class="text-xs text-gray-400">{{ currentIdx + 1 }} / {{ myTournaments.length }}</p>
                        </div>
                        <button
                            @click="goNext"
                            :disabled="currentIdx === myTournaments.length - 1"
                            :class="['w-10 h-10 rounded-xl flex items-center justify-center transition', currentIdx === myTournaments.length - 1 ? 'text-gray-300' : 'text-gray-600 hover:bg-gray-100 active:bg-gray-200']"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                    </div>
                    <div class="flex justify-center gap-1.5 pb-3">
                        <button
                            v-for="(t, idx) in myTournaments"
                            :key="t.id"
                            @click="router.visit(route('tournaments.show', t.id))"
                            :class="['w-2 h-2 rounded-full transition', idx === currentIdx ? 'bg-indigo-600' : 'bg-gray-300']"
                        />
                    </div>
                </div>

                <!-- Header Mobile -->
                <div class="sm:hidden bg-white rounded-2xl shadow-sm p-4 mb-4">
                    <h1 class="text-lg font-bold text-gray-900">{{ tournament.name }}</h1>
                    <p class="text-sm text-gray-500 mt-1">
                        {{ formatLabel }} - {{ tournament.team_count }} equipes
                    </p>
                    <div class="flex flex-wrap gap-2 mt-2">
                        <span
                            :class="[
                                'px-2 py-0.5 text-xs rounded-full',
                                tournament.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'
                            ]"
                        >
                            {{ statusLabel }}
                        </span>
                        <span
                            v-if="tournament.status === 'active'"
                            :class="[
                                'px-2 py-0.5 text-xs rounded-full',
                                tournament.predictions_open ? 'bg-blue-100 text-blue-800' : 'bg-red-100 text-red-800'
                            ]"
                        >
                            {{ tournament.predictions_open ? 'Pronostics ouverts' : 'Pronostics fermes' }}
                        </span>
                    </div>

                    <!-- Actions mobile -->
                    <div v-if="isAdmin || $page.props.auth.user.is_admin" class="flex flex-wrap gap-2 mt-4 pt-4 border-t">
                        <Link :href="route('tournaments.edit', tournament.id)">
                            <SecondaryButton class="text-sm">Modifier</SecondaryButton>
                        </Link>
                        <PrimaryButton
                            v-if="tournament.status === 'draft'"
                            @click="activateTournament"
                            class="text-sm"
                        >
                            Activer
                        </PrimaryButton>
                        <button
                            v-if="tournament.status === 'active'"
                            @click="togglePredictions"
                            :class="[
                                'px-4 py-2 text-sm font-medium rounded-md transition',
                                tournament.predictions_open
                                    ? 'bg-red-600 text-white hover:bg-red-700'
                                    : 'bg-emerald-600 text-white hover:bg-emerald-700'
                            ]"
                        >
                            {{ tournament.predictions_open ? 'Fermer les pronostics' : 'Ouvrir les pronostics' }}
                        </button>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="bg-white shadow-sm sm:rounded-lg mb-6">
                    <div class="border-b border-gray-200 overflow-x-auto">
                        <nav class="flex -mb-px min-w-max">
                            <button
                                @click="activeTab = 'teams'"
                                :class="[
                                    'px-6 py-3 text-sm font-medium border-b-2 transition',
                                    activeTab === 'teams'
                                        ? 'border-indigo-500 text-indigo-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700'
                                ]"
                            >
                                Equipes ({{ tournament.teams.length }})
                            </button>
                            <button
                                v-if="tournament.format === 'groups_elimination'"
                                @click="activeTab = 'poules'"
                                :class="[
                                    'px-6 py-3 text-sm font-medium border-b-2 transition',
                                    activeTab === 'poules'
                                        ? 'border-indigo-500 text-indigo-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700'
                                ]"
                            >
                                Poules ({{ tournament.tournament_groups?.length || 0 }})
                            </button>
                            <button
                                @click="activeTab = 'matches'"
                                :class="[
                                    'px-6 py-3 text-sm font-medium border-b-2 transition',
                                    activeTab === 'matches'
                                        ? 'border-indigo-500 text-indigo-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700'
                                ]"
                            >
                                Matchs ({{ tournament.matches.length }})
                            </button>
                            <button
                                @click="activeTab = 'members'"
                                :class="[
                                    'px-6 py-3 text-sm font-medium border-b-2 transition',
                                    activeTab === 'members'
                                        ? 'border-indigo-500 text-indigo-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700'
                                ]"
                            >
                                Membres ({{ tournament.members_count ?? tournament.members?.length ?? 0 }})
                            </button>
                            <button
                                @click="activeTab = 'winner'"
                                :class="[
                                    'px-6 py-3 text-sm font-medium border-b-2 transition flex items-center gap-2',
                                    activeTab === 'winner'
                                        ? 'border-amber-500 text-amber-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700'
                                ]"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                                </svg>
                                Vainqueur
                            </button>
                            <button
                                @click="activeTab = 'stats'"
                                :class="[
                                    'px-6 py-3 text-sm font-medium border-b-2 transition flex items-center gap-2',
                                    activeTab === 'stats'
                                        ? 'border-indigo-500 text-indigo-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700'
                                ]"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3v18h18M7 14l4-4 4 4 5-6" />
                                </svg>
                                Statistiques
                            </button>
                        </nav>
                    </div>
                </div>

                <!-- Teams Tab -->
                <div v-if="activeTab === 'teams'" class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold">Equipes</h3>
                            <div class="flex gap-2" v-if="isAdmin">
                                <PrimaryButton @click="showPredefinedTeamsModal = true">
                                    Ajouter des equipes
                                </PrimaryButton>
                                <SecondaryButton @click="showAddTeamModal = true">
                                    Ajouter manuellement
                                </SecondaryButton>
                            </div>
                        </div>

                        <div v-if="tournament.teams.length === 0" class="text-center py-8 text-gray-500">
                            Aucune equipe inscrite
                        </div>

                        <div v-else class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div
                                v-for="team in tournament.teams"
                                :key="team.id"
                                class="p-4 border rounded-lg flex items-center justify-between group"
                            >
                                <div class="flex items-center gap-2">
                                    <TeamFlag :flag="team.flag" size="lg" />
                                    <div>
                                        <div class="font-medium">{{ team.name }}</div>
                                        <div v-if="team.short_name" class="text-sm text-gray-500">
                                            {{ team.short_name }}
                                        </div>
                                    </div>
                                </div>
                                <button
                                    v-if="isAdmin && tournament.status === 'draft'"
                                    @click="deleteTeam(team.id)"
                                    class="text-red-500 hover:text-red-700 opacity-0 group-hover:opacity-100 transition"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Poules Tab -->
                <div v-if="activeTab === 'poules'" class="space-y-6">
                    <!-- Actions -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6" v-if="isAdmin">
                        <div class="flex flex-wrap gap-4 items-center justify-between">
                            <div class="flex gap-2">
                                <PrimaryButton @click="showCreateGroupModal = true">
                                    Creer un groupe
                                </PrimaryButton>
                                <SecondaryButton
                                    v-if="tournament.tournament_groups?.length === 0"
                                    @click="createDefaultGroups"
                                >
                                    Creer 12 groupes (A-L)
                                </SecondaryButton>
                            </div>
                            <div class="flex items-center gap-4">
                                <span class="text-sm text-gray-500">
                                    {{ groupMatchesCount }}/{{ expectedGroupMatches }} matchs generes
                                </span>
                                <PrimaryButton
                                    @click="generateGroupMatches"
                                    :disabled="tournament.tournament_groups?.length === 0"
                                    class="bg-green-600 hover:bg-green-700"
                                >
                                    Generer les matchs de poule
                                </PrimaryButton>
                            </div>
                        </div>
                    </div>

                    <!-- Unassigned teams warning -->
                    <div v-if="unassignedTeams.length > 0" class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                        <p class="text-yellow-800 font-medium mb-2">
                            {{ unassignedTeams.length }} equipe(s) non assignee(s) :
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <span
                                v-for="team in unassignedTeams"
                                :key="team.id"
                                class="inline-flex items-center gap-1 px-2 py-1 bg-yellow-100 rounded text-sm"
                            >
                                <TeamFlag :flag="team.flag" size="sm" /> {{ team.name }}
                            </span>
                        </div>
                    </div>

                    <!-- Groups grid -->
                    <div v-if="tournament.tournament_groups?.length === 0" class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <p class="text-center text-gray-500">Aucun groupe cree. Commencez par creer des groupes.</p>
                    </div>

                    <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div
                            v-for="group in tournament.tournament_groups"
                            :key="group.id"
                            class="bg-white overflow-hidden shadow-sm sm:rounded-lg"
                        >
                            <div class="p-4 bg-gray-50 border-b flex justify-between items-center">
                                <h4 class="font-semibold text-lg">{{ group.name }}</h4>
                                <div class="flex gap-2" v-if="isAdmin">
                                    <button
                                        @click="openAddTeamToGroup(group)"
                                        class="text-indigo-600 hover:text-indigo-800 text-sm"
                                    >
                                        + Equipe
                                    </button>
                                    <button
                                        @click="deleteGroup(group.id)"
                                        class="text-red-500 hover:text-red-700 text-sm"
                                    >
                                        Supprimer
                                    </button>
                                </div>
                            </div>
                            <div class="p-2">
                                <div v-if="!group.teams?.length" class="text-center text-gray-400 py-4">
                                    Aucune equipe
                                </div>
                                <div v-else class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead>
                                            <tr class="text-xs text-gray-500 border-b">
                                                <th class="text-left py-2 px-2">#</th>
                                                <th class="text-left py-2 px-1">Equipe</th>
                                                <th class="text-center py-2 px-1 w-8">J</th>
                                                <th class="text-center py-2 px-1 w-8">G</th>
                                                <th class="text-center py-2 px-1 w-8">N</th>
                                                <th class="text-center py-2 px-1 w-8">P</th>
                                                <th class="text-center py-2 px-1 w-10">+/-</th>
                                                <th class="text-center py-2 px-2 w-10 font-bold">Pts</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="(standing, idx) in getGroupStandings(group)"
                                                :key="standing.team.id"
                                                :class="[
                                                    'border-b last:border-0',
                                                    idx < 2 ? 'bg-green-50' : ''
                                                ]"
                                            >
                                                <td class="py-2 px-2 font-medium text-gray-500">{{ idx + 1 }}</td>
                                                <td class="py-2 px-1">
                                                    <div class="flex items-center gap-2">
                                                        <TeamFlag :flag="standing.team.flag" size="sm" />
                                                        <span class="font-medium truncate">{{ standing.team.short_name || standing.team.name }}</span>
                                                    </div>
                                                </td>
                                                <td class="text-center py-2 px-1 text-gray-600">{{ standing.played }}</td>
                                                <td class="text-center py-2 px-1 text-gray-600">{{ standing.won }}</td>
                                                <td class="text-center py-2 px-1 text-gray-600">{{ standing.drawn }}</td>
                                                <td class="text-center py-2 px-1 text-gray-600">{{ standing.lost }}</td>
                                                <td class="text-center py-2 px-1 text-gray-600">
                                                    <span :class="standing.goal_difference > 0 ? 'text-green-600' : standing.goal_difference < 0 ? 'text-red-600' : ''">
                                                        {{ standing.goal_difference > 0 ? '+' : '' }}{{ standing.goal_difference }}
                                                    </span>
                                                </td>
                                                <td class="text-center py-2 px-2 font-bold text-gray-900">{{ standing.points }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Matches Tab -->
                <div v-if="activeTab === 'matches'" class="space-y-6">
                    <!-- Header -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-semibold">Matchs</h3>
                            <PrimaryButton v-if="isAdmin" @click="showAddMatchModal = true">
                                Ajouter un match
                            </PrimaryButton>
                        </div>
                    </div>

                    <div v-if="tournament.matches.length === 0" class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <p class="text-center text-gray-500">Aucun match programme</p>
                    </div>

                    <template v-else>
                        <!-- Phase de poules -->
                        <div v-if="matchesByPoule.length > 0">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4 px-1">Phase de poules</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                                <div
                                    v-for="poule in matchesByPoule"
                                    :key="poule.name"
                                    class="bg-white overflow-hidden shadow-sm sm:rounded-lg"
                                >
                                    <!-- En-tête du groupe -->
                                    <div class="px-4 py-3 bg-gradient-to-r from-indigo-500 to-indigo-600">
                                        <h4 class="font-semibold text-white text-center">{{ poule.name }}</h4>
                                    </div>

                                    <!-- Liste des matchs du groupe -->
                                    <div class="divide-y divide-gray-100">
                                        <div
                                            v-for="match in poule.matches"
                                            :key="match.id"
                                            class="p-3 hover:bg-gray-50 transition"
                                        >
                                            <div class="flex items-center justify-between gap-2">
                                                <!-- Equipe domicile -->
                                                <div class="flex items-center gap-2 flex-1 min-w-0">
                                                    <TeamFlag :flag="match.home_team?.flag" size="md" class="flex-shrink-0" />
                                                    <span class="font-medium truncate text-sm">
                                                        {{ match.home_team?.name || match.placeholder_home || 'TBD' }}
                                                    </span>
                                                </div>

                                                <!-- Score ou date -->
                                                <div class="flex-shrink-0 text-center min-w-[60px]">
                                                    <span
                                                        v-if="match.status === 'completed'"
                                                        class="font-bold text-gray-900 bg-gray-100 px-2 py-1 rounded"
                                                    >
                                                        {{ match.home_score }} - {{ match.away_score }}
                                                    </span>
                                                    <span
                                                        v-else
                                                        class="text-xs text-gray-400"
                                                    >
                                                        {{ match.scheduled_at ? new Date(match.scheduled_at).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' }) : 'vs' }}
                                                    </span>
                                                    <!-- Mon pronostic -->
                                                    <div v-if="userPredictions[match.id]" class="mt-1">
                                                        <span
                                                            :class="[
                                                                'text-xs px-2 py-0.5 rounded font-medium',
                                                                match.status === 'completed'
                                                                    ? userPredictions[match.id].result_type === 'exact'
                                                                        ? 'bg-emerald-100 text-emerald-700'
                                                                        : userPredictions[match.id].result_type === 'correct_winner'
                                                                            ? 'bg-amber-100 text-amber-700'
                                                                            : 'bg-red-100 text-red-700'
                                                                    : 'bg-indigo-100 text-indigo-700'
                                                            ]"
                                                        >
                                                            Mon prono: {{ userPredictions[match.id].home_score }}-{{ userPredictions[match.id].away_score }}
                                                        </span>
                                                    </div>
                                                </div>

                                                <!-- Equipe exterieur -->
                                                <div class="flex items-center gap-2 flex-1 min-w-0 justify-end">
                                                    <span class="font-medium truncate text-sm text-right">
                                                        {{ match.away_team?.name || match.placeholder_away || 'TBD' }}
                                                    </span>
                                                    <TeamFlag :flag="match.away_team?.flag" size="md" class="flex-shrink-0" />
                                                </div>
                                            </div>

                                            <!-- Boutons actions -->
                                            <div class="mt-2 flex justify-center gap-3">
                                                <Link
                                                    :href="route('predictions.show', match.id)"
                                                    class="inline-flex items-center gap-1 text-xs text-indigo-600 hover:text-indigo-800 hover:underline"
                                                >
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                    </svg>
                                                    Pronostiquer
                                                </Link>
                                                <Link
                                                    v-if="isAdmin"
                                                    :href="route('tournaments.matches.edit', [tournament.id, match.id])"
                                                    class="inline-flex items-center gap-1 text-xs text-gray-500 hover:text-gray-700 hover:underline"
                                                >
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                                                    </svg>
                                                    Modifier
                                                </Link>
                                                <button
                                                    v-if="(isAdmin || $page.props.auth.user.is_admin) && match.home_team && match.away_team"
                                                    @click="openResultModal(match)"
                                                    class="inline-flex items-center gap-1 text-xs text-emerald-600 hover:text-emerald-800 hover:underline"
                                                >
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                    {{ match.status === 'completed' ? 'Modifier score' : 'Entrer score' }}
                                                </button>
                                                <button
                                                    v-if="isAdmin || $page.props.auth.user.is_admin"
                                                    @click="openScheduleModal(match)"
                                                    class="inline-flex items-center gap-1 text-xs text-blue-500 hover:text-blue-700 hover:underline"
                                                >
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                    Horaire
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Phases éliminatoires -->
                        <div v-if="Object.keys(knockoutMatches).length > 0" class="mt-8">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4 px-1">Phases éliminatoires</h3>
                            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                                <div class="divide-y divide-gray-200">
                                    <div v-for="(matches, round) in knockoutMatches" :key="round" class="p-4">
                                        <h4 class="font-semibold text-gray-700 mb-3 flex items-center gap-2">
                                            <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                                            {{ roundLabels[round] || round }}
                                        </h4>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                            <div
                                                v-for="match in matches"
                                                :key="match.id"
                                                class="flex items-center justify-between p-3 border rounded-lg hover:bg-gray-50 transition"
                                            >
                                                <div class="flex items-center gap-3 flex-1">
                                                    <TeamFlag :flag="match.home_team?.flag" size="lg" />
                                                    <span class="font-medium">
                                                        {{ match.home_team?.name || match.placeholder_home || 'A determiner' }}
                                                    </span>
                                                </div>
                                                <div class="flex-shrink-0 px-3 text-center">
                                                    <span
                                                        v-if="match.status === 'completed'"
                                                        class="font-bold bg-gray-100 px-3 py-1 rounded"
                                                    >
                                                        {{ match.home_score }} - {{ match.away_score }}
                                                    </span>
                                                    <span v-else class="text-gray-400">vs</span>
                                                    <!-- Mon pronostic -->
                                                    <div v-if="userPredictions[match.id]" class="mt-1">
                                                        <span
                                                            :class="[
                                                                'text-xs px-2 py-0.5 rounded font-medium',
                                                                match.status === 'completed'
                                                                    ? userPredictions[match.id].result_type === 'exact'
                                                                        ? 'bg-emerald-100 text-emerald-700'
                                                                        : userPredictions[match.id].result_type === 'correct_winner'
                                                                            ? 'bg-amber-100 text-amber-700'
                                                                            : 'bg-red-100 text-red-700'
                                                                    : 'bg-indigo-100 text-indigo-700'
                                                            ]"
                                                        >
                                                            {{ userPredictions[match.id].home_score }}-{{ userPredictions[match.id].away_score }}
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="flex items-center gap-3 flex-1 justify-end">
                                                    <span class="font-medium">
                                                        {{ match.away_team?.name || match.placeholder_away || 'A determiner' }}
                                                    </span>
                                                    <TeamFlag :flag="match.away_team?.flag" size="lg" />
                                                </div>
                                                <div class="ml-4 flex gap-2 flex-shrink-0">
                                                    <Link
                                                        :href="route('predictions.show', match.id)"
                                                        class="text-indigo-600 hover:text-indigo-800 text-sm"
                                                    >
                                                        Pronostiquer
                                                    </Link>
                                                    <Link
                                                        v-if="isAdmin"
                                                        :href="route('tournaments.matches.edit', [tournament.id, match.id])"
                                                        class="text-gray-500 hover:text-gray-700 text-sm"
                                                    >
                                                        Modifier
                                                    </Link>
                                                    <button
                                                        v-if="(isAdmin || $page.props.auth.user.is_admin) && match.home_team && match.away_team"
                                                        @click="openResultModal(match)"
                                                        class="text-emerald-600 hover:text-emerald-800 text-sm"
                                                    >
                                                        {{ match.status === 'completed' ? 'Modifier score' : 'Entrer score' }}
                                                    </button>
                                                    <button
                                                        v-if="isAdmin || $page.props.auth.user.is_admin"
                                                        @click="openScheduleModal(match)"
                                                        class="inline-flex items-center gap-1 text-blue-500 hover:text-blue-700 text-sm"
                                                    >
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                        </svg>
                                                        Horaire
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Members Tab -->
                <div v-if="activeTab === 'members'" class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-6">
                            <div>
                                <h3 class="text-lg font-semibold">Membres du tournoi</h3>
                                <p class="text-sm text-gray-500 mt-1">Classement des participants</p>
                            </div>
                            <!-- Code d'accès (visible pour owner/admin) -->
                            <div v-if="isAdmin || isAdmin" class="text-right">
                                <p class="text-xs text-gray-500 mb-1">Code d'accès</p>
                                <span class="font-mono text-lg font-bold tracking-widest bg-indigo-50 text-indigo-700 px-3 py-1.5 rounded-lg">
                                    {{ tournament.access_code }}
                                </span>
                            </div>
                        </div>

                        <!-- Bouton rejoindre (si non membre) -->
                        <div v-if="!isMember" class="mb-6 p-4 bg-indigo-50 rounded-xl border border-indigo-200 text-center">
                            <p class="text-sm text-indigo-700 mb-3">Vous n'êtes pas encore membre de ce tournoi.</p>
                            <form @submit.prevent="() => router.post(route('tournaments.join'), { access_code: tournament.access_code })">
                                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
                                    Rejoindre ce tournoi
                                </button>
                            </form>
                        </div>

                        <!-- Bouton quitter (si membre mais pas créateur) -->
                        <div v-else-if="!isAdmin" class="mb-6 text-right">
                            <form @submit.prevent="() => router.delete(route('tournaments.leave', tournament.id))">
                                <button type="submit" class="px-4 py-2 bg-red-50 text-red-600 border border-red-200 rounded-lg text-sm font-medium hover:bg-red-100 transition">
                                    Quitter le tournoi
                                </button>
                            </form>
                        </div>

                        <!-- Liste des membres avec classement -->
                        <div v-if="!tournament.members?.length" class="text-center py-8 text-gray-500">
                            Aucun membre pour l'instant
                        </div>

                        <div v-else class="space-y-2">
                            <div
                                v-for="(member, idx) in tournament.members"
                                :key="member.id"
                                :class="[
                                    'flex items-center gap-3 p-3 rounded-xl border',
                                    member.id === $page.props.auth.user.id
                                        ? 'bg-indigo-50 border-indigo-200'
                                        : idx === 0 ? 'bg-amber-50 border-amber-200'
                                        : idx === 1 ? 'bg-slate-50 border-slate-200'
                                        : idx === 2 ? 'bg-orange-50 border-orange-200'
                                        : 'bg-gray-50 border-gray-200'
                                ]"
                            >
                                <!-- Position -->
                                <div class="w-8 text-center">
                                    <span :class="[
                                        'w-7 h-7 rounded-full flex items-center justify-center text-sm font-bold inline-flex',
                                        idx === 0 ? 'bg-amber-200 text-amber-700' :
                                        idx === 1 ? 'bg-slate-200 text-slate-600' :
                                        idx === 2 ? 'bg-orange-200 text-orange-700' :
                                        'bg-gray-200 text-gray-500'
                                    ]">{{ idx + 1 }}</span>
                                </div>

                                <!-- Avatar + nom -->
                                <div class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center text-gray-600 font-medium text-sm flex-shrink-0">
                                    {{ member.name?.charAt(0)?.toUpperCase() }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium text-gray-800 truncate">{{ member.name }}</span>
                                        <span v-if="member.pivot?.role === 'admin'" class="text-[10px] bg-indigo-100 text-indigo-600 px-1.5 py-0.5 rounded font-medium">Admin</span>
                                        <span v-if="member.id === $page.props.auth.user.id" class="text-[10px] bg-emerald-100 text-emerald-600 px-1.5 py-0.5 rounded font-medium">Toi</span>
                                    </div>
                                </div>

                                <!-- Stats -->
                                <div class="flex items-center gap-2 text-xs">
                                    <span class="bg-emerald-50 text-emerald-600 px-1.5 py-0.5 rounded font-medium">{{ member.pivot?.exact_scores ?? 0 }}</span>
                                    <span class="bg-amber-50 text-amber-600 px-1.5 py-0.5 rounded font-medium">{{ member.pivot?.correct_results ?? 0 }}</span>
                                    <span class="bg-red-50 text-red-500 px-1.5 py-0.5 rounded font-medium">{{ member.pivot?.wrong_predictions ?? 0 }}</span>
                                </div>

                                <!-- Points -->
                                <div class="text-right w-16">
                                    <span class="font-bold text-gray-800">{{ member.pivot?.total_points ?? 0 }}</span>
                                    <span class="text-xs text-gray-400 ml-0.5">pts</span>
                                </div>
                            </div>
                        </div>

                        <!-- Liens rapides (si membre) -->
                        <div v-if="isMember" class="mt-6 flex flex-wrap justify-center gap-3">
                            <Link
                                :href="route('tournaments.allPredictions', tournament.id)"
                                class="inline-flex items-center gap-2 px-4 py-2 bg-purple-600 text-white rounded-lg text-sm font-medium hover:bg-purple-700 transition"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                                Tous les pronostics
                            </Link>
                            <Link
                                :href="route('tournaments.specialPronos', tournament.id)"
                                class="inline-flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-green-500 to-blue-500 text-white rounded-lg text-sm font-medium hover:opacity-90 transition"
                            >
                                <span class="text-base leading-none">🎯</span>
                                Pronos Spéciaux
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Stats Tab -->
                <div v-if="activeTab === 'stats'" class="space-y-4">
                    <!-- Action admin : publier le récap du jour -->
                    <div v-if="isAdmin || $page.props.auth.user.is_admin" class="bg-gradient-to-r from-indigo-600 to-purple-600 shadow-sm sm:rounded-lg p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-white">Publier le récap du jour</p>
                            <p class="text-xs text-indigo-100 mt-0.5">
                                Une fois les scores du jour saisis, publie le récap : les joueurs le verront immédiatement (popup), sans attendre midi.
                            </p>
                        </div>
                        <div class="flex items-center gap-3 shrink-0">
                            <span v-if="recapDone" class="inline-flex items-center gap-1 text-sm font-semibold text-white">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                Récap publié
                            </span>
                            <button
                                @click="publishRecap"
                                :disabled="recapProcessing"
                                class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-white text-indigo-700 rounded-lg text-sm font-bold hover:bg-indigo-50 transition disabled:opacity-60 disabled:cursor-not-allowed whitespace-nowrap"
                            >
                                <svg v-if="!recapProcessing" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" />
                                </svg>
                                <svg v-else class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                </svg>
                                {{ recapProcessing ? 'Publication…' : 'Publier le récap' }}
                            </button>
                        </div>
                    </div>

                    <!-- Action admin : initialiser la baseline (une seule fois) -->
                    <div v-if="isAdmin || $page.props.auth.user.is_admin" class="bg-white shadow-sm sm:rounded-lg p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <p class="text-sm font-medium text-gray-800">Initialiser la baseline (1 seule fois)</p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                À utiliser au lancement : marque les matchs déjà terminés comme « déjà vus » pour que les futurs récaps ne contiennent que les nouveaux matchs. Aucune donnée supprimée.
                            </p>
                        </div>
                        <button
                            @click="initRecap"
                            :disabled="initRecapProcessing"
                            class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 border border-gray-300 rounded-lg text-sm font-medium hover:bg-gray-200 transition disabled:opacity-60 disabled:cursor-not-allowed whitespace-nowrap"
                        >
                            <svg v-if="!initRecapProcessing" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <svg v-else class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                            </svg>
                            {{ initRecapProcessing ? 'Initialisation…' : 'Initialiser la baseline' }}
                        </button>
                    </div>

                    <!-- Action admin : reconstruire l'historique -->
                    <div v-if="isAdmin || $page.props.auth.user.is_admin" class="bg-white shadow-sm sm:rounded-lg p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <p class="text-sm font-medium text-gray-800">Reconstruire l'historique</p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                Génère les courbes depuis le début du tournoi à partir des matchs déjà terminés. Les relevés existants ne sont pas modifiés (les bonus spéciaux ne sont pas inclus).
                            </p>
                        </div>
                        <button
                            @click="backfillStats"
                            :disabled="backfillProcessing"
                            class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-purple-600 text-white rounded-lg text-sm font-medium hover:bg-purple-700 transition disabled:opacity-60 disabled:cursor-not-allowed whitespace-nowrap"
                        >
                            <svg v-if="!backfillProcessing" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-8v16M5 6h14a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2z" />
                            </svg>
                            <svg v-else class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                            </svg>
                            {{ backfillProcessing ? 'Reconstruction…' : "Reconstruire l'historique" }}
                        </button>
                    </div>

                    <!-- Action admin : capturer un relevé manuellement -->
                    <div v-if="isAdmin || $page.props.auth.user.is_admin" class="bg-white shadow-sm sm:rounded-lg p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <p class="text-sm font-medium text-gray-800">Relevé du classement</p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                Un relevé est pris automatiquement chaque jour à 12h. Tu peux en forcer un maintenant pour créer/mettre à jour le point d'aujourd'hui.
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span v-if="snapshotDone" class="inline-flex items-center gap-1 text-sm font-medium text-emerald-600">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                Relevé enregistré
                            </span>
                            <button
                                @click="takeSnapshot"
                                :disabled="snapshotProcessing"
                                class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition disabled:opacity-60 disabled:cursor-not-allowed whitespace-nowrap"
                            >
                                <svg v-if="!snapshotProcessing" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                                <svg v-else class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                </svg>
                                {{ snapshotProcessing ? 'Enregistrement…' : 'Prendre un snapshot maintenant' }}
                            </button>
                        </div>
                    </div>

                    <StatsChart :stats-data="statsData" />
                </div>

                <!-- Winner Tab -->
                <div v-if="activeTab === 'winner'" class="space-y-6">
                    <!-- Admin: Désigner le vainqueur -->
                    <div v-if="isAdmin" class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex justify-between items-center mb-4">
                                <div>
                                    <h3 class="text-lg font-semibold">Désigner le vainqueur</h3>
                                    <p class="text-sm text-gray-500">En tant qu'administrateur, vous pouvez désigner le vainqueur du tournoi</p>
                                </div>
                                <PrimaryButton
                                    @click="showSetWinnerModal = true"
                                    class="bg-amber-600 hover:bg-amber-700"
                                >
                                    {{ tournament.winner_team_id ? 'Modifier le vainqueur' : 'Désigner le vainqueur' }}
                                </PrimaryButton>
                            </div>

                            <!-- Vainqueur actuel -->
                            <div v-if="tournament.winner_team" class="bg-gradient-to-b from-yellow-50 to-amber-50 rounded-xl p-6 border border-yellow-200 text-center">
                                <div class="text-xs text-yellow-600 font-medium mb-2 uppercase tracking-wide">Vainqueur du tournoi</div>
                                <div class="flex items-center justify-center gap-3 mb-2">
                                    <TeamFlag :flag="tournament.winner_team?.flag" size="xl" />
                                    <span class="font-bold text-2xl text-gray-900">
                                        {{ tournament.winner_team?.name }}
                                    </span>
                                </div>
                            </div>
                            <div v-else class="bg-gray-50 rounded-xl p-6 text-center text-gray-500">
                                Aucun vainqueur désigné pour l'instant
                            </div>
                        </div>
                    </div>

                    <!-- Mon pronostic vainqueur -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex justify-between items-center mb-4">
                                <div>
                                    <h3 class="text-lg font-semibold flex items-center gap-2">
                                        <svg class="w-5 h-5 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                                        </svg>
                                        Mon pronostic vainqueur
                                    </h3>
                                    <p class="text-sm text-gray-500">Choisis tes 3 favoris pour gagner des points bonus</p>
                                </div>
                                <button
                                    v-if="tournament.predictions_open && !tournament.winner_predictions_locked && !showWinnerForm"
                                    @click="initWinnerForm"
                                    class="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                                >
                                    {{ userWinnerPrediction ? 'Modifier' : 'Faire mon pronostic' }}
                                </button>
                            </div>

                            <!-- Points expliqués -->
                            <div class="mb-6 p-4 bg-gray-50 rounded-xl">
                                <h4 class="text-sm font-semibold text-gray-700 mb-2">Comment ça marche ?</h4>
                                <div class="flex flex-wrap gap-4 text-sm">
                                    <div class="flex items-center gap-2">
                                        <span class="w-6 h-6 rounded-full bg-amber-200 text-amber-700 flex items-center justify-center text-xs font-bold">1</span>
                                        <span>1er choix = <strong class="text-amber-600">+30 pts</strong></span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="w-6 h-6 rounded-full bg-slate-200 text-slate-600 flex items-center justify-center text-xs font-bold">2</span>
                                        <span>2ème choix = <strong class="text-slate-600">+20 pts</strong></span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="w-6 h-6 rounded-full bg-orange-200 text-orange-700 flex items-center justify-center text-xs font-bold">3</span>
                                        <span>3ème choix = <strong class="text-orange-600">+10 pts</strong></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Vainqueur déjà désigné - Afficher résultat -->
                            <div v-if="tournament.winner_team_id && userWinnerPrediction" class="space-y-4">
                                <div class="text-center mb-4">
                                    <span class="text-sm font-medium text-emerald-600 bg-emerald-50 px-3 py-1 rounded-full">
                                        Tournoi terminé - Résultats
                                    </span>
                                </div>

                                <div class="space-y-2">
                                    <!-- 1er choix -->
                                    <div :class="[
                                        'flex items-center justify-between p-4 rounded-xl border',
                                        userWinnerPrediction.first_choice_team_id === tournament.winner_team_id
                                            ? 'bg-emerald-50 border-emerald-300'
                                            : 'bg-amber-50 border-amber-200'
                                    ]">
                                        <div class="flex items-center gap-3">
                                            <span class="w-8 h-8 rounded-full bg-amber-200 text-amber-700 flex items-center justify-center font-bold">1</span>
                                            <TeamFlag :flag="userWinnerPrediction.first_choice_team?.flag" size="lg" />
                                            <span class="font-medium text-gray-800">{{ userWinnerPrediction.first_choice_team?.name }}</span>
                                        </div>
                                        <div>
                                            <span v-if="userWinnerPrediction.first_choice_team_id === tournament.winner_team_id" class="font-bold text-emerald-600">+30 pts ✓</span>
                                            <span v-else class="text-gray-400">+30 pts</span>
                                        </div>
                                    </div>

                                    <!-- 2ème choix -->
                                    <div :class="[
                                        'flex items-center justify-between p-4 rounded-xl border',
                                        userWinnerPrediction.second_choice_team_id === tournament.winner_team_id
                                            ? 'bg-emerald-50 border-emerald-300'
                                            : 'bg-slate-50 border-slate-200'
                                    ]">
                                        <div class="flex items-center gap-3">
                                            <span class="w-8 h-8 rounded-full bg-slate-200 text-slate-600 flex items-center justify-center font-bold">2</span>
                                            <TeamFlag :flag="userWinnerPrediction.second_choice_team?.flag" size="lg" />
                                            <span class="font-medium text-gray-800">{{ userWinnerPrediction.second_choice_team?.name }}</span>
                                        </div>
                                        <div>
                                            <span v-if="userWinnerPrediction.second_choice_team_id === tournament.winner_team_id" class="font-bold text-emerald-600">+15 pts ✓</span>
                                            <span v-else class="text-gray-400">+20 pts</span>
                                        </div>
                                    </div>

                                    <!-- 3ème choix -->
                                    <div :class="[
                                        'flex items-center justify-between p-4 rounded-xl border',
                                        userWinnerPrediction.third_choice_team_id === tournament.winner_team_id
                                            ? 'bg-emerald-50 border-emerald-300'
                                            : 'bg-orange-50 border-orange-200'
                                    ]">
                                        <div class="flex items-center gap-3">
                                            <span class="w-8 h-8 rounded-full bg-orange-200 text-orange-700 flex items-center justify-center font-bold">3</span>
                                            <TeamFlag :flag="userWinnerPrediction.third_choice_team?.flag" size="lg" />
                                            <span class="font-medium text-gray-800">{{ userWinnerPrediction.third_choice_team?.name }}</span>
                                        </div>
                                        <div>
                                            <span v-if="userWinnerPrediction.third_choice_team_id === tournament.winner_team_id" class="font-bold text-emerald-600">+10 pts ✓</span>
                                            <span v-else class="text-gray-400">+10 pts</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Total des points -->
                                <div class="mt-4 p-4 bg-gray-100 rounded-xl text-center">
                                    <span class="text-gray-600">Points bonus gagnés : </span>
                                    <span class="font-bold text-xl" :class="userWinnerPrediction.points_earned > 0 ? 'text-emerald-600' : 'text-gray-500'">
                                        +{{ userWinnerPrediction.points_earned || 0 }} pts
                                    </span>
                                </div>
                            </div>

                            <!-- Formulaire de pronostic -->
                            <div v-else-if="showWinnerForm && tournament.predictions_open && !tournament.winner_predictions_locked">
                                <form @submit.prevent="submitWinnerPrediction" class="space-y-4">
                                    <!-- 1er choix -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            1er choix <span class="text-amber-500">(+30 pts si gagne)</span>
                                        </label>
                                        <select
                                            v-model="winnerForm.first_choice_team_id"
                                            class="w-full rounded-lg border-gray-300 focus:ring-indigo-500 focus:border-indigo-500"
                                        >
                                            <option :value="null">Sélectionner une équipe...</option>
                                            <option v-for="team in availableForFirst" :key="team.id" :value="team.id">
                                                {{ team.name }}
                                            </option>
                                        </select>
                                        <InputError class="mt-2" :message="winnerForm.errors.first_choice_team_id" />
                                    </div>

                                    <!-- 2ème choix -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            2ème choix <span class="text-gray-400">(+20 pts si gagne)</span>
                                        </label>
                                        <select
                                            v-model="winnerForm.second_choice_team_id"
                                            class="w-full rounded-lg border-gray-300 focus:ring-indigo-500 focus:border-indigo-500"
                                            :disabled="!winnerForm.first_choice_team_id"
                                        >
                                            <option :value="null">Sélectionner une équipe...</option>
                                            <option v-for="team in availableForSecond" :key="team.id" :value="team.id">
                                                {{ team.name }}
                                            </option>
                                        </select>
                                        <InputError class="mt-2" :message="winnerForm.errors.second_choice_team_id" />
                                    </div>

                                    <!-- 3ème choix -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            3ème choix <span class="text-orange-400">(+10 pts si gagne)</span>
                                        </label>
                                        <select
                                            v-model="winnerForm.third_choice_team_id"
                                            class="w-full rounded-lg border-gray-300 focus:ring-indigo-500 focus:border-indigo-500"
                                            :disabled="!winnerForm.second_choice_team_id"
                                        >
                                            <option :value="null">Sélectionner une équipe...</option>
                                            <option v-for="team in availableForThird" :key="team.id" :value="team.id">
                                                {{ team.name }}
                                            </option>
                                        </select>
                                        <InputError class="mt-2" :message="winnerForm.errors.third_choice_team_id" />
                                    </div>

                                    <!-- Boutons -->
                                    <div class="flex gap-3 pt-4">
                                        <SecondaryButton @click="showWinnerForm = false" type="button">
                                            Annuler
                                        </SecondaryButton>
                                        <PrimaryButton
                                            :disabled="!winnerForm.first_choice_team_id || !winnerForm.second_choice_team_id || !winnerForm.third_choice_team_id || winnerForm.processing"
                                        >
                                            Valider mon pronostic
                                        </PrimaryButton>
                                    </div>
                                </form>
                            </div>

                            <!-- Affichage du pronostic existant -->
                            <div v-else-if="userWinnerPrediction && !showWinnerForm" class="space-y-2">
                                <!-- 1er choix -->
                                <div class="flex items-center justify-between p-4 bg-gradient-to-r from-amber-50 to-yellow-50 rounded-xl border border-amber-200">
                                    <div class="flex items-center gap-3">
                                        <span class="w-8 h-8 rounded-full bg-amber-200 text-amber-700 flex items-center justify-center font-bold">1</span>
                                        <TeamFlag :flag="userWinnerPrediction.first_choice_team?.flag" size="lg" />
                                        <span class="font-medium text-gray-800">{{ userWinnerPrediction.first_choice_team?.name }}</span>
                                    </div>
                                    <span class="text-sm font-medium text-amber-600">+30 pts</span>
                                </div>

                                <!-- 2ème choix -->
                                <div class="flex items-center justify-between p-4 bg-gradient-to-r from-slate-50 to-gray-50 rounded-xl border border-slate-200">
                                    <div class="flex items-center gap-3">
                                        <span class="w-8 h-8 rounded-full bg-slate-200 text-slate-600 flex items-center justify-center font-bold">2</span>
                                        <TeamFlag :flag="userWinnerPrediction.second_choice_team?.flag" size="lg" />
                                        <span class="font-medium text-gray-800">{{ userWinnerPrediction.second_choice_team?.name }}</span>
                                    </div>
                                    <span class="text-sm font-medium text-slate-500">+20 pts</span>
                                </div>

                                <!-- 3ème choix -->
                                <div class="flex items-center justify-between p-4 bg-gradient-to-r from-orange-50 to-amber-50 rounded-xl border border-orange-200">
                                    <div class="flex items-center gap-3">
                                        <span class="w-8 h-8 rounded-full bg-orange-200 text-orange-700 flex items-center justify-center font-bold">3</span>
                                        <TeamFlag :flag="userWinnerPrediction.third_choice_team?.flag" size="lg" />
                                        <span class="font-medium text-gray-800">{{ userWinnerPrediction.third_choice_team?.name }}</span>
                                    </div>
                                    <span class="text-sm font-medium text-orange-500">+10 pts</span>
                                </div>

                                <div v-if="tournament.winner_predictions_locked || !tournament.predictions_open" class="text-center mt-4">
                                    <span class="text-sm text-gray-400">
                                        {{ tournament.winner_predictions_locked ? 'Pronostic vainqueur définitivement verrouillé' : 'Pronostics fermés' }}
                                    </span>
                                </div>
                            </div>

                            <!-- Pas de pronostic -->
                            <div v-else-if="!userWinnerPrediction && !showWinnerForm" class="text-center py-8">
                                <div class="w-16 h-16 mx-auto mb-4 bg-yellow-100 rounded-full flex items-center justify-center">
                                    <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                                    </svg>
                                </div>
                                <h4 class="text-lg font-medium text-gray-800 mb-2">Qui va gagner ?</h4>
                                <p class="text-gray-500 mb-4">Choisis tes 3 équipes favorites et gagne des points bonus !</p>
                                <PrimaryButton
                                    v-if="tournament.predictions_open && !tournament.winner_predictions_locked"
                                    @click="initWinnerForm"
                                >
                                    Faire mon pronostic
                                </PrimaryButton>
                                <p v-else class="text-sm text-gray-400">
                                    {{ tournament.winner_predictions_locked ? 'Les pronostics vainqueur sont définitivement verrouillés' : 'Les pronostics sont fermés' }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Admin: Désigner le gros loser -->
                    <div v-if="isAdmin || $page.props.auth.user.is_admin" class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex justify-between items-center mb-4">
                                <div>
                                    <h3 class="text-lg font-semibold">Désigner le gros loser</h3>
                                    <p class="text-sm text-gray-500">L'équipe avec 0 point et la pire différence de buts</p>
                                </div>
                                <PrimaryButton @click="showSetLoserModal = true" class="bg-gray-700 hover:bg-gray-800">
                                    {{ tournament.loser_team_id ? 'Modifier' : 'Désigner' }}
                                </PrimaryButton>
                            </div>
                            <div v-if="tournament.loser_team" class="bg-gray-50 rounded-xl p-4 border border-gray-200 flex items-center gap-3">
                                <TeamFlag :flag="tournament.loser_team?.flag" size="xl" />
                                <span class="font-bold text-xl text-gray-900">{{ tournament.loser_team?.name }}</span>
                            </div>
                            <div v-else class="bg-gray-50 rounded-xl p-4 text-center text-gray-500">Aucun gros loser désigné pour l'instant</div>
                        </div>
                    </div>

                    <!-- Mon pronostic: Le gros loser -->
                    <div v-if="isMember" class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex justify-between items-center mb-4">
                                <div>
                                    <h3 class="text-lg font-semibold">Le gros loser</h3>
                                    <p class="text-sm text-gray-500">L'équipe avec 0 pt et la pire diff. de buts (+15 pts)</p>
                                </div>
                                <button
                                    v-if="!tournament.winner_predictions_locked && tournament.predictions_open && !showLoserForm"
                                    @click="initLoserForm"
                                    class="text-sm font-medium text-gray-600 hover:text-gray-800"
                                >
                                    {{ userLoserPrediction ? 'Modifier' : 'Faire mon pronostic' }}
                                </button>
                            </div>

                            <!-- Résultat affiché -->
                            <div v-if="tournament.loser_team_id && userLoserPrediction">
                                <div :class="['flex items-center justify-between p-4 rounded-xl border', userLoserPrediction.team_id === tournament.loser_team_id ? 'bg-emerald-50 border-emerald-300' : 'bg-gray-50 border-gray-200']">
                                    <div class="flex items-center gap-3">
                                        <TeamFlag :flag="userLoserPrediction.team?.flag" size="lg" />
                                        <span class="font-medium text-gray-800">{{ userLoserPrediction.team?.name }}</span>
                                    </div>
                                    <span v-if="userLoserPrediction.team_id === tournament.loser_team_id" class="font-bold text-emerald-600">+15 pts ✓</span>
                                    <span v-else class="text-gray-400">0 pts</span>
                                </div>
                                <div class="mt-3 p-3 bg-gray-100 rounded-xl text-center">
                                    <span class="text-gray-600">Points bonus : </span>
                                    <span class="font-bold text-xl" :class="(userLoserPrediction.points_earned ?? 0) > 0 ? 'text-emerald-600' : 'text-gray-500'">
                                        +{{ userLoserPrediction.points_earned ?? 0 }} pts
                                    </span>
                                </div>
                            </div>

                            <!-- Formulaire -->
                            <div v-else-if="showLoserForm && !tournament.winner_predictions_locked && tournament.predictions_open">
                                <form @submit.prevent="submitLoserPrediction" class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Quelle équipe sera la pire ?</label>
                                        <select v-model="loserForm.team_id" class="w-full rounded-lg border-gray-300 focus:ring-gray-500 focus:border-gray-500">
                                            <option :value="null">Sélectionner une équipe...</option>
                                            <option v-for="team in tournament.teams" :key="team.id" :value="team.id">{{ team.name }}</option>
                                        </select>
                                        <InputError class="mt-2" :message="loserForm.errors.team_id" />
                                    </div>
                                    <div class="flex gap-3">
                                        <SecondaryButton @click="showLoserForm = false" type="button">Annuler</SecondaryButton>
                                        <PrimaryButton :disabled="!loserForm.team_id || loserForm.processing" class="bg-gray-700 hover:bg-gray-800">Valider</PrimaryButton>
                                    </div>
                                </form>
                            </div>

                            <!-- Pronostic existant -->
                            <div v-else-if="userLoserPrediction && !showLoserForm">
                                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl border border-gray-200">
                                    <div class="flex items-center gap-3">
                                        <TeamFlag :flag="userLoserPrediction.team?.flag" size="lg" />
                                        <span class="font-medium text-gray-800">{{ userLoserPrediction.team?.name }}</span>
                                    </div>
                                    <span class="text-sm text-gray-400">+15 pts si correct</span>
                                </div>
                                <div v-if="tournament.winner_predictions_locked || !tournament.predictions_open" class="text-center mt-3">
                                    <span class="text-sm text-gray-400">Pronostic verrouillé</span>
                                </div>
                            </div>

                            <!-- Pas de pronostic -->
                            <div v-else class="text-center py-6">
                                <p class="text-gray-500 mb-3">Désigne l'équipe la plus décevante de la compétition</p>
                                <PrimaryButton v-if="!tournament.winner_predictions_locked && tournament.predictions_open" @click="initLoserForm" class="bg-gray-700 hover:bg-gray-800">
                                    Faire mon pronostic
                                </PrimaryButton>
                                <p v-else class="text-sm text-gray-400">Pronostic verrouillé</p>
                            </div>
                        </div>
                    </div>

                    <!-- Admin: Désigner la star de la compétition -->
                    <div v-if="isAdmin || $page.props.auth.user.is_admin" class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex justify-between items-center mb-4">
                                <div>
                                    <h3 class="text-lg font-semibold">Désigner la star de la compétition</h3>
                                    <p class="text-sm text-gray-500">Le meilleur buteur de la compétition</p>
                                </div>
                                <PrimaryButton @click="showSetTopScorerModal = true" class="bg-blue-600 hover:bg-blue-700">
                                    {{ tournament.top_scorer_name ? 'Modifier' : 'Désigner' }}
                                </PrimaryButton>
                            </div>
                            <div v-if="tournament.top_scorer_name" class="bg-blue-50 rounded-xl p-4 border border-blue-200 text-center">
                                <div class="text-xs text-blue-600 font-medium mb-2 uppercase tracking-wide">Meilleur buteur</div>
                                <span class="font-bold text-xl text-gray-900">{{ tournament.top_scorer_name }}</span>
                            </div>
                            <div v-else class="bg-gray-50 rounded-xl p-4 text-center text-gray-500">Aucune star désignée pour l'instant</div>
                        </div>
                    </div>

                    <!-- Mon pronostic: Star de la compétition -->
                    <div v-if="isMember" class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex justify-between items-center mb-4">
                                <div>
                                    <h3 class="text-lg font-semibold">Star de la compétition</h3>
                                    <p class="text-sm text-gray-500">Meilleur buteur de la compétition (+15 pts)</p>
                                </div>
                                <button
                                    v-if="!tournament.winner_predictions_locked && tournament.predictions_open && !showTopScorerForm"
                                    @click="initTopScorerForm"
                                    class="text-sm font-medium text-blue-600 hover:text-blue-800"
                                >
                                    {{ userTopScorerPrediction ? 'Modifier' : 'Faire mon pronostic' }}
                                </button>
                            </div>

                            <!-- Résultat affiché -->
                            <div v-if="tournament.top_scorer_name && userTopScorerPrediction">
                                <div :class="['flex items-center justify-between p-4 rounded-xl border', userTopScorerPrediction.player_name?.toLowerCase() === tournament.top_scorer_name?.toLowerCase() ? 'bg-emerald-50 border-emerald-300' : 'bg-blue-50 border-blue-200']">
                                    <span class="font-medium text-gray-800">{{ userTopScorerPrediction.player_name }}</span>
                                    <span v-if="userTopScorerPrediction.player_name?.toLowerCase() === tournament.top_scorer_name?.toLowerCase()" class="font-bold text-emerald-600">+15 pts ✓</span>
                                    <span v-else class="text-gray-400">0 pts</span>
                                </div>
                                <div class="mt-3 p-3 bg-gray-100 rounded-xl text-center">
                                    <span class="text-gray-600">Points bonus : </span>
                                    <span class="font-bold text-xl" :class="(userTopScorerPrediction.points_earned ?? 0) > 0 ? 'text-emerald-600' : 'text-gray-500'">
                                        +{{ userTopScorerPrediction.points_earned ?? 0 }} pts
                                    </span>
                                </div>
                            </div>

                            <!-- Formulaire -->
                            <div v-else-if="showTopScorerForm && !tournament.winner_predictions_locked && tournament.predictions_open">
                                <form @submit.prevent="submitTopScorerPrediction" class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Nom du meilleur buteur</label>
                                        <TextInput v-model="topScorerForm.player_name" type="text" class="w-full" placeholder="Ex: Kylian Mbappé" required />
                                        <InputError class="mt-2" :message="topScorerForm.errors.player_name" />
                                    </div>
                                    <div class="flex gap-3">
                                        <SecondaryButton @click="showTopScorerForm = false" type="button">Annuler</SecondaryButton>
                                        <PrimaryButton :disabled="!topScorerForm.player_name || topScorerForm.processing" class="bg-blue-600 hover:bg-blue-700">Valider</PrimaryButton>
                                    </div>
                                </form>
                            </div>

                            <!-- Pronostic existant -->
                            <div v-else-if="userTopScorerPrediction && !showTopScorerForm">
                                <div class="flex items-center justify-between p-4 bg-blue-50 rounded-xl border border-blue-200">
                                    <span class="font-medium text-gray-800">{{ userTopScorerPrediction.player_name }}</span>
                                    <span class="text-sm text-blue-600">+15 pts si correct</span>
                                </div>
                                <div v-if="tournament.winner_predictions_locked || !tournament.predictions_open" class="text-center mt-3">
                                    <span class="text-sm text-gray-400">Pronostic verrouillé</span>
                                </div>
                            </div>

                            <!-- Pas de pronostic -->
                            <div v-else class="text-center py-6">
                                <p class="text-gray-500 mb-3">Nomme le joueur qui sera le meilleur buteur de la compétition</p>
                                <PrimaryButton v-if="!tournament.winner_predictions_locked && tournament.predictions_open" @click="initTopScorerForm" class="bg-blue-600 hover:bg-blue-700">
                                    Faire mon pronostic
                                </PrimaryButton>
                                <p v-else class="text-sm text-gray-400">Pronostic verrouillé</p>
                            </div>
                        </div>
                    </div>

                    <!-- Admin: Désigner le Vrai Gros Loser -->
                    <div v-if="isAdmin || $page.props.auth.user.is_admin" class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex justify-between items-center mb-4">
                                <div>
                                    <h3 class="text-lg font-semibold">Désigner le Vrai Gros Loser</h3>
                                    <p class="text-sm text-gray-500">Le participant qui finira dernier au classement du tournoi</p>
                                </div>
                                <PrimaryButton @click="showSetLastPlaceModal = true" class="bg-red-700 hover:bg-red-800">
                                    {{ tournament.last_place_user_id ? 'Modifier' : 'Désigner' }}
                                </PrimaryButton>
                            </div>
                            <div v-if="tournament.last_place_user" class="bg-red-50 rounded-xl p-4 border border-red-200 flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-red-200 flex items-center justify-center text-red-700 font-bold text-lg flex-shrink-0">
                                    {{ tournament.last_place_user.name?.charAt(0).toUpperCase() }}
                                </div>
                                <span class="font-bold text-xl text-gray-900">{{ tournament.last_place_user.name }}</span>
                            </div>
                            <div v-else class="bg-gray-50 rounded-xl p-4 text-center text-gray-500">Aucun Vrai Gros Loser désigné pour l'instant</div>
                        </div>
                    </div>

                    <!-- Mon pronostic: Vrai Gros Loser -->
                    <div v-if="isMember" class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex justify-between items-center mb-4">
                                <div>
                                    <h3 class="text-lg font-semibold">Le Vrai Gros Loser</h3>
                                    <p class="text-sm text-gray-500">Qui va finir dernier du classement ? (+15 pts)</p>
                                </div>
                                <button
                                    v-if="!tournament.winner_predictions_locked && tournament.predictions_open && !showLastPlaceForm"
                                    @click="initLastPlaceForm"
                                    class="text-sm font-medium text-red-600 hover:text-red-800"
                                >
                                    {{ userLastPlacePrediction ? 'Modifier' : 'Faire mon pronostic' }}
                                </button>
                            </div>

                            <!-- Résultat affiché -->
                            <div v-if="tournament.last_place_user_id && userLastPlacePrediction">
                                <div :class="['flex items-center justify-between p-4 rounded-xl border', userLastPlacePrediction.predicted_user_id === tournament.last_place_user_id ? 'bg-emerald-50 border-emerald-300' : 'bg-red-50 border-red-200']">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-red-200 flex items-center justify-center text-red-700 font-bold text-sm flex-shrink-0">
                                            {{ userLastPlacePrediction.predicted_user?.name?.charAt(0).toUpperCase() }}
                                        </div>
                                        <span class="font-medium text-gray-800">{{ userLastPlacePrediction.predicted_user?.name }}</span>
                                    </div>
                                    <span v-if="userLastPlacePrediction.predicted_user_id === tournament.last_place_user_id" class="font-bold text-emerald-600">+15 pts ✓</span>
                                    <span v-else class="text-gray-400">0 pts</span>
                                </div>
                                <div class="mt-3 p-3 bg-gray-100 rounded-xl text-center">
                                    <span class="text-gray-600">Points bonus : </span>
                                    <span class="font-bold text-xl" :class="(userLastPlacePrediction.points_earned ?? 0) > 0 ? 'text-emerald-600' : 'text-gray-500'">
                                        +{{ userLastPlacePrediction.points_earned ?? 0 }} pts
                                    </span>
                                </div>
                            </div>

                            <!-- Formulaire -->
                            <div v-else-if="showLastPlaceForm && !tournament.winner_predictions_locked && tournament.predictions_open">
                                <form @submit.prevent="submitLastPlacePrediction" class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Qui va finir dernier ?</label>
                                        <select v-model="lastPlaceForm.predicted_user_id" class="w-full rounded-lg border-gray-300 focus:ring-red-500 focus:border-red-500">
                                            <option :value="null">Sélectionner un joueur...</option>
                                            <option v-for="member in tournament.members" :key="member.id" :value="member.id">{{ member.name }}</option>
                                        </select>
                                        <InputError class="mt-2" :message="lastPlaceForm.errors.predicted_user_id" />
                                    </div>
                                    <div class="flex gap-3">
                                        <SecondaryButton @click="showLastPlaceForm = false" type="button">Annuler</SecondaryButton>
                                        <PrimaryButton :disabled="!lastPlaceForm.predicted_user_id || lastPlaceForm.processing" class="bg-red-700 hover:bg-red-800">Valider</PrimaryButton>
                                    </div>
                                </form>
                            </div>

                            <!-- Pronostic existant -->
                            <div v-else-if="userLastPlacePrediction && !showLastPlaceForm">
                                <div class="flex items-center justify-between p-4 bg-red-50 rounded-xl border border-red-200">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-red-200 flex items-center justify-center text-red-700 font-bold text-sm flex-shrink-0">
                                            {{ userLastPlacePrediction.predicted_user?.name?.charAt(0).toUpperCase() }}
                                        </div>
                                        <span class="font-medium text-gray-800">{{ userLastPlacePrediction.predicted_user?.name }}</span>
                                    </div>
                                    <span class="text-sm text-red-500">+15 pts si correct</span>
                                </div>
                                <div v-if="tournament.winner_predictions_locked || !tournament.predictions_open" class="text-center mt-3">
                                    <span class="text-sm text-gray-400">Pronostic verrouillé</span>
                                </div>
                            </div>

                            <!-- Pas de pronostic -->
                            <div v-else class="text-center py-6">
                                <p class="text-gray-500 mb-3">Désigne le participant qui finira dernier au classement</p>
                                <PrimaryButton v-if="!tournament.winner_predictions_locked && tournament.predictions_open" @click="initLastPlaceForm" class="bg-red-700 hover:bg-red-800">
                                    Faire mon pronostic
                                </PrimaryButton>
                                <p v-else class="text-sm text-gray-400">Pronostic verrouillé</p>
                            </div>
                        </div>
                    </div>

                    <!-- Lien vers tous les pronostics vainqueur -->
                    <div v-if="isMember" class="text-center">
                        <Link
                            :href="route('tournaments.allWinnerPredictions', tournament.id)"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-amber-500 text-white rounded-lg text-sm font-medium hover:bg-amber-600 transition"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            Voir tous les pronostics vainqueur
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- Predefined Teams Modal -->
        <Modal :show="showPredefinedTeamsModal" @close="showPredefinedTeamsModal = false" max-width="4xl">
            <div class="p-6">
                <h2 class="text-lg font-semibold mb-4">Ajouter des equipes</h2>

                <div class="flex gap-4 mb-4">
                    <div class="flex-1">
                        <TextInput
                            v-model="searchQuery"
                            type="text"
                            class="w-full"
                            placeholder="Rechercher une equipe..."
                        />
                    </div>
                    <select
                        v-model="selectedConfederation"
                        class="rounded-md border-gray-300"
                    >
                        <option v-for="conf in confederations" :key="conf.value" :value="conf.value">
                            {{ conf.label }}
                        </option>
                    </select>
                </div>

                <div v-if="selectedTeams.length > 0" class="mb-4 p-3 bg-indigo-50 rounded-lg">
                    <span class="text-indigo-700 font-medium">
                        {{ selectedTeams.length }} equipe(s) selectionnee(s)
                    </span>
                </div>

                <div class="max-h-96 overflow-y-auto">
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2">
                        <button
                            v-for="team in filteredPredefinedTeams"
                            :key="team.name"
                            @click="!isTeamAlreadyAdded(team) && toggleTeamSelection(team)"
                            :disabled="isTeamAlreadyAdded(team)"
                            :class="[
                                'p-3 border rounded-lg text-left transition flex items-center gap-2',
                                isTeamAlreadyAdded(team)
                                    ? 'bg-gray-100 text-gray-400 cursor-not-allowed'
                                    : isTeamSelected(team)
                                        ? 'border-indigo-500 bg-indigo-50'
                                        : 'hover:bg-gray-50'
                            ]"
                        >
                            <TeamFlag :flag="team.flag" size="lg" />
                            <div class="min-w-0">
                                <div class="font-medium truncate">{{ team.name }}</div>
                                <div class="text-xs text-gray-500">{{ team.short_name }}</div>
                            </div>
                        </button>
                    </div>
                </div>

                <div class="flex justify-end gap-4 mt-6">
                    <SecondaryButton @click="showPredefinedTeamsModal = false; selectedTeams = [];">
                        Annuler
                    </SecondaryButton>
                    <PrimaryButton
                        @click="importSelectedTeams"
                        :disabled="selectedTeams.length === 0"
                    >
                        Ajouter {{ selectedTeams.length }} equipe(s)
                    </PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- Add Team Modal (Manual) -->
        <Modal :show="showAddTeamModal" @close="showAddTeamModal = false">
            <div class="p-6">
                <h2 class="text-lg font-semibold mb-4">Ajouter une equipe manuellement</h2>
                <form @submit.prevent="submitTeam" class="space-y-4">
                    <div>
                        <InputLabel for="team_name" value="Nom de l'equipe" />
                        <TextInput
                            id="team_name"
                            v-model="teamForm.name"
                            type="text"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError class="mt-2" :message="teamForm.errors.name" />
                    </div>

                    <div>
                        <InputLabel for="short_name" value="Abreviation (optionnel)" />
                        <TextInput
                            id="short_name"
                            v-model="teamForm.short_name"
                            type="text"
                            class="mt-1 block w-full"
                            maxlength="5"
                        />
                    </div>

                    <div>
                        <InputLabel for="flag" value="Drapeau emoji (optionnel)" />
                        <TextInput
                            id="flag"
                            v-model="teamForm.flag"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="Ex: FR"
                        />
                    </div>

                    <div class="flex justify-end gap-4">
                        <SecondaryButton @click="showAddTeamModal = false">
                            Annuler
                        </SecondaryButton>
                        <PrimaryButton :disabled="teamForm.processing">
                            Ajouter
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Create Group Modal -->
        <Modal :show="showCreateGroupModal" @close="showCreateGroupModal = false">
            <div class="p-6">
                <h2 class="text-lg font-semibold mb-4">Creer un groupe</h2>
                <form @submit.prevent="createGroup" class="space-y-4">
                    <div>
                        <InputLabel for="group_name" value="Nom du groupe" />
                        <TextInput
                            id="group_name"
                            v-model="groupForm.name"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="Ex: Groupe A"
                            required
                        />
                        <InputError class="mt-2" :message="groupForm.errors.name" />
                    </div>

                    <div class="flex justify-end gap-4">
                        <SecondaryButton @click="showCreateGroupModal = false">
                            Annuler
                        </SecondaryButton>
                        <PrimaryButton :disabled="groupForm.processing">
                            Creer
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Add Team to Group Modal -->
        <Modal :show="showAddTeamToGroupModal" @close="showAddTeamToGroupModal = false" max-width="2xl">
            <div class="p-6">
                <h2 class="text-lg font-semibold mb-4">
                    Ajouter des equipes au {{ selectedGroupForTeam?.name }}
                </h2>

                <div v-if="unassignedTeams.length === 0" class="text-center py-8 text-gray-500">
                    Toutes les equipes sont deja assignees a des groupes.
                </div>

                <div v-else>
                    <p class="text-sm text-gray-500 mb-4">
                        Selectionnez les equipes a ajouter ({{ selectedTeamsForGroup.length }} selectionnee(s))
                    </p>

                    <div class="grid grid-cols-2 md:grid-cols-3 gap-2 max-h-96 overflow-y-auto">
                        <button
                            v-for="team in unassignedTeams"
                            :key="team.id"
                            type="button"
                            @click="toggleTeamForGroup(team.id)"
                            :class="[
                                'p-3 border rounded-lg text-left transition flex items-center gap-2',
                                selectedTeamsForGroup.includes(team.id)
                                    ? 'border-indigo-500 bg-indigo-50 ring-2 ring-indigo-500'
                                    : 'hover:bg-gray-50'
                            ]"
                        >
                            <TeamFlag :flag="team.flag" size="md" />
                            <span class="font-medium text-sm truncate">{{ team.name }}</span>
                        </button>
                    </div>
                </div>

                <div class="flex justify-end gap-4 mt-6">
                    <SecondaryButton @click="showAddTeamToGroupModal = false">
                        Annuler
                    </SecondaryButton>
                    <PrimaryButton
                        @click="addTeamToGroup"
                        :disabled="selectedTeamsForGroup.length === 0"
                    >
                        Ajouter {{ selectedTeamsForGroup.length }} equipe(s)
                    </PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- Add Match Modal -->
        <Modal :show="showAddMatchModal" @close="showAddMatchModal = false">
            <div class="p-6">
                <h2 class="text-lg font-semibold mb-4">Ajouter un match</h2>
                <form @submit.prevent="submitMatch" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="home_team" value="Equipe domicile" />
                            <select
                                id="home_team"
                                v-model="matchForm.home_team_id"
                                class="mt-1 block w-full rounded-md border-gray-300"
                            >
                                <option value="">Selectionner...</option>
                                <option v-for="team in tournament.teams" :key="team.id" :value="team.id">
                                    {{ team.name }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <InputLabel for="away_team" value="Equipe exterieur" />
                            <select
                                id="away_team"
                                v-model="matchForm.away_team_id"
                                class="mt-1 block w-full rounded-md border-gray-300"
                            >
                                <option value="">Selectionner...</option>
                                <option v-for="team in tournament.teams" :key="team.id" :value="team.id">
                                    {{ team.name }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <InputLabel for="round" value="Tour" />
                        <select
                            id="round"
                            v-model="matchForm.round"
                            class="mt-1 block w-full rounded-md border-gray-300"
                        >
                            <option value="group">Phase de poules</option>
                            <option value="round_of_32">32emes de finale</option>
                            <option value="round_of_16">8emes de finale</option>
                            <option value="quarter">Quarts de finale</option>
                            <option value="semi">Demi-finales</option>
                            <option value="final">Finale</option>
                        </select>
                    </div>

                    <div>
                        <InputLabel for="scheduled_at" value="Date et heure du match (optionnel)" />
                        <TextInput
                            id="scheduled_at"
                            v-model="matchForm.scheduled_at"
                            type="datetime-local"
                            class="mt-1 block w-full"
                        />
                    </div>

                    <div class="flex justify-end gap-4">
                        <SecondaryButton @click="showAddMatchModal = false">
                            Annuler
                        </SecondaryButton>
                        <PrimaryButton :disabled="matchForm.processing">
                            Ajouter
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Schedule Modal -->
        <Modal :show="showScheduleModal" @close="showScheduleModal = false">
            <div class="p-6">
                <h2 class="text-lg font-semibold mb-1">Modifier l'horaire</h2>
                <div v-if="selectedMatchForSchedule" class="mb-4 flex items-center justify-center gap-3 p-3 bg-gray-50 rounded-lg text-sm font-medium text-gray-700">
                    <span>{{ selectedMatchForSchedule.home_team?.name || selectedMatchForSchedule.placeholder_home || 'TBD' }}</span>
                    <span class="text-gray-400">vs</span>
                    <span>{{ selectedMatchForSchedule.away_team?.name || selectedMatchForSchedule.placeholder_away || 'TBD' }}</span>
                </div>
                <form @submit.prevent="submitSchedule" class="space-y-4">
                    <div>
                        <InputLabel for="schedule_scheduled_at" value="Date et heure du match" />
                        <TextInput
                            id="schedule_scheduled_at"
                            v-model="scheduleForm.scheduled_at"
                            type="datetime-local"
                            class="mt-1 block w-full"
                        />
                        <InputError class="mt-1" :message="scheduleForm.errors.scheduled_at" />
                    </div>
                    <div class="flex justify-end gap-4 pt-2">
                        <SecondaryButton @click="showScheduleModal = false" type="button">Annuler</SecondaryButton>
                        <PrimaryButton :disabled="scheduleForm.processing" class="bg-blue-600 hover:bg-blue-700">
                            Enregistrer
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Result Modal -->
        <Modal :show="showResultModal" @close="showResultModal = false">
            <div class="p-6">
                <h2 class="text-lg font-semibold mb-4">Entrer le resultat</h2>

                <div v-if="selectedMatchForResult" class="mb-6">
                    <div class="flex items-center justify-center gap-4 p-4 bg-gray-50 rounded-lg">
                        <div class="flex items-center gap-2">
                            <TeamFlag :flag="selectedMatchForResult.home_team?.flag" size="lg" />
                            <span class="font-medium">{{ selectedMatchForResult.home_team?.name }}</span>
                        </div>
                        <span class="text-gray-400">vs</span>
                        <div class="flex items-center gap-2">
                            <span class="font-medium">{{ selectedMatchForResult.away_team?.name }}</span>
                            <TeamFlag :flag="selectedMatchForResult.away_team?.flag" size="lg" />
                        </div>
                    </div>
                </div>

                <form @submit.prevent="submitResult" class="space-y-4">
                    <div class="flex items-center justify-center gap-4">
                        <div class="text-center">
                            <InputLabel class="mb-2">{{ selectedMatchForResult?.home_team?.name }}</InputLabel>
                            <TextInput
                                v-model="resultForm.home_score"
                                type="number"
                                min="0"
                                max="99"
                                class="w-20 text-center text-2xl font-bold"
                            />
                        </div>

                        <span class="text-2xl font-bold text-gray-400 mt-6">-</span>

                        <div class="text-center">
                            <InputLabel class="mb-2">{{ selectedMatchForResult?.away_team?.name }}</InputLabel>
                            <TextInput
                                v-model="resultForm.away_score"
                                type="number"
                                min="0"
                                max="99"
                                class="w-20 text-center text-2xl font-bold"
                            />
                        </div>
                    </div>

                    <InputError :message="resultForm.errors.home_score" />
                    <InputError :message="resultForm.errors.away_score" />

                    <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 text-sm text-amber-800">
                        <strong>Attention :</strong> Valider le resultat calculera automatiquement les points de tous les pronostics et mettra a jour les classements.
                    </div>

                    <div class="flex justify-end gap-4 pt-4">
                        <SecondaryButton @click="showResultModal = false">
                            Annuler
                        </SecondaryButton>
                        <PrimaryButton :disabled="resultForm.processing" class="bg-emerald-600 hover:bg-emerald-700">
                            Valider le resultat
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Set Loser Modal (Admin) -->
        <Modal :show="showSetLoserModal" @close="showSetLoserModal = false">
            <div class="p-6">
                <h2 class="text-lg font-semibold mb-4">Désigner le gros loser</h2>
                <div class="mb-4 p-4 bg-gray-50 border border-gray-200 rounded-lg">
                    <p class="text-sm text-gray-700">
                        <strong>Note :</strong> Cette action calculera les +15 points pour les joueurs ayant correctement prédit la pire équipe.
                    </p>
                </div>
                <form @submit.prevent="submitSetLoser" class="space-y-4">
                    <div>
                        <InputLabel for="loser_team" value="Équipe perdante (0 pt, pire diff. de buts)" />
                        <select id="loser_team" v-model="setLoserForm.loser_team_id" class="mt-1 block w-full rounded-md border-gray-300">
                            <option :value="null">Sélectionner l'équipe...</option>
                            <option v-for="team in tournament.teams" :key="team.id" :value="team.id">{{ team.name }}</option>
                        </select>
                    </div>
                    <div class="flex justify-end gap-4 pt-4">
                        <SecondaryButton @click="showSetLoserModal = false">Annuler</SecondaryButton>
                        <PrimaryButton :disabled="!setLoserForm.loser_team_id || setLoserForm.processing" class="bg-gray-700 hover:bg-gray-800">
                            Confirmer
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Set Top Scorer Modal (Admin) -->
        <Modal :show="showSetTopScorerModal" @close="showSetTopScorerModal = false">
            <div class="p-6">
                <h2 class="text-lg font-semibold mb-4">Désigner la star de la compétition</h2>
                <div class="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <p class="text-sm text-blue-800">
                        <strong>Note :</strong> Cette action calculera les +15 points pour les joueurs ayant correctement prédit le meilleur buteur.
                    </p>
                </div>
                <form @submit.prevent="submitSetTopScorer" class="space-y-4">
                    <div>
                        <InputLabel for="top_scorer_name" value="Nom du meilleur buteur" />
                        <TextInput id="top_scorer_name" v-model="setTopScorerForm.top_scorer_name" type="text" class="mt-1 block w-full" placeholder="Ex: Kylian Mbappé" />
                    </div>
                    <div class="flex justify-end gap-4 pt-4">
                        <SecondaryButton @click="showSetTopScorerModal = false">Annuler</SecondaryButton>
                        <PrimaryButton :disabled="!setTopScorerForm.top_scorer_name || setTopScorerForm.processing" class="bg-blue-600 hover:bg-blue-700">
                            Confirmer
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Set Last Place Modal (Admin) -->
        <Modal :show="showSetLastPlaceModal" @close="showSetLastPlaceModal = false">
            <div class="p-6">
                <h2 class="text-lg font-semibold mb-4">Désigner le Vrai Gros Loser</h2>
                <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                    <p class="text-sm text-red-800">
                        <strong>Note :</strong> Cette action calculera les +15 points pour les joueurs ayant correctement prédit le participant dernier au classement.
                    </p>
                </div>
                <form @submit.prevent="submitSetLastPlace" class="space-y-4">
                    <div>
                        <InputLabel for="last_place_user" value="Participant qui finira dernier" />
                        <select id="last_place_user" v-model="setLastPlaceForm.last_place_user_id" class="mt-1 block w-full rounded-md border-gray-300">
                            <option :value="null">Sélectionner un joueur...</option>
                            <option v-for="member in tournament.members" :key="member.id" :value="member.id">
                                {{ member.name }}
                            </option>
                        </select>
                    </div>
                    <div class="flex justify-end gap-4 pt-4">
                        <SecondaryButton @click="showSetLastPlaceModal = false">Annuler</SecondaryButton>
                        <PrimaryButton :disabled="!setLastPlaceForm.last_place_user_id || setLastPlaceForm.processing" class="bg-red-700 hover:bg-red-800">
                            Confirmer
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Generate Matches Modal -->
        <Modal :show="showGenerateMatchesModal" @close="showGenerateMatchesModal = false">
            <div class="p-6">
                <h2 class="text-lg font-semibold mb-1">Générer les matchs de poule</h2>
                <p class="text-sm text-gray-500 mb-4">Les matchs existants ne seront pas dupliqués.</p>
                <form @submit.prevent="submitGenerateMatches" class="space-y-4">
                    <div>
                        <InputLabel for="gen_scheduled_at" value="Date et heure des matchs (optionnel)" />
                        <TextInput
                            id="gen_scheduled_at"
                            v-model="generateMatchesForm.scheduled_at"
                            type="datetime-local"
                            class="mt-1 block w-full"
                        />
                        <InputError class="mt-1" :message="generateMatchesForm.errors.scheduled_at" />
                    </div>
                    <p class="text-xs text-gray-400">Laissez vide pour générer sans date. Vous pourrez modifier chaque match individuellement ensuite.</p>
                    <div class="flex justify-end gap-4 pt-2">
                        <SecondaryButton @click="showGenerateMatchesModal = false" type="button">Annuler</SecondaryButton>
                        <PrimaryButton :disabled="generateMatchesForm.processing" class="bg-green-600 hover:bg-green-700">
                            Générer les matchs
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Set Winner Modal (Admin) -->
        <Modal :show="showSetWinnerModal" @close="showSetWinnerModal = false">
            <div class="p-6">
                <h2 class="text-lg font-semibold mb-4">Désigner le vainqueur du tournoi</h2>

                <div class="mb-4 p-4 bg-amber-50 border border-amber-200 rounded-lg">
                    <p class="text-sm text-amber-800">
                        <strong>Attention :</strong> Cette action calculera les points bonus pour tous les pronostics vainqueur et mettra à jour les classements de tous les groupes.
                    </p>
                </div>

                <form @submit.prevent="submitSetWinner" class="space-y-4">
                    <div>
                        <InputLabel for="winner_team" value="Équipe vainqueur" />
                        <select
                            id="winner_team"
                            v-model="setWinnerForm.winner_team_id"
                            class="mt-1 block w-full rounded-md border-gray-300"
                        >
                            <option :value="null">Sélectionner l'équipe...</option>
                            <option v-for="team in tournament.teams" :key="team.id" :value="team.id">
                                {{ team.name }}
                            </option>
                        </select>
                    </div>

                    <div class="flex justify-end gap-4 pt-4">
                        <SecondaryButton @click="showSetWinnerModal = false">
                            Annuler
                        </SecondaryButton>
                        <PrimaryButton
                            :disabled="!setWinnerForm.winner_team_id || setWinnerForm.processing"
                            class="bg-amber-600 hover:bg-amber-700"
                        >
                            Confirmer le vainqueur
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
