<script setup>
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/Components/layout/PageHeader.vue';
import NotificationSettings from '@/Components/settings/NotificationSettings.vue';
import InstallApp from '@/Components/settings/InstallApp.vue';
import { NATIVE } from '@/lib/native';

defineProps({ notifications: { type: Object, required: true } });
</script>

<template>
    <Head :title="$t('pengaturan.notifications')" />
    <PageHeader :title="$t('pengaturan.notifications')" back="/pengaturan" />
    <div class="space-y-4">
        <InstallApp v-if="!NATIVE" />
        <NotificationSettings :settings="notifications" />
    </div>
</template>
