<script setup>
import { ref } from 'vue';
import { useForm, usePage, router } from '@inertiajs/vue3';

const user = usePage().props.auth.user;

const form = useForm({ avatar: null });
const preview = ref(user.avatar_url ?? null);
const fileInput = ref(null);

const onFileChange = (e) => {
    const file = e.target.files[0];
    if (!file) return;
    form.avatar = file;
    preview.value = URL.createObjectURL(file);
};

const submit = () => {
    form.post(route('profile.avatar'), {
        forceFormData: true,
        onSuccess: () => { form.reset(); },
    });
};

const deleteAvatar = () => {
    router.delete(route('profile.avatar.delete'), {
        onSuccess: () => { preview.value = null; },
    });
};
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900">Photo de profil</h2>
            <p class="mt-1 text-sm text-gray-600">
                Visible dans le classement par tous les membres du tournoi.
            </p>
        </header>

        <div class="mt-6 flex items-center gap-6">

            <!-- Aperçu -->
            <div class="relative flex-shrink-0">
                <div class="w-20 h-20 rounded-full overflow-hidden bg-gradient-to-br from-indigo-400 to-violet-500 flex items-center justify-center shadow-md">
                    <img
                        v-if="preview"
                        :src="preview"
                        alt="Avatar"
                        class="w-full h-full object-cover"
                    />
                    <span v-else class="text-white font-bold text-2xl">
                        {{ user.name?.charAt(0)?.toUpperCase() }}
                    </span>
                </div>
                <!-- Bouton supprimer (si avatar existant) -->
                <button
                    v-if="user.avatar_url"
                    type="button"
                    @click="deleteAvatar"
                    class="absolute -top-1 -right-1 w-5 h-5 rounded-full bg-red-500 text-white flex items-center justify-center hover:bg-red-600 transition shadow"
                    title="Supprimer la photo"
                >
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Actions -->
            <div class="flex flex-col gap-3">
                <input
                    ref="fileInput"
                    type="file"
                    accept="image/jpeg,image/png,image/webp,image/gif"
                    class="hidden"
                    @change="onFileChange"
                />
                <button
                    type="button"
                    @click="fileInput.click()"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50 transition"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    Choisir une photo
                </button>

                <button
                    v-if="form.avatar"
                    type="button"
                    @click="submit"
                    :disabled="form.processing"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 transition disabled:opacity-50"
                >
                    <svg v-if="form.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                    </svg>
                    {{ form.processing ? 'Envoi...' : 'Enregistrer la photo' }}
                </button>

                <p v-if="form.errors.avatar" class="text-sm text-red-600">{{ form.errors.avatar }}</p>
                <p class="text-xs text-gray-400">JPG, PNG, WebP ou GIF · max 2 Mo</p>
            </div>
        </div>
    </section>
</template>
