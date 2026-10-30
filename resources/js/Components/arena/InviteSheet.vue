<script setup>
// The pop-up that says "your partner invites you to play". Accepting takes you straight into the game.
import '@/lib/arenaFonts';
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import Buddy from '@/Components/arena/Buddy.vue';
import { arena, markAnswered } from '@/composables/useArenaWatch';
import { http } from '@/lib/http';
import { sfx, unlock } from '@/lib/arenaAudio';

const busy = ref(false);
const failed = ref('');

async function accept() {
    const invite = arena.incoming;
    if (!invite || busy.value) return;
    unlock();
    busy.value = true;
    failed.value = '';
    try {
        const { id } = await http.post(`/arena/sesi/${invite.id}/terima`);
        markAnswered(invite.id);
        sfx('pop');
        router.visit(`/arena/sesi/${id}`);
    } catch (error) {
        failed.value = error.status === 409 ? 'Ajakan ini sudah tidak berlaku.' : 'Belum berhasil, coba lagi ya.';
        if (error.status === 409) markAnswered(invite.id);
    } finally {
        busy.value = false;
    }
}

async function later() {
    const invite = arena.incoming;
    if (!invite || busy.value) return;
    busy.value = true;
    markAnswered(invite.id);
    try {
        await http.post(`/arena/sesi/${invite.id}/tolak`);
    } catch {
        /* the invitation simply expires */
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <Teleport to="body">
        <div v-if="arena.incoming" class="fixed inset-0 z-[80] grid place-items-end bg-[#3a1030]/45 p-4 pb-[max(1rem,env(safe-area-inset-bottom))] sm:place-items-center" role="dialog" aria-modal="true" aria-labelledby="arena-invite-title">
            <div class="ar-stage ar-card ar-pop w-full max-w-sm !min-h-0 !overflow-visible px-6 pb-6 pt-4 text-center" style="--ar-bg: #fffaf3">
                <div class="mx-auto -mt-16 w-24"><Buddy tone="blue" mood="cheer" :size="96" class="ar-bounce" /></div>
                <p id="arena-invite-title" class="ar-title mt-1 text-[1.7rem] leading-tight text-[#e0245e]">{{ arena.incoming.from }} mengajakmu main!</p>
                <p class="mt-1 text-base font-extrabold">{{ arena.incoming.title }}</p>
                <p class="mt-1 text-sm font-semibold text-[var(--ar-soft)]">Kalau kamu terima, kalian langsung masuk ke permainan.</p>
                <p v-if="failed" class="mt-2 text-sm font-bold text-[#d70015]" role="alert">{{ failed }}</p>
                <div class="mt-5 flex flex-col gap-3">
                    <button type="button" class="ar-btn ar-btn--green ar-btn--lg" :disabled="busy" @click="accept">Ayo main!</button>
                    <button type="button" class="ar-btn ar-btn--white ar-btn--sm" :disabled="busy" @click="later">Nanti ya</button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
