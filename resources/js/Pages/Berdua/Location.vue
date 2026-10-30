<script setup>
import { computed, onBeforeUnmount, onMounted, ref, shallowRef } from 'vue';
import { Head } from '@inertiajs/vue3';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { Crosshair, MapPin, ShieldCheck } from '@lucide/vue';
import PageHeader from '@/Components/layout/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import { http } from '@/lib/http';
import { e2e, seal } from '@/lib/e2e';
import { t } from '@/i18n';

const props = defineProps({ state: Object });

// With end-to-end encryption a position arrives locked ("sealed"), already opened by this device; distance is measured here.
function metres(a, b) {
    const rad = (d) => (d * Math.PI) / 180;
    const h = Math.sin(rad(b.lat - a.lat) / 2) ** 2 + Math.cos(rad(a.lat)) * Math.cos(rad(b.lat)) * Math.sin(rad(b.lng - a.lng) / 2) ** 2;
    return Math.round(2 * 6371000 * Math.asin(Math.min(1, Math.sqrt(h))));
}
function opened(pos) {
    if (!pos || typeof pos.sealed !== 'string') return pos;
    try {
        const p = JSON.parse(pos.sealed);
        return Number.isFinite(p.lat) && Number.isFinite(p.lng) ? { lat: p.lat, lng: p.lng, accuracy: p.accuracy ?? null, secondsAgo: pos.secondsAgo } : null;
    } catch {
        return null;
    }
}
function normalize(state) {
    const me = opened(state.me);
    const partner = opened(state.partner);
    return { ...state, me, partner, distance: state.distance ?? (me && partner ? metres(me, partner) : null) };
}
async function payload(pos) {
    const plain = { lat: Math.round(pos.coords.latitude * 1e5) / 1e5, lng: Math.round(pos.coords.longitude * 1e5) / 1e5, accuracy: Math.round(pos.coords.accuracy ?? 0) };
    return e2e.enabled ? { sealed: await seal(JSON.stringify(plain)) } : plain;
}

const st = ref(normalize(props.state));
const busy = ref(false);
const error = ref('');
const supported = typeof navigator !== 'undefined' && 'geolocation' in navigator && window.isSecureContext;
const mapEl = ref(null);
const map = shallowRef(null);
const markers = {};
let watchId = null;
let lastSent = 0;
let poll = null;
let fitted = false;

const FRESH = 15 * 60;
const km = (m) => (m < 1000 ? `${m} m` : `${(m / 1000).toLocaleString('id-ID', { maximumFractionDigits: 1 })} km`);
const age = (s) => (s < 60 ? t('location.justNow') : s < 3600 ? t('location.minAgo', { n: Math.round(s / 60) }) : t('location.hourAgo', { n: Math.round(s / 3600) }));
const partnerStale = computed(() => st.value.partner && st.value.partner.secondsAgo > FRESH);

const icon = (label, cls) => L.divIcon({ className: '', html: `<span class="loc-pin ${cls}">${label}</span>`, iconSize: [40, 40], iconAnchor: [20, 20] });

function draw() {
    if (!map.value) return;
    const items = [['me', st.value.me, t('location.you').slice(0, 1).toUpperCase(), 'loc-me'], ['partner', st.value.partner, (st.value.partnerName ?? '?').slice(0, 1).toUpperCase(), 'loc-partner']];
    const points = [];
    for (const [key, pos, label, cls] of items) {
        if (!pos) {
            markers[key]?.remove();
            delete markers[key];
            continue;
        }
        const ll = [pos.lat, pos.lng];
        points.push(ll);
        if (markers[key]) markers[key].setLatLng(ll);
        else markers[key] = L.marker(ll, { icon: icon(label, cls), keyboard: false }).addTo(map.value);
    }
    if (!fitted && points.length) {
        fitted = true;
        recenter(points);
    }
}

function recenter(points = Object.values(markers).map((m) => m.getLatLng())) {
    if (!map.value || !points.length) return;
    if (points.length === 1) map.value.setView(points[0], 15);
    else map.value.fitBounds(points, { padding: [60, 60], maxZoom: 16 });
}

async function refresh() {
    try {
        st.value = normalize(await http.get('/lokasi/data'));
        draw();
    } catch {
        /* the next poll tries again */
    }
}

function send(pos) {
    const now = Date.now();
    if (now - lastSent < 12000) return;
    lastSent = now;
    payload(pos)
        .then((body) => http.post('/lokasi/bagikan', body))
        .then((data) => { st.value = normalize(data); draw(); })
        .catch(() => {});
}

function watch() {
    if (watchId !== null || !supported) return;
    watchId = navigator.geolocation.watchPosition(send, () => {}, { enableHighAccuracy: true, maximumAge: 10000, timeout: 20000 });
}

function unwatch() {
    if (watchId !== null) navigator.geolocation.clearWatch(watchId);
    watchId = null;
}

function start() {
    error.value = '';
    if (!supported) {
        error.value = t('location.unsupported');
        return;
    }
    busy.value = true;
    // Asked inside the tap, so the browser shows its permission prompt.
    navigator.geolocation.getCurrentPosition(
        async (pos) => {
            lastSent = 0;
            try {
                st.value = normalize(await http.post('/lokasi/bagikan', await payload(pos)));
                lastSent = Date.now();
                draw();
                watch();
            } catch {
                error.value = t('location.failed');
            }
            busy.value = false;
        },
        (e) => {
            error.value = e.code === 1 ? t('location.denied') : t('location.noFix');
            busy.value = false;
        },
        { enableHighAccuracy: true, timeout: 20000 },
    );
}

async function stop() {
    busy.value = true;
    unwatch();
    try {
        st.value = normalize(await http.delete('/lokasi/bagikan'));
        draw();
    } catch {
        error.value = t('location.failed');
    }
    busy.value = false;
}

const onVisible = () => {
    if (document.visibilityState === 'visible') refresh();
};

onMounted(() => {
    map.value = L.map(mapEl.value, { zoomControl: false, attributionControl: true }).setView([-2.5, 118], 4);
    L.control.zoom({ position: 'bottomright' }).addTo(map.value);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© OpenStreetMap', referrerPolicy: 'origin' }).addTo(map.value);
    draw();
    if (st.value.sharing) watch();
    poll = setInterval(() => document.visibilityState === 'visible' && refresh(), 10000);
    document.addEventListener('visibilitychange', onVisible);
});

onBeforeUnmount(() => {
    clearInterval(poll);
    document.removeEventListener('visibilitychange', onVisible);
    unwatch();
    map.value?.remove();
});
</script>

<template>
    <Head :title="t('location.title')" />
    <PageHeader :title="t('location.title')" :lead="t('location.lead')" back="/berdua" />

    <div class="relative overflow-hidden rounded-[1.5rem] shadow-soft">
        <div ref="mapEl" class="h-[22rem] w-full bg-blush-soft" role="application" :aria-label="t('location.mapLabel')"></div>
        <button type="button" class="glass absolute right-3 top-3 z-[500] grid size-10 place-items-center rounded-full text-plum" :aria-label="t('location.recenter')" @click="recenter()">
            <Crosshair class="size-5" aria-hidden="true" />
        </button>
    </div>

    <section class="glass mt-3 rounded-[1.5rem] p-4" aria-live="polite">
        <p class="text-xs font-semibold uppercase tracking-[0.08em] text-ink-muted">{{ t('location.distance') }}</p>
        <p v-if="st.distance !== null" class="font-display text-4xl font-semibold text-plum">{{ km(st.distance) }}</p>
        <p v-else class="text-sm text-ink-muted">
            {{ !st.sharing ? t('location.needYou') : !st.partnerSharing ? t('location.waiting', { name: st.partnerName ?? '' }) : t('location.noPosition') }}
        </p>
        <ul class="mt-3 space-y-2 text-sm">
            <li class="flex items-center justify-between gap-2">
                <span class="flex items-center gap-2 text-plum"><MapPin class="size-4 text-lavender-ink" aria-hidden="true" />{{ t('location.you') }}</span>
                <span class="text-ink-muted">{{ st.me ? age(st.me.secondsAgo) : st.sharing ? t('location.locating') : t('location.off') }}</span>
            </li>
            <li class="flex items-center justify-between gap-2">
                <span class="flex items-center gap-2 text-plum"><MapPin class="size-4 text-berry" aria-hidden="true" />{{ st.partnerName }}</span>
                <span class="text-ink-muted">{{ st.partner ? age(st.partner.secondsAgo) : st.partnerSharing ? t('location.locating') : t('location.off') }}</span>
            </li>
        </ul>
        <p v-if="partnerStale" class="mt-2 text-xs text-ink-muted">{{ t('location.stale', { name: st.partnerName ?? '' }) }}</p>
    </section>

    <p v-if="error" class="mt-3 text-sm font-semibold text-danger" role="alert">{{ error }}</p>
    <div class="mt-3">
        <Button v-if="!st.sharing" block :loading="busy" @click="start">{{ t('location.start') }}</Button>
        <Button v-else block variant="secondary" :loading="busy" @click="stop">{{ t('location.stop') }}</Button>
    </div>

    <p class="mt-4 flex items-start gap-2 text-xs text-ink-muted">
        <ShieldCheck class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
        <span>{{ t('location.privacy') }}</span>
    </p>
</template>

<style>
.loc-pin {
    display: grid;
    width: 40px;
    height: 40px;
    place-items: center;
    border-radius: 9999px;
    border: 3px solid #fff;
    color: #fff;
    font-weight: 700;
    box-shadow: 0 4px 12px rgb(0 0 0 / 0.25);
}
.loc-me { background: #6b5bd6; }
.loc-partner { background: #d6457a; }
</style>
