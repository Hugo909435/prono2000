<script setup>
import { ref, computed } from 'vue';
import VueApexCharts from 'vue3-apexcharts';

const props = defineProps({
    // { days: ['2026-05-26', ...], players: [{ id, name, points:[], positions:[] }] }
    statsData: {
        type: Object,
        default: () => ({ days: [], players: [] }),
    },
});

// 'points' ou 'classement'
const mode = ref('points');

const hasData = computed(
    () => (props.statsData?.days?.length ?? 0) > 0 && (props.statsData?.players?.length ?? 0) > 0
);

// Palette de couleurs distinctes et lisibles
const palette = [
    '#6366f1', '#ef4444', '#10b981', '#f59e0b', '#3b82f6', '#ec4899',
    '#8b5cf6', '#14b8a6', '#f97316', '#06b6d4', '#84cc16', '#e11d48',
    '#0ea5e9', '#a855f7', '#22c55e', '#eab308', '#f43f5e', '#2dd4bf',
];

const categories = computed(() =>
    (props.statsData?.days ?? []).map((d) => {
        const dt = new Date(d + 'T12:00:00');
        return dt.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' });
    })
);

const series = computed(() =>
    (props.statsData?.players ?? []).map((p) => ({
        name: p.name,
        data: mode.value === 'points' ? p.points : p.positions,
    }))
);

const playerCount = computed(() => props.statsData?.players?.length ?? 0);

const chartOptions = computed(() => ({
    chart: {
        type: 'line',
        height: 420,
        fontFamily: 'inherit',
        toolbar: {
            show: true,
            tools: { download: true, selection: false, zoom: true, zoomin: true, zoomout: true, pan: false, reset: true },
        },
        zoom: { enabled: true },
        animations: { enabled: true, easing: 'easeinout', speed: 600 },
    },
    colors: palette,
    stroke: { curve: 'smooth', width: 3 },
    markers: { size: 4, hover: { sizeOffset: 2 } },
    dataLabels: { enabled: false },
    legend: {
        position: 'bottom',
        horizontalAlign: 'center',
        fontSize: '13px',
        markers: { radius: 12 },
        itemMargin: { horizontal: 8, vertical: 4 },
    },
    grid: { borderColor: '#e5e7eb', strokeDashArray: 4 },
    xaxis: {
        categories: categories.value,
        title: { text: 'Journée', style: { color: '#6b7280', fontWeight: 500 } },
        labels: { style: { colors: '#6b7280' } },
        axisBorder: { color: '#e5e7eb' },
        axisTicks: { color: '#e5e7eb' },
    },
    yaxis: {
        // Classement : 1er en haut → axe inversé, pas de décimales
        reversed: mode.value === 'classement',
        forceNiceScale: true,
        min: mode.value === 'classement' ? 1 : undefined,
        decimalsInFloat: 0,
        title: {
            text: mode.value === 'points' ? 'Points' : 'Position',
            style: { color: '#6b7280', fontWeight: 500 },
        },
        labels: {
            style: { colors: '#6b7280' },
            formatter: (val) => (val == null ? '' : Math.round(val)),
        },
    },
    tooltip: {
        shared: true,
        intersect: false,
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
        <div class="p-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                <div>
                    <h3 class="text-lg font-semibold">Statistiques</h3>
                    <p class="text-sm text-gray-500 mt-1">Évolution des joueurs jour par jour</p>
                </div>

                <!-- Sélecteur Points / Classement -->
                <div class="inline-flex rounded-xl bg-gray-100 p-1 self-start">
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

            <!-- Pas de données -->
            <div v-if="!hasData" class="text-center py-12 text-gray-500">
                <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3v18h18M7 14l4-4 4 4 5-6" />
                </svg>
                <p class="font-medium">Pas encore de statistiques</p>
                <p class="text-sm mt-1">Les courbes apparaissent après le premier relevé quotidien (chaque jour à 12h).</p>
            </div>

            <!-- Graphique -->
            <div v-else>
                <VueApexCharts
                    :key="mode"
                    type="line"
                    height="420"
                    :options="chartOptions"
                    :series="series"
                />
                <p class="text-xs text-gray-400 text-center mt-2">
                    {{ playerCount }} joueur(s) · clique sur un nom dans la légende pour l'afficher/le masquer
                </p>
            </div>
        </div>
    </div>
</template>
