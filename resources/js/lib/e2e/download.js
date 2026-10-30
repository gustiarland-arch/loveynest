import { TOKEN_PATTERN } from './core';
import { e2e, unsealString } from './index';

/** Spreadsheet apps run a cell that starts with these as a formula. */
const FORMULA = /^[=+\-@\t\r]/;

/**
 * Downloads an export (CSV or JSON) after opening the locked text in it on this device,
 * so the file you keep is readable while the server only ever produced locked text.
 */
export async function downloadUnsealed(url, format) {
    const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: format === 'csv' ? 'text/csv' : 'application/json' } });
    if (!response.ok) throw new Error(response.status === 429 ? 'Terlalu sering. Coba lagi nanti.' : 'Unduhan gagal.');
    let text = await response.text();
    const name = /filename="?([^";]+)"?/.exec(response.headers.get('content-disposition') ?? '')?.[1] ?? (format === 'csv' ? 'ekspor.csv' : 'ekspor.json');

    if (e2e.enabled && text.includes('e2e1:')) {
        const tokens = [...new Set(text.match(TOKEN_PATTERN) ?? [])];
        const opened = new Map(await Promise.all(tokens.map(async (t) => [t, await unsealString(t)])));
        text = text.replace(TOKEN_PATTERN, (t) => {
            const plain = opened.get(t) ?? t;
            if (format === 'json') return JSON.stringify(plain).slice(1, -1);
            // A locked token is a whole CSV field without quotes; the opened text may need them.
            return `"${(FORMULA.test(plain) ? `'${plain}` : plain).replace(/"/g, '""')}"`;
        });
    }

    const link = document.createElement('a');
    link.href = URL.createObjectURL(new Blob([text], { type: format === 'csv' ? 'text/csv;charset=utf-8' : 'application/json;charset=utf-8' }));
    link.download = name;
    document.body.append(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(link.href), 10000);
}
