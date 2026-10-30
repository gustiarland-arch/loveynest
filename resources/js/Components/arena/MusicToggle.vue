<script setup>
// One round button for music and sound effects together.
import { ref } from 'vue';
import { Music, VolumeX } from '@lucide/vue';
import { musicOn, setMusic, setSfx, sfxOn, startMusic, unlock } from '@/lib/arenaAudio';

const props = defineProps({ track: { type: String, default: 'hub' }, light: { type: Boolean, default: false } });

const on = ref(musicOn() || sfxOn());

function toggle() {
    unlock();
    on.value = !on.value;
    setSfx(on.value);
    setMusic(on.value, on.value ? props.track : null);
    if (on.value) startMusic(props.track);
}
</script>

<template>
    <button
        type="button"
        class="ar-chip !size-11 !justify-center !rounded-xl !p-0 text-[#b0306a] transition active:translate-y-0.5"
        :aria-label="on ? 'Matikan musik' : 'Nyalakan musik'"
        :aria-pressed="on"
        @click="toggle"
    >
        <Music v-if="on" class="size-5" aria-hidden="true" />
        <VolumeX v-else class="size-5" aria-hidden="true" />
    </button>
</template>
