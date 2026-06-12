<script setup>
import { ref, computed } from 'vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import { Link, usePage } from '@inertiajs/vue3';

const showProfileMenu = ref(false);
const isAdmin = computed(() => usePage().props.auth.user?.is_admin);

// Navigation items — routeMatch supports an array of patterns
const navItems = [
    {
        name: 'Accueil',
        route: 'dashboard',
        routeMatch: ['dashboard'],
        icon: 'home',
    },
    {
        name: 'Tournois',
        route: 'tournaments.index',
        routeMatch: ['tournaments.*'],
        icon: 'trophy',
    },
    {
        name: 'Pronos',
        route: 'predictions.index',
        routeMatch: ['predictions.*', 'tournaments.allPredictions'],
        icon: 'chart',
    },
    {
        name: 'Classement',
        route: 'classement',
        routeMatch: ['classement'],
        icon: 'podium',
    },
];

// Routes explicitement assignées à un item prioritaire
const explicitRoutes = navItems
    .flatMap(i => i.routeMatch)
    .filter(p => !p.includes('*'));

const isNavActive = (item) => {
    // Si la route courante est explicitement listée dans un item, seul cet item doit être actif
    for (const explicit of explicitRoutes) {
        if (route().current(explicit)) {
            return item.routeMatch.includes(explicit);
        }
    }
    return item.routeMatch.some(p => route().current(p));
};
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <!-- Header Desktop -->
        <header class="hidden sm:block bg-white border-b border-gray-200 sticky top-0 z-40">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-14">
                    <!-- Logo & Nav -->
                    <div class="flex items-center gap-8">
                        <Link :href="route('dashboard')" class="flex items-center gap-2">
                            <img src="/images/logo.png" alt="Prono2000" class="h-9 w-auto" />
                            <span class="text-lg font-bold bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent">
                                Prono2000
                            </span>
                        </Link>

                        <nav class="flex items-center gap-1">
                            <Link
                                v-for="item in navItems"
                                :key="item.route"
                                :href="route(item.route)"
                                :class="[
                                    'px-4 py-2 rounded-lg text-sm font-medium transition',
                                    isNavActive(item)
                                        ? 'bg-indigo-50 text-indigo-700'
                                        : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'
                                ]"
                            >
                                {{ item.name }}
                            </Link>
                            <Link
                                v-if="isAdmin"
                                :href="route('admin.users')"
                                :class="[
                                    'px-4 py-2 rounded-lg text-sm font-medium transition',
                                    route().current('admin.*')
                                        ? 'bg-red-50 text-red-700'
                                        : 'text-red-600 hover:bg-red-50 hover:text-red-700'
                                ]"
                            >
                                Gestion des comptes
                            </Link>
                        </nav>
                    </div>

                    <!-- User Menu -->
                    <div class="flex items-center gap-2">
                        <div id="header-recap-slot"></div>
                        <Dropdown align="right" width="48">
                            <template #trigger>
                                <button class="flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-gray-100 transition">
                                    <div class="relative w-8 h-8 flex-shrink-0">
                                        <div class="w-8 h-8 rounded-full overflow-hidden bg-gradient-to-br from-indigo-500 to-purple-500 flex items-center justify-center text-white font-medium text-sm">
                                            <img v-if="$page.props.auth.user.avatar_url" :src="$page.props.auth.user.avatar_url" class="w-full h-full object-cover" alt="avatar" />
                                            <span v-else>{{ $page.props.auth.user.name.charAt(0) }}</span>
                                        </div>
                                        <span class="absolute bottom-0 right-0 block w-2.5 h-2.5 bg-green-400 border-2 border-white rounded-full"></span>
                                    </div>
                                    <span class="text-sm font-medium text-gray-700">{{ $page.props.auth.user.name }}</span>
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                            </template>

                            <template #content>
                                <DropdownLink :href="route('profile.edit')">
                                    Mon profil
                                </DropdownLink>
                                <DropdownLink v-if="$page.props.auth.user.is_admin" :href="route('admin.users')">
                                    Gestion des comptes
                                </DropdownLink>
                                <DropdownLink :href="route('logout')" method="post" as="button">
                                    Deconnexion
                                </DropdownLink>
                            </template>
                        </Dropdown>
                    </div>
                </div>
            </div>
        </header>

        <!-- Header Mobile -->
        <header id="mobile-header" class="sm:hidden bg-white border-b border-gray-200 sticky top-0 z-40">
            <div class="flex items-center justify-between px-4 h-14">
                <Link :href="route('dashboard')" class="flex items-center gap-2">
                    <img src="/images/logo.png" alt="Prono2000" class="h-8 w-auto" />
                    <span class="text-base font-bold bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent">
                        Prono2000
                    </span>
                </Link>

                <div class="flex items-center gap-2">
                    <div id="mobile-header-recap-slot"></div>
                    <Link :href="route('profile.edit')" class="flex items-center">
                        <div class="relative w-8 h-8">
                            <div class="w-8 h-8 rounded-full overflow-hidden bg-gradient-to-br from-indigo-500 to-purple-500 flex items-center justify-center text-white font-medium text-sm">
                                <img v-if="$page.props.auth.user.avatar_url" :src="$page.props.auth.user.avatar_url" class="w-full h-full object-cover" alt="avatar" />
                                <span v-else>{{ $page.props.auth.user.name.charAt(0) }}</span>
                            </div>
                            <span class="absolute bottom-0 right-0 block w-2.5 h-2.5 bg-green-400 border-2 border-white rounded-full"></span>
                        </div>
                    </Link>
                </div>
            </div>
        </header>

        <!-- Page Heading Desktop (si fourni) -->
        <div v-if="$slots.header" class="hidden sm:block bg-white border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                <slot name="header" />
            </div>
        </div>

        <!-- Page Content -->
        <main class="pb-20 sm:pb-0">
            <slot />
        </main>

        <!-- Bottom Navigation Mobile -->
        <nav id="mobile-bottom-nav" class="sm:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 z-50 safe-area-bottom">
            <div class="flex justify-around items-center h-16">
                <Link
                    v-for="item in navItems"
                    :key="item.route"
                    :href="route(item.route)"
                    :class="[
                        'flex flex-col items-center justify-center w-full h-full transition',
                        isNavActive(item)
                            ? 'text-indigo-600'
                            : 'text-gray-400'
                    ]"
                >
                    <!-- Home Icon -->
                    <svg v-if="item.icon === 'home'" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>

                    <!-- Trophy Icon -->
                    <svg v-if="item.icon === 'trophy'" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                    </svg>

                    <!-- Users Icon -->
                    <svg v-if="item.icon === 'users'" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>

                    <!-- Chart Icon -->
                    <svg v-if="item.icon === 'chart'" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>

                    <!-- Podium Icon -->
                    <svg v-if="item.icon === 'podium'" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 17h4v4H3v-4zm7-6h4v10h-4V11zm7-4h4v14h-4V7z" />
                    </svg>

                    <span class="text-xs mt-1 font-medium">{{ item.name }}</span>
                </Link>
                <Link
                    v-if="isAdmin"
                    :href="route('admin.users')"
                    :class="[
                        'flex flex-col items-center justify-center w-full h-full transition',
                        route().current('admin.*') ? 'text-red-600' : 'text-gray-400'
                    ]"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span class="text-xs mt-1 font-medium">Admin</span>
                </Link>
            </div>
        </nav>
    </div>
</template>

<style scoped>
.safe-area-bottom {
    padding-bottom: env(safe-area-inset-bottom);
}
</style>
