<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TeamFlag from '@/Components/TeamFlag.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    myTournaments: Array,
    matchesOfDay: Array,
    selectedDate: String,
    availableDates: Array,
    userPredictions: Object,
    userWinnerPredictions: Object,
    membersWinnerPredictions: Object,
    doubleStatsPerTournament: { type: Object, default: () => ({}) },
    myBlocksPerTournament: { type: Object, default: () => ({}) },
    mySwapsPerTournament: { type: Object, default: () => ({}) },
    takenBlocksPerTournament: { type: Object, default: () => ({}) },
    takenSwapsPerTournament: { type: Object, default: () => ({}) },
    allSwapsPerTournament: { type: Object, default: () => ({}) },
    allBlocksPerTournament: { type: Object, default: () => ({}) },
    recapData: { type: Object, default: null },
});

// ── Popup récap quotidien ────────────────────────────────────────────────────
const showRecap = ref(false);
const recapPhase = ref('logo'); // 'logo' | 'data'
const logoAnimStep = ref('zoom'); // 'zoom' | 'spin' | 'text' | 'explode'

const logoAnimClass = computed(() => {
    if (logoAnimStep.value === 'zoom')    return 'animate-logo-zoom';
    if (logoAnimStep.value === 'spin')    return 'animate-logo-spin';
    if (logoAnimStep.value === 'explode') return 'animate-logo-explode';
    return ''; // 'text' : logo statique, animation terminée
});

const todayKey = new Date().toISOString().slice(0, 10);
const lastRecapKey = `recap_shown_${todayKey}`;

onMounted(() => {
    const forceOpen = new URLSearchParams(window.location.search).has('recap');
    if (forceOpen || (!localStorage.getItem(lastRecapKey) && props.recapData?.hasData)) {
        showRecap.value = true;
        recapPhase.value = 'logo';
        document.body.style.overflow = 'hidden';
        logoAnimStep.value = 'zoom';
        if (!forceOpen) localStorage.setItem(lastRecapKey, '1');
        setTimeout(() => { logoAnimStep.value = 'spin'; },    3000);
        setTimeout(() => { logoAnimStep.value = 'text'; },    4500);
        setTimeout(() => { logoAnimStep.value = 'explode'; }, 6800);
        setTimeout(() => { recapPhase.value = 'data'; },      7400);
    }
});

const openRecap = () => {
    showRecap.value = true;
    recapPhase.value = 'data';
    document.body.style.overflow = 'hidden';
};

const closeRecap = () => {
    showRecap.value = false;
    document.body.style.overflow = '';
};

watch([showRecap, recapPhase], ([isRecap, phase]) => {
    document.body.classList.toggle('logo-animating', isRecap && phase === 'logo');
});

onUnmounted(() => {
    document.body.classList.remove('logo-animating');
    document.body.style.overflow = '';
});

const fallingTexts = [
    // Géants
    { id:  1, tx: '-380px', peak: '-580px', rot: '-22deg', delay: '0.10s', dur: '5.0s', size: '48px', font: 'Impact, sans-serif',              color: '#818cf8' },
    { id:  2, tx:  '420px', peak: '-520px', rot:  '18deg', delay: '0.35s', dur: '4.6s', size: '42px', font: "'Arial Black', sans-serif",        color: '#fbbf24' },
    { id:  3, tx: '-240px', peak: '-680px', rot: '-12deg', delay: '0.60s', dur: '5.4s', size: '52px', font: 'Georgia, serif',                   color: '#f87171' },
    { id:  4, tx:  '310px', peak: '-610px', rot:  '25deg', delay: '1.20s', dur: '4.8s', size: '44px', font: 'Impact, sans-serif',              color: '#67e8f9' },
    { id:  5, tx: '-480px', peak: '-560px', rot: '-40deg', delay: '2.50s', dur: '5.2s', size: '56px', font: 'Impact, sans-serif',              color: '#a78bfa' },
    { id:  6, tx:  '440px', peak: '-680px', rot:  '38deg', delay: '0.75s', dur: '5.6s', size: '50px', font: 'Georgia, serif',                   color: '#f87171' },
    { id:  7, tx: '-320px', peak: '-740px', rot: '-15deg', delay: '1.90s', dur: '6.0s', size: '46px', font: "'Trebuchet MS', sans-serif",       color: '#67e8f9' },
    { id:  8, tx:  '280px', peak: '-490px', rot:  '20deg', delay: '3.40s', dur: '4.6s', size: '40px', font: "'Arial Black', sans-serif",        color: '#fde047' },
    { id:  9, tx:  '-60px', peak: '-700px', rot:  '-8deg', delay: '0.45s', dur: '5.8s', size: '54px', font: 'Impact, sans-serif',              color: '#34d399' },
    // Grands
    { id: 10, tx: '-180px', peak: '-460px', rot: '-16deg', delay: '0.25s', dur: '4.2s', size: '32px', font: "'Trebuchet MS', sans-serif",       color: '#a78bfa' },
    { id: 11, tx:  '250px', peak: '-490px', rot:  '14deg', delay: '0.80s', dur: '4.0s', size: '28px', font: 'Impact, sans-serif',              color: '#fb923c' },
    { id: 12, tx: '-340px', peak: '-420px', rot: '-28deg', delay: '1.50s', dur: '4.4s', size: '36px', font: 'Georgia, serif',                   color: '#f472b6' },
    { id: 13, tx:  '460px', peak: '-380px', rot:  '32deg', delay: '0.50s', dur: '3.8s', size: '30px', font: "'Arial Black', sans-serif",        color: '#4ade80' },
    { id: 14, tx: '-120px', peak: '-540px', rot:  '-8deg', delay: '2.00s', dur: '5.0s', size: '34px', font: "'Courier New', monospace",         color: '#fde047' },
    { id: 15, tx:  '180px', peak: '-720px', rot:  '10deg', delay: '1.00s', dur: '5.8s', size: '38px', font: 'Impact, sans-serif',              color: '#ffffff' },
    // Moyens
    { id: 16, tx: '-290px', peak: '-350px', rot: '-20deg', delay: '0.15s', dur: '3.6s', size: '18px', font: 'Verdana, sans-serif',              color: '#c7d2fe' },
    { id: 17, tx:  '200px', peak: '-400px', rot:  '22deg', delay: '0.70s', dur: '3.8s', size: '20px', font: 'Impact, sans-serif',              color: '#38bdf8' },
    { id: 18, tx:  '-80px', peak: '-480px', rot:  '-6deg', delay: '1.80s', dur: '4.2s', size: '16px', font: 'Georgia, serif',                   color: '#e879f9' },
    { id: 19, tx:  '140px', peak: '-360px', rot:  '12deg', delay: '2.40s', dur: '3.4s', size: '22px', font: "'Courier New', monospace",         color: '#fb923c' },
    { id: 20, tx: '-420px', peak: '-500px', rot: '-35deg', delay: '0.90s', dur: '4.6s', size: '14px', font: "'Arial Black', sans-serif",        color: '#818cf8' },
    { id: 21, tx:  '370px', peak: '-450px', rot:  '28deg', delay: '1.60s', dur: '4.2s', size: '24px', font: 'Impact, sans-serif',              color: '#fbbf24' },
    { id: 22, tx:  '80px',  peak: '-600px', rot:   '8deg', delay: '2.80s', dur: '5.2s', size: '20px', font: 'Georgia, serif',                   color: '#34d399' },
    // Petits
    { id: 23, tx:  '-50px', peak: '-380px', rot: '-15deg', delay: '0.40s', dur: '3.8s', size: '10px', font: 'Impact, sans-serif',              color: '#c7d2fe' },
    { id: 24, tx:  '320px', peak: '-340px', rot:  '20deg', delay: '1.10s', dur: '3.6s', size: '12px', font: 'Georgia, serif',                   color: '#f472b6' },
    { id: 25, tx: '-440px', peak: '-280px', rot: '-30deg', delay: '1.40s', dur: '3.4s', size:  '9px', font: 'Verdana, sans-serif',              color: '#67e8f9' },
    { id: 26, tx:  '110px', peak: '-430px', rot:  '16deg', delay: '2.20s', dur: '4.0s', size: '11px', font: "'Courier New', monospace",         color: '#a78bfa' },
    { id: 27, tx: '-200px', peak: '-500px', rot: '-18deg', delay: '2.60s', dur: '4.4s', size: '10px', font: "'Arial Black', sans-serif",        color: '#fde047' },
    { id: 28, tx:  '260px', peak: '-560px', rot:  '24deg', delay: '0.55s', dur: '4.8s', size: '12px', font: 'Impact, sans-serif',              color: '#38bdf8' },
    // Minuscules
    { id: 29, tx: '-350px', peak: '-420px', rot: '-24deg', delay: '1.70s', dur: '4.2s', size:  '6px', font: 'Georgia, serif',                   color: '#ffffff' },
    { id: 30, tx:  '390px', peak: '-280px', rot:  '30deg', delay: '0.20s', dur: '3.4s', size:  '7px', font: 'Verdana, sans-serif',              color: '#fb923c' },
    { id: 31, tx: '-100px', peak: '-640px', rot:  '-4deg', delay: '3.20s', dur: '5.4s', size:  '5px', font: 'Impact, sans-serif',              color: '#e879f9' },
    { id: 32, tx:  '170px', peak: '-320px', rot:  '15deg', delay: '0.65s', dur: '3.6s', size:  '6px', font: "'Courier New', monospace",         color: '#4ade80' },
    { id: 33, tx: '-270px', peak: '-380px', rot: '-22deg', delay: '2.00s', dur: '3.8s', size:  '7px', font: 'Georgia, serif',                   color: '#fbbf24' },
    { id: 34, tx:  '490px', peak: '-460px', rot:  '35deg', delay: '1.30s', dur: '4.2s', size:  '5px', font: "'Arial Black', sans-serif",        color: '#818cf8' },
    { id: 35, tx: '-160px', peak: '-320px', rot: '-10deg', delay: '3.00s', dur: '3.6s', size:  '8px', font: 'Verdana, sans-serif',              color: '#f87171' },
];

const formatFootballDay = (dateStr) => {
    if (!dateStr) return '';
    const date = new Date(dateStr + 'T12:00:00');
    return date.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' });
};

const positionLabel = (pos) => {
    if (!pos) return '?';
    if (pos === 1) return '1er';
    return `${pos}ème`;
};

const positionDiff = computed(() => {
    const curr = props.recapData?.myCurrentPosition;
    const prev = props.recapData?.myPreviousPosition;
    if (!curr || !prev) return null;
    const diff = prev - curr; // positif = montée
    return diff;
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

// Rejoindre un tournoi par code
const joinCode = ref('');
const joinTournament = () => {
    router.post(route('tournaments.join'), { access_code: joinCode.value.toUpperCase() });
};

// ── Pronos Spéciaux ──────────────────────────────────────────────────────────

const page = usePage();
const csrf = () => document.querySelector('meta[name="csrf-token"]').getAttribute('content');

// État local pour les doublés (matchId → bool)
const localDoubleActive = ref({});
watch(() => props.userPredictions, (preds) => {
    const map = {};
    Object.entries(preds || {}).forEach(([k, v]) => { map[k] = v.is_doubled; });
    localDoubleActive.value = map;
}, { immediate: true });

// Stats doublés (tournamentId → {used, max, remaining})
const localDoubleStats = ref({});
watch(() => props.doubleStatsPerTournament, (stats) => {
    localDoubleStats.value = JSON.parse(JSON.stringify(stats || {}));
}, { immediate: true });

// Blocs posés (tournamentId → { opponentId → {id, target_user_id, target_match_id} })
const localBlocks = ref({});
watch(() => props.myBlocksPerTournament, (blocks) => {
    localBlocks.value = JSON.parse(JSON.stringify(blocks || {}));
}, { immediate: true });

// Échanges posés (tournamentId → { opponentId → {id, target_user_id, initiator_match_id, target_match_id} })
const localSwaps = ref({});
watch(() => props.mySwapsPerTournament, (swaps) => {
    localSwaps.value = JSON.parse(JSON.stringify(swaps || {}));
}, { immediate: true });

// Slots déjà pris par d'autres joueurs (tournamentId → { "opponentId_matchId" → true })
const takenBlocks = ref({});
watch(() => props.takenBlocksPerTournament, (v) => { takenBlocks.value = JSON.parse(JSON.stringify(v || {})); }, { immediate: true });
const takenSwaps = ref({});
watch(() => props.takenSwapsPerTournament, (v) => { takenSwaps.value = JSON.parse(JSON.stringify(v || {})); }, { immediate: true });

// Tous les échanges/blocs (pour affichage visuel dans la grille)
const localAllSwaps = ref({});
watch(() => props.allSwapsPerTournament, (v) => { localAllSwaps.value = JSON.parse(JSON.stringify(v || {})); }, { immediate: true });
const localAllBlocks = ref({});
watch(() => props.allBlocksPerTournament, (v) => { localAllBlocks.value = JSON.parse(JSON.stringify(v || {})); }, { immediate: true });

const isBlockTaken = (match, opponentId) => !!takenBlocks.value[match.tournament_id]?.[`${opponentId}_${match.id}`];
const isSwapTaken = (match, opponentId) => !!takenSwaps.value[match.tournament_id]?.[`${opponentId}_${match.id}`];

// Récupérer l'échange actif pour un membre sur un match (initiateur ou cible)
const getSwapForMember = (match, memberId) => {
    const swaps = localAllSwaps.value[match.tournament_id] || [];
    return swaps.find(s =>
        (s.initiator_user_id === memberId && s.initiator_match_id === match.id) ||
        (s.target_user_id === memberId && s.target_match_id === match.id)
    ) || null;
};

// Récupérer le bloc actif sur un membre pour un match
const getBlockForMember = (match, memberId) => {
    const blocks = localAllBlocks.value[match.tournament_id] || [];
    return blocks.find(b => b.target_user_id === memberId && b.target_match_id === match.id) || null;
};

// Nom d'un membre du tournoi courant
const getMemberName = (memberId) =>
    currentTournament.value?.members?.find(m => m.id === memberId)?.name || '?';

// Prono effectif après application de l'échange
const getEffectivePrediction = (match, memberId) => {
    const swap = getSwapForMember(match, memberId);
    if (!swap) return getMemberPrediction(match, memberId);
    const partnerId = swap.initiator_user_id === memberId ? swap.target_user_id : swap.initiator_user_id;
    return getMemberPrediction(match, partnerId);
};

// Confirmation avant échange
const swapConfirmDialog = ref(null);

const showSwapConfirm = (match, opponentId) => {
    const myPred = getMemberPrediction(match, page.props.auth.user.id);
    const opponentPred = getMemberPrediction(match, opponentId);
    const opponentName = currentOpponents.value.find(o => o.id === opponentId)?.name || '?';
    swapConfirmDialog.value = { match, opponentId, myPred, opponentPred, opponentName };
};

const confirmSwap = async () => {
    if (!swapConfirmDialog.value) return;
    const { match, opponentId } = swapConfirmDialog.value;
    swapConfirmDialog.value = null;
    await placeSwap(match, opponentId);
};

const doubleLoading = ref({});
const blockLoading = ref({});
const swapLoading = ref({});

// Adversaires du tournoi courant (sans l'utilisateur connecté)
const currentOpponents = computed(() =>
    currentTournament.value?.members?.filter(m => m.id !== page.props.auth.user.id) || []
);

// Le match est-il éligible aux pronos spéciaux (boutons actifs) ?
const canSpecialProno = (match) =>
    match.round === 'group'
    && match.status === 'scheduled'
    && !match.tournament?.predictions_open;

// Afficher la strip même quand pas encore éligible (pour info + bonuses visibles)
const showSpecialPronoStrip = (match) => match.round === 'group';

// Y a-t-il un bloc actif sur ce match (pour n'importe quel adversaire) ?
const hasAnyBlockOnMatch = (match) => {
    const blocks = localBlocks.value[match.tournament_id];
    if (!blocks) return false;
    return Object.values(blocks).some(b => b.target_match_id === match.id);
};

const isBlockLoading = (matchId, opponentId) =>
    !!blockLoading.value[`${matchId}-${opponentId}`];

// Toggler le doublé
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
            const stats = localDoubleStats.value[match.tournament_id] || { used: 0, max: 3, remaining: 3 };
            localDoubleStats.value[match.tournament_id] = { ...stats, remaining: data.remaining, used: stats.max - data.remaining };
        } else {
            alert(data.message);
        }
    } finally {
        doubleLoading.value[match.id] = false;
    }
};

// Poser un bloc sur un adversaire pour ce match
const placeBlock = async (match, opponentId) => {
    const key = `${match.id}-${opponentId}`;
    if (blockLoading.value[key]) return;
    blockLoading.value[key] = true;
    try {
        const res = await fetch('/blocks', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
            body: JSON.stringify({ tournament_id: match.tournament_id, target_user_id: opponentId, match_id: match.id }),
        });
        const data = await res.json();
        if (data.success) {
            const tId = match.tournament_id;
            if (!localBlocks.value[tId]) localBlocks.value[tId] = {};
            localBlocks.value[tId] = {
                ...localBlocks.value[tId],
                [opponentId]: { id: data.block_id, target_user_id: opponentId, target_match_id: match.id },
            };
            if (!localAllBlocks.value[tId]) localAllBlocks.value[tId] = [];
            localAllBlocks.value[tId] = [
                ...localAllBlocks.value[tId].filter(b => !(b.blocker_user_id === page.props.auth.user.id && b.target_user_id === opponentId && b.target_match_id === match.id)),
                { blocker_user_id: page.props.auth.user.id, target_user_id: opponentId, target_match_id: match.id },
            ];
        } else {
            alert(data.message);
        }
    } finally {
        blockLoading.value[key] = false;
    }
};

// Retirer un bloc
const removeBlock = async (blockId, tournamentId) => {
    const tBlocks = localBlocks.value[tournamentId] || {};
    const blockEntry = Object.values(tBlocks).find(b => b?.id === blockId);
    const res = await fetch(`/blocks/${blockId}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
    });
    const data = await res.json();
    if (data.success) {
        const updated = { ...localBlocks.value[tournamentId] };
        Object.keys(updated).forEach(k => { if (updated[k]?.id === blockId) delete updated[k]; });
        localBlocks.value[tournamentId] = updated;
        if (blockEntry) {
            localAllBlocks.value[tournamentId] = (localAllBlocks.value[tournamentId] || []).filter(b =>
                !(b.blocker_user_id === page.props.auth.user.id && b.target_user_id === blockEntry.target_user_id && b.target_match_id === blockEntry.target_match_id)
            );
        }
    } else {
        alert(data.message);
    }
};

// Poser un échange avec un adversaire pour ce match
const placeSwap = async (match, opponentId) => {
    const key = `${match.id}-${opponentId}`;
    if (swapLoading.value[key]) return;
    swapLoading.value[key] = true;
    try {
        const res = await fetch('/swaps', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
            body: JSON.stringify({ tournament_id: match.tournament_id, target_user_id: opponentId, initiator_match_id: match.id, target_match_id: match.id }),
        });
        const data = await res.json();
        if (data.success) {
            const tId = match.tournament_id;
            if (!localSwaps.value[tId]) localSwaps.value[tId] = {};
            localSwaps.value[tId] = {
                ...localSwaps.value[tId],
                [opponentId]: { id: data.swap_id, target_user_id: opponentId, initiator_match_id: match.id, target_match_id: match.id },
            };
            if (!localAllSwaps.value[tId]) localAllSwaps.value[tId] = [];
            localAllSwaps.value[tId] = [
                ...localAllSwaps.value[tId].filter(s => !(s.initiator_user_id === page.props.auth.user.id && s.target_user_id === opponentId && s.initiator_match_id === match.id)),
                { initiator_user_id: page.props.auth.user.id, target_user_id: opponentId, initiator_match_id: match.id, target_match_id: match.id },
            ];
        } else {
            alert(data.message);
        }
    } finally {
        swapLoading.value[key] = false;
    }
};

// Retirer un échange
const removeSwap = async (swapId, tournamentId) => {
    const tSwaps = localSwaps.value[tournamentId] || {};
    const swapEntry = Object.values(tSwaps).find(s => s?.id === swapId);
    const res = await fetch(`/swaps/${swapId}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
    });
    const data = await res.json();
    if (data.success) {
        const updated = { ...localSwaps.value[tournamentId] };
        Object.keys(updated).forEach(k => { if (updated[k]?.id === swapId) delete updated[k]; });
        localSwaps.value[tournamentId] = updated;
        if (swapEntry) {
            localAllSwaps.value[tournamentId] = (localAllSwaps.value[tournamentId] || []).filter(s =>
                !(s.initiator_user_id === page.props.auth.user.id && s.target_user_id === swapEntry.target_user_id && s.initiator_match_id === swapEntry.initiator_match_id)
            );
        }
    } else {
        alert(data.message);
    }
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
                                    <div class="w-10 h-10 mx-auto mb-2 rounded-full overflow-hidden shadow-inner">
                                        <img v-if="currentTournament.members[1]?.avatar_url" :src="currentTournament.members[1].avatar_url" class="w-full h-full object-cover" />
                                        <div v-else class="w-full h-full bg-gradient-to-br from-slate-200 to-slate-300 flex items-center justify-center text-slate-600 font-bold text-sm">
                                            {{ currentTournament.members[1]?.name?.charAt(0)?.toUpperCase() }}
                                        </div>
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
                                    <div class="w-12 h-12 mx-auto mb-2 rounded-full overflow-hidden shadow-inner">
                                        <img v-if="currentTournament.members[0]?.avatar_url" :src="currentTournament.members[0].avatar_url" class="w-full h-full object-cover" />
                                        <div v-else class="w-full h-full bg-gradient-to-br from-amber-200 to-yellow-300 flex items-center justify-center text-amber-700 font-bold text-lg">
                                            {{ currentTournament.members[0]?.name?.charAt(0)?.toUpperCase() }}
                                        </div>
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
                                    <div class="w-10 h-10 mx-auto mb-2 rounded-full overflow-hidden shadow-inner">
                                        <img v-if="currentTournament.members[2]?.avatar_url" :src="currentTournament.members[2].avatar_url" class="w-full h-full object-cover" />
                                        <div v-else class="w-full h-full bg-gradient-to-br from-orange-200 to-orange-300 flex items-center justify-center text-orange-700 font-bold text-sm">
                                            {{ currentTournament.members[2]?.name?.charAt(0)?.toUpperCase() }}
                                        </div>
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
                                <div class="w-8 h-8 rounded-full overflow-hidden flex items-center justify-center shadow-inner">
                                    <img v-if="member.avatar_url" :src="member.avatar_url" class="w-full h-full object-cover" />
                                    <div v-else class="w-full h-full bg-gray-100 flex items-center justify-center text-gray-500 font-medium text-xs">
                                        {{ member.name?.charAt(0)?.toUpperCase() }}
                                    </div>
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
                    <div v-else-if="currentTournament?.members?.length > 0 && currentTournament?.members?.length < 3" class="divide-y divide-gray-50">
                        <div
                            v-for="(member, idx) in currentTournament.members"
                            :key="member.id"
                            :class="[
                                'flex items-center gap-3 px-4 py-3.5 transition-colors',
                                member.id === $page.props.auth.user.id
                                    ? 'bg-indigo-50/60 border-l-[3px] border-indigo-500'
                                    : idx === 0 ? 'bg-amber-50/40' : 'hover:bg-gray-50/60'
                            ]"
                        >
                            <!-- Rang -->
                            <div class="w-8 flex-shrink-0 text-center">
                                <span v-if="idx === 0" class="text-xl leading-none">🥇</span>
                                <span v-else-if="idx === 1" class="text-xl leading-none">🥈</span>
                                <span v-else class="text-sm font-semibold text-gray-400">{{ idx + 1 }}</span>
                            </div>

                            <!-- Avatar -->
                            <div class="w-9 h-9 rounded-full flex-shrink-0 shadow-sm overflow-hidden bg-gray-100 flex items-center justify-center">
                                <img
                                    v-if="member.avatar_url"
                                    :src="member.avatar_url"
                                    :alt="member.name"
                                    class="w-full h-full object-cover"
                                />
                                <div
                                    v-else
                                    :class="[
                                        'w-full h-full bg-gradient-to-br flex items-center justify-center font-bold text-sm',
                                        idx === 0 ? 'from-amber-400 to-yellow-300 text-amber-900' :
                                        idx === 1 ? 'from-slate-300 to-slate-200 text-slate-700' :
                                        'from-gray-300 to-gray-200 text-gray-700'
                                    ]"
                                >
                                    {{ member.name?.charAt(0)?.toUpperCase() }}
                                </div>
                            </div>

                            <!-- Nom -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-1.5">
                                    <span class="font-semibold text-sm text-gray-900 truncate">{{ member.name }}</span>
                                    <span
                                        v-if="member.id === $page.props.auth.user.id"
                                        class="text-[10px] bg-indigo-100 text-indigo-600 px-1.5 py-0.5 rounded-full font-semibold flex-shrink-0"
                                    >
                                        Toi
                                    </span>
                                </div>
                            </div>

                            <!-- Score -->
                            <div class="flex-shrink-0 text-right">
                                <span
                                    :class="[
                                        'text-lg font-extrabold tabular-nums',
                                        idx === 0 ? 'text-amber-500' :
                                        idx === 1 ? 'text-slate-500' :
                                        member.id === $page.props.auth.user.id ? 'text-indigo-600' : 'text-gray-800'
                                    ]"
                                >
                                    {{ member.pivot?.total_points ?? 0 }}
                                </span>
                                <span class="text-[10px] text-gray-400 ml-0.5">pts</span>
                            </div>
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

                            </div>

                            <!-- Strip Pronos Spéciaux (visible pour tous les matchs de poule) -->
                            <div
                                v-if="showSpecialPronoStrip(match)"
                                :class="[
                                    'px-3 py-2 border-t',
                                    canSpecialProno(match) ? 'bg-emerald-50 border-emerald-100' : 'bg-gray-50 border-gray-100'
                                ]"
                                @click.stop
                            >
                                <!-- Message informatif si pas encore éligible -->
                                <div v-if="!canSpecialProno(match)" class="flex items-center gap-1.5">
                                    <span class="text-[10px] font-medium text-gray-400">
                                        {{ match.status === 'completed' ? '⚡ Bonus de poule (match terminé)' : match.tournament?.predictions_open ? '⚡ Pronos spéciaux disponibles après fermeture des pronostics' : '⚡ Pronos spéciaux disponibles avant le début du match' }}
                                    </span>
                                    <!-- Afficher les bonus actifs même hors fenêtre d'action -->
                                    <span v-if="localDoubleActive[match.id]" class="text-[10px] font-bold text-amber-600 bg-amber-50 px-1.5 py-0.5 rounded">⚡x2 actif</span>
                                    <template v-for="opponent in currentOpponents" :key="opponent.id">
                                        <span v-if="localBlocks[match.tournament_id]?.[opponent.id]?.target_match_id === match.id" class="text-[10px] text-red-500 bg-red-50 px-1.5 py-0.5 rounded">🛡 {{ opponent.name }}</span>
                                        <span v-if="localSwaps[match.tournament_id]?.[opponent.id]?.initiator_match_id === match.id" class="text-[10px] text-purple-600 bg-purple-50 px-1.5 py-0.5 rounded">↔ {{ opponent.name }}</span>
                                    </template>
                                </div>

                                <!-- Boutons d'action quand éligible -->
                                <div v-else class="flex items-center gap-2 flex-wrap">
                                    <!-- x2 Doubler -->
                                    <button
                                        v-if="userPredictions[match.id]"
                                        @click.stop="toggleDouble(match)"
                                        :disabled="doubleLoading[match.id] || (!localDoubleActive[match.id] && (localDoubleStats[match.tournament_id]?.remaining ?? 0) <= 0)"
                                        :class="[
                                            'flex items-center gap-1 px-2 py-0.5 rounded text-xs font-semibold transition',
                                            localDoubleActive[match.id]
                                                ? 'bg-emerald-500 text-white'
                                                : (localDoubleStats[match.tournament_id]?.remaining ?? 0) > 0
                                                    ? 'bg-white text-emerald-700 border border-emerald-300'
                                                    : 'bg-gray-100 text-gray-400 cursor-not-allowed'
                                        ]"
                                    >
                                        ⚡x2
                                        <span class="text-[10px] opacity-60">
                                            {{ localDoubleActive[match.id] ? '✓' : `(${localDoubleStats[match.tournament_id]?.used ?? 0}/3)` }}
                                        </span>
                                    </button>

                                    <span v-if="currentOpponents.length" class="text-gray-300 text-xs select-none">|</span>

                                    <!-- Par adversaire : 🛡 et ↔ inline -->
                                    <div
                                        v-for="opponent in currentOpponents"
                                        :key="opponent.id"
                                        class="flex items-center gap-1 bg-white rounded-lg px-2 py-0.5 border border-gray-200"
                                    >
                                        <span class="text-xs font-medium text-gray-600 max-w-[56px] truncate">{{ opponent.name }}</span>
                                        <button
                                            @click.stop="localBlocks[match.tournament_id]?.[opponent.id]?.target_match_id === match.id ? removeBlock(localBlocks[match.tournament_id][opponent.id].id, match.tournament_id) : placeBlock(match, opponent.id)"
                                            :disabled="isBlockLoading(match.id, opponent.id) || localSwaps[match.tournament_id]?.[opponent.id]?.initiator_match_id === match.id || (isBlockTaken(match, opponent.id) && localBlocks[match.tournament_id]?.[opponent.id]?.target_match_id !== match.id)"
                                            :class="[
                                                'text-xs px-1 py-0.5 rounded transition',
                                                localSwaps[match.tournament_id]?.[opponent.id]?.initiator_match_id === match.id
                                                    ? 'text-gray-300 cursor-not-allowed'
                                                    : isBlockTaken(match, opponent.id) && localBlocks[match.tournament_id]?.[opponent.id]?.target_match_id !== match.id
                                                        ? 'text-gray-300 cursor-not-allowed'
                                                        : localBlocks[match.tournament_id]?.[opponent.id]?.target_match_id === match.id
                                                            ? 'bg-red-500 text-white'
                                                            : localBlocks[match.tournament_id]?.[opponent.id]
                                                                ? 'bg-orange-100 text-orange-700'
                                                                : 'text-gray-400 hover:text-red-500'
                                            ]"
                                            :title="localSwaps[match.tournament_id]?.[opponent.id]?.initiator_match_id === match.id ? 'Échange actif — annulez d\'abord' : isBlockTaken(match, opponent.id) && localBlocks[match.tournament_id]?.[opponent.id]?.target_match_id !== match.id ? 'Déjà bloqué par un autre joueur' : 'Bloquer le prono'"
                                        >🛡</button>
                                        <button
                                            @click.stop="localSwaps[match.tournament_id]?.[opponent.id]?.initiator_match_id === match.id ? removeSwap(localSwaps[match.tournament_id][opponent.id].id, match.tournament_id) : showSwapConfirm(match, opponent.id)"
                                            :disabled="swapLoading[`${match.id}-${opponent.id}`] || localBlocks[match.tournament_id]?.[opponent.id]?.target_match_id === match.id || (isSwapTaken(match, opponent.id) && localSwaps[match.tournament_id]?.[opponent.id]?.initiator_match_id !== match.id)"
                                            :class="[
                                                'text-xs px-1 py-0.5 rounded transition',
                                                localBlocks[match.tournament_id]?.[opponent.id]?.target_match_id === match.id
                                                    ? 'text-gray-300 cursor-not-allowed'
                                                    : isSwapTaken(match, opponent.id) && localSwaps[match.tournament_id]?.[opponent.id]?.initiator_match_id !== match.id
                                                        ? 'text-gray-300 cursor-not-allowed'
                                                        : localSwaps[match.tournament_id]?.[opponent.id]?.initiator_match_id === match.id
                                                            ? 'bg-purple-500 text-white'
                                                            : localSwaps[match.tournament_id]?.[opponent.id]
                                                                ? 'bg-purple-100 text-purple-700'
                                                                : 'text-gray-400 hover:text-purple-500'
                                            ]"
                                            :title="localBlocks[match.tournament_id]?.[opponent.id]?.target_match_id === match.id ? 'Bloc actif — annulez d\'abord' : isSwapTaken(match, opponent.id) && localSwaps[match.tournament_id]?.[opponent.id]?.initiator_match_id !== match.id ? 'Déjà en échange avec un autre joueur' : 'Échanger les pronos'"
                                        >↔</button>
                                    </div>
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
                                                getSwapForMember(match, member.id)
                                                    ? 'bg-purple-50 border-purple-300'
                                                    : getBlockForMember(match, member.id)
                                                        ? 'bg-red-50 border-red-200'
                                                        : member.id === $page.props.auth.user.id
                                                            ? 'bg-indigo-50 border-indigo-200'
                                                            : getMemberPrediction(match, member.id) && match.status === 'completed'
                                                                ? resultTypeBg(getMemberPrediction(match, member.id).result_type)
                                                                : 'bg-white border-gray-200'
                                            ]"
                                        >
                                            <div class="flex flex-col gap-0.5">
                                                <!-- Avatar + Nom + score sur la même ligne -->
                                                <div class="flex items-center justify-between gap-1">
                                                    <div class="flex items-center gap-1.5 min-w-0">
                                                        <div class="w-5 h-5 rounded-full overflow-hidden flex-shrink-0 shadow-inner">
                                                            <img v-if="member.avatar_url" :src="member.avatar_url" class="w-full h-full object-cover" />
                                                            <div v-else class="w-full h-full bg-gray-200 flex items-center justify-center text-gray-500 font-bold text-[8px]">
                                                                {{ member.name?.charAt(0)?.toUpperCase() }}
                                                            </div>
                                                        </div>
                                                        <span class="text-[10px] font-medium text-gray-700 truncate">{{ member.name }}</span>
                                                    </div>
                                                    <div class="flex items-center gap-1 flex-shrink-0">
                                                        <span v-if="getMemberPrediction(match, member.id)?.is_doubled" class="text-[9px] font-bold text-amber-600">⚡x2</span>
                                                        <span
                                                            v-if="getEffectivePrediction(match, member.id)"
                                                            :class="[
                                                                'text-xs font-bold px-1.5 py-0.5 rounded',
                                                                getSwapForMember(match, member.id)
                                                                    ? 'bg-purple-200 text-purple-800'
                                                                    : match.status === 'completed'
                                                                        ? resultTypeColor(getMemberPrediction(match, member.id)?.result_type)
                                                                        : 'bg-gray-200 text-gray-600'
                                                            ]"
                                                        >
                                                            {{ getEffectivePrediction(match, member.id).home_score }}-{{ getEffectivePrediction(match, member.id).away_score }}
                                                        </span>
                                                        <span v-else class="text-xs text-gray-400">-</span>
                                                    </div>
                                                </div>
                                                <!-- Badges bloc/échange visibles par tous -->
                                                <div class="flex items-center gap-1 flex-wrap">
                                                    <span
                                                        v-if="getBlockForMember(match, member.id)"
                                                        class="text-[9px] font-semibold text-red-600 bg-red-100 px-1 rounded"
                                                    >🛡 {{ getMemberName(getBlockForMember(match, member.id).blocker_user_id) }}</span>
                                                    <span
                                                        v-if="getSwapForMember(match, member.id)"
                                                        class="text-[9px] font-semibold text-purple-700 bg-purple-100 px-1 rounded"
                                                    >↔ {{ getMemberName(getSwapForMember(match, member.id).initiator_user_id === member.id ? getSwapForMember(match, member.id).target_user_id : getSwapForMember(match, member.id).initiator_user_id) }}</span>
                                                </div>
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
                        :href="route('tournaments.allBonusPredictions', currentTournament.id)"
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

        <!-- Bouton discret "Voir le récap" -->
        <div v-if="recapData?.hasData" class="max-w-2xl mx-auto px-4 pb-2">
            <button
                @click="openRecap"
                class="w-full flex items-center justify-center gap-2 py-2 text-xs font-medium text-indigo-500 bg-indigo-50 hover:bg-indigo-100 rounded-xl border border-indigo-100 transition"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                Voir le récap d'hier
            </button>
        </div>

        <!-- Bouton récap dans le header (desktop) -->
        <Teleport v-if="recapData?.hasData" to="#header-recap-slot">
            <button
                @click="openRecap"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium text-indigo-600 hover:bg-indigo-50 transition"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                Récap
            </button>
        </Teleport>

        <!-- Bouton récap dans le header (mobile) -->
        <Teleport v-if="recapData?.hasData" to="#mobile-header-recap-slot">
            <button
                @click="openRecap"
                class="w-8 h-8 flex items-center justify-center rounded-full text-indigo-600 hover:bg-indigo-50 transition"
                title="Récap du jour"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
            </button>
        </Teleport>

        <!-- Popup récap quotidien - phase cinématique logo -->
        <div
            v-if="showRecap && recapPhase === 'logo'"
            class="fixed inset-0 z-50 flex items-center justify-center overflow-hidden bg-black/80 backdrop-blur-sm"
        >
            <div v-if="logoAnimStep === 'explode'" class="absolute inset-0 bg-white animate-logo-flash z-20 pointer-events-none"></div>
            <div v-if="logoAnimStep === 'explode'" class="absolute inset-0 flex items-center justify-center z-10 pointer-events-none">
                <div class="absolute rounded-full border-4 border-white/50 animate-logo-ring" style="width:55vw;height:55vw;"></div>
                <div class="absolute rounded-full border-2 border-indigo-300/40 animate-logo-ring" style="width:85vw;height:85vw;animation-delay:0.07s;"></div>
            </div>
            <!-- Textes qui tombent -->
            <div class="absolute inset-0 flex items-center justify-center pointer-events-none overflow-hidden z-10">
                <span
                    v-for="t in fallingTexts"
                    :key="t.id"
                    class="absolute font-black whitespace-nowrap select-none animate-text-fall"
                    :style="`--tx:${t.tx}; --peak:${t.peak}; --rot:${t.rot}; animation-delay:${t.delay}; animation-duration:${t.dur}; font-size:${t.size}; font-family:${t.font}; color:${t.color};`"
                >Prono2000</span>
            </div>

            <img
                src="/images/logo.png"
                alt="Prono2000"
                :class="['object-contain relative z-20 w-[78vw] h-[78vw] max-w-none', logoAnimClass]"
                style="filter: drop-shadow(0 0 60px rgba(165,180,252,0.7))"
            />
            <!-- "Prono 2000" qui grossit après le spin -->
            <div
                v-if="logoAnimStep === 'text' || logoAnimStep === 'explode'"
                class="absolute inset-0 flex flex-col items-center justify-end pb-20 pointer-events-none z-30"
            >
                <span
                    class="text-white font-black tracking-widest animate-text-grow"
                    style="font-size: clamp(2.5rem, 11vw, 5rem); font-family: Impact, sans-serif; text-shadow: 0 0 40px rgba(165,180,252,0.9), 0 4px 20px rgba(0,0,0,0.8);"
                >PRONO 2000</span>
            </div>

            <p v-if="logoAnimStep === 'zoom' || logoAnimStep === 'spin'" class="absolute bottom-16 text-indigo-300/70 text-xs font-semibold tracking-widest uppercase animate-pulse">Récap du jour</p>
        </div>

        <!-- Popup récap quotidien - phase données -->
        <div
            v-if="showRecap && recapPhase === 'data'"
            class="fixed inset-0 bg-black/60 flex items-start justify-center z-50 p-4 pt-4 overflow-y-auto"
            @click.self="closeRecap"
        >
            <div class="relative w-full max-w-sm animate-popup-enter">

                    <!-- Logo flottant au-dessus de la carte -->
                    <div class="flex justify-center mb-[-1px]">
                        <img src="/images/logo.png" alt="Prono2000" class="w-36 h-36 object-contain drop-shadow-2xl" />
                    </div>

                    <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">

                    <!-- Header -->
                    <div class="bg-indigo-600 px-5 py-3 flex items-center justify-between">
                        <div>
                            <p class="text-indigo-200 text-xs font-medium uppercase tracking-wide">Récap</p>
                            <h2 class="text-white font-bold text-base capitalize">{{ formatFootballDay(recapData?.footballDay) }}</h2>
                            <p class="text-indigo-300 text-xs mt-0.5">{{ recapData?.tournamentName }}</p>
                        </div>
                        <button @click="closeRecap" class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center hover:bg-white/30 transition">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="p-4 space-y-4 max-h-[55vh] overflow-y-auto">

                        <!-- Mes points + classement -->
                        <div class="bg-indigo-50 rounded-xl p-3 flex items-center justify-between gap-3">
                            <div class="text-center flex-1">
                                <div class="text-2xl font-extrabold text-indigo-600">
                                    +{{ recapData?.myPointsEarned ?? 0 }}
                                </div>
                                <div class="text-xs text-indigo-400 font-medium">points hier</div>
                            </div>
                            <div class="w-px h-10 bg-indigo-200"></div>
                            <div class="text-center flex-1">
                                <div class="flex items-center justify-center gap-1">
                                    <span class="text-xl font-extrabold text-gray-800">
                                        {{ positionLabel(recapData?.myCurrentPosition) }}
                                    </span>
                                    <!-- Indicateur de progression -->
                                    <template v-if="positionDiff !== null">
                                        <span v-if="positionDiff > 0" class="text-xs font-bold text-emerald-600 bg-emerald-100 px-1.5 py-0.5 rounded-full">
                                            +{{ positionDiff }}
                                        </span>
                                        <span v-else-if="positionDiff < 0" class="text-xs font-bold text-red-500 bg-red-100 px-1.5 py-0.5 rounded-full">
                                            {{ positionDiff }}
                                        </span>
                                        <span v-else class="text-xs text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded-full">
                                            =
                                        </span>
                                    </template>
                                </div>
                                <div class="text-xs text-gray-400 font-medium">classement</div>
                                <div v-if="recapData?.myPreviousPosition" class="text-[10px] text-gray-400 mt-0.5">
                                    (était {{ positionLabel(recapData.myPreviousPosition) }} hier)
                                </div>
                            </div>
                        </div>

                        <!-- 1er et dernier -->
                        <div v-if="recapData?.firstPlace || recapData?.lastPlace" class="grid grid-cols-2 gap-2">
                            <div v-if="recapData?.firstPlace" class="bg-amber-50 rounded-xl p-3 border border-amber-200 text-center">
                                <div class="text-lg mb-1">1er</div>
                                <div class="text-xs font-bold text-amber-800 truncate">{{ recapData.firstPlace.name }}</div>
                                <div class="text-sm font-extrabold text-amber-600 mt-1">{{ recapData.firstPlace.points }} pts</div>
                                <div class="text-[10px] text-amber-500 mt-0.5">
                                    depuis {{ recapData.firstPlace.streak }} jour{{ recapData.firstPlace.streak > 1 ? 's' : '' }}
                                </div>
                            </div>
                            <div v-if="recapData?.lastPlace" class="bg-gray-50 rounded-xl p-3 border border-gray-200 text-center">
                                <div class="text-lg mb-1">Dernier</div>
                                <div class="text-xs font-bold text-gray-700 truncate">{{ recapData.lastPlace.name }}</div>
                                <div class="text-sm font-extrabold text-gray-500 mt-1">{{ recapData.lastPlace.points }} pts</div>
                                <div class="text-[10px] text-gray-400 mt-0.5">
                                    depuis {{ recapData.lastPlace.streak }} jour{{ recapData.lastPlace.streak > 1 ? 's' : '' }}
                                </div>
                            </div>
                        </div>

                        <!-- Classement complet -->
                        <div v-if="recapData?.rankings?.length" class="space-y-1">
                            <div class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">Classement</div>
                            <div
                                v-for="player in recapData.rankings"
                                :key="player.name"
                                :class="['flex items-center gap-2 px-3 py-2 rounded-xl text-sm', player.isMe ? 'bg-indigo-50 border border-indigo-200' : 'bg-gray-50']"
                            >
                                <span class="w-5 text-center font-bold text-gray-500 shrink-0">{{ player.currentPosition }}</span>
                                <span class="flex-1 font-medium truncate" :class="player.isMe ? 'text-indigo-700 font-bold' : 'text-gray-800'">{{ player.name }}</span>
                                <span v-if="player.previousPosition === null || player.previousPosition === undefined" class="text-gray-400 text-xs shrink-0">—</span>
                                <span v-else-if="player.previousPosition > player.currentPosition" class="text-emerald-600 font-bold text-xs shrink-0">
                                    ▲ +{{ player.previousPosition - player.currentPosition }}
                                </span>
                                <span v-else-if="player.previousPosition < player.currentPosition" class="text-red-500 font-bold text-xs shrink-0">
                                    ▼ {{ player.previousPosition - player.currentPosition }}
                                </span>
                                <span v-else class="text-gray-400 font-bold text-xs shrink-0">=</span>
                                <span class="text-xs text-gray-500 font-semibold shrink-0">{{ player.totalPoints }} pts</span>
                            </div>
                        </div>

                        <!-- Matchs joués -->
                        <div v-if="recapData?.matchesPlayed?.length">
                            <div class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">
                                {{ recapData.matchesPlayed.length }} match{{ recapData.matchesPlayed.length > 1 ? 's' : '' }} joué{{ recapData.matchesPlayed.length > 1 ? 's' : '' }}
                            </div>
                            <div class="space-y-2">
                                <div
                                    v-for="match in recapData.matchesPlayed"
                                    :key="match.id"
                                    class="bg-gray-50 rounded-xl p-3 border border-gray-100"
                                >
                                    <!-- Score réel -->
                                    <div class="flex items-center justify-between gap-2 mb-2">
                                        <div class="flex items-center gap-1 flex-1 min-w-0">
                                            <TeamFlag :flag="match.homeFlag" size="sm" />
                                            <span class="text-xs font-semibold text-gray-700 truncate">{{ match.homeTeam }}</span>
                                        </div>
                                        <span class="text-sm font-bold bg-gray-900 text-white px-2 py-0.5 rounded-lg shrink-0">
                                            {{ match.homeScore }} - {{ match.awayScore }}
                                        </span>
                                        <div class="flex items-center gap-1 flex-1 min-w-0 justify-end">
                                            <span class="text-xs font-semibold text-gray-700 truncate text-right">{{ match.awayTeam }}</span>
                                            <TeamFlag :flag="match.awayFlag" size="sm" />
                                        </div>
                                    </div>

                                    <!-- Mon prono -->
                                    <div v-if="match.myPrediction" class="flex items-center justify-between">
                                        <span class="text-[10px] text-gray-400">Mon prono :</span>
                                        <div class="flex items-center gap-1.5">
                                            <span :class="[
                                                'text-xs font-bold px-2 py-0.5 rounded',
                                                match.myPrediction.result_type === 'exact' ? 'bg-emerald-500 text-white' :
                                                match.myPrediction.result_type === 'correct_winner' ? 'bg-amber-500 text-white' :
                                                'bg-red-500 text-white'
                                            ]">
                                                {{ match.myPrediction.home_score }}-{{ match.myPrediction.away_score }}
                                            </span>
                                            <span :class="[
                                                'text-xs font-bold',
                                                match.myPrediction.points_earned > 0 ? 'text-emerald-600' : 'text-gray-400'
                                            ]">
                                                {{ match.myPrediction.points_earned > 0 ? '+' + match.myPrediction.points_earned + ' pts' : '0 pt' }}
                                            </span>
                                        </div>
                                    </div>
                                    <div v-else class="text-[10px] text-gray-400 text-center">Pas de pronostic</div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Footer -->
                    <div class="px-4 pt-0 pb-3">
                        <button
                            @click="closeRecap"
                            class="w-full py-2.5 text-sm font-medium text-white bg-indigo-600 rounded-xl hover:bg-indigo-700 transition"
                        >
                            Fermer
                        </button>
                    </div>

                    <!-- Prono 2000 en bas -->
                    <div class="bg-gradient-to-r from-indigo-800 to-indigo-600 py-3 flex justify-center">
                        <span
                            class="text-white font-black tracking-widest"
                            style="font-size: clamp(1.8rem, 9vw, 2.8rem); font-family: Impact, sans-serif; letter-spacing: 0.12em; text-shadow: 0 2px 12px rgba(0,0,0,0.5);"
                        >PRONO 2000</span>
                    </div>
                    </div><!-- fin bg-white -->
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

<style>
body.logo-animating #mobile-header,
body.logo-animating #mobile-bottom-nav {
    display: none !important;
}
</style>
