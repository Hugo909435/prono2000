<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TeamFlag from '@/Components/TeamFlag.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

const props = defineProps({
    tournament: Object,
    members: Array,
    winnerPredictions: Object,
    predictionsLocked: Boolean,
});

const currentUserId = usePage().props.auth.user.id;

const getPrediction = (memberId) => props.winnerPredictions[memberId] || null;

const rankColors = [
    { bg: 'bg-amber-50 border-amber-200', badge: 'bg-amber-200 text-amber-700', pts: 'text-amber-600' },
    { bg: 'bg-slate-50 border-slate-200', badge: 'bg-slate-200 text-slate-600', pts: 'text-slate-500' },
    { bg: 'bg-orange-50 border-orange-200', badge: 'bg-orange-200 text-orange-700', pts: 'text-orange-500' },
];
const points = [30, 20, 10];
const choiceKeys = ['first_choice_team', 'second_choice_team', 'third_choice_team'];
</script>

<template>
    <Head :title="`Pronostics vainqueur - ${tournament.name}`" />

    <AuthenticatedLayout>
        <div class="py-4 sm:py-8">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 space-y-6">

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
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                                <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                                </svg>
                                Pronostics vainqueur
                            </h1>
                            <p class="text-sm text-gray-500 mt-1">{{ tournament.name }}</p>
                        </div>
                        <div class="text-right">
                            <div class="text-2xl font-bold text-indigo-600">{{ members.length }}</div>
                            <div class="text-xs text-gray-500">joueurs</div>
                        </div>
                    </div>

                    <!-- Vainqueur désigné -->
                    <div v-if="tournament.winner_team" class="mt-4 flex items-center gap-3 p-3 bg-gradient-to-r from-yellow-50 to-amber-50 rounded-xl border border-yellow-200">
                        <svg class="w-5 h-5 text-yellow-500 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                        </svg>
                        <span class="text-sm font-medium text-yellow-800">Vainqueur du tournoi :</span>
                        <TeamFlag :flag="tournament.winner_team.flag" size="sm" />
                        <span class="font-bold text-gray-900">{{ tournament.winner_team.name }}</span>
                    </div>

                    <!-- Avertissement pronostics non verrouillés -->
                    <div v-if="!predictionsLocked" class="mt-4 p-3 bg-amber-50 border border-amber-200 rounded-xl text-sm text-amber-700 flex items-center gap-2">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        Pronostics non verrouillés — seul le vôtre est visible pour l'instant.
                    </div>

                    <!-- Légende points -->
                    <div class="mt-4 pt-4 border-t flex flex-wrap gap-4 text-xs text-gray-600">
                        <div v-for="(c, i) in rankColors" :key="i" class="flex items-center gap-1">
                            <span :class="['w-5 h-5 rounded-full flex items-center justify-center text-xs font-bold', c.badge]">{{ i + 1 }}</span>
                            <span>{{ points[i] }} pts</span>
                        </div>
                    </div>
                </div>

                <!-- Liste des joueurs -->
                <div class="space-y-4">
                    <div
                        v-for="member in members"
                        :key="member.id"
                        :class="[
                            'bg-white rounded-2xl shadow-sm overflow-hidden',
                            member.id === currentUserId ? 'ring-2 ring-indigo-400' : ''
                        ]"
                    >
                        <!-- Nom du joueur -->
                        <div class="px-5 py-3 bg-gray-50 border-b flex items-center gap-2">
                            <span class="font-semibold text-gray-800">{{ member.name }}</span>
                            <span v-if="member.id === currentUserId" class="text-xs text-indigo-600 font-medium">(vous)</span>
                        </div>

                        <!-- Ses choix -->
                        <div class="p-4">
                            <!-- Pronostics non verrouillés et pas l'utilisateur courant -->
                            <div v-if="!predictionsLocked && member.id !== currentUserId" class="flex items-center gap-2 text-gray-400 py-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                                <span class="text-sm">Caché jusqu'au verrouillage</span>
                            </div>

                            <!-- Aucun pronostic posé -->
                            <div v-else-if="!getPrediction(member.id)" class="text-sm text-gray-400 py-2 text-center">
                                Aucun pronostic vainqueur
                            </div>

                            <!-- Ses 3 choix -->
                            <div v-else class="space-y-2">
                                <div
                                    v-for="(c, i) in rankColors"
                                    :key="i"
                                    :class="['flex items-center justify-between p-3 rounded-xl border', c.bg]"
                                >
                                    <div class="flex items-center gap-3">
                                        <span :class="['w-7 h-7 rounded-full flex items-center justify-center font-bold text-sm', c.badge]">{{ i + 1 }}</span>
                                        <TeamFlag :flag="getPrediction(member.id)[choiceKeys[i]]?.flag" size="md" />
                                        <span class="font-medium text-gray-800">
                                            {{ getPrediction(member.id)[choiceKeys[i]]?.name ?? '—' }}
                                        </span>
                                    </div>
                                    <div class="text-right">
                                        <span
                                            v-if="tournament.winner_team_id && getPrediction(member.id)[choiceKeys[i] + '_id'] === tournament.winner_team_id"
                                            :class="['font-bold text-emerald-600']"
                                        >
                                            +{{ points[i] }} pts ✓
                                        </span>
                                        <span v-else :class="['text-sm font-medium', c.pts]">+{{ points[i] }} pts</span>
                                    </div>
                                </div>

                                <!-- Total si vainqueur désigné -->
                                <div v-if="tournament.winner_team_id" class="text-right text-sm font-bold pt-1">
                                    Total :
                                    <span :class="getPrediction(member.id).points_earned > 0 ? 'text-emerald-600' : 'text-gray-400'">
                                        +{{ getPrediction(member.id).points_earned ?? 0 }} pts
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
