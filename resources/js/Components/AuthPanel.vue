<script setup>
import { ref, computed, onMounted } from 'vue'
import { Head, useForm, Link } from '@inertiajs/vue3'
import InputError from '@/Components/InputError.vue'

const props = defineProps({
    initialView:       { type: String,  default: 'login' },
    canResetPassword:  { type: Boolean, default: false   },
    status:            { type: String,  default: null    },
})

/* ── State ── */
const view      = ref(props.initialView)
const direction = ref(null)
const particles = ref([])

onMounted(() => {
    particles.value = Array.from({ length: 48 }, (_, i) => ({
        id:    i,
        x:     Math.random() * 100,
        y:     Math.random() * 100,
        size:  Math.random() * 1.8 + 0.4,
        delay: Math.random() * 9,
        dur:   Math.random() * 4 + 3,
    }))
})

/* ── Forms ── */
const loginForm = useForm({
    email: '', password: '', remember: false,
})
const registerForm = useForm({
    name: '', email: '', password: '', password_confirmation: '',
})

/* ── Navigation ── */
const switchTo = (target) => {
    if (target === view.value) return
    direction.value = target === 'register' ? 'fwd' : 'bck'
    view.value = target
}

/* ── Submissions ── */
const submitLogin = () =>
    loginForm.post(route('login'), { onFinish: () => loginForm.reset('password') })

const submitRegister = () =>
    registerForm.post(route('register'), {
        onFinish: () => registerForm.reset('password', 'password_confirmation'),
    })

/* ── Transition name based on direction ── */
const transName = computed(() =>
    direction.value ? `slide-${direction.value}` : 'fade-only'
)
</script>

<template>
    <Head :title="view === 'login' ? 'Connexion' : 'Inscription'" />

    <div class="page-root">

        <!-- ── Animated background ── -->
        <div class="bg-layer">
            <div class="blob b1" /><div class="blob b2" /><div class="blob b3" />
            <div class="grid-mesh" />
            <span
                v-for="p in particles" :key="p.id"
                class="star"
                :style="{
                    left: p.x + '%', top: p.y + '%',
                    width: p.size + 'px', height: p.size + 'px',
                    animationDelay: p.delay + 's', animationDuration: p.dur + 's',
                }"
            />
        </div>

        <!-- ── Main column ── -->
        <div class="col">

            <!-- Logo -->
            <div class="logo-block anim-drop">
                <div class="logo-halo">
                    <img src="/images/logo.png" alt="Prono2000" class="h-14 w-auto" />
                </div>
                <span class="logo-name">
                    Prono<span class="logo-accent">2000</span>
                </span>
            </div>

            <!-- Card -->
            <div class="card-shell anim-rise">
                <div class="card-inner">

                    <!-- Status message -->
                    <p v-if="status" class="status-msg">{{ status }}</p>

                    <!-- ── Tab switcher ── -->
                    <div class="tabs">
                        <div
                            class="tab-pill"
                            :style="{ transform: view === 'register' ? 'translateX(100%)' : 'translateX(0)' }"
                        />
                        <button
                            class="tab-btn"
                            :class="view === 'login' ? 'tab-active' : 'tab-inactive'"
                            @click="switchTo('login')"
                        >
                            Connexion
                        </button>
                        <button
                            class="tab-btn"
                            :class="view === 'register' ? 'tab-active' : 'tab-inactive'"
                            @click="switchTo('register')"
                        >
                            Inscription
                        </button>
                    </div>

                    <!-- ── Animated form area ── -->
                    <div class="form-area">
                        <Transition :name="transName" mode="out-in">

                            <!-- Login -->
                            <form v-if="view === 'login'" key="login" @submit.prevent="submitLogin" class="form-body">

                                <div class="field-group">
                                    <div class="field-wrap">
                                        <span class="field-icon">
                                            <!-- Email icon -->
                                            <svg class="w-[15px] h-[15px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                            </svg>
                                        </span>
                                        <input
                                            v-model="loginForm.email"
                                            type="email" required autofocus autocomplete="username"
                                            placeholder="ton@email.com"
                                            class="field-input"
                                        />
                                    </div>
                                    <InputError :message="loginForm.errors.email" />
                                </div>

                                <div class="field-group">
                                    <div class="field-wrap">
                                        <span class="field-icon">
                                            <!-- Lock icon -->
                                            <svg class="w-[15px] h-[15px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                            </svg>
                                        </span>
                                        <input
                                            v-model="loginForm.password"
                                            type="password" required autocomplete="current-password"
                                            placeholder="••••••••"
                                            class="field-input"
                                        />
                                    </div>
                                    <div class="flex justify-between items-center mt-1.5">
                                        <InputError :message="loginForm.errors.password" />
                                        <Link
                                            v-if="canResetPassword"
                                            :href="route('password.request')"
                                            class="text-xs text-violet-400 hover:text-violet-300 transition ml-auto"
                                        >
                                            Mot de passe oublié ?
                                        </Link>
                                    </div>
                                </div>

                                <label class="remember-row">
                                    <input
                                        v-model="loginForm.remember"
                                        type="checkbox"
                                        class="w-4 h-4 rounded-[5px] bg-white/8 border-white/15 text-violet-500 focus:ring-0 focus:ring-offset-0 cursor-pointer"
                                    />
                                    <span>Se souvenir de moi</span>
                                </label>

                                <button type="submit" :disabled="loginForm.processing" class="btn-cta">
                                    <span v-if="loginForm.processing" class="spinner" />
                                    <span v-else>Se connecter</span>
                                </button>
                            </form>

                            <!-- Register -->
                            <form v-else key="register" @submit.prevent="submitRegister" class="form-body">

                                <div class="field-group">
                                    <div class="field-wrap">
                                        <span class="field-icon">
                                            <!-- User icon -->
                                            <svg class="w-[15px] h-[15px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                            </svg>
                                        </span>
                                        <input
                                            v-model="registerForm.name"
                                            type="text" required autofocus autocomplete="name"
                                            placeholder="Ton pseudo"
                                            class="field-input"
                                        />
                                    </div>
                                    <InputError :message="registerForm.errors.name" />
                                </div>

                                <div class="field-group">
                                    <div class="field-wrap">
                                        <span class="field-icon">
                                            <svg class="w-[15px] h-[15px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                            </svg>
                                        </span>
                                        <input
                                            v-model="registerForm.email"
                                            type="email" required autocomplete="username"
                                            placeholder="ton@email.com"
                                            class="field-input"
                                        />
                                    </div>
                                    <InputError :message="registerForm.errors.email" />
                                </div>

                                <div class="field-group">
                                    <div class="field-wrap">
                                        <span class="field-icon">
                                            <svg class="w-[15px] h-[15px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                            </svg>
                                        </span>
                                        <input
                                            v-model="registerForm.password"
                                            type="password" required autocomplete="new-password"
                                            placeholder="••••••••"
                                            class="field-input"
                                        />
                                    </div>
                                    <InputError :message="registerForm.errors.password" />
                                </div>

                                <div class="field-group">
                                    <div class="field-wrap">
                                        <span class="field-icon">
                                            <!-- Shield check icon -->
                                            <svg class="w-[15px] h-[15px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                            </svg>
                                        </span>
                                        <input
                                            v-model="registerForm.password_confirmation"
                                            type="password" required autocomplete="new-password"
                                            placeholder="Confirmer"
                                            class="field-input"
                                        />
                                    </div>
                                    <InputError :message="registerForm.errors.password_confirmation" />
                                </div>

                                <button type="submit" :disabled="registerForm.processing" class="btn-cta">
                                    <span v-if="registerForm.processing" class="spinner" />
                                    <span v-else>Créer mon compte</span>
                                </button>
                            </form>

                        </Transition>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>

/* ══════════════════════════════════
   PAGE
══════════════════════════════════ */
.page-root {
    min-height: 100vh;
    background: #070711;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px 16px;
    position: relative;
    overflow: hidden;
}

/* ══════════════════════════════════
   BACKGROUND
══════════════════════════════════ */
.bg-layer {
    position: fixed;
    inset: 0;
    pointer-events: none;
    overflow: hidden;
}
.blob {
    position: absolute;
    border-radius: 50%;
    filter: blur(110px);
    opacity: 0.16;
}
.b1 { width: 650px; height: 650px; background: #7c3aed; top: -20%; left: -18%; animation: blobA 22s ease-in-out infinite; }
.b2 { width: 550px; height: 550px; background: #db2777; bottom: -15%; right: -12%; animation: blobB 26s ease-in-out infinite; animation-delay: -8s; }
.b3 { width: 400px; height: 400px; background: #2563eb; top: 38%; right: 10%; animation: blobA 18s ease-in-out infinite; animation-delay: -14s; }

@keyframes blobA {
    0%,100% { transform: translate(0,0) scale(1); }
    40%      { transform: translate(45px,-55px) scale(1.09); }
    70%      { transform: translate(-20px,30px) scale(0.96); }
}
@keyframes blobB {
    0%,100% { transform: translate(0,0) scale(1); }
    50%      { transform: translate(-45px,-35px) scale(1.1); }
}

.grid-mesh {
    position: absolute; inset: 0;
    background-image:
        linear-gradient(rgba(255,255,255,0.016) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,0.016) 1px, transparent 1px);
    background-size: 52px 52px;
}

.star {
    position: absolute;
    border-radius: 50%;
    background: white;
    opacity: 0;
    animation: starBlink linear infinite;
}
@keyframes starBlink {
    0%,100% { opacity: 0; transform: scale(.35); }
    50%      { opacity: .45; transform: scale(1); }
}

/* ══════════════════════════════════
   COLUMN
══════════════════════════════════ */
.col {
    position: relative;
    z-index: 10;
    width: 100%;
    max-width: 420px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 28px;
}

/* ══════════════════════════════════
   LOGO
══════════════════════════════════ */
.logo-block {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
}
.logo-halo {
    position: relative;
    display: inline-flex;
}
.logo-halo::after {
    content: '';
    position: absolute;
    inset: -10px;
    border-radius: 50%;
    background: radial-gradient(ellipse, rgba(139,92,246,.35) 0%, transparent 68%);
    animation: haloPulse 3.5s ease-in-out infinite;
    pointer-events: none;
}
@keyframes haloPulse {
    0%,100% { opacity: .55; transform: scale(1); }
    50%      { opacity: 1;   transform: scale(1.22); }
}
.logo-name {
    font-size: 1.25rem;
    font-weight: 800;
    letter-spacing: -.02em;
    color: white;
}
.logo-accent {
    background: linear-gradient(135deg, #a78bfa, #ec4899);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

/* ══════════════════════════════════
   CARD
══════════════════════════════════ */
.card-shell {
    width: 100%;
    background: linear-gradient(140deg,
        rgba(124,58,237,.28) 0%,
        rgba(219,39,119,.16) 55%,
        rgba(37,99,235,.18) 100%);
    border-radius: 28px;
    padding: 1px;
}
.card-inner {
    background: #0d0d1c;
    border-radius: 27px;
    padding: 30px 28px;
}

.status-msg {
    margin-bottom: 16px;
    font-size: .875rem;
    font-weight: 500;
    color: #34d399;
}

/* ══════════════════════════════════
   TABS
══════════════════════════════════ */
.tabs {
    position: relative;
    display: flex;
    background: rgba(255,255,255,.05);
    border-radius: 18px;
    padding: 4px;
    margin-bottom: 26px;
    border: 1px solid rgba(255,255,255,.06);
}
.tab-pill {
    position: absolute;
    top: 4px; left: 4px;
    width: calc(50% - 4px);
    height: calc(100% - 8px);
    border-radius: 14px;
    background: rgba(255,255,255,.09);
    border: 1px solid rgba(255,255,255,.12);
    box-shadow: 0 2px 10px rgba(0,0,0,.35), inset 0 1px 0 rgba(255,255,255,.07);
    transition: transform 0.42s cubic-bezier(0.4, 0, 0.2, 1);
}
.tab-btn {
    position: relative;
    z-index: 1;
    width: 50%;
    padding: 9px 12px;
    font-size: .875rem;
    font-weight: 600;
    border-radius: 14px;
    text-align: center;
    transition: color 0.3s ease;
    border: none;
    background: transparent;
    cursor: pointer;
}
.tab-active  { color: white; }
.tab-inactive { color: rgba(255,255,255,.35); }

/* ══════════════════════════════════
   FORM
══════════════════════════════════ */
.form-area { overflow: hidden; }
.form-body { display: flex; flex-direction: column; gap: 14px; }

.field-group { display: flex; flex-direction: column; gap: 4px; }
.field-wrap {
    position: relative;
    display: flex;
    align-items: center;
}
.field-icon {
    position: absolute;
    left: 13px;
    color: rgba(255,255,255,.25);
    pointer-events: none;
    display: flex;
    align-items: center;
    z-index: 1;
}
.field-input {
    width: 100%;
    background: rgba(255,255,255,.05);
    border: 1px solid rgba(255,255,255,.08);
    border-radius: 12px;
    padding: 11px 14px 11px 40px;
    color: white;
    font-size: .875rem;
    outline: none;
    transition: border-color .2s, background .2s, box-shadow .2s;
}
.field-input::placeholder { color: rgba(255,255,255,.2); }
.field-input:focus {
    border-color: rgba(139,92,246,.55);
    background: rgba(255,255,255,.07);
    box-shadow: 0 0 0 3px rgba(139,92,246,.12), 0 0 20px rgba(139,92,246,.06);
}

.remember-row {
    display: flex;
    align-items: center;
    gap: 9px;
    cursor: pointer;
    user-select: none;
    font-size: .85rem;
    color: rgba(255,255,255,.38);
}

/* ══════════════════════════════════
   CTA BUTTON
══════════════════════════════════ */
.btn-cta {
    width: 100%;
    margin-top: 4px;
    padding: 13px;
    border-radius: 13px;
    font-weight: 700;
    font-size: .9rem;
    color: white;
    background: linear-gradient(135deg, #7c3aed 0%, #9d24b2 50%, #db2777 100%);
    background-size: 200%;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 0 28px rgba(124,58,237,.45), 0 4px 14px rgba(0,0,0,.35);
    transition: transform .2s, box-shadow .2s, filter .2s;
    animation: btnShift 5s ease infinite;
}
.btn-cta:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 0 50px rgba(124,58,237,.65), 0 8px 24px rgba(0,0,0,.3);
    filter: brightness(1.08);
}
.btn-cta:active:not(:disabled) { transform: scale(.97); }
.btn-cta:disabled { opacity: .55; cursor: not-allowed; }

@keyframes btnShift {
    0%,100% { background-position: 0%; }
    50%      { background-position: 100%; }
}

/* ── Spinner ── */
.spinner {
    display: inline-block;
    width: 15px; height: 15px;
    border: 2px solid rgba(255,255,255,.3);
    border-top-color: white;
    border-radius: 50%;
    animation: spin .7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ══════════════════════════════════
   SLIDE TRANSITIONS
══════════════════════════════════ */

/* login → register : slide from right */
.slide-fwd-enter-from  { opacity: 0; transform: translateX(32px); }
.slide-fwd-enter-active { transition: opacity .38s cubic-bezier(0.16,1,.3,1), transform .38s cubic-bezier(0.16,1,.3,1); }
.slide-fwd-leave-to    { opacity: 0; transform: translateX(-32px); }
.slide-fwd-leave-active { transition: opacity .22s ease, transform .22s ease; }

/* register → login : slide from left */
.slide-bck-enter-from  { opacity: 0; transform: translateX(-32px); }
.slide-bck-enter-active { transition: opacity .38s cubic-bezier(0.16,1,.3,1), transform .38s cubic-bezier(0.16,1,.3,1); }
.slide-bck-leave-to    { opacity: 0; transform: translateX(32px); }
.slide-bck-leave-active { transition: opacity .22s ease, transform .22s ease; }

/* First render: simple fade */
.fade-only-enter-from   { opacity: 0; }
.fade-only-enter-active { transition: opacity .3s ease; }

/* ══════════════════════════════════
   PAGE ENTRY
══════════════════════════════════ */
.anim-drop {
    animation: dropIn .5s cubic-bezier(.16,1,.3,1) both;
}
.anim-rise {
    animation: riseIn .6s cubic-bezier(.16,1,.3,1) both;
    animation-delay: .08s;
}
@keyframes dropIn {
    from { opacity: 0; transform: translateY(-18px); }
    to   { opacity: 1; transform: translateY(0); }
}
@keyframes riseIn {
    from { opacity: 0; transform: translateY(22px); }
    to   { opacity: 1; transform: translateY(0); }
}
</style>
