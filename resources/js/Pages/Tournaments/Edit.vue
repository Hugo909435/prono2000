<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';

const props = defineProps({
    tournament: Object,
});

const form = useForm({
    name: props.tournament.name,
    description: props.tournament.description || '',
});

const submit = () => {
    form.patch(route('tournaments.update', props.tournament.id));
};

const deleteTournament = () => {
    if (confirm('Êtes-vous sûr de vouloir supprimer ce tournoi ? Cette action est irréversible.')) {
        router.delete(route('tournaments.destroy', props.tournament.id));
    }
};
</script>

<template>
    <Head title="Modifier le tournoi" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Modifier : {{ tournament.name }}
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <form @submit.prevent="submit" class="p-6 space-y-6">
                        <!-- Nom -->
                        <div>
                            <InputLabel for="name" value="Nom du tournoi" />
                            <TextInput
                                id="name"
                                type="text"
                                class="mt-1 block w-full"
                                v-model="form.name"
                                required
                            />
                            <InputError class="mt-2" :message="form.errors.name" />
                        </div>

                        <!-- Description -->
                        <div>
                            <InputLabel for="description" value="Description (optionnel)" />
                            <textarea
                                id="description"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                v-model="form.description"
                                rows="3"
                            />
                            <InputError class="mt-2" :message="form.errors.description" />
                        </div>

                        <!-- Infos non modifiables -->
                        <div class="p-4 bg-gray-50 rounded-lg">
                            <p class="text-sm text-gray-600">
                                <strong>Format :</strong>
                                {{ tournament.format === 'elimination' ? 'Éliminatoire direct' : 'Poules + Éliminatoires' }}
                            </p>
                            <p class="text-sm text-gray-600 mt-1">
                                <strong>Nombre d'équipes :</strong> {{ tournament.team_count }}
                            </p>
                            <p class="text-xs text-gray-400 mt-2">
                                Ces paramètres ne peuvent pas être modifiés après la création.
                            </p>
                        </div>

                        <div class="flex items-center justify-between">
                            <DangerButton type="button" @click="deleteTournament">
                                Supprimer le tournoi
                            </DangerButton>

                            <div class="flex items-center gap-4">
                                <Link
                                    :href="route('tournaments.show', tournament.id)"
                                    class="text-sm text-gray-600 hover:text-gray-900"
                                >
                                    Annuler
                                </Link>

                                <PrimaryButton :disabled="form.processing">
                                    Enregistrer
                                </PrimaryButton>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
