<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Plus, Sparkles, ImagePlus, Trash2 } from '@lucide/vue';
import PageHeader from '@/Components/layout/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import Button from '@/Components/ui/Button.vue';
import Dialog from '@/Components/ui/Dialog.vue';
import Badge from '@/Components/ui/Badge.vue';
import TextField from '@/Components/ui/TextField.vue';
import TextArea from '@/Components/ui/TextArea.vue';
import SelectField from '@/Components/ui/SelectField.vue';
import DateField from '@/Components/ui/DateField.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import { formatDate } from '@/lib/dates';
import { t } from '@/i18n';
import { fitImage } from '@/lib/image';
import { submitSealed } from '@/lib/e2e';
import { sealPhoto } from '@/lib/e2e/media';

const props = defineProps({ dreams: Array });

const statuses = ['wish', 'planned', 'active', 'done', 'cancelled'];
const categories = ['tempat', 'makanan', 'film', 'pengalaman', 'liburan', 'belajar', 'lainnya'];
const statusOptions = statuses.map((value) => ({ value, label: t(`dreams.status.${value}`) }));
const categoryOptions = categories.map((value) => ({ value, label: t(`dreams.cat.${value}`) }));
const tones = { wish: 'neutral', planned: 'lavender', active: 'rose', done: 'sage', cancelled: 'neutral' };

const filter = ref('all');
const shown = computed(() => (filter.value === 'all' ? props.dreams : props.dreams.filter((d) => d.status === filter.value)));

const open = ref(false);
const editing = ref(null);
const fileInput = ref(null);
const preview = ref(null);
const form = useForm({ title: '', note: '', category: 'pengalaman', status: 'wish', target_date: '', pct: 0, image: null, remove_image: false });

function openForm(d = null) {
    editing.value = d;
    form.clearErrors();
    form.defaults({ title: d?.title ?? '', note: d?.note ?? '', category: d?.category ?? 'pengalaman', status: d?.status ?? 'wish', target_date: d?.target_date ?? '', pct: d?.progress ?? 0, image: null, remove_image: false });
    form.reset();
    preview.value = d?.image ?? null;
    open.value = true;
}
async function pick(event) {
    const picked = event.target.files?.[0];
    if (!picked) return;
    const file = await fitImage(picked);
    form.image = file;
    form.remove_image = false;
    preview.value = URL.createObjectURL(file);
}
function clearImage() {
    form.image = null;
    form.remove_image = true;
    preview.value = null;
    if (fileInput.value) fileInput.value.value = '';
}
function save() {
    // Multipart updates go through POST so the file survives PHP's parsing.
    const options = { preserveScroll: true, forceFormData: true, onSuccess: () => (open.value = false) };
    const url = editing.value ? `/impian/${editing.value.id}` : '/impian';
    submitSealed(form, ['title', 'note'], (f) => f.post(url, options), ({ pct, ...d }) => ({ ...d, note: d.note || '', target_date: d.target_date || '', progress: Number(pct), remove_image: d.remove_image ? 1 : 0 }), lockImage);
}
/** The picture is shrunk, given a thumbnail and locked here; the server only stores the locked files. */
async function lockImage(data) {
    if (!(data.image instanceof Blob)) return data;
    const locked = await sealPhoto(data.image);
    return locked ? { ...data, image: locked.full, image_thumb: locked.thumb, image_width: locked.width, image_height: locked.height } : data;
}
const removing = ref(null);
function remove() {
    router.delete(`/impian/${removing.value.id}`, { preserveScroll: true, onSuccess: () => { removing.value = null; open.value = false; } });
}
</script>

<template>
    <Head :title="t('dreams.title')" />
    <PageHeader :title="t('dreams.title')" :lead="t('dreams.lead')" back="/berdua">
        <template #action><Button size="sm" @click="openForm()"><Plus class="size-4" aria-hidden="true" />{{ t('growth.add') }}</Button></template>
    </PageHeader>

    <div class="mb-3 flex flex-wrap gap-2" role="group" :aria-label="t('dreams.filter')">
        <button v-for="s in ['all', ...statuses]" :key="s" type="button" class="h-9 rounded-full px-3.5 text-xs font-semibold transition" :class="filter === s ? 'bg-berry text-white' : 'bg-surface text-ink-muted ring-1 ring-line'" @click="filter = s">{{ s === 'all' ? t('budget.all') : t(`dreams.status.${s}`) }}</button>
    </div>

    <ul v-if="shown.length" class="grid gap-3 sm:grid-cols-2">
        <li v-for="d in shown" :key="d.id">
            <button type="button" class="glass block w-full overflow-hidden rounded-[1.5rem] text-left" @click="openForm(d)">
                <img v-if="d.image" :src="d.image" alt="" loading="lazy" class="h-40 w-full object-cover" />
                <div class="p-4">
                    <div class="flex items-start justify-between gap-2"><p class="break-words font-semibold text-plum" :class="d.status === 'done' && 'line-through opacity-70'">{{ d.title }}</p><Badge :tone="tones[d.status]">{{ t(`dreams.status.${d.status}`) }}</Badge></div>
                    <p class="mt-0.5 text-xs text-ink-muted">{{ t(`dreams.cat.${d.category}`) }}<template v-if="d.target_date"> · {{ formatDate(d.target_date) }}</template></p>
                    <p v-if="d.note" class="mt-1.5 line-clamp-2 break-words text-sm text-plum">{{ d.note }}</p>
                    <div v-if="d.progress > 0 && d.status !== 'done'" class="mt-3 h-1.5 overflow-hidden rounded-full bg-plum/[0.1]" role="progressbar" :aria-valuenow="d.progress" aria-valuemin="0" aria-valuemax="100" :aria-label="d.title"><div class="h-full rounded-full bg-brand" :style="{ width: `${d.progress}%` }" /></div>
                </div>
            </button>
        </li>
    </ul>
    <Card v-else :padded="false"><EmptyState :title="t('dreams.empty')" :body="t('dreams.emptyBody')" :icon="Sparkles"><Button variant="soft" @click="openForm()">{{ t('growth.add') }}</Button></EmptyState></Card>

    <Dialog v-model:open="open" :title="editing ? t('dreams.edit') : t('dreams.new')">
        <form id="dr-form" class="space-y-4" novalidate @submit.prevent="save">
            <div>
                <input ref="fileInput" type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="pick" />
                <div v-if="preview" class="relative overflow-hidden rounded-[1.125rem]"><img :src="preview" alt="" class="h-40 w-full object-cover" /><button type="button" class="absolute right-2 top-2 grid size-9 place-items-center rounded-full bg-surface/90 text-danger" :aria-label="t('dreams.removeImage')" @click="clearImage"><Trash2 class="size-4" aria-hidden="true" /></button></div>
                <Button v-else type="button" variant="soft" block @click="fileInput.click()"><ImagePlus class="size-4" aria-hidden="true" />{{ t('dreams.addImage') }}</Button>
                <p v-if="form.errors.image" class="mt-1 text-xs text-danger" role="alert">{{ form.errors.image }}</p>
            </div>
            <TextField v-model="form.title" :label="t('dreams.fieldTitle')" :maxlength="120" :error="form.errors.title" required />
            <div class="grid grid-cols-2 gap-3"><SelectField v-model="form.category" :label="t('dreams.fieldCategory')" :options="categoryOptions" /><SelectField v-model="form.status" :label="t('dreams.fieldStatus')" :options="statusOptions" /></div>
            <DateField v-model="form.target_date" :label="t('dreams.fieldDate')" clearable />
            <label class="block"><span class="mb-1 block text-sm font-semibold text-plum">{{ t('dreams.fieldProgress') }}: {{ form.pct }}%</span><input v-model="form.pct" type="range" min="0" max="100" step="5" class="w-full accent-[#b0446c]" /></label>
            <TextArea v-model="form.note" :label="t('dreams.fieldNote')" :maxlength="1000" :error="form.errors.note" />
        </form>
        <template #footer>
            <Button v-if="editing" variant="danger-ghost" class="sm:mr-auto" @click="removing = editing"><Trash2 class="size-4" aria-hidden="true" />{{ t('common.delete') }}</Button>
            <Button variant="secondary" @click="open = false">{{ t('common.cancel') }}</Button>
            <Button type="submit" form="dr-form" :loading="form.processing">{{ t('common.save') }}</Button>
        </template>
    </Dialog>
    <ConfirmDialog :open="!!removing" :title="t('dreams.deleteTitle')" :description="removing?.title" :confirm-label="t('common.delete')" danger @update:open="(v) => !v && (removing = null)" @confirm="remove" />
</template>
