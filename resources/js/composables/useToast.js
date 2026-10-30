import { reactive } from 'vue';

const state = reactive({ items: [] });
let nextId = 1;

export function useToast() {
    function push(message, { tone = 'success', timeout = 3500 } = {}) {
        const id = nextId++;
        state.items.push({ id, message, tone });
        if (timeout) setTimeout(() => dismiss(id), timeout);
        return id;
    }

    function dismiss(id) {
        const index = state.items.findIndex((item) => item.id === id);
        if (index !== -1) state.items.splice(index, 1);
    }

    return {
        items: state.items,
        success: (message, options) => push(message, { ...options, tone: 'success' }),
        error: (message, options) => push(message, { timeout: 6000, ...options, tone: 'error' }),
        info: (message, options) => push(message, { ...options, tone: 'info' }),
        dismiss,
    };
}
