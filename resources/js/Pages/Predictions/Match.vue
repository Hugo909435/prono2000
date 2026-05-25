<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    match: Object,
    prediction: Object,
    canPredict: Boolean,
    hasDouble: Boolean,
    doubleStats: Object,
    canManageScore: Boolean,
});

// Double state (group stage only)
const localHasDouble = ref(props.hasDouble);
const localDoubleStats = ref({ ...props.doubleStats });
const doubleLoading = ref(false);

const isGroupStage = computed(() => props.match.round === 'group');

const canToggleDouble = computed(() => {
    if (!isGroupStage.value) return false;
    if (props.match.status !== 'scheduled') return false;
    if (!props.prediction) return false;
    if (localHasDouble.value) return true;
    return localDoubleStats.value && localDoubleStats.value.remaining > 0;
});

const csrf = () => document.querySelector('meta[name="csrf-token"]').getAttribute('content');

const toggleDouble = async () => {
    if (doubleLoading.value) return;
    doubleLoading.value = true;
    try {
        const response = await fetch(route('doubles.toggle', props.match.id), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
        });
        const data = await response.json();
        if (data.success) {
            localHasDouble.value = data.active;
            localDoubleStats.value.remaining = data.remaining;
        } else {
            alert(data.message);
        }
    } catch (error) {
        console.error('Erreur toggle double:', error);
    } finally {
        doubleLoading.value = false;
    }
};

const form = useForm({
    home_score: props.prediction?.home_score ?? 0,
    away_score: props.prediction?.away_score ?? 0,
});

const scoreForm = useForm({
    home_score: props.match.home_score ?? 0,
    away_score: props.match.away_score ?? 0,
});

const submitScore = () => {
    scoreForm.post(route('tournaments.matches.result', [props.match.tournament.id, props.match.id]));
};

const incrementScore = (field) => {
    if (scoreForm[field] < 99) scoreForm[field]++;
};

const decrementScore = (field) => {
    if (scoreForm[field] > 0) scoreForm[field]--;
};

const submit = () => {
    form.post(route('predictions.store', props.match.id));
};

const increment = (field) => {
    if (form[field] < 99) form[field]++;
};

const decrement = (field) => {
    if (form[field] > 0) form[field]--;
};

// Countdown
const timeRemaining = ref('');
const updateCountdown = () => {
    if (!props.match.deadline_at) {
        timeRemaining.value = '';
        return;
    }

    const deadline = new Date(props.match.deadline_at);
    const now = new Date();
    const diff = deadline - now;

    if (diff <= 0) {
        timeRemaining.value = 'Pronostics clos';
        return;
    }

    const days = Math.floor(diff / (1000 * 60 * 60 * 24));
    const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
    const seconds = Math.floor((diff % (1000 * 60)) / 1000);

    if (days > 0) {
        timeRemaining.value = `${days}j ${hours}h ${minutes}m`;
    } else if (hours > 0) {
        timeRemaining.value = `${hours}h ${minutes}m ${seconds}s`;
    } else {
        timeRemaining.value = `${minutes}m ${seconds}s`;
    }
};

let intervalId;
onMounted(() => {
    updateCountdown();
    intervalId = setInterval(updateCountdown, 1000);
});

onUnmounted(() => {
    if (intervalId) clearInterval(intervalId);
});

const matchDate = computed(() => {
    if (!props.match.scheduled_at) return null;
    return new Date(props.match.scheduled_at).toLocaleDateString('fr-FR', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        hour: '2-digit',
        minute: '2-digit',
    });
});
</script>

<template>
    <Head title="Pronostic" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Faire un pronostic
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <!-- Tournament info -->
                        <div class="text-center mb-6">
                            <Link
                                :href="route('tournaments.show', match.tournament.id)"
                                class="text-sm text-indigo-600 hover:text-indigo-800"
                            >
                                {{ match.tournament.name }}
                            </Link>
                            <p v-if="matchDate" class="text-gray-500 mt-1">
                                {{ matchDate }}
                            </p>
                        </div>

                        <!-- Teams -->
                        <div class="flex items-center justify-center gap-8 mb-8">
                            <div class="text-center">
                                <div class="text-xl font-bold">
                                    {{ match.home_team?.name || 'À déterminer' }}
                                </div>
                                <div v-if="match.home_team?.short_name" class="text-gray-500">
                                    {{ match.home_team.short_name }}
                                </div>
                            </div>

                            <div class="text-3xl font-bold text-gray-300">VS</div>

                            <div class="text-center">
                                <div class="text-xl font-bold">
                                    {{ match.away_team?.name || 'À déterminer' }}
                                </div>
                                <div v-if="match.away_team?.short_name" class="text-gray-500">
                                    {{ match.away_team.short_name }}
                                </div>
                            </div>
                        </div>

                        <!-- Score if completed -->
                        <div v-if="match.status === 'completed' && !canManageScore" class="text-center mb-8">
                            <div class="text-sm text-gray-500 mb-2">Résultat final</div>
                            <div class="text-4xl font-bold">
                                {{ match.home_score }} - {{ match.away_score }}
                            </div>
                        </div>

                        <!-- Score Management (admin / creator only) -->
                        <div v-if="canManageScore" class="mb-8 bg-amber-50 border border-amber-200 rounded-lg p-5">
                            <div class="flex items-center gap-2 mb-4">
                                <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                                <h3 class="font-semibold text-amber-800">
                                    {{ match.status === 'completed' ? 'Modifier le score' : 'Entrer le score' }}
                                </h3>
                            </div>

                            <div v-if="match.status === 'completed'" class="text-center mb-4">
                                <div class="text-sm text-gray-500 mb-1">Score actuel</div>
                                <div class="text-3xl font-bold text-gray-800">
                                    {{ match.home_score }} - {{ match.away_score }}
                                </div>
                            </div>

                            <form @submit.prevent="submitScore" class="space-y-4">
                                <div class="flex items-center justify-center gap-8">
                                    <div class="text-center">
                                        <InputLabel :value="match.home_team?.name || 'Domicile'" class="mb-2" />
                                        <div class="flex items-center gap-2">
                                            <button
                                                type="button"
                                                @click="decrementScore('home_score')"
                                                class="w-9 h-9 rounded-full bg-amber-200 hover:bg-amber-300 flex items-center justify-center text-xl font-bold"
                                            >-</button>
                                            <input
                                                type="number"
                                                v-model.number="scoreForm.home_score"
                                                min="0"
                                                max="99"
                                                class="w-16 h-14 text-center text-2xl font-bold border-amber-300 rounded-lg"
                                            />
                                            <button
                                                type="button"
                                                @click="incrementScore('home_score')"
                                                class="w-9 h-9 rounded-full bg-amber-200 hover:bg-amber-300 flex items-center justify-center text-xl font-bold"
                                            >+</button>
                                        </div>
                                        <InputError class="mt-1" :message="scoreForm.errors.home_score" />
                                    </div>

                                    <div class="text-3xl font-bold text-gray-300">-</div>

                                    <div class="text-center">
                                        <InputLabel :value="match.away_team?.name || 'Extérieur'" class="mb-2" />
                                        <div class="flex items-center gap-2">
                                            <button
                                                type="button"
                                                @click="decrementScore('away_score')"
                                                class="w-9 h-9 rounded-full bg-amber-200 hover:bg-amber-300 flex items-center justify-center text-xl font-bold"
                                            >-</button>
                                            <input
                                                type="number"
                                                v-model.number="scoreForm.away_score"
                                                min="0"
                                                max="99"
                                                class="w-16 h-14 text-center text-2xl font-bold border-amber-300 rounded-lg"
                                            />
                                            <button
                                                type="button"
                                                @click="incrementScore('away_score')"
                                                class="w-9 h-9 rounded-full bg-amber-200 hover:bg-amber-300 flex items-center justify-center text-xl font-bold"
                                            >+</button>
                                        </div>
                                        <InputError class="mt-1" :message="scoreForm.errors.away_score" />
                                    </div>
                                </div>

                                <div class="bg-amber-100 rounded-lg p-3 text-sm text-amber-800">
                                    Valider le score calculera automatiquement les points de tous les pronostics et mettra à jour les classements.
                                </div>

                                <button
                                    type="submit"
                                    :disabled="scoreForm.processing"
                                    class="w-full py-2 px-4 bg-amber-600 hover:bg-amber-700 text-white font-semibold rounded-lg transition disabled:opacity-50"
                                >
                                    {{ match.status === 'completed' ? 'Modifier le score' : 'Valider le score' }}
                                </button>
                            </form>
                        </div>

                        <!-- Prediction Form -->
                        <form v-if="canPredict" @submit.prevent="submit" class="space-y-6">
                            <div class="flex items-center justify-center gap-8">
                                <!-- Home Score -->
                                <div class="text-center">
                                    <InputLabel value="Domicile" class="mb-2" />
                                    <div class="flex items-center gap-2">
                                        <button
                                            type="button"
                                            @click="decrement('home_score')"
                                            class="w-10 h-10 rounded-full bg-gray-200 hover:bg-gray-300 flex items-center justify-center text-xl font-bold"
                                        >
                                            -
                                        </button>
                                        <input
                                            type="number"
                                            v-model.number="form.home_score"
                                            min="0"
                                            max="99"
                                            class="w-20 h-16 text-center text-3xl font-bold border-gray-300 rounded-lg"
                                        />
                                        <button
                                            type="button"
                                            @click="increment('home_score')"
                                            class="w-10 h-10 rounded-full bg-gray-200 hover:bg-gray-300 flex items-center justify-center text-xl font-bold"
                                        >
                                            +
                                        </button>
                                    </div>
                                    <InputError class="mt-2" :message="form.errors.home_score" />
                                </div>

                                <div class="text-4xl font-bold text-gray-300">-</div>

                                <!-- Away Score -->
                                <div class="text-center">
                                    <InputLabel value="Extérieur" class="mb-2" />
                                    <div class="flex items-center gap-2">
                                        <button
                                            type="button"
                                            @click="decrement('away_score')"
                                            class="w-10 h-10 rounded-full bg-gray-200 hover:bg-gray-300 flex items-center justify-center text-xl font-bold"
                                        >
                                            -
                                        </button>
                                        <input
                                            type="number"
                                            v-model.number="form.away_score"
                                            min="0"
                                            max="99"
                                            class="w-20 h-16 text-center text-3xl font-bold border-gray-300 rounded-lg"
                                        />
                                        <button
                                            type="button"
                                            @click="increment('away_score')"
                                            class="w-10 h-10 rounded-full bg-gray-200 hover:bg-gray-300 flex items-center justify-center text-xl font-bold"
                                        >
                                            +
                                        </button>
                                    </div>
                                    <InputError class="mt-2" :message="form.errors.away_score" />
                                </div>
                            </div>

                            <!-- Countdown -->
                            <div v-if="timeRemaining" class="text-center">
                                <div class="text-sm text-gray-500">Temps restant</div>
                                <div class="text-lg font-semibold text-indigo-600">
                                    {{ timeRemaining }}
                                </div>
                            </div>

                            <!-- Points info -->
                            <div class="bg-gray-50 rounded-lg p-4 text-sm text-gray-600">
                                <p class="font-medium mb-2">Attribution des points :</p>
                                <ul class="list-disc list-inside space-y-1">
                                    <li><strong>6 points</strong> pour le score exact</li>
                                    <li><strong>2 points</strong> pour le bon vainqueur (ou match nul)</li>
                                    <li><strong>0 point</strong> si incorrect</li>
                                </ul>
                            </div>

                            <!-- Prono Doublé (phase de groupes uniquement) -->
                            <div v-if="isGroupStage" class="bg-gradient-to-r from-green-50 to-emerald-50 rounded-lg p-4 border border-green-100">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-green-500 to-emerald-500 flex items-center justify-center text-white font-bold text-sm">
                                            x2
                                        </div>
                                        <div>
                                            <p class="font-semibold text-green-800">Prono Doublé</p>
                                            <p class="text-xs text-green-600">
                                                {{ localDoubleStats?.remaining ?? 0 }} / {{ localDoubleStats?.max ?? 3 }} restant(s) · 12, 4 ou 0 pts
                                            </p>
                                        </div>
                                    </div>
                                    <button
                                        v-if="canToggleDouble"
                                        @click="toggleDouble"
                                        :disabled="doubleLoading"
                                        :class="[
                                            'px-4 py-2 rounded-lg font-semibold text-sm transition-all',
                                            localHasDouble
                                                ? 'bg-gradient-to-r from-green-500 to-emerald-500 text-white shadow-md hover:shadow-lg'
                                                : 'bg-white text-green-600 border-2 border-green-300 hover:border-green-500',
                                            doubleLoading ? 'opacity-50 cursor-wait' : ''
                                        ]"
                                    >
                                        {{ localHasDouble ? 'Actif ✓' : 'Doubler' }}
                                    </button>
                                    <span v-else-if="localHasDouble" class="px-4 py-2 rounded-lg font-semibold text-sm bg-gradient-to-r from-green-500 to-emerald-500 text-white">
                                        Actif ✓
                                    </span>
                                    <span v-else class="px-4 py-2 rounded-lg font-semibold text-sm bg-gray-200 text-gray-500">
                                        {{ !props.prediction ? 'Faites d\'abord votre prono' : 'Quota plein' }}
                                    </span>
                                </div>
                            </div>

                            <PrimaryButton
                                type="submit"
                                class="w-full justify-center"
                                :disabled="form.processing"
                            >
                                {{ prediction ? 'Modifier mon pronostic' : 'Valider mon pronostic' }}
                            </PrimaryButton>
                        </form>

                        <!-- Already predicted (deadline passed) -->
                        <div v-else-if="prediction" class="text-center">
                            <div class="text-sm text-gray-500 mb-2">Votre pronostic</div>
                            <div class="text-4xl font-bold mb-4">
                                {{ prediction.home_score }} - {{ prediction.away_score }}
                            </div>

                            <div v-if="prediction.result_type" class="mt-4">
                                <span
                                    :class="[
                                        'px-4 py-2 rounded-full font-medium',
                                        prediction.result_type === 'exact' ? 'bg-green-100 text-green-800' : '',
                                        prediction.result_type === 'correct_winner' ? 'bg-yellow-100 text-yellow-800' : '',
                                        prediction.result_type === 'wrong' ? 'bg-red-100 text-red-800' : ''
                                    ]"
                                >
                                    {{ prediction.result_type === 'exact' ? 'Score exact ! +6 pts' : '' }}
                                    {{ prediction.result_type === 'correct_winner' ? 'Bon vainqueur ! +2 pts' : '' }}
                                    {{ prediction.result_type === 'wrong' ? 'Incorrect' : '' }}
                                </span>
                            </div>

                            <p class="text-red-600 font-medium mt-4">
                                Les pronostics sont clos pour ce match.
                            </p>
                        </div>

                        <!-- No prediction, deadline passed -->
                        <div v-else class="text-center py-8">
                            <p class="text-red-600 font-medium">
                                Les pronostics sont clos pour ce match.
                            </p>
                            <p class="text-gray-500 mt-2">
                                Vous n'avez pas fait de pronostic pour ce match.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
