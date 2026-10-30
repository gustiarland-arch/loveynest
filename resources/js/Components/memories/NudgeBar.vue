<script setup>
import { ref } from 'vue';
import { nudges } from '@/lib/nudges';
import { http, HttpError } from '@/lib/http';
import { useToast } from '@/composables/useToast';
import { t } from '@/i18n';

const props = defineProps({
    partnerName: { type: String, required: true },
    received: { type: Array, default: () => [] },
});

const toast = useToast();
const sent = ref({});
const emoji = { peluk: '🤗', kangen: '🥺', cium: '😘', semangat: '💪' };
const pops = ref([]);
let popId = 0;
// A few emoji float up from the button so the sender sees the feeling leave too.
function pop(kind) {
    const base = popId++;
    const items = Array.from({ length: 5 }, (_, i) => ({ id: `${base}-${i}`, kind, dx: (i - 2) * 16, delay: i * 70 }));
    pops.value = [...pops.value, ...items];
    setTimeout(() => (pops.value = pops.value.filter((p) => !items.includes(p))), 1400);
}

async function send(kind) {
    try {
        await http.post('/nudges', { kind });
        sent.value = { ...sent.value, [kind]: true };
        pop(kind);
        toast.success(t('nudge.sent', { name: props.partnerName }));
        setTimeout(() => (sent.value = { ...sent.value, [kind]: false }), 1800);
    } catch (error) {
        toast.error(error instanceof HttpError && error.status === 429 ? t('nudge.wait') : t('chat.offline'));
    }
}
</script>

<template>
    <div class="relative">
        <p v-if="received.length" class="mb-3 rounded-[1rem] bg-lavender px-4 py-3 text-sm text-lavender-ink">
            <template v-for="(r, i) in received" :key="r.kind">{{ i ? ', ' : '' }}{{ t(`nudge.got.${r.kind}`) }}<template v-if="r.count > 1"> ×{{ r.count }}</template></template>
        </p>
        <ul class="grid grid-cols-4 gap-2">
            <li v-for="n in nudges" :key="n.kind">
                <button
                    type="button"
                    class="glass flex w-full flex-col items-center gap-1.5 rounded-[1.125rem] px-1 py-2.5 text-[0.75rem] font-semibold text-ink-muted transition active:scale-95 hover:shadow-lift"
                    :class="sent[n.kind] && 'ring-2 ring-berry'"
                    @click="send(n.kind)"
                >
                    <span class="grid size-[1.875rem] place-items-center rounded-[0.5625rem] transition" :class="[n.tone, sent[n.kind] && 'scale-110']">
                        <component :is="n.icon" class="size-4" aria-hidden="true" />
                    </span>
                    {{ t(`nudge.label.${n.kind}`) }}
                </button>
            </li>
        </ul>
        <span v-for="p in pops" :key="p.id" class="nudge-pop pointer-events-none absolute bottom-6 left-1/2 text-2xl" :style="{ '--dx': p.dx + 'px', animationDelay: p.delay + 'ms' }" aria-hidden="true">{{ emoji[p.kind] }}</span>
    </div>
</template>

<style scoped>
.nudge-pop { opacity: 0; animation: nudge-pop 1.1s ease-out both; }
@keyframes nudge-pop { 0% { transform: translate(-50%, 0) scale(0.5); opacity: 0; } 20% { opacity: 1; } 100% { transform: translate(calc(-50% + var(--dx)), -90px) scale(1.2); opacity: 0; } }
@media (prefers-reduced-motion: reduce) { .nudge-pop { display: none; } }
</style>
