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
    hasBooster: Boolean,
    boosterStats: Object,
});

// Booster state
const localHasBooster = ref(props.hasBooster);
const localBoosterStats = ref({ ...props.boosterStats });
const boosterLoading = ref(false);

const canToggleBooster = computed(() => {
    if (props.match.status !== 'scheduled') return false;
    if (props.match.scheduled_at && new Date(props.match.scheduled_at) <= new Date()) return false;
    if (localHasBooster.value) return true;
    return localBoosterStats.value && localBoosterStats.value.remaining > 0;
});

const toggleBooster = async () => {
    if (boosterLoading.value) return;

    boosterLoading.value = true;

    try {
        const response = await fetch(route('boosters.toggle', props.match.id), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            },
        });

        const data = await response.json();

        if (data.success) {
            localHasBooster.value = data.active;
            localBoosterStats.value.remaining = data.remaining;
        }
    } catch (error) {
        console.error('Erreur lors du toggle du booster:', error);
    } finally {
        boosterLoading.value = false;
    }
};

const form = useForm({
    home_score: props.prediction?.home_score ?? 0,
    away_score: props.prediction?.away_score ?? 0,
});

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
                        <div v-if="match.status === 'completed'" class="text-center mb-8">
                            <div class="text-sm text-gray-500 mb-2">Résultat final</div>
                            <div class="text-4xl font-bold">
                                {{ match.home_score }} - {{ match.away_score }}
                            </div>
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
                                    <li><strong>3 points</strong> pour le score exact</li>
                                    <li><strong>1 point</strong> pour le bon vainqueur (ou match nul)</li>
                                    <li><strong>0 point</strong> si incorrect</li>
                                </ul>
                            </div>

                            <!-- Booster x2 -->
                            <div class="bg-gradient-to-r from-purple-50 to-indigo-50 rounded-lg p-4 border border-purple-100">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-purple-500 to-indigo-500 flex items-center justify-center">
                                            <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-purple-800">Doubleur de points x2</p>
                                            <p class="text-xs text-purple-600">
                                                {{ localBoosterStats.remaining }} / {{ localBoosterStats.max }} restant(s)
                                            </p>
                                        </div>
                                    </div>
                                    <button
                                        v-if="canToggleBooster"
                                        @click="toggleBooster"
                                        :disabled="boosterLoading"
                                        :class="[
                                            'px-4 py-2 rounded-lg font-semibold text-sm transition-all',
                                            localHasBooster
                                                ? 'bg-gradient-to-r from-purple-500 to-indigo-500 text-white shadow-md hover:shadow-lg'
                                                : 'bg-white text-purple-600 border-2 border-purple-300 hover:border-purple-500',
                                            boosterLoading ? 'opacity-50 cursor-wait' : ''
                                        ]"
                                    >
                                        {{ localHasBooster ? 'Actif ✓' : 'Activer' }}
                                    </button>
                                    <span
                                        v-else-if="localHasBooster"
                                        class="px-4 py-2 rounded-lg font-semibold text-sm bg-gradient-to-r from-purple-500 to-indigo-500 text-white"
                                    >
                                        Actif ✓
                                    </span>
                                    <span
                                        v-else
                                        class="px-4 py-2 rounded-lg font-semibold text-sm bg-gray-200 text-gray-500"
                                    >
                                        Indisponible
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
                                    {{ prediction.result_type === 'exact' ? 'Score exact ! +3 pts' : '' }}
                                    {{ prediction.result_type === 'correct_winner' ? 'Bon vainqueur ! +1 pt' : '' }}
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
