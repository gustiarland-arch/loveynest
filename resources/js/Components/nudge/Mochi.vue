<script setup>
// A round little character: the two "mochi" of the couple. The face and arms are drawn per feeling.
defineProps({
    tone: { type: String, default: 'pink' }, // pink | lilac
    face: { type: String, default: 'smile' }, // smile | kiss | sleepy | cheer
    arms: { type: String, default: 'down' }, // down | hug | hold | up
    flip: { type: Boolean, default: false },
});

const palette = {
    pink: { body: '#ffb3cb', shade: '#f48fb1', cheek: '#ff6f9c' },
    lilac: { body: '#cdbdff', shade: '#a995f0', cheek: '#ff8fb8' },
};
</script>

<template>
    <svg viewBox="0 0 100 96" class="mochi block overflow-visible" :style="{ transform: flip ? 'scaleX(-1)' : undefined }" aria-hidden="true">
        <ellipse cx="50" cy="92" rx="30" ry="4" fill="#000" opacity=".1" />
        <template v-if="arms === 'down'">
            <ellipse cx="9" cy="62" rx="8" ry="12" :fill="palette[tone].shade" />
            <ellipse cx="91" cy="62" rx="8" ry="12" :fill="palette[tone].shade" />
        </template>
        <ellipse cx="36" cy="88" rx="9" ry="6" :fill="palette[tone].shade" />
        <ellipse cx="64" cy="88" rx="9" ry="6" :fill="palette[tone].shade" />
        <path d="M8 58C8 24 28 6 50 6s42 18 42 52c0 22-16 32-42 32S8 80 8 58Z" :fill="palette[tone].body" />
        <path d="M22 30c6-10 16-15 26-16" fill="none" stroke="#fff" stroke-opacity=".6" stroke-width="5" stroke-linecap="round" />
        <ellipse cx="22" cy="62" rx="8" ry="5" :fill="palette[tone].cheek" class="cheek" opacity=".55" />
        <ellipse cx="78" cy="62" rx="8" ry="5" :fill="palette[tone].cheek" class="cheek" opacity=".55" />

        <g v-if="face !== 'sleepy'" class="eyes">
            <template v-if="face === 'kiss'">
                <path d="M28 52q6-8 12 0M60 52q6-8 12 0" fill="none" stroke="#3d2336" stroke-width="3.4" stroke-linecap="round" />
            </template>
            <template v-else>
                <ellipse cx="34" cy="52" rx="5" ry="6.2" fill="#3d2336" /><circle cx="35.8" cy="49.6" r="1.8" fill="#fff" />
                <ellipse cx="66" cy="52" rx="5" ry="6.2" fill="#3d2336" /><circle cx="67.8" cy="49.6" r="1.8" fill="#fff" />
            </template>
        </g>
        <g v-else>
            <path d="M28 54q6 6 12 0M60 54q6 6 12 0" fill="none" stroke="#3d2336" stroke-width="3.4" stroke-linecap="round" />
            <path d="M74 60c2 4 4 6 4 9a4 4 0 1 1-8 0c0-3 2-5 4-9Z" fill="#8fd3ff" class="tear" />
        </g>

        <!-- Arms that are in front of the body: reaching out to hug, holding something, or cheering. -->
        <template v-if="arms === 'hug'">
            <ellipse cx="94" cy="70" rx="17" ry="8" transform="rotate(-12 94 70)" :fill="palette[tone].shade" />
            <ellipse cx="14" cy="76" rx="11" ry="7" transform="rotate(20 14 76)" :fill="palette[tone].shade" />
        </template>
        <template v-else-if="arms === 'hold'">
            <ellipse cx="30" cy="74" rx="15" ry="7.5" transform="rotate(24 30 74)" :fill="palette[tone].shade" />
            <ellipse cx="70" cy="74" rx="15" ry="7.5" transform="rotate(-24 70 74)" :fill="palette[tone].shade" />
        </template>
        <template v-else-if="arms === 'up'">
            <ellipse cx="6" cy="34" rx="7" ry="16" transform="rotate(-28 6 34)" :fill="palette[tone].shade" />
            <ellipse cx="94" cy="34" rx="7" ry="16" transform="rotate(28 94 34)" :fill="palette[tone].shade" />
        </template>

        <path v-if="face === 'smile'" d="M42 66q8 9 16 0" fill="none" stroke="#3d2336" stroke-width="3.2" stroke-linecap="round" />
        <path v-else-if="face === 'cheer'" d="M40 63h20c0 9-4 14-10 14s-10-5-10-14Z" fill="#3d2336" />
        <ellipse v-else-if="face === 'kiss'" cx="50" cy="68" rx="4.2" ry="5" fill="#ff4f86" class="pucker" />
        <path v-else d="M44 70q6-4 12 0" fill="none" stroke="#3d2336" stroke-width="3" stroke-linecap="round" />
    </svg>
</template>

<style scoped>
.eyes { transform-box: fill-box; transform-origin: center; animation: blink 4.2s infinite; }
@keyframes blink { 0%, 93%, 100% { transform: scaleY(1); } 96% { transform: scaleY(0.1); } }
.pucker { transform-box: fill-box; transform-origin: center; animation: pucker 1.2s ease-in-out infinite; }
@keyframes pucker { 50% { transform: scale(1.25); } }
.tear { animation: tear 2.4s ease-in infinite; }
@keyframes tear { 0% { opacity: 0; transform: translateY(-3px); } 30% { opacity: 1; } 100% { opacity: 0; transform: translateY(10px); } }
@media (prefers-reduced-motion: reduce) {
    .eyes, .pucker, .tear { animation: none; }
}
</style>
