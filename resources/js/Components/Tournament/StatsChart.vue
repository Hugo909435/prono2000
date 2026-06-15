<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import VueApexCharts from 'vue3-apexcharts';

const props = defineProps({
    // Vue par jour : { days: ['2026-05-26', ...], players: [{ id, name, points:[], positions:[] }] }
    statsData: {
        type: Object,
        default: () => ({ days: [], players: [] }),
    },
    // Vue par match : { labels: ['M1', ...], matches: [{ home, away, score }], players: [...] }
    matchData: {
        type: Object,
        default: () => ({ labels: [], matches: [], players: [] }),
    },
    // id du joueur connecté → sa courbe est mise en avant et affichée par défaut
    currentUserId: {
        type: [Number, String],
        default: null,
    },
});

// 'points' ou 'classement'
const mode = ref('points');

// 'match' (par défaut) ou 'jour' → granularité de l'axe horizontal
const hasMatchData = computed(
    () => (props.matchData?.labels?.length ?? 0) > 0 && (props.matchData?.players?.length ?? 0) > 0
);
const hasDayData = computed(
    () => (props.statsData?.days?.length ?? 0) > 0 && (props.statsData?.players?.length ?? 0) > 0
);
// Match par défaut ; bascule sur le jour si la vue match n'a pas de données
const granularity = ref('match');
onMounted(() => {
    if (!hasMatchData.value && hasDayData.value) granularity.value = 'jour';
});

// Jeu de données actif selon la granularité
const activeData = computed(() =>
    granularity.value === 'match' ? props.matchData : props.statsData
);

// Détection mobile réactive.
// IMPORTANT : on l'initialise dès le setup (SPA, `window` disponible) pour éviter
// un basculement false→true APRÈS le 1er rendu. Sinon le `:key` du graphique
// (qui dépend de isMobile) forçait un remontage d'ApexCharts juste après son
// initialisation → canvas vide sur mobile uniquement.
const mqMobile = typeof window !== 'undefined' ? window.matchMedia('(max-width: 640px)') : null;
const isMobile = ref(mqMobile ? mqMobile.matches : false);
const onMq = (e) => { isMobile.value = e.matches; };
onMounted(() => mqMobile?.addEventListener?.('change', onMq));
onUnmounted(() => mqMobile?.removeEventListener?.('change', onMq));

const hasData = computed(() => hasMatchData.value || hasDayData.value);

// Palette de couleurs distinctes et lisibles
const palette = [
    '#6366f1', '#ef4444', '#10b981', '#f59e0b', '#3b82f6', '#ec4899',
    '#8b5cf6', '#14b8a6', '#f97316', '#06b6d4', '#84cc16', '#e11d48',
    '#0ea5e9', '#a855f7', '#22c55e', '#eab308', '#f43f5e', '#2dd4bf',
];

const allPlayers = computed(() => activeData.value?.players ?? []);
const playerCount = computed(() => allPlayers.value.length);

// Couleur stable par joueur (basée sur son rang dans la liste)
const colorOf = (idx) => palette[idx % palette.length];
const isMe = (p) => props.currentUserId != null && String(p.id) === String(props.currentUserId);

// Dernière valeur connue de points (pour le tri / sélection par défaut)
const latestPoints = (p) => {
    const arr = p.points ?? [];
    for (let i = arr.length - 1; i >= 0; i--) {
        if (arr[i] != null) return arr[i];
    }
    return -Infinity;
};

// --- Sélection des joueurs affichés -------------------------------------
const selected = ref(new Set());

function defaultSelection() {
    const players = allPlayers.value;
    const ids = players.map((p) => p.id);
    // Peu de joueurs → on affiche tout le monde
    if (players.length <= 6) return new Set(ids);
    // Beaucoup de joueurs → on n'affiche que le top 5 + moi pour rester lisible
    const ranked = [...players].sort((a, b) => latestPoints(b) - latestPoints(a));
    const set = new Set(ranked.slice(0, 5).map((p) => p.id));
    const me = players.find((p) => isMe(p));
    if (me) set.add(me.id);
    return set;
}

watch(allPlayers, () => { selected.value = defaultSelection(); }, { immediate: true });

const toggle = (id) => {
    const next = new Set(selected.value);
    next.has(id) ? next.delete(id) : next.add(id);
    selected.value = next;
};
const selectAll = () => { selected.value = new Set(allPlayers.value.map((p) => p.id)); };
const selectNone = () => { selected.value = new Set(); };
const selectMe = () => {
    const me = allPlayers.value.find((p) => isMe(p));
    selected.value = new Set(me ? [me.id] : []);
};
const hasMe = computed(() => allPlayers.value.some((p) => isMe(p)));

// --- Données du graphique ----------------------------------------------
const categories = computed(() => {
    if (granularity.value === 'match') {
        return props.matchData?.labels ?? [];
    }
    return (props.statsData?.days ?? []).map((d) => {
        const dt = new Date(d + 'T12:00:00');
        return dt.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' });
    });
});

// Métadonnées des matchs (pour l'infobulle de la vue « par match »)
const matchMeta = computed(() => props.matchData?.matches ?? []);

// Joueurs visibles, dans l'ordre d'origine (couleurs stables)
const visiblePlayers = computed(() =>
    allPlayers.value
        .map((p, idx) => ({ ...p, color: colorOf(idx), me: isMe(p) }))
        .filter((p) => selected.value.has(p.id))
);

const series = computed(() =>
    visiblePlayers.value.map((p) => ({
        name: p.name,
        data: mode.value === 'points' ? p.points : p.positions,
    }))
);

const seriesColors = computed(() => visiblePlayers.value.map((p) => p.color));
const seriesWidths = computed(() => visiblePlayers.value.map((p) => (p.me ? 4 : 2.5)));

const chartHeight = computed(() => (isMobile.value ? 340 : 440));

const chartOptions = computed(() => ({
    chart: {
        type: 'line',
        height: chartHeight.value,
        fontFamily: 'inherit',
        toolbar: {
            show: !isMobile.value,
            tools: { download: true, selection: false, zoom: true, zoomin: true, zoomout: true, pan: false, reset: true },
        },
        zoom: { enabled: !isMobile.value },
        animations: { enabled: true, easing: 'easeinout', speed: 500 },
    },
    colors: seriesColors.value,
    stroke: { curve: 'smooth', width: seriesWidths.value },
    markers: { size: isMobile.value ? 0 : 4, hover: { size: 6 } },
    dataLabels: { enabled: false },
    legend: { show: false }, // remplacée par les puces personnalisées
    grid: {
        borderColor: '#e5e7eb',
        strokeDashArray: 4,
        padding: { left: 4, right: 8 },
    },
    xaxis: {
        categories: categories.value,
        tickAmount: isMobile.value ? 5 : undefined,
        // Toujours un objet : passer `undefined` fait planter ApexCharts
        // (lecture de title.text) → graphique vide sur mobile.
        title: {
            text: isMobile.value ? '' : (granularity.value === 'match' ? 'Match' : 'Journée'),
            style: { color: '#6b7280', fontWeight: 500 },
        },
        labels: {
            style: { colors: '#6b7280', fontSize: isMobile.value ? '10px' : '12px' },
            rotate: isMobile.value ? -45 : 0,
            rotateAlways: false,
            hideOverlappingLabels: true,
        },
        axisBorder: { color: '#e5e7eb' },
        axisTicks: { color: '#e5e7eb' },
        tooltip: { enabled: false },
    },
    yaxis: {
        // Classement : 1er en haut → axe inversé, pas de décimales
        reversed: mode.value === 'classement',
        forceNiceScale: true,
        min: mode.value === 'classement' ? 1 : undefined,
        decimalsInFloat: 0,
        title: {
            text: isMobile.value ? '' : (mode.value === 'points' ? 'Points' : 'Position'),
            style: { color: '#6b7280', fontWeight: 500 },
        },
        labels: {
            style: { colors: '#6b7280', fontSize: isMobile.value ? '10px' : '12px' },
            formatter: (val) => (val == null ? '' : Math.round(val)),
        },
    },
    tooltip: {
        shared: true,
        intersect: false,
        followCursor: true,
        x: {
            formatter: (val, opts) => {
                if (granularity.value !== 'match') return val ?? '';
                const m = matchMeta.value[opts?.dataPointIndex];
                return m ? `${m.home} – ${m.away} (${m.score})` : (val ?? '');
            },
        },
        y: {
            formatter: (val) => {
                if (val == null) return '—';
                return mode.value === 'points'
                    ? `${Math.round(val)} pts`
                    : `${Math.round(val)}${Math.round(val) === 1 ? 'er' : 'e'}`;
            },
        },
    },
}));
</script>

<template>
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-4 sm:p-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4 sm:mb-6">
                <div>
                    <h3 class="text-lg font-semibold">Statistiques</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        {{ granularity === 'match' ? 'Évolution des joueurs match par match' : 'Évolution des joueurs jour par jour' }}
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2 self-start">
                    <!-- Sélecteur Match / Jour -->
                    <div class="inline-flex rounded-xl bg-gray-100 p-1">
                        <button
                            @click="granularity = 'match'"
                            :disabled="!hasMatchData"
                            :class="[
                                'px-4 py-1.5 text-sm font-medium rounded-lg transition',
                                granularity === 'match' ? 'bg-white text-indigo-600 shadow-sm' : 'text-gray-500 hover:text-gray-700',
                                !hasMatchData ? 'opacity-40 cursor-not-allowed' : '',
                            ]"
                        >
                            Par match
                        </button>
                        <button
                            @click="granularity = 'jour'"
                            :disabled="!hasDayData"
                            :class="[
                                'px-4 py-1.5 text-sm font-medium rounded-lg transition',
                                granularity === 'jour' ? 'bg-white text-indigo-600 shadow-sm' : 'text-gray-500 hover:text-gray-700',
                                !hasDayData ? 'opacity-40 cursor-not-allowed' : '',
                            ]"
                        >
                            Par jour
                        </button>
                    </div>

                    <!-- Sélecteur Points / Classement -->
                    <div class="inline-flex rounded-xl bg-gray-100 p-1">
                        <button
                            @click="mode = 'points'"
                            :class="[
                                'px-4 py-1.5 text-sm font-medium rounded-lg transition',
                                mode === 'points' ? 'bg-white text-indigo-600 shadow-sm' : 'text-gray-500 hover:text-gray-700',
                            ]"
                        >
                            Points
                        </button>
                        <button
                            @click="mode = 'classement'"
                            :class="[
                                'px-4 py-1.5 text-sm font-medium rounded-lg transition',
                                mode === 'classement' ? 'bg-white text-indigo-600 shadow-sm' : 'text-gray-500 hover:text-gray-700',
                            ]"
                        >
                            Classement
                        </button>
                    </div>
                </div>
            </div>

            <!-- Pas de données -->
            <div v-if="!hasData" class="text-center py-12 text-gray-500">
                <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3v18h18M7 14l4-4 4 4 5-6" />
                </svg>
                <p class="font-medium">Pas encore de statistiques</p>
                <p class="text-sm mt-1">Les courbes apparaissent dès le premier match terminé (vue par match) ou au premier relevé quotidien à 12h (vue par jour).</p>
            </div>

            <!-- Graphique -->
            <div v-else>
                <!-- Filtre joueurs : actions rapides -->
                <div class="flex flex-wrap items-center gap-2 mb-3">
                    <button
                        @click="selectAll"
                        class="px-2.5 py-1 text-xs font-medium rounded-full border border-gray-200 text-gray-600 hover:bg-gray-50 transition"
                    >
                        Tous
                    </button>
                    <button
                        v-if="hasMe"
                        @click="selectMe"
                        class="px-2.5 py-1 text-xs font-medium rounded-full border border-indigo-200 text-indigo-600 hover:bg-indigo-50 transition"
                    >
                        Moi seul
                    </button>
                    <button
                        @click="selectNone"
                        class="px-2.5 py-1 text-xs font-medium rounded-full border border-gray-200 text-gray-600 hover:bg-gray-50 transition"
                    >
                        Aucun
                    </button>
                    <span class="text-xs text-gray-400 ml-auto">{{ selected.size }}/{{ playerCount }} affichés</span>
                </div>

                <!-- Filtre joueurs : puces -->
                <div class="flex flex-wrap gap-1.5 mb-4 max-h-28 overflow-y-auto">
                    <button
                        v-for="(p, idx) in allPlayers"
                        :key="p.id"
                        @click="toggle(p.id)"
                        :class="[
                            'inline-flex items-center gap-1.5 pl-1.5 pr-2.5 py-1 rounded-full border text-xs font-medium transition',
                            selected.has(p.id)
                                ? 'bg-white border-gray-300 text-gray-800 shadow-sm'
                                : 'bg-gray-50 border-gray-100 text-gray-400',
                            isMe(p) ? 'ring-1 ring-indigo-300' : '',
                        ]"
                    >
                        <span
                            class="w-2.5 h-2.5 rounded-full flex-shrink-0"
                            :style="{ backgroundColor: selected.has(p.id) ? colorOf(idx) : '#d1d5db' }"
                        ></span>
                        <span class="truncate max-w-[7rem]">{{ p.name }}</span>
                        <span v-if="isMe(p)" class="text-[9px] text-indigo-500 font-semibold">moi</span>
                    </button>
                </div>

                <!-- Aucun joueur sélectionné -->
                <div v-if="series.length === 0" class="text-center py-12 text-gray-400 text-sm">
                    Sélectionne au moins un joueur ci-dessus pour afficher les courbes.
                </div>

                <VueApexCharts
                    v-else
                    :key="granularity + '-' + mode + '-' + (isMobile ? 'm' : 'd')"
                    type="line"
                    :height="chartHeight"
                    :options="chartOptions"
                    :series="series"
                />
                <p class="text-xs text-gray-400 text-center mt-2">
                    Touche une courbe pour voir le détail · clique sur un nom ci-dessus pour l'afficher/le masquer
                </p>
            </div>
        </div>
    </div>
</template>
