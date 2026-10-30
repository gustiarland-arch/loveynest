import { usePage } from '@inertiajs/vue3';

const fallbackZone = 'Asia/Jakarta';

export function userTimezone() {
    try {
        return usePage().props.auth?.user?.timezone ?? fallbackZone;
    } catch {
        return fallbackZone;
    }
}

/** Formats an ISO timestamp in the signed-in user's time zone. */
export function formatDate(iso, options = { dateStyle: 'medium' }, zone = userTimezone()) {
    if (!iso) return '';
    return new Intl.DateTimeFormat('id-ID', { timeZone: zone, ...options }).format(new Date(iso));
}

export function formatDateTime(iso, zone = userTimezone()) {
    return formatDate(iso, { dateStyle: 'medium', timeStyle: 'short' }, zone);
}

/** Hour of day (0–23) right now in the given zone. */
export function currentHour(zone = userTimezone()) {
    return Number(new Intl.DateTimeFormat('en-GB', { timeZone: zone, hour: '2-digit', hourCycle: 'h23' }).format(new Date()));
}

export function browserTimezone() {
    try {
        return Intl.DateTimeFormat().resolvedOptions().timeZone || null;
    } catch {
        return null;
    }
}

/** 'YYYY-MM-DD' of an instant in a zone. */
export function dateKey(iso, zone = userTimezone()) {
    return new Intl.DateTimeFormat('en-CA', { timeZone: zone, year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date(iso));
}

export function todayKey(zone = userTimezone()) {
    return dateKey(new Date().toISOString(), zone);
}

/** Adds days to a 'YYYY-MM-DD' key using calendar arithmetic (no zone involved). */
export function addDaysToKey(key, days) {
    const [y, m, d] = key.split('-').map(Number);
    const date = new Date(Date.UTC(y, m - 1, d + days));
    return date.toISOString().slice(0, 10);
}

/** Human label for a date key: "Hari ini", "Besok", "Kemarin", or "Sen, 3 Nov". */
export function dayLabel(key, t, zone = userTimezone()) {
    const today = todayKey(zone);
    if (key === today) return t('dates.today');
    if (key === addDaysToKey(today, 1)) return t('dates.tomorrow');
    if (key === addDaysToKey(today, -1)) return t('dates.yesterday');
    const [y, m, d] = key.split('-').map(Number);
    const sameYear = y === Number(today.slice(0, 4));
    return new Intl.DateTimeFormat('id-ID', {
        timeZone: 'UTC',
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        ...(sameYear ? {} : { year: 'numeric' }),
    }).format(new Date(Date.UTC(y, m - 1, d)));
}

export function timeLabel(iso, zone = userTimezone()) {
    return new Intl.DateTimeFormat('id-ID', { timeZone: zone, hour: '2-digit', minute: '2-digit' }).format(new Date(iso));
}

/** Due label for a task resource. */
export function dueLabel(task, t) {
    if (!task.due_date) return null;
    const day = dayLabel(task.due_date, t);
    return task.due_has_time ? `${day}, ${task.due_time}` : day;
}

/** Event time label in the viewer's zone ("Sepanjang hari" for all-day). */
export function eventTimeLabel(event, t) {
    if (event.all_day) return t('calendar.allDay');
    const start = timeLabel(event.starts_at);
    return event.ends_at ? `${start} – ${timeLabel(event.ends_at)}` : start;
}

/** Day key an event should be filed under in the grid. */
export function eventDayKey(event) {
    return event.all_day ? event.start_date : dateKey(event.starts_at);
}

/** All day keys an event covers (multi-day events appear on each day, capped at 31). */
export function eventDayKeys(event) {
    const first = eventDayKey(event);
    const last = event.ends_at ? (event.all_day ? event.end_date : dateKey(event.ends_at)) : first;
    const keys = [first];
    let key = first;
    while (key < last && keys.length < 31) {
        key = addDaysToKey(key, 1);
        keys.push(key);
    }
    return keys;
}
