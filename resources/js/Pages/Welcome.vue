<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';

defineProps({
    canLogin: { type: Boolean },
    canRegister: { type: Boolean },
});

const particles = ref([]);
onMounted(() => {
    particles.value = Array.from({ length: 55 }, (_, i) => ({
        id: i,
        x: Math.random() * 100,
        y: Math.random() * 100,
        size: Math.random() * 2.2 + 0.4,
        delay: Math.random() * 8,
        duration: Math.random() * 5 + 3,
    }));
});
</script>

<template>
    <Head title="Prono2000" />

    <div class="relative min-h-screen bg-[#06060f] overflow-hidden flex flex-col">

        <!-- ── Background ── -->
        <div class="absolute inset-0 pointer-events-none">
            <div class="blob b1" />
            <div class="blob b2" />
            <div class="blob b3" />
            <div class="blob b4" />
            <div class="grid-bg" />
            <span
                v-for="p in particles"
                :key="p.id"
                class="particle"
                :style="{
                    left: p.x + '%',
                    top: p.y + '%',
                    width: p.size + 'px',
                    height: p.size + 'px',
                    animationDelay: p.delay + 's',
                    animationDuration: p.duration + 's',
                }"
            />
        </div>

        <!-- ── Nav ── -->
        <nav class="relative z-20 anim-fade-down flex items-center justify-between px-6 sm:px-14 py-5 max-w-7xl mx-auto w-full">
            <div class="flex items-center gap-2.5">
                <img src="/images/logo.png" alt="Prono2000" class="h-8 w-auto" />
                <span class="font-extrabold text-lg tracking-tight text-white">
                    Prono<span class="grad-text">2000</span>
                </span>
            </div>
            <div v-if="canLogin" class="flex items-center gap-2">
                <template v-if="$page.props.auth.user">
                    <Link :href="route('dashboard')" class="btn-ghost">Tableau de bord</Link>
                </template>
                <template v-else>
                    <Link :href="route('login')" class="btn-ghost">Connexion</Link>
                    <Link v-if="canRegister" :href="route('register')" class="btn-nav">S'inscrire</Link>
                </template>
            </div>
        </nav>

        <!-- ── Hero ── -->
        <main class="relative z-10 flex-1 flex flex-col items-center justify-center px-6 pb-16 text-center">

            <!-- Halo central -->
            <div class="central-halo" />

            <!-- Badges flottants (desktop only) -->
            <div class="badge-f badge-tl anim-entry" style="animation-delay:.9s">
                <span class="badge-dot gold" />🏆 Top classement
            </div>
            <div class="badge-f badge-tr anim-entry" style="animation-delay:1.1s">
                🎯 Score exact &nbsp;<span class="badge-pts">+6 pts</span>
            </div>
            <div class="badge-f badge-bl anim-entry" style="animation-delay:1.3s">
                ⚡ Joker x2 &nbsp;<span class="badge-pts purple">activé</span>
            </div>
            <div class="badge-f badge-br anim-entry" style="animation-delay:1.5s">
                <span class="badge-dot green" />🔥 +18 pts ce soir
            </div>

            <!-- Logo -->
            <div class="relative mb-5 anim-entry" style="animation-delay:.05s">
                <div class="logo-halo" />
                <img
                    src="/images/logo.png"
                    alt="Prono2000"
                    class="relative h-32 sm:h-44 w-auto llama-float drop-shadow-2xl"
                />
            </div>

            <!-- Titre -->
            <h1 class="anim-entry text-[clamp(3.5rem,12vw,7rem)] font-black tracking-tighter leading-none mb-3" style="animation-delay:.18s">
                <span class="title-white">Prono</span><span class="title-grad">2000</span>
            </h1>

            <!-- Sous-titre -->
            <p class="anim-entry text-white/45 text-base sm:text-lg max-w-sm leading-relaxed mb-10" style="animation-delay:.32s">
                Pronostique, défie tes amis<br/>et grimpe dans le classement.
            </p>

            <!-- CTA -->
            <div v-if="canLogin" class="anim-entry flex flex-col sm:flex-row gap-3 mb-16" style="animation-delay:.46s">
                <template v-if="$page.props.auth.user">
                    <Link :href="route('dashboard')" class="btn-primary">
                        Accéder à l'app &nbsp;→
                    </Link>
                </template>
                <template v-else>
                    <Link :href="route('login')" class="btn-primary">Se connecter</Link>
                    <Link v-if="canRegister" :href="route('register')" class="btn-outline">S'inscrire gratuitement</Link>
                </template>
            </div>

            <!-- Cards -->
            <div class="anim-entry grid grid-cols-1 sm:grid-cols-3 gap-4 max-w-3xl w-full" style="animation-delay:.6s">
                <div class="card">
                    <div class="card-icon-wrap purple">🏆</div>
                    <div class="card-title">Pronostics entre amis</div>
                    <div class="card-desc">Crée un tournoi privé et invite tes amis avec un code unique.</div>
                </div>
                <div class="card card-center">
                    <div class="card-icon-wrap pink">📊</div>
                    <div class="card-title">Classements en direct</div>
                    <div class="card-desc">Suis ta progression après chaque résultat de match.</div>
                </div>
                <div class="card">
                    <div class="card-icon-wrap blue">⚡</div>
                    <div class="card-title">Récompenses & défis</div>
                    <div class="card-desc">Jokers x2 et bonus pour dominer la compétition.</div>
                </div>
            </div>
        </main>
    </div>
</template>

<style scoped>

/* ══ BACKGROUND ══ */
.blob {
    position: absolute;
    border-radius: 50%;
    filter: blur(120px);
    opacity: 0.18;
}
.b1 { width: 700px; height: 700px; background: #7c3aed; top: -25%; left: -18%; animation: db1 20s ease-in-out infinite; }
.b2 { width: 550px; height: 550px; background: #db2777; bottom: -15%; right: -12%; animation: db2 24s ease-in-out infinite; animation-delay: -6s; }
.b3 { width: 450px; height: 450px; background: #2563eb; top: 35%; left: 45%; animation: db3 17s ease-in-out infinite; animation-delay: -11s; }
.b4 { width: 350px; height: 350px; background: #9333ea; top: 55%; left: 18%; animation: db1 28s ease-in-out infinite; animation-delay: -4s; }

@keyframes db1 {
    0%,100% { transform: translate(0,0) scale(1); }
    40%      { transform: translate(50px,-60px) scale(1.1); }
    70%      { transform: translate(-20px,30px) scale(0.95); }
}
@keyframes db2 {
    0%,100% { transform: translate(0,0) scale(1); }
    50%      { transform: translate(-55px,-35px) scale(1.12); }
}
@keyframes db3 {
    0%,100% { transform: translate(0,0) scale(1); }
    33%      { transform: translate(35px,-45px) scale(0.92); }
    66%      { transform: translate(-25px,25px) scale(1.08); }
}

.grid-bg {
    position: absolute; inset: 0;
    background-image:
        linear-gradient(rgba(255,255,255,0.018) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,0.018) 1px, transparent 1px);
    background-size: 52px 52px;
}

.particle {
    position: absolute;
    border-radius: 50%;
    background: white;
    opacity: 0;
    animation: sparkle linear infinite;
}
@keyframes sparkle {
    0%,100% { opacity: 0; transform: scale(.4); }
    50%      { opacity: .55; transform: scale(1); }
}

/* ══ NAV ══ */
.grad-text {
    background: linear-gradient(135deg, #a78bfa, #ec4899);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}
.btn-ghost {
    padding: 8px 14px; border-radius: 10px;
    font-size: .875rem; font-weight: 500;
    color: rgba(255,255,255,.55);
    transition: color .2s, background .2s;
    text-decoration: none;
}
.btn-ghost:hover { color: white; background: rgba(255,255,255,.07); }

.btn-nav {
    display: inline-flex; padding: 8px 18px; border-radius: 10px;
    font-size: .875rem; font-weight: 600; color: white;
    background: linear-gradient(135deg, #7c3aed, #db2777);
    box-shadow: 0 0 18px rgba(124,58,237,.45);
    transition: transform .2s, box-shadow .2s, filter .2s;
    text-decoration: none;
}
.btn-nav:hover { transform: translateY(-2px); box-shadow: 0 0 32px rgba(124,58,237,.7); filter: brightness(1.1); }

/* ══ HERO ══ */
.central-halo {
    position: absolute;
    width: 700px; height: 700px;
    border-radius: 50%;
    background: radial-gradient(ellipse at center, rgba(124,58,237,.13) 0%, transparent 68%);
    top: 50%; left: 50%; transform: translate(-50%,-50%);
    pointer-events: none;
    animation: haloPulse 5s ease-in-out infinite;
}
@keyframes haloPulse {
    0%,100% { opacity: .8; transform: translate(-50%,-50%) scale(1); }
    50%      { opacity: 1;  transform: translate(-50%,-50%) scale(1.12); }
}

/* ── Logo ── */
.logo-halo {
    position: absolute; inset: -24px;
    border-radius: 50%;
    background: radial-gradient(ellipse, rgba(167,139,250,.4) 0%, transparent 68%);
    animation: logoPulse 3.5s ease-in-out infinite;
}
@keyframes logoPulse {
    0%,100% { opacity: .5; transform: scale(1); }
    50%      { opacity: 1;  transform: scale(1.25); }
}
.llama-float {
    animation: llamaFloat 4s ease-in-out infinite;
    transform-origin: bottom center;
}
@keyframes llamaFloat {
    0%,100% { transform: translateY(0)   rotate(-1.5deg); }
    50%      { transform: translateY(-18px) rotate(1.5deg); }
}

/* ── Titre ── */
.title-white {
    background: linear-gradient(175deg, #ffffff 30%, rgba(255,255,255,.55));
    -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
}
.title-grad {
    background: linear-gradient(135deg, #a78bfa 0%, #ec4899 50%, #60a5fa 100%);
    -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
    background-size: 200%;
    animation: gradShift 4s ease infinite;
}
@keyframes gradShift {
    0%   { background-position: 0%; }
    50%  { background-position: 100%; }
    100% { background-position: 0%; }
}

/* ── Boutons ── */
.btn-primary {
    display: inline-flex; align-items: center; justify-content: center;
    padding: 15px 38px; border-radius: 16px;
    font-weight: 700; font-size: 1rem; color: white;
    background: linear-gradient(135deg, #7c3aed, #db2777);
    box-shadow: 0 0 35px rgba(124,58,237,.5), 0 0 70px rgba(219,39,119,.2);
    transition: transform .2s, box-shadow .2s, filter .2s;
    text-decoration: none;
}
.btn-primary:hover {
    transform: translateY(-3px) scale(1.03);
    box-shadow: 0 0 55px rgba(124,58,237,.8), 0 0 90px rgba(219,39,119,.4);
    filter: brightness(1.1);
}
.btn-primary:active { transform: scale(.97); }

.btn-outline {
    display: inline-flex; align-items: center; justify-content: center;
    padding: 15px 38px; border-radius: 16px;
    font-weight: 700; font-size: 1rem; color: white;
    background: rgba(255,255,255,.05);
    border: 1px solid rgba(255,255,255,.14);
    backdrop-filter: blur(10px);
    transition: transform .2s, background .2s, border-color .2s, box-shadow .2s;
    text-decoration: none;
}
.btn-outline:hover {
    transform: translateY(-3px) scale(1.03);
    background: rgba(255,255,255,.09);
    border-color: rgba(167,139,250,.5);
    box-shadow: 0 0 35px rgba(167,139,250,.2);
}

/* ── Cards ── */
.card {
    padding: 22px;
    border-radius: 20px;
    background: rgba(255,255,255,.04);
    border: 1px solid rgba(255,255,255,.08);
    backdrop-filter: blur(18px);
    text-align: left;
    transition: transform .25s, border-color .25s, background .25s, box-shadow .25s;
}
.card:hover {
    transform: translateY(-6px);
    border-color: rgba(167,139,250,.3);
    background: rgba(255,255,255,.07);
    box-shadow: 0 24px 60px rgba(124,58,237,.15);
}
.card-center {
    border-color: rgba(167,139,250,.18);
    background: rgba(124,58,237,.08);
}
.card-icon-wrap {
    display: inline-flex; align-items: center; justify-content: center;
    width: 44px; height: 44px; border-radius: 12px;
    font-size: 1.3rem; margin-bottom: 12px;
}
.card-icon-wrap.purple { background: rgba(124,58,237,.2); }
.card-icon-wrap.pink   { background: rgba(219,39,119,.2); }
.card-icon-wrap.blue   { background: rgba(37,99,235,.2); }
.card-title { font-weight: 600; font-size: .9rem; color: white; margin-bottom: 6px; }
.card-desc  { font-size: .78rem; color: rgba(255,255,255,.38); line-height: 1.65; }

/* ── Badges flottants ── */
.badge-f {
    display: none;
    position: absolute;
    padding: 8px 16px; border-radius: 999px;
    font-size: .73rem; font-weight: 600;
    background: rgba(255,255,255,.06);
    border: 1px solid rgba(255,255,255,.12);
    backdrop-filter: blur(14px);
    color: rgba(255,255,255,.7);
    white-space: nowrap; z-index: 8;
    align-items: center; gap: 6px;
}
@media (min-width: 900px) { .badge-f { display: inline-flex; } }

.badge-tl { top: 14%; left:  7%; animation: bf0 6s ease-in-out infinite; }
.badge-tr { top: 18%; right: 7%; animation: bf1 7s ease-in-out infinite; animation-delay: -2s; }
.badge-bl { bottom: 26%; left:  5%; animation: bf0 8s ease-in-out infinite; animation-delay: -1s; }
.badge-br { bottom: 28%; right: 6%; animation: bf1 6.5s ease-in-out infinite; animation-delay: -3s; }

@keyframes bf0 {
    0%,100% { transform: translateY(0); }
    50%      { transform: translateY(-10px); }
}
@keyframes bf1 {
    0%,100% { transform: translateY(0); }
    50%      { transform: translateY(10px); }
}

.badge-dot {
    display: inline-block; width: 7px; height: 7px; border-radius: 50%;
}
.badge-dot.gold  { background: #fbbf24; box-shadow: 0 0 6px #fbbf24; }
.badge-dot.green { background: #34d399; box-shadow: 0 0 6px #34d399; animation: dotPulse 2s ease-in-out infinite; }
@keyframes dotPulse { 0%,100%{opacity:1} 50%{opacity:.4} }

.badge-pts { color: #a78bfa; font-weight: 700; }
.badge-pts.purple { color: #c084fc; }

/* ══ ENTRÉES ══ */
.anim-entry {
    opacity: 0;
    animation: entryUp .75s cubic-bezier(.16,1,.3,1) forwards;
}
.anim-fade-down { animation: fadeDown .6s ease forwards; }

@keyframes entryUp {
    from { opacity: 0; transform: translateY(30px); }
    to   { opacity: 1; transform: translateY(0); }
}
@keyframes fadeDown {
    from { opacity: 0; transform: translateY(-12px); }
    to   { opacity: 1; transform: translateY(0); }
}
</style>
