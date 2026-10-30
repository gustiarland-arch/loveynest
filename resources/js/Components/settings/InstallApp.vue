<script setup>
import { computed } from 'vue';
import { Smartphone, Share, SquarePlus } from '@lucide/vue';
import Card from '@/Components/ui/Card.vue';
import Button from '@/Components/ui/Button.vue';
import { installEvent, installed, isIos, promptInstall } from '@/lib/install';
import { t } from '@/i18n';

const ios = computed(() => isIos());
</script>

<template>
    <Card>
        <div class="flex items-start gap-3">
            <span class="grid size-10 shrink-0 place-items-center rounded-full bg-blush text-berry-ink"><Smartphone class="size-5" aria-hidden="true" /></span>
            <div class="min-w-0 flex-1">
                <h2 class="font-display text-lg font-semibold text-plum">{{ t('install.title') }}</h2>
                <p v-if="installed" class="mt-1 text-sm text-ink-muted">{{ t('install.done') }}</p>
                <template v-else>
                    <p class="mt-1 text-sm text-ink-muted">{{ t('install.lead') }}</p>
                    <Button v-if="installEvent" class="mt-3" @click="promptInstall">{{ t('install.button') }}</Button>
                    <ol v-else-if="ios" class="mt-3 space-y-2 text-sm text-plum">
                        <li class="flex items-center gap-2"><Share class="size-4 shrink-0 text-berry" aria-hidden="true" />{{ t('install.iosStep1') }}</li>
                        <li class="flex items-center gap-2"><SquarePlus class="size-4 shrink-0 text-berry" aria-hidden="true" />{{ t('install.iosStep2') }}</li>
                        <li>{{ t('install.iosStep3') }}</li>
                    </ol>
                    <p v-else class="mt-3 text-sm text-plum">{{ t('install.other') }}</p>
                </template>
                <a v-if="!ios" href="/aplikasi" class="mt-3 inline-flex min-h-11 items-center text-sm font-semibold text-rose-ink underline-offset-4 hover:underline">{{ t('install.android') }}</a>
            </div>
        </div>
    </Card>
</template>
