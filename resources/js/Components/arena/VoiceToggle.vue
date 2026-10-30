<script setup>
// Voice on/off while playing as a pair: one round button, with a small dot for the state of the call.
import { computed } from 'vue';
import { Mic, MicOff } from '@lucide/vue';
import { useArenaVoice } from '@/composables/useArenaVoice';
import { unlock } from '@/lib/arenaAudio';

const props = defineProps({ session: { type: Number, required: true }, me: { type: Number, required: true }, partner: { type: Number, required: true }, name: { type: String, default: 'pasangan' } });

const { status, toggle, supported } = useArenaVoice(props.session, props.me, props.partner);

const active = computed(() => ['asking', 'waiting', 'connecting', 'on'].includes(status.value));
const hint = computed(
    () =>
        ({
            off: 'Nyalakan suara',
            asking: 'Izinkan mikrofon…',
            waiting: `Menunggu ${props.name} menyalakan suara`,
            connecting: 'Menyambungkan…',
            on: 'Suara tersambung. Ketuk untuk mematikan',
            denied: 'Mikrofon tidak diizinkan. Buka pengaturan browser atau aplikasi, lalu coba lagi',
            unsupported: 'Perangkat ini belum mendukung suara',
        })[status.value],
);
const caption = computed(() => ({ waiting: `Menunggu ${props.name}…`, connecting: 'Menyambungkan…', denied: 'Mikrofon ditolak', asking: 'Izinkan mikrofon' })[status.value] ?? '');
const dot = computed(() => ({ on: '#35c987', connecting: '#ffb020', waiting: '#ffb020', asking: '#ffb020', denied: '#e5484d' })[status.value] ?? null);

function tap() {
    unlock();
    toggle();
}
</script>

<template>
    <div v-if="supported" class="relative">
        <button
            type="button"
            class="ar-chip !size-11 !justify-center !rounded-xl !p-0 transition active:translate-y-0.5"
            :class="active ? '!bg-[#d9ffe9] text-[#1f9a64]' : 'text-[#b0306a]'"
            :aria-label="hint"
            :aria-pressed="active"
            data-testid="voice"
            :data-status="status"
            @click="tap"
        >
            <Mic v-if="active" class="size-5" aria-hidden="true" />
            <MicOff v-else class="size-5" aria-hidden="true" />
        </button>
        <span v-if="dot" class="pointer-events-none absolute -right-1 -top-1 size-3.5 rounded-full border-2 border-[#3b1d33]" :style="{ background: dot }" aria-hidden="true" />
        <p v-if="caption" class="pointer-events-none absolute right-0 top-full z-20 mt-1 whitespace-nowrap rounded-full border-2 border-[#3b1d33] bg-white px-2.5 py-0.5 text-[0.7rem] font-semibold text-[#3b1d33]" role="status" data-testid="voice-caption">{{ caption }}</p>
    </div>
</template>
