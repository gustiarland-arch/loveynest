<script setup>
import { computed, onMounted, ref } from 'vue';
import { LockKeyhole } from '@lucide/vue';
import Card from '@/Components/ui/Card.vue';
import Button from '@/Components/ui/Button.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import { e2e, refresh, removeDevice, resetKey } from '@/lib/e2e';
import { formatDateTime } from '@/lib/dates';
import { useToast } from '@/composables/useToast';

const toast = useToast();
const removing = ref(null);
const resetOpen = ref(false);
const busy = ref(false);

onMounted(() => e2e.enabled && refresh());

const state = computed(() => ({
    ready: 'Aktif. Isi baru dikunci di perangkat ini sebelum dikirim.',
    waiting: 'Perangkat ini menunggu izin dari perangkat yang sudah punya kunci.',
    loading: 'Memuat kunci…',
    unsupported: 'Tidak bisa dipakai di alamat ini: butuh https://.',
    nospace: 'Kunci berdua dibuat setelah kamu membuat atau bergabung ke ruang berdua.',
    error: `Kunci belum bisa dimuat: ${e2e.error ?? ''}`,
})[e2e.status] ?? '');

async function remove() {
    busy.value = true;
    try {
        await removeDevice(removing.value.id);
        toast.success('Perangkat dihapus. Ia tidak menerima kunci baru lagi.');
        removing.value = null;
    } catch (error) {
        toast.error(error.message);
    } finally {
        busy.value = false;
    }
}

async function reset() {
    busy.value = true;
    try {
        await resetKey();
        toast.success('Kunci baru dibuat. Perangkat lain perlu diizinkan lagi.');
        resetOpen.value = false;
    } catch (error) {
        toast.error(error.message);
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <Card v-if="e2e.enabled">
        <h2 class="flex items-center gap-2 text-lg font-semibold text-plum"><LockKeyhole class="size-4 text-berry-ink" aria-hidden="true" />Enkripsi end-to-end</h2>
        <p class="mt-2 text-sm text-ink-muted">Chat, foto, pesan suara, jurnal, surat, catatan, tugas, acara dan isi lain dikunci di HP kalian. Server dan pemilik hosting hanya menyimpan teks acak. Yang tetap terlihat di server: siapa, kapan, ukuran berkas, angka (jumlah uang, tanggal, nilai mood) dan nama akun.</p>
        <p class="mt-2 text-sm font-semibold text-plum">{{ state }}</p>
        <p v-if="e2e.fingerprint" class="mt-1 text-sm text-ink-muted">Kode perangkat ini: <span class="font-mono font-bold tracking-widest text-plum">{{ e2e.fingerprint }}</span></p>
        <p class="mt-2 rounded-[1rem] bg-warning-soft px-3 py-2 text-xs text-plum">Tidak ada pemulihan. Bila semua perangkat yang punya kunci hilang, direset, atau data browsernya dihapus, isi yang terkunci tidak bisa dibuka lagi oleh siapa pun.</p>

        <ul v-if="e2e.devices.length" class="mt-3 space-y-2">
            <li v-for="d in e2e.devices" :key="d.id" class="flex items-center gap-3 rounded-[1rem] bg-surface px-3 py-2.5 ring-1 ring-line">
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold text-plum">{{ d.label || 'Perangkat' }}<span v-if="d.current" class="ml-2 rounded-full bg-sage px-2 py-0.5 text-[0.6875rem] text-sage-ink">Perangkat ini</span></p>
                    <p class="truncate text-xs text-ink-muted">{{ d.hasKey ? 'Punya kunci' : 'Belum punya kunci' }}<template v-if="d.lastSeen"> · {{ formatDateTime(d.lastSeen) }}</template></p>
                </div>
                <Button v-if="!d.current" size="sm" variant="secondary" @click="removing = d">Hapus</Button>
            </li>
        </ul>
        <Button v-if="e2e.status === 'ready' || e2e.status === 'waiting'" class="mt-4" variant="danger-ghost" @click="resetOpen = true">Buat kunci baru</Button>
    </Card>

    <ConfirmDialog :open="!!removing" title="Hapus perangkat ini?" description="Perangkat itu tidak lagi menerima kunci. Kunci yang sudah ia simpan tetap ada di sana; buat kunci baru bila perangkat itu hilang." confirm-label="Hapus" danger :loading="busy" @update:open="(v) => !v && (removing = null)" @confirm="remove" />
    <ConfirmDialog v-model:open="resetOpen" title="Buat kunci baru?" description="Isi baru dikunci dengan kunci baru, dan perangkat lain (punyamu dan pasangan) perlu diizinkan lagi. Isi lama tetap bisa dibuka di perangkat yang masih punya kunci lama." confirm-label="Buat kunci baru" danger :loading="busy" @confirm="reset" />
</template>
