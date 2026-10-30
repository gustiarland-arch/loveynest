<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { playSound } from '@/lib/sound';
import { usePage } from '@inertiajs/vue3';
import { http } from '@/lib/http';
import { t } from '@/i18n';
import Mochi from './Mochi.vue';

// Shows what the partner sent (hug, kiss, miss you, cheer) as a short full-screen animation, once per item.
// Items come from /nudges/pending: when the app opens, when it comes back to the foreground, and every 20 s while it is open.
const page = usePage();
const queue = ref([]);
const seenIds = new Set();
const current = computed(() => queue.value[0] ?? null);
let poll = null;
let closeTimer = null;
let busy = false;

const hasPartner = computed(() => !!page.props.partner);

async function check() {
    if (busy || current.value || document.visibilityState !== 'visible' || !hasPartner.value) return;
    busy = true;
    try {
        const data = await http.get('/nudges/pending', { timeout: 8000 });
        const fresh = (data?.items ?? []).filter((n) => !seenIds.has(n.id));
        if (fresh.length) queue.value = fresh;
    } catch {
        // Offline or a hiccup: the next check tries again.
    } finally {
        busy = false;
    }
}

function close() {
    clearTimeout(closeTimer);
    queue.value = queue.value.slice(1);
}

watch(current, (n) => {
    clearTimeout(closeTimer);
    if (!n) return;
    seenIds.add(n.id);
    if (n.id > 0) http.post(`/nudges/${n.id}/seen`, {}).catch(() => {});
    navigator.vibrate?.([30, 50, 30]);
    playSound(n.kind);
    closeTimer = setTimeout(close, 8500);
});

function onVisible() {
    if (document.visibilityState === 'visible') check();
}
// Lets a test (or a "play again" button later) run a scene without the server.
function onPreview(e) {
    queue.value = [{ id: -Date.now(), kind: e.detail.kind, from: e.detail.from ?? 'Dia' }];
}
function onKey(e) {
    if (e.key === 'Escape' && current.value) close();
}

onMounted(() => {
    check();
    poll = setInterval(check, 20000);
    document.addEventListener('visibilitychange', onVisible);
    window.addEventListener('nudge-preview', onPreview);
    window.addEventListener('keydown', onKey);
});
onBeforeUnmount(() => {
    clearInterval(poll);
    clearTimeout(closeTimer);
    document.removeEventListener('visibilitychange', onVisible);
    window.removeEventListener('nudge-preview', onPreview);
    window.removeEventListener('keydown', onKey);
});

// Deterministic "random" so every scene looks the same on both phones.
const rnd = (i, salt) => {
    const x = Math.sin(i * 12.9898 + salt * 78.233) * 43758.5453;
    return x - Math.floor(x);
};
const hearts = Array.from({ length: 14 }, (_, i) => ({
    s: { '--dx': `${Math.round((rnd(i, 1) - 0.5) * 280)}px`, '--dy': `${-Math.round(60 + rnd(i, 2) * 200)}px`, '--delay': `${(0.9 + rnd(i, 3) * 1.1).toFixed(2)}s`, '--size': `${(1 + rnd(i, 4) * 1.1).toFixed(2)}rem` },
    c: ['💖', '💗', '💕', '💘'][i % 4],
}));
const confetti = Array.from({ length: 26 }, (_, i) => ({
    s: { left: `${Math.round(rnd(i, 5) * 100)}%`, '--delay': `${(rnd(i, 6) * 2.2).toFixed(2)}s`, '--dur': `${(2.2 + rnd(i, 7) * 1.6).toFixed(2)}s`, '--rot': `${Math.round(rnd(i, 8) * 720 - 360)}deg`, background: ['#ff6f9c', '#ffd166', '#7ad7c1', '#8f7cf0', '#ff9f68'][i % 5] },
}));
const stars = Array.from({ length: 12 }, (_, i) => ({ s: { left: `${Math.round(rnd(i, 9) * 96)}%`, top: `${Math.round(rnd(i, 10) * 55)}%`, '--delay': `${(rnd(i, 11) * 2).toFixed(2)}s` } }));
const rise = Array.from({ length: 8 }, (_, i) => ({ s: { left: `${20 + Math.round(rnd(i, 12) * 60)}%`, '--delay': `${(i * 0.55).toFixed(2)}s` }, c: i % 3 === 0 ? '💭' : '💙' }));
</script>

<template>
    <Teleport to="body">
        <Transition name="stage">
            <div
                v-if="current"
                :key="current.id"
                class="stage fixed inset-0 z-[90] grid place-items-center overflow-hidden px-6 text-white"
                :class="`stage-${current.kind}`"
                role="dialog"
                aria-modal="true"
                :aria-label="t(`nudge.stage.${current.kind}`, { name: current.from })"
                @click="close"
            >
                <div v-if="current.kind === 'semangat'" class="absolute inset-0" aria-hidden="true">
                    <span v-for="(c, i) in confetti" :key="i" class="confetti" :style="c.s" />
                </div>
                <div v-if="current.kind === 'kangen'" class="absolute inset-0" aria-hidden="true">
                    <span v-for="(s, i) in stars" :key="i" class="twinkle" :style="s.s">✦</span>
                    <svg class="moon" viewBox="0 0 100 100"><path d="M62 12a38 38 0 1 0 26 62A32 32 0 0 1 62 12Z" fill="#fff6c9" /></svg>
                    <span v-for="(r, i) in rise" :key="'r' + i" class="rise" :style="r.s">{{ r.c }}</span>
                </div>

                <div class="relative z-[1] flex w-full max-w-sm flex-col items-center">
                    <div class="scene relative aspect-square w-[min(84vw,21rem)]" aria-hidden="true">
                        <!-- Peluk: two mochi run to each other and squeeze. -->
                        <template v-if="current.kind === 'peluk'">
                            <div class="absolute bottom-[10%] left-[10%] w-[44%] hug-l"><Mochi tone="pink" face="smile" arms="hug" /></div>
                            <div class="absolute bottom-[10%] right-[10%] w-[44%] hug-r"><Mochi tone="lilac" face="smile" arms="hug" flip /></div>
                            <span v-for="(h, i) in hearts" :key="i" class="burst" :style="h.s">{{ h.c }}</span>
                        </template>

                        <!-- Cium: a kiss lands between them and rings out. -->
                        <template v-else-if="current.kind === 'cium'">
                            <div class="absolute bottom-[12%] left-[4%] w-[42%] kiss-l"><Mochi tone="pink" face="kiss" /></div>
                            <div class="absolute bottom-[12%] right-[4%] w-[42%] kiss-r"><Mochi tone="lilac" face="smile" flip /></div>
                            <span class="ring ring-a" /><span class="ring ring-b" />
                            <svg class="lips" viewBox="-34 -6 68 52"><path d="M0 6C-14-8-30 0-28 12-26 26-6 32 0 38 6 32 26 26 28 12 30 0 14-8 0 6Z" fill="#ff2e63" /><path d="M-24 14C-12 18 12 18 24 14" fill="none" stroke="#b3123f" stroke-width="2.2" stroke-linecap="round" /><path d="M-14 6c4-4 9-3 14 1" fill="none" stroke="#fff" stroke-opacity=".55" stroke-width="3" stroke-linecap="round" /></svg>
                            <span v-for="(h, i) in hearts.slice(0, 9)" :key="i" class="burst late" :style="h.s">{{ h.c }}</span>
                        </template>

                        <!-- Kangen: a sleepy mochi hugs a heart pillow under the moon. -->
                        <template v-else-if="current.kind === 'kangen'">
                            <div class="absolute bottom-[8%] left-1/2 w-[56%] -translate-x-1/2"><div class="sway"><Mochi tone="lilac" face="sleepy" arms="hold" /><span class="pillow">💗</span></div></div>
                        </template>

                        <!-- Semangat: a cheering mochi with a headband jumps in front of a rising sun. -->
                        <template v-else>
                            <svg class="sun" viewBox="-60 -60 120 120"><g class="sun-rays"><template v-for="i in 12" :key="i"><rect x="-4" y="-56" width="8" height="18" rx="4" fill="#ffe27a" :transform="`rotate(${i * 30})`" /></template></g><circle r="32" fill="#ffd34d" /><circle r="32" fill="none" stroke="#fff" stroke-opacity=".5" stroke-width="3" /></svg>
                            <div class="absolute bottom-[8%] left-1/2 w-[50%] -translate-x-1/2"><div class="jump"><Mochi tone="pink" face="cheer" arms="up" />
                                <svg class="absolute inset-0 overflow-visible" viewBox="0 0 100 96"><path d="M13 36Q50 8 87 36" fill="none" stroke="#ff3d6e" stroke-width="7" stroke-linecap="round" /><path d="M84 32l10-6-3 12Zm0 0l8 10-12 0Z" fill="#ff3d6e" /></svg></div></div>
                        </template>
                    </div>

                    <p class="caption mt-2 text-center font-display text-[2rem] font-bold leading-tight">{{ t(`nudge.stage.${current.kind}`, { name: current.from }) }}</p>
                    <p class="mt-1 text-center text-sm font-semibold opacity-90">{{ t(`nudge.stageSub.${current.kind}`) }}</p>
                    <p class="mt-6 text-xs font-semibold uppercase tracking-[0.12em] opacity-70">{{ t('nudge.tapClose') }}</p>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.stage { background: var(--bg); backdrop-filter: blur(6px); }
.stage-peluk { --bg: linear-gradient(160deg, #ff9db8f2, #ffc8a2f2); }
.stage-cium { --bg: linear-gradient(160deg, #ff5d8ff0, #b58bffee); }
.stage-kangen { --bg: linear-gradient(170deg, #1f2250f5, #5a4aa8f2); }
.stage-semangat { --bg: linear-gradient(170deg, #ffb25cf2, #ff7aa5f0); }
.caption { text-shadow: 0 3px 14px rgb(0 0 0 / 0.25); animation: pop 0.7s 0.5s cubic-bezier(0.2, 1.6, 0.4, 1) both; }

.stage-enter-active { transition: opacity 0.3s ease; }
.stage-leave-active { transition: opacity 0.25s ease, transform 0.25s ease; }
.stage-enter-from, .stage-leave-to { opacity: 0; }
.stage-leave-to { transform: scale(1.05); }

/* Base state is the finished picture, so with reduced motion the animations are simply switched off. */
.hug-l { animation: run-l 0.9s cubic-bezier(0.3, 1.2, 0.5, 1) both, squeeze 1.1s 1s ease-in-out infinite; transform-origin: bottom center; }
.hug-r { animation: run-r 0.9s cubic-bezier(0.3, 1.2, 0.5, 1) both, squeeze 1.1s 1s ease-in-out infinite; transform-origin: bottom center; }
.kiss-l { animation: lean-l 1s cubic-bezier(0.3, 1.4, 0.5, 1) both; transform-origin: bottom center; }
.kiss-r { animation: lean-r 1s 0.1s cubic-bezier(0.3, 1.4, 0.5, 1) both; transform-origin: bottom center; }
.lips { position: absolute; left: 50%; top: 22%; width: 26%; transform: translateX(-50%); filter: drop-shadow(0 6px 10px rgb(120 0 40 / 0.35)); animation: stamp 0.9s 0.7s cubic-bezier(0.2, 1.8, 0.4, 1) both; }
.ring { position: absolute; left: 50%; top: 19%; width: 26%; aspect-ratio: 1; margin-left: -13%; border: 3px solid #ffffffcc; border-radius: 50%; opacity: 0; animation: ring 1.6s 1.05s ease-out infinite; }
.ring-b { animation-delay: 1.6s; }
.sway { position: relative; animation: sway 3.4s ease-in-out infinite; transform-origin: bottom center; }
.pillow { position: absolute; left: 50%; top: 52%; font-size: 3rem; transform: translateX(-50%); filter: drop-shadow(0 4px 6px rgb(0 0 0 / 0.25)); animation: thump 1.2s ease-in-out infinite; }
.moon { position: absolute; right: 8%; top: 7%; width: 5.5rem; filter: drop-shadow(0 0 18px #fff6c9aa); animation: drop 1s 0.2s ease-out both; }
.twinkle { position: absolute; font-size: 0.9rem; opacity: 0.9; animation: twinkle 2s var(--delay) ease-in-out infinite; }
.rise { position: absolute; bottom: 56%; font-size: 1.4rem; opacity: 0; animation: rise 4.4s var(--delay) ease-out infinite; }
.sun { position: absolute; left: 50%; top: 2%; width: 78%; transform: translateX(-50%); animation: sunrise 1.2s ease-out both; }
.sun-rays { transform-origin: center; animation: spin 14s linear infinite; }
.jump { position: relative; animation: jump 0.7s ease-in-out infinite; transform-origin: bottom center; }
.burst { position: absolute; left: 50%; top: 42%; font-size: var(--size); opacity: 0; animation: burst 2.4s var(--delay) ease-out infinite; }
.burst.late { animation-delay: calc(var(--delay) + 0.6s); }
.confetti { position: absolute; top: -6%; width: 10px; height: 16px; border-radius: 3px; animation: fall var(--dur) var(--delay) linear infinite; }

@keyframes pop { from { transform: scale(0.4) translateY(14px); opacity: 0; } }
@keyframes run-l { from { transform: translateX(-130%) rotate(-12deg); } }
@keyframes run-r { from { transform: translateX(130%) rotate(12deg); } }
@keyframes squeeze { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.06, 0.93); } }
@keyframes lean-l { from { transform: translateX(-90%) rotate(-18deg); } 60% { transform: translateX(8%) rotate(8deg); } }
@keyframes lean-r { from { transform: translateX(90%) rotate(18deg); } 60% { transform: translateX(-6%) rotate(-6deg); } }
@keyframes stamp { from { transform: translateX(-50%) scale(0) rotate(-40deg); opacity: 0; } 60% { transform: translateX(-50%) scale(1.35) rotate(8deg); opacity: 1; } }
@keyframes ring { 0% { transform: scale(0.6); opacity: 0.9; } 100% { transform: scale(3.2); opacity: 0; } }
@keyframes sway { 0%, 100% { transform: rotate(-3deg); } 50% { transform: rotate(3deg) translateY(2px); } }
@keyframes thump { 0%, 100% { transform: translateX(-50%) scale(1); } 50% { transform: translateX(-50%) scale(1.14); } }
@keyframes drop { from { transform: translateY(-60px) rotate(-30deg); opacity: 0; } }
@keyframes twinkle { 0%, 100% { opacity: 0.15; transform: scale(0.7); } 50% { opacity: 1; transform: scale(1.3); } }
@keyframes rise { 0% { transform: translateY(0) scale(0.7); opacity: 0; } 15% { opacity: 0.95; } 100% { transform: translateY(-190px) scale(1.2); opacity: 0; } }
@keyframes sunrise { from { transform: translate(-50%, 70%) scale(0.7); opacity: 0; } }
@keyframes spin { to { transform: rotate(360deg); } }
@keyframes jump { 0%, 100% { transform: translateY(0) scale(1.04, 0.94); } 50% { transform: translateY(-26px) scale(0.97, 1.04); } }
@keyframes burst { 0% { transform: translate(-50%, 0) scale(0.2); opacity: 0; } 15% { opacity: 1; } 100% { transform: translate(calc(-50% + var(--dx)), var(--dy)) scale(1.15); opacity: 0; } }
@keyframes fall { from { transform: translateY(0) rotate(0); } to { transform: translateY(115vh) rotate(var(--rot)); } }

@media (prefers-reduced-motion: reduce) {
    .caption, .hug-l, .hug-r, .kiss-l, .kiss-r, .lips, .ring, .sway, .pillow, .moon, .twinkle, .rise, .sun, .sun-rays, .jump, .burst, .confetti { animation: none; }
    .ring, .rise, .burst, .confetti { display: none; }
}
</style>
