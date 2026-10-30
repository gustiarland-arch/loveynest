import id from './id';

const catalogs = { id };
let current = 'id';

function lookup(catalog, key) {
    return key.split('.').reduce((node, part) => (node == null ? undefined : node[part]), catalog);
}

/**
 * Translate a dotted key, replacing `:name` placeholders.
 * Unknown keys return the key itself so a missing string is visible, not blank.
 */
export function t(key, params = {}) {
    const value = lookup(catalogs[current], key) ?? lookup(catalogs.id, key) ?? key;
    if (typeof value !== 'string') return key;
    return value.replace(/:(\w+)/g, (match, name) => (name in params ? String(params[name]) : match));
}

export function setLocale(locale) {
    if (catalogs[locale]) current = locale;
}

export const i18n = {
    install(app) {
        app.config.globalProperties.$t = t;
    },
};
