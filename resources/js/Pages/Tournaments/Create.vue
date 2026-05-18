<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    description: '',
    format: 'elimination',
    team_count: 16,
    group_count: null,
    teams_per_group: null,
    qualified_per_group: null,
});

const submit = () => {
    form.post(route('tournaments.store'));
};

const teamCountOptions = [8, 16, 32, 48];
</script>

<template>
    <Head title="Créer un tournoi" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Créer un tournoi
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
                                autofocus
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

                        <!-- Format -->
                        <div>
                            <InputLabel for="format" value="Format du tournoi" />
                            <select
                                id="format"
                                v-model="form.format"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="elimination">Éliminatoire direct</option>
                                <option value="groups_elimination">Phases de poules + Éliminatoires</option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.format" />
                        </div>

                        <!-- Nombre d'équipes -->
                        <div>
                            <InputLabel for="team_count" value="Nombre d'équipes" />
                            <select
                                id="team_count"
                                v-model="form.team_count"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option v-for="count in teamCountOptions" :key="count" :value="count">
                                    {{ count }} équipes
                                </option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.team_count" />
                        </div>

                        <!-- Options pour phases de poules -->
                        <template v-if="form.format === 'groups_elimination'">
                            <div class="grid grid-cols-3 gap-4">
                                <div>
                                    <InputLabel for="group_count" value="Nombre de poules" />
                                    <TextInput
                                        id="group_count"
                                        type="number"
                                        class="mt-1 block w-full"
                                        v-model="form.group_count"
                                        min="2"
                                        max="12"
                                    />
                                    <InputError class="mt-2" :message="form.errors.group_count" />
                                </div>

                                <div>
                                    <InputLabel for="teams_per_group" value="Équipes par poule" />
                                    <TextInput
                                        id="teams_per_group"
                                        type="number"
                                        class="mt-1 block w-full"
                                        v-model="form.teams_per_group"
                                        min="3"
                                        max="8"
                                    />
                                    <InputError class="mt-2" :message="form.errors.teams_per_group" />
                                </div>

                                <div>
                                    <InputLabel for="qualified_per_group" value="Qualifiés par poule" />
                                    <TextInput
                                        id="qualified_per_group"
                                        type="number"
                                        class="mt-1 block w-full"
                                        v-model="form.qualified_per_group"
                                        min="1"
                                        max="4"
                                    />
                                    <InputError class="mt-2" :message="form.errors.qualified_per_group" />
                                </div>
                            </div>
                        </template>

                        <div class="flex items-center justify-end gap-4">
                            <Link
                                :href="route('tournaments.index')"
                                class="text-sm text-gray-600 hover:text-gray-900"
                            >
                                Annuler
                            </Link>

                            <PrimaryButton :disabled="form.processing">
                                Créer le tournoi
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
