import { CloudRain, Cloud, CloudSun, Sun, Rainbow } from '@lucide/vue';

// Weather as feeling, to match the clouds of the brand. Colours come from theme tokens.
export const moods = [
    { level: 1, icon: CloudRain, tone: 'bg-lavender text-lavender-ink', heat: 'bg-lavender-ink/45' },
    { level: 2, icon: Cloud, tone: 'bg-mist text-mist-ink', heat: 'bg-mist-ink/40' },
    { level: 3, icon: CloudSun, tone: 'bg-peach text-peach-ink', heat: 'bg-peach-ink/35' },
    { level: 4, icon: Sun, tone: 'bg-warning-soft text-warning', heat: 'bg-rose/70' },
    { level: 5, icon: Rainbow, tone: 'bg-sage text-sage-ink', heat: 'bg-berry' },
];

export const moodFor = (level) => moods.find((m) => m.level === level) ?? null;
