<script setup>
import { ref } from 'vue';
import { LockKeyhole, ShieldCheck, ShieldAlert, Smartphone } from '@lucide/vue';
import Button from '@/Components/ui/Button.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import TextField from '@/Components/ui/TextField.vue';
import { e2e, approve, removeDevice, resetKey, refresh, usePassword, dismissPasswordPrompt } from '@/lib/e2e';
import { useToast } from '@/composables/useToast';

const toast = useToast();
const busy = ref(null);
const confirmReset = ref(false);
const password = ref('');
const passwordError = ref('');

async function submitPassword() {
    if (!password.value) return;
    busy.value = 'pw';
    passwordError.value = '';
    const enabling = e2e.passwordPrompt === 'enable';
    try {
        await usePassword(password.value);
        password.value = '';
        toast.success(enabling ? 'Pemulihan dengan password aktif.' : 'Kunci terbuka. Pesan yang terkunci sudah bisa dibaca.');
    } catch (error) {
        passwordError.value = error.message;
    } finally {
        busy.value = null;
    }
}

async function run(id, fn, done) {
    busy.value = id;
    try {
        await fn();
        if (done) toast.success(done);
    } catch (error) {
        toast.error(error.message);
    } finally {
        busy.value = null;
    }
}

const allow = (d) => run(`a${d.id}`, () => approve(d), 'Perangkat diizinkan. Kunci berdua sudah dikirim terkunci.');
const deny = (d) => run(`d${d.id}`, () => removeDevice(d.id), 'Permintaan ditolak.');
async function startOver() {
    await run('reset', resetKey, 'Kunci baru dibuat. Isi lama yang terkunci tidak bisa dibuka lagi.');
    confirmReset.value = false;
}
</script>

<template>
    <div v-if="e2e.status === 'unsupported'" class="mb-4 flex gap-3 rounded-2xl bg-danger-soft p-4 text-sm text-danger" role="alert">
        <ShieldAlert class="size-5 shrink-0" aria-hidden="true" />
        <p>Enkripsi end-to-end butuh alamat <b>https://</b>. Di alamat ini isi tidak bisa dikunci, jadi menyimpan dimatikan. Buka LoveyNest lewat alamat https situsmu.</p>
    </div>

    <div v-else-if="e2e.status === 'waiting'" class="glass mb-4 rounded-2xl p-4 text-sm" role="status">
        <div class="flex gap-3">
            <LockKeyhole class="size-5 shrink-0 text-berry" aria-hidden="true" />
            <div class="min-w-0 space-y-2">
                <p class="font-semibold text-plum">Perangkat ini belum punya kunci berdua</p>
                <form v-if="e2e.passwordPrompt === 'unlock'" class="space-y-2" novalidate @submit.prevent="submitPassword">
                    <p class="text-ink-muted">Cara tercepat: masukkan <b>password login</b> kamu untuk membuka kuncinya.</p>
                    <TextField v-model="password" label="Password login" type="password" autocomplete="current-password" :error="passwordError" />
                    <Button type="submit" size="sm" :loading="busy === 'pw'">Buka dengan password</Button>
                    <p class="text-xs text-ink-muted">Atau lewat perangkat lain seperti di bawah.</p>
                </form>
                <p class="text-ink-muted">Buka LoveyNest di HP yang sudah dipakai (punyamu atau pasangan), lalu ketuk <b>Izinkan</b>. Pastikan kode di sana sama dengan kode ini:</p>
                <p class="font-mono text-lg font-bold tracking-widest text-plum">{{ e2e.fingerprint }}</p>
                <p class="text-xs text-ink-muted">Sampai diizinkan, isi yang terkunci tampil sebagai "🔒 Terkunci" dan belum bisa menyimpan isi baru.</p>
                <button type="button" class="text-xs font-semibold text-danger underline" @click="confirmReset = true">Tidak ada perangkat lain yang punya kunci? Mulai kunci baru</button>
            </div>
        </div>
    </div>

    <div v-else-if="e2e.status === 'error'" class="mb-4 flex items-center gap-3 rounded-2xl bg-danger-soft p-4 text-sm text-danger" role="alert">
        <ShieldAlert class="size-5 shrink-0" aria-hidden="true" />
        <p class="flex-1">Kunci enkripsi belum bisa dimuat: {{ e2e.error }}</p>
        <Button size="sm" variant="secondary" @click="refresh()">Coba lagi</Button>
    </div>

    <form v-if="e2e.status === 'ready' && e2e.passwordPrompt === 'enable'" class="glass mb-4 space-y-3 rounded-2xl p-4 text-sm" novalidate @submit.prevent="submitPassword">
        <p class="flex items-center gap-2 font-semibold text-plum"><ShieldCheck class="size-5 text-berry" aria-hidden="true" /> Cegah pesan terkunci di perangkat baru</p>
        <p class="text-ink-muted">Masukkan password login sekali. Setelah itu, kalau ganti HP atau data browser terhapus, cukup masukkan password yang sama untuk membuka pesan lagi. Password tidak disimpan di server, hanya salinan kunci yang terkunci olehnya.</p>
        <TextField v-model="password" label="Password login" type="password" autocomplete="current-password" :error="passwordError" />
        <div class="flex gap-2">
            <Button type="submit" size="sm" :loading="busy === 'pw'">Aktifkan</Button>
            <Button type="button" size="sm" variant="ghost" @click="dismissPasswordPrompt">Nanti</Button>
        </div>
    </form>

    <div v-if="e2e.status === 'ready' && e2e.pending.length" class="glass mb-4 space-y-3 rounded-2xl p-4 text-sm" role="status">
        <p class="flex items-center gap-2 font-semibold text-plum"><ShieldCheck class="size-5 text-berry" aria-hidden="true" /> Perangkat baru minta kunci berdua</p>
        <div v-for="d in e2e.pending" :key="d.id" class="rounded-xl bg-surface/70 p-3">
            <p class="flex items-center gap-2 font-semibold"><Smartphone class="size-4" aria-hidden="true" />{{ d.mine ? 'Perangkatmu' : d.name }} · {{ d.label || 'Perangkat' }}</p>
            <p class="mt-1 text-ink-muted">Kode: <span class="font-mono font-bold tracking-widest text-plum">{{ d.code }}</span></p>
            <p class="mt-1 text-xs text-ink-muted">Izinkan hanya bila kode ini sama persis dengan yang tampil di perangkat baru itu.</p>
            <div class="mt-2 flex gap-2">
                <Button size="sm" :loading="busy === `a${d.id}`" @click="allow(d)">Izinkan</Button>
                <Button size="sm" variant="ghost" :loading="busy === `d${d.id}`" @click="deny(d)">Tolak</Button>
            </div>
        </div>
    </div>

    <ConfirmDialog
        v-model:open="confirmReset"
        title="Mulai kunci baru?"
        description="Semua isi yang dikunci dengan kunci lama (chat, foto, jurnal, dan lainnya) tidak akan bisa dibuka lagi di perangkat mana pun yang belum punya kunci lama. Tidak ada cara memulihkannya."
        confirm-label="Ya, mulai kunci baru"
        danger
        :loading="busy === 'reset'"
        @confirm="startOver"
    />
</template>
