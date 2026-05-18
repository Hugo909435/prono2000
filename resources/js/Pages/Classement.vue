<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    tournaments: Array,
});

const currentIndex = ref(0);

const currentTournament = computed(() =>
    props.tournaments.length ? props.tournaments[currentIndex.value] : null
);

const prev = () => { if (currentIndex.value > 0) currentIndex.value--; };
const next = () => { if (currentIndex.value < props.tournaments.length - 1) currentIndex.value++; };

const medal = (idx) => ['🥇', '🥈', '🥉'][idx] ?? null;

const avatarColor = (idx) => {
    const colors = [
        'from-amber-400 to-yellow-300 text-amber-900',
        'from-slate-300 to-slate-200 text-slate-700',
        'from-orange-400 to-amber-300 text-orange-900',
        'from-indigo-400 to-violet-400 text-white',
        'from-emerald-400 to-teal-400 text-white',
        'from-pink-400 to-rose-400 text-white',
    ];
    return colors[idx] ?? 'from-gray-300 to-gray-200 text-gray-700';
};
</script>

<template>
    <Head title="Classement" />

    <AuthenticatedLayout>
        <div class="px-4 py-4 max-w-2xl mx-auto space-y-4">

            <!-- Pas de tournoi -->
            <div v-if="!tournaments.length" class="bg-white rounded-2xl p-10 text-center shadow-sm">
                <div class="text-4xl mb-3">🏆</div>
                <p class="text-gray-500 text-sm">Rejoins un tournoi pour voir le classement.</p>
            </div>

            <template v-else>

                <!-- Sélecteur de tournoi -->
                <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
                    <div class="flex items-center justify-between px-3 py-3">
                        <button
                            @click="prev"
                            :disabled="currentIndex === 0"
                            :class="[
                                'w-10 h-10 rounded-xl flex items-center justify-center transition',
                                currentIndex === 0 ? 'text-gray-200' : 'text-gray-500 hover:bg-gray-100 active:bg-gray-200'
                            ]"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                        </button>

                        <div class="text-center">
                            <h2 class="text-base font-bold text-gray-900">{{ currentTournament.name }}</h2>
                            <p class="text-xs text-gray-400">{{ currentTournament.members?.length ?? 0 }} participant(s)</p>
                        </div>

                        <button
                            @click="next"
                            :disabled="currentIndex === tournaments.length - 1"
                            :class="[
                                'w-10 h-10 rounded-xl flex items-center justify-center transition',
                                currentIndex === tournaments.length - 1 ? 'text-gray-200' : 'text-gray-500 hover:bg-gray-100 active:bg-gray-200'
                            ]"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>
                    </div>

                    <!-- Dots -->
                    <div v-if="tournaments.length > 1" class="flex justify-center gap-1.5 pb-3">
                        <button
                            v-for="(t, i) in tournaments" :key="t.id"
                            @click="currentIndex = i"
                            :class="['w-2 h-2 rounded-full transition', i === currentIndex ? 'bg-indigo-600' : 'bg-gray-200']"
                        />
                    </div>
                </div>

                <!-- Classement -->
                <div class="bg-white rounded-2xl shadow-sm overflow-hidden">

                    <!-- Header -->
                    <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                        <h3 class="text-sm font-bold text-gray-800">Classement</h3>
                        <div class="flex items-center gap-3 text-[10px] text-gray-400 font-medium">
                            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-emerald-400 inline-block"></span>Exact</span>
                            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-amber-400 inline-block"></span>Bon</span>
                            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-red-400 inline-block"></span>Raté</span>
                        </div>
                    </div>

                    <!-- Aucun membre -->
                    <div v-if="!currentTournament.members?.length" class="py-12 text-center text-gray-400 text-sm">
                        Aucun participant pour l'instant.
                    </div>

                    <!-- Liste des joueurs -->
                    <div v-else class="divide-y divide-gray-50">
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
                            <!-- Rang / médaille -->
                            <div class="w-8 flex-shrink-0 text-center">
                                <span v-if="medal(idx)" class="text-xl leading-none">{{ medal(idx) }}</span>
                                <span v-else class="text-sm font-semibold text-gray-400">{{ idx + 1 }}</span>
                            </div>

                            <!-- Avatar -->
                            <div class="w-9 h-9 rounded-full flex-shrink-0 shadow-sm overflow-hidden">
                                <img
                                    v-if="member.avatar_url"
                                    :src="member.avatar_url"
                                    :alt="member.name"
                                    class="w-full h-full object-cover"
                                />
                                <div
                                    v-else
                                    :class="['w-full h-full bg-gradient-to-br flex items-center justify-center font-bold text-sm', avatarColor(idx)]"
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
                                <!-- Stats détaillées -->
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="flex items-center gap-1 text-[11px] font-medium text-emerald-600">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 inline-block"></span>
                                        {{ member.pivot?.exact_scores ?? 0 }} exact
                                    </span>
                                    <span class="flex items-center gap-1 text-[11px] font-medium text-amber-600">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-400 inline-block"></span>
                                        {{ member.pivot?.correct_results ?? 0 }} bon
                                    </span>
                                    <span class="flex items-center gap-1 text-[11px] font-medium text-red-400">
                                        <span class="w-1.5 h-1.5 rounded-full bg-red-400 inline-block"></span>
                                        {{ member.pivot?.wrong_predictions ?? 0 }} raté
                                    </span>
                                </div>
                            </div>

                            <!-- Score -->
                            <div class="flex-shrink-0 text-right">
                                <span
                                    :class="[
                                        'text-xl font-extrabold tabular-nums',
                                        idx === 0 ? 'text-amber-500' :
                                        idx === 1 ? 'text-slate-500' :
                                        idx === 2 ? 'text-orange-400' :
                                        member.id === $page.props.auth.user.id ? 'text-indigo-600' : 'text-gray-800'
                                    ]"
                                >
                                    {{ member.pivot?.total_points ?? 0 }}
                                </span>
                                <span class="text-[10px] text-gray-400 ml-0.5">pts</span>
                            </div>
                        </div>
                    </div>
                </div>

            </template>
        </div>
    </AuthenticatedLayout>
</template>
