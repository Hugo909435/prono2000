<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    tournaments: Object,
});

const formatLabel = (format) => {
    return format === 'elimination' ? 'Éliminatoire' : 'Poules + Éliminatoires';
};

const statusLabel = (status) => {
    const labels = {
        draft: 'Brouillon',
        active: 'En cours',
        completed: 'Terminé',
    };
    return labels[status] || status;
};

const statusColor = (status) => {
    const colors = {
        draft: 'bg-gray-100 text-gray-800',
        active: 'bg-green-100 text-green-800',
        completed: 'bg-blue-100 text-blue-800',
    };
    return colors[status] || 'bg-gray-100 text-gray-800';
};
</script>

<template>
    <Head title="Tournois" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Tournois
                </h2>
                <Link :href="route('tournaments.create')">
                    <PrimaryButton>Créer un tournoi</PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div v-if="tournaments.data.length === 0" class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-center text-gray-500">
                        <p>Aucun tournoi pour le moment.</p>
                        <Link :href="route('tournaments.create')" class="mt-4 inline-block text-indigo-600 hover:text-indigo-800">
                            Créer votre premier tournoi
                        </Link>
                    </div>
                </div>

                <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <Link
                        v-for="tournament in tournaments.data"
                        :key="tournament.id"
                        :href="route('tournaments.show', tournament.id)"
                        class="bg-white overflow-hidden shadow-sm sm:rounded-lg hover:shadow-md transition-shadow"
                    >
                        <div class="p-6">
                            <div class="flex items-start justify-between">
                                <h3 class="text-lg font-semibold text-gray-900">
                                    {{ tournament.name }}
                                </h3>
                                <span
                                    :class="statusColor(tournament.status)"
                                    class="px-2 py-1 text-xs font-medium rounded-full"
                                >
                                    {{ statusLabel(tournament.status) }}
                                </span>
                            </div>

                            <p class="mt-2 text-sm text-gray-600">
                                {{ formatLabel(tournament.format) }} - {{ tournament.team_count }} équipes
                            </p>

                            <div class="mt-4 flex items-center gap-4 text-sm text-gray-500">
                                <span>{{ tournament.teams_count }} équipes inscrites</span>
                                <span>{{ tournament.matches_count }} matchs</span>
                            </div>

                            <div class="mt-4 text-sm text-gray-500">
                                Créé par {{ tournament.creator.name }}
                            </div>
                        </div>
                    </Link>
                </div>

                <!-- Pagination -->
                <div v-if="tournaments.links && tournaments.links.length > 3" class="mt-6 flex justify-center">
                    <nav class="flex gap-2">
                        <Link
                            v-for="link in tournaments.links"
                            :key="link.label"
                            :href="link.url"
                            :class="[
                                'px-3 py-2 text-sm rounded-md',
                                link.active ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50',
                                !link.url ? 'opacity-50 cursor-not-allowed' : ''
                            ]"
                            v-html="link.label"
                        />
                    </nav>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
