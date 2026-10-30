<script setup>
// Tangkap Hati: hearts and stars rain down, thunder clouds too. Slide the basket to catch the sweet ones.
// Everything falls in an order made from the seed, so two phones playing together get the very same rain.
import { onBeforeUnmount, onMounted, ref } from 'vue';
import Face from '@/Components/arena/Face.vue';
import { frameFit, frameUrl } from '@/lib/frames';
import Sticker from '@/Components/arena/Sticker.vue';
import { bits } from '@/lib/arenaArt';
import { rng } from '@/lib/rng';
import { sfx } from '@/lib/arenaAudio';

const props = defineProps({
    seed: { type: Number, required: true },
    limit: { type: Number, default: 45000 },
    me: { type: Object, required: true },
    partner: { type: Object, default: null },
});
const emit = defineEmits(['finish']);

const wrap = ref(null);
const canvas = ref(null);
const score = ref(0);
const lives = ref(3);
const secondsLeft = ref(Math.ceil(props.limit / 1000));
const combo = ref(0);
const over = ref(false);

let ctx = null;
let W = 0;
let H = 0;
let raf = 0;
let last = 0;
let elapsed = 0;
let target = 0.5;
let px = 0.5;
let keyDir = 0;
let nextIndex = 0;
let items = [];
let sparks = [];
let hudTimer = 0;
let finishTimer = 0;
let observer = null;
let done = false;
let bounce = 0;
let shake = 0;
const picture = new Image();
let pictureReady = false;
const frameImg = new Image();
let frameReady = false;
const fit = frameFit(props.me.avatarFrame);

// The rain, made from the seed: when, where, what and how fast.
const total = props.limit / 1000;
const schedule = (() => {
    const rand = rng(props.seed);
    const list = [];
    let t = 0.9;
    while (t < total + 1) {
        const progress = Math.min(t / 45, 1);
        const r = rand();
        const type = list.length < 3 ? 'heart' : r < 0.17 ? 'cloud' : r < 0.25 ? 'star' : 'heart';
        list.push({ t, x: 0.09 + rand() * 0.82, type, speed: 0.3 + progress * 0.18 + rand() * 0.07, sway: rand() * 6.28 });
        t += (0.78 - progress * 0.3) * (0.8 + rand() * 0.4);
    }
    return list;
})();

function size() {
    const box = wrap.value.getBoundingClientRect();
    const dpr = Math.min(window.devicePixelRatio || 1, 2);
    W = Math.max(200, box.width);
    H = Math.max(260, box.height);
    canvas.value.width = Math.round(W * dpr);
    canvas.value.height = Math.round(H * dpr);
    ctx = canvas.value.getContext('2d');
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
}

const radius = () => Math.min(Math.max(W * 0.075, 24), 38);
const basketTop = () => H - radius() * 2 - 30;

function pointer(event) {
    const box = canvas.value.getBoundingClientRect();
    target = Math.min(0.96, Math.max(0.04, (event.clientX - box.left) / box.width));
}

function keydown(event) {
    if (event.key === 'ArrowLeft' || event.key === 'a') keyDir = -1;
    else if (event.key === 'ArrowRight' || event.key === 'd') keyDir = 1;
}

function keyup(event) {
    if (['ArrowLeft', 'a', 'ArrowRight', 'd'].includes(event.key)) keyDir = 0;
}

function burst(x, y, color, text) {
    for (let i = 0; i < 7; i++) sparks.push({ x, y, vx: (Math.random() - 0.5) * 160, vy: -Math.random() * 140 - 20, life: 0.6, color });
    if (text) sparks.push({ x, y: y - 10, vx: 0, vy: -50, life: 0.9, text, color });
}

function catchItem(item) {
    const x = item.x * W;
    if (item.type === 'cloud') {
        lives.value--;
        combo.value = 0;
        sfx('bad');
        navigator.vibrate?.(80);
        burst(x, basketTop(), '#ff8fa8', 'Aduh!');
        shake = 0.5;
    } else {
        combo.value++;
        const points = item.type === 'star' ? 40 : 10 + Math.min(combo.value, 10);
        score.value += points;
        sfx(item.type === 'star' ? 'star' : 'heart');
        bounce = 1;
        burst(x, basketTop(), item.type === 'star' ? '#ffd54a' : '#ff8fb8', `+${points}`);
    }
}

function update(dt) {
    elapsed += dt;
    bounce = Math.max(0, bounce - dt * 4);
    shake = Math.max(0, shake - dt * 2.4);
    px += (target - px) * Math.min(1, dt * 16);
    if (keyDir) target = Math.min(0.96, Math.max(0.04, target + keyDir * dt * 1.1));

    while (nextIndex < schedule.length && schedule[nextIndex].t <= elapsed) {
        const s = schedule[nextIndex++];
        items.push({ x: s.x, y: -0.06, type: s.type, speed: s.speed, sway: s.sway, spin: 0, id: nextIndex });
    }

    const r = radius();
    const top = basketTop();
    const half = r * 1.35;
    for (const item of items) {
        const before = item.y * H;
        item.y += (item.speed * dt) * (H > 0 ? 1 : 0);
        item.spin += dt * 3;
        const iy = item.y * H;
        const ix = item.x * W + Math.sin(elapsed * 2 + item.sway) * 5;
        const ir = item.type === 'star' ? 17 : item.type === 'cloud' ? 21 : 17;
        if (!item.caught && before + ir * 0.6 < top && iy + ir * 0.6 >= top && Math.abs(ix - px * W) <= half + ir * 0.35) {
            item.caught = true;
            catchItem(item);
        } else if (!item.caught && iy - ir > H) {
            item.caught = true;
            if (item.type !== 'cloud') combo.value = 0;
        }
    }
    items = items.filter((i) => !i.caught && i.y < 1.2);

    for (const s of sparks) {
        s.x += s.vx * dt;
        s.y += s.vy * dt;
        s.vy += (s.text ? 0 : 420) * dt;
        s.life -= dt;
    }
    sparks = sparks.filter((s) => s.life > 0);

    if (!over.value && (lives.value <= 0 || elapsed >= total)) endGame();
}

function endGame() {
    over.value = true;
    secondsLeft.value = Math.max(0, Math.ceil(total - elapsed));
    sfx(lives.value > 0 ? 'win' : 'lose');
    finishTimer = setTimeout(() => {
        if (done) return;
        done = true;
        emit('finish', { score: score.value });
    }, 1100);
}

// ----- Drawing -----
// The falling things are the hand-drawn stickers, turned into small pictures once.
const sprites = {};
function makeSprite(key, inner, box) {
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="${box}" width="160" height="160">${inner}</svg>`;
    const img = new Image();
    img.src = `data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}`;
    sprites[key] = img;
}
makeSprite('heart', bits.heart(), '0 0 64 64');
makeSprite('star', bits.starBig(), '0 0 64 64');
makeSprite('storm', bits.storm, '0 0 72 70');
makeSprite('spark', bits.heart('#ffb0cb'), '0 0 64 64');

function drawSprite(key, x, y, size, rot = 0) {
    const img = sprites[key];
    if (!img || !img.complete || !img.naturalWidth) return;
    ctx.save();
    ctx.translate(x, y);
    ctx.rotate(rot);
    ctx.drawImage(img, -size, -size, size * 2, size * 2);
    ctx.restore();
}

function drawPlayer() {
    const r = radius();
    const x = px * W;
    const cy = H - r - 14;
    const top = basketTop();
    const half = r * 1.35;
    const squash = 1 + bounce * 0.18;
    // basket (outlined, with a woven pattern)
    ctx.save();
    ctx.translate(x, top + r * 0.55);
    ctx.scale(1 / squash, squash);
    ctx.translate(0, -r * 0.55);
    ctx.lineJoin = 'round';
    ctx.lineWidth = 3.5;
    ctx.strokeStyle = '#3b1d33';
    ctx.fillStyle = '#ffb36b';
    ctx.beginPath();
    ctx.moveTo(-half, 0);
    ctx.quadraticCurveTo(-half * 0.9, r * 1.2, 0, r * 1.2);
    ctx.quadraticCurveTo(half * 0.9, r * 1.2, half, 0);
    ctx.closePath();
    ctx.fill();
    ctx.stroke();
    ctx.strokeStyle = 'rgb(59 29 51 / .35)';
    ctx.lineWidth = 2;
    for (let i = -2; i <= 2; i++) {
        ctx.beginPath();
        ctx.moveTo(i * half * 0.32, 5);
        ctx.lineTo(i * half * 0.24, r * 1.05);
        ctx.stroke();
    }
    ctx.beginPath();
    ctx.moveTo(-half * 0.8, r * 0.55);
    ctx.lineTo(half * 0.8, r * 0.55);
    ctx.stroke();
    ctx.strokeStyle = '#3b1d33';
    ctx.lineWidth = 3.5;
    ctx.fillStyle = '#ff9a3d';
    ctx.beginPath();
    ctx.ellipse(0, 1, half + 4, 8, 0, 0, 6.3);
    ctx.fill();
    ctx.stroke();
    ctx.restore();
    // face of whoever plays
    ctx.save();
    ctx.beginPath();
    ctx.arc(x, cy, r, 0, 6.3);
    ctx.closePath();
    ctx.fillStyle = '#ff8fab';
    ctx.fill();
    ctx.clip();
    if (pictureReady) {
        ctx.drawImage(picture, x - r, cy - r, r * 2, r * 2);
    } else {
        ctx.fillStyle = '#fff';
        ctx.font = `${r}px 'Lilita One', system-ui, sans-serif`;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText((props.me.name ?? '?').slice(0, 1).toUpperCase(), x, cy + 2);
    }
    ctx.restore();
    ctx.beginPath();
    ctx.arc(x, cy, r, 0, 6.3);
    ctx.lineWidth = 4;
    ctx.strokeStyle = '#3b1d33';
    ctx.stroke();
    // the profile frame, when they picked one
    if (frameReady) {
        const box = (r * 2 * fit.width) / fit.photo;
        ctx.drawImage(frameImg, x - box / 2, cy - box / 2, box, box);
    }
}

function draw() {
    ctx.clearRect(0, 0, W, H);
    ctx.save();
    if (shake > 0) ctx.translate((Math.random() - 0.5) * shake * 22, (Math.random() - 0.5) * shake * 22);
    for (const item of items) {
        const x = item.x * W + Math.sin(elapsed * 2 + item.sway) * 5;
        const y = item.y * H;
        if (item.type === 'heart') drawSprite('heart', x, y, 19, Math.sin(item.spin + item.sway) * 0.25);
        else if (item.type === 'star') drawSprite('star', x, y, 21, item.spin * 0.6);
        else drawSprite('storm', x, y, 25, Math.sin(item.spin) * 0.08);
    }
    drawPlayer();
    for (const s of sparks) {
        ctx.globalAlpha = Math.max(0, Math.min(1, s.life * 1.6));
        if (s.text) {
            ctx.font = "26px 'Lilita One', system-ui, sans-serif";
            ctx.textAlign = 'center';
            ctx.lineWidth = 6;
            ctx.lineJoin = 'round';
            ctx.strokeStyle = '#3b1d33';
            ctx.strokeText(s.text, s.x, s.y);
            ctx.fillStyle = s.color;
            ctx.fillText(s.text, s.x, s.y);
        } else {
            drawSprite('spark', s.x, s.y, 5 + s.life * 5, s.life * 6);
        }
    }
    ctx.globalAlpha = 1;
    ctx.restore();
}

function frame(time) {
    raf = requestAnimationFrame(frame);
    const dt = Math.min(0.05, (time - last) / 1000 || 0);
    last = time;
    if (!over.value || sparks.length) update(over.value ? 0 : dt);
    if (over.value) {
        for (const s of sparks) {
            s.x += s.vx * dt;
            s.y += s.vy * dt;
            s.life -= dt;
        }
        sparks = sparks.filter((s) => s.life > 0);
    }
    draw();
}

onMounted(() => {
    size();
    px = target = 0.5;
    const frameSrc = frameUrl(props.me.avatarFrame);
    if (frameSrc) {
        frameImg.onload = () => (frameReady = frameImg.naturalWidth > 0);
        frameImg.src = frameSrc;
    }
    if (props.me.avatarUrl) {
        picture.onload = () => (pictureReady = picture.naturalWidth > 0);
        picture.src = props.me.avatarUrl;
    }
    observer = new ResizeObserver(() => size());
    observer.observe(wrap.value);
    window.addEventListener('keydown', keydown);
    window.addEventListener('keyup', keyup);
    hudTimer = setInterval(() => {
        if (!over.value) secondsLeft.value = Math.max(0, Math.ceil(total - elapsed));
    }, 200);
    raf = requestAnimationFrame((t) => {
        last = t;
        frame(t);
    });
});

onBeforeUnmount(() => {
    cancelAnimationFrame(raf);
    clearInterval(hudTimer);
    clearTimeout(finishTimer);
    observer?.disconnect();
    window.removeEventListener('keydown', keydown);
    window.removeEventListener('keyup', keyup);
    done = true;
});
</script>

<template>
    <div ref="wrap" class="relative min-h-[60dvh] flex-1 overflow-hidden" data-testid="tangkap">
        <canvas ref="canvas" class="absolute inset-0 size-full touch-none" @pointerdown="pointer" @pointermove="pointer" />
        <div class="pointer-events-none absolute inset-x-0 top-0 flex items-center justify-between gap-2 px-4 pt-2">
            <div class="ar-chip text-base"><Face :person="me" :size="28" ring="#3b1d33" /> <span data-testid="score" class="ar-title tabular-nums text-lg">{{ score }}</span></div>
            <div class="ar-chip tabular-nums" :class="secondsLeft <= 10 ? '!bg-[#ff9aa8]' : ''" aria-live="off">⏱ {{ secondsLeft }} dtk</div>
            <div class="ar-chip" :aria-label="`${lives} nyawa`">
                <Sticker v-for="n in 3" :key="n" name="heart" :size="22" :class="n <= lives ? '' : 'opacity-25 grayscale'" />
            </div>
        </div>
        <p v-if="combo >= 5 && !over" class="ar-title ar-outline ar-pop pointer-events-none absolute left-1/2 top-16 -translate-x-1/2 text-center text-3xl text-[#ffd54a]" :key="combo >= 10 ? 'x' : 'y'">Kombo x{{ combo }}!</p>
        <div v-if="over" class="pointer-events-none absolute inset-0 grid place-items-center"><p class="ar-title ar-outline ar-pop text-6xl text-white">{{ lives > 0 ? 'Waktu habis!' : 'Yah, habis!' }}</p></div>
    </div>
</template>
