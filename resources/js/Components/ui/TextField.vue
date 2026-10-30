<script setup>
import { computed, ref, useId } from 'vue';
import { Eye, EyeOff } from '@lucide/vue';

defineOptions({ inheritAttrs: false });

const model = defineModel({ type: [String, Number], default: '' });

const props = defineProps({
    label: { type: String, required: true },
    type: { type: String, default: 'text' },
    error: { type: String, default: null },
    hint: { type: String, default: null },
    autocomplete: { type: String, default: null },
    required: { type: Boolean, default: false },
    maxlength: { type: Number, default: null },
    inputmode: { type: String, default: null },
    icon: { type: [Object, Function], default: null },
});

const id = useId();
const reveal = ref(false);
const inputType = computed(() => (props.type === 'password' && reveal.value ? 'text' : props.type));
</script>

<template>
    <div class="space-y-1.5">
        <label :for="id" class="block text-sm font-semibold text-plum">{{ label }}</label>
        <div class="relative">
            <component :is="icon" v-if="icon" class="pointer-events-none absolute left-3.5 top-1/2 size-[1.125rem] -translate-y-1/2 text-ink-muted" aria-hidden="true" />
            <input
                :id="id"
                v-model="model"
                :type="inputType"
                :autocomplete="autocomplete"
                :required="required"
                :maxlength="maxlength"
                :inputmode="inputmode"
                :aria-invalid="error ? 'true' : undefined"
                :aria-describedby="error || hint ? `${id}-desc` : undefined"
                class="block h-12 w-full rounded-[0.875rem] border bg-surface/90 px-4 text-base text-plum placeholder:text-ink-muted/70 transition focus:border-rose-ink focus:outline-none focus:ring-4 focus:ring-blush"
                :class="[error ? 'border-danger' : 'border-line', icon && 'pl-11', type === 'password' && 'pr-12']"
                v-bind="$attrs"
            />
            <button
                v-if="type === 'password'"
                type="button"
                class="absolute right-1.5 top-1/2 grid size-9 -translate-y-1/2 place-items-center rounded-full text-ink-muted hover:bg-blush-soft hover:text-plum"
                :aria-label="reveal ? $t('common.hidePassword') : $t('common.showPassword')"
                :aria-pressed="reveal ? 'true' : 'false'"
                @click="reveal = !reveal"
            >
                <EyeOff v-if="reveal" class="size-[1.125rem]" aria-hidden="true" />
                <Eye v-else class="size-[1.125rem]" aria-hidden="true" />
            </button>
        </div>
        <p v-if="error" :id="`${id}-desc`" class="text-xs font-medium text-danger">{{ error }}</p>
        <p v-else-if="hint" :id="`${id}-desc`" class="text-xs text-ink-muted">{{ hint }}</p>
    </div>
</template>
