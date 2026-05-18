<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TeamFlag from '@/Components/TeamFlag.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    tournament: Object,
    match: Object,
    teams: Array,
    groups: Array,
});

// Formater les dates pour datetime-local
const formatDateForInput = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toISOString().slice(0, 16);
};

const form = useForm({
    home_team_id: props.match.home_team_id || '',
    away_team_id: props.match.away_team_id || '',
    tournament_group_id: props.match.tournament_group_id || '',
    round: props.match.round || 'group',
    match_number: props.match.match_number || '',
    scheduled_at: formatDateForInput(props.match.scheduled_at),
    deadline_at: formatDateForInput(props.match.deadline_at),
    placeholder_home: props.match.placeholder_home || '',
    placeholder_away: props.match.placeholder_away || '',
});

const submit = () => {
    form.put(route('tournaments.matches.update', [props.tournament.id, props.match.id]));
};

const roundLabels = {
    group: 'Phase de poules',
    round_of_32: '32emes de finale',
    round_of_16: '8emes de finale',
    quarter: 'Quarts de finale',
    semi: 'Demi-finales',
    final: 'Finale',
};
</script>

<template>
    <Head title="Modifier le match" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('tournaments.show', tournament.id)" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </Link>
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        Modifier le match
                    </h2>
                    <p class="text-sm text-gray-500">{{ tournament.name }}</p>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <form @submit.prevent="submit" class="space-y-6">
                            <!-- Equipes -->
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <InputLabel for="home_team" value="Equipe domicile" />
                                    <select
                                        id="home_team"
                                        v-model="form.home_team_id"
                                        class="mt-1 block w-full rounded-md border-gray-300"
                                    >
                                        <option value="">A determiner</option>
                                        <option v-for="team in teams" :key="team.id" :value="team.id">
                                            {{ team.name }}
                                        </option>
                                    </select>
                                    <InputError class="mt-2" :message="form.errors.home_team_id" />
                                </div>

                                <div>
                                    <InputLabel for="away_team" value="Equipe exterieur" />
                                    <select
                                        id="away_team"
                                        v-model="form.away_team_id"
                                        class="mt-1 block w-full rounded-md border-gray-300"
                                    >
                                        <option value="">A determiner</option>
                                        <option v-for="team in teams" :key="team.id" :value="team.id">
                                            {{ team.name }}
                                        </option>
                                    </select>
                                    <InputError class="mt-2" :message="form.errors.away_team_id" />
                                </div>
                            </div>

                            <!-- Placeholders (si pas d'equipe) -->
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <InputLabel for="placeholder_home" value="Placeholder domicile (ex: 1er Groupe A)" />
                                    <TextInput
                                        id="placeholder_home"
                                        v-model="form.placeholder_home"
                                        type="text"
                                        class="mt-1 block w-full"
                                        placeholder="Ex: 1er Groupe A"
                                    />
                                </div>

                                <div>
                                    <InputLabel for="placeholder_away" value="Placeholder exterieur" />
                                    <TextInput
                                        id="placeholder_away"
                                        v-model="form.placeholder_away"
                                        type="text"
                                        class="mt-1 block w-full"
                                        placeholder="Ex: 2eme Groupe B"
                                    />
                                </div>
                            </div>

                            <!-- Phase et Groupe -->
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <InputLabel for="round" value="Phase" />
                                    <select
                                        id="round"
                                        v-model="form.round"
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

                                <div v-if="groups.length > 0">
                                    <InputLabel for="tournament_group_id" value="Groupe (poule)" />
                                    <select
                                        id="tournament_group_id"
                                        v-model="form.tournament_group_id"
                                        class="mt-1 block w-full rounded-md border-gray-300"
                                    >
                                        <option value="">Aucun</option>
                                        <option v-for="group in groups" :key="group.id" :value="group.id">
                                            {{ group.name }}
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <!-- Dates -->
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <InputLabel for="scheduled_at" value="Date et heure du match" />
                                    <TextInput
                                        id="scheduled_at"
                                        v-model="form.scheduled_at"
                                        type="datetime-local"
                                        class="mt-1 block w-full"
                                    />
                                    <InputError class="mt-2" :message="form.errors.scheduled_at" />
                                </div>

                                <div>
                                    <InputLabel for="deadline_at" value="Date limite pronostic" />
                                    <TextInput
                                        id="deadline_at"
                                        v-model="form.deadline_at"
                                        type="datetime-local"
                                        class="mt-1 block w-full"
                                    />
                                    <InputError class="mt-2" :message="form.errors.deadline_at" />
                                </div>
                            </div>

                            <!-- Boutons -->
                            <div class="flex justify-end gap-4 pt-4 border-t">
                                <Link :href="route('tournaments.show', tournament.id)">
                                    <SecondaryButton type="button">
                                        Annuler
                                    </SecondaryButton>
                                </Link>
                                <PrimaryButton :disabled="form.processing">
                                    Enregistrer
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
