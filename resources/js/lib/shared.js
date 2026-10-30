import { reactive } from 'vue';

/**
 * Photos shared into the Android app, waiting for the person to pick where they go (see ShareSheet).
 * `target` is set once they chose: the chat or an album page then takes the files.
 */
export const shared = reactive({ files: [], target: null });

export function receive(files) {
    shared.files = files.slice(0, 10);
    shared.target = null;
}

export function choose(target) {
    shared.target = target;
}

/** The waiting photos for this target (once), or an empty list. */
export function takeFor(target) {
    if (shared.target !== target || !shared.files.length) return [];
    const files = shared.files;
    shared.files = [];
    shared.target = null;
    return files;
}

export function cancel() {
    shared.files = [];
    shared.target = null;
}
