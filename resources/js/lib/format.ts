export function formatDate(iso: string | null | undefined): string {
    if (!iso) {
        return '';
    }

    return new Date(iso).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

export function timeAgo(iso: string): string {
    const seconds = Math.round((Date.now() - new Date(iso).getTime()) / 1000);
    const units: [number, Intl.RelativeTimeFormatUnit][] = [
        [60, 'second'],
        [60, 'minute'],
        [24, 'hour'],
        [7, 'day'],
        [4.35, 'week'],
        [12, 'month'],
        [Infinity, 'year'],
    ];
    let value = seconds;

    for (const [size, unit] of units) {
        if (Math.abs(value) < size) {
            return new Intl.RelativeTimeFormat(undefined, {
                numeric: 'auto',
            }).format(-Math.round(value), unit);
        }

        value /= size;
    }

    return '';
}

export function formatUsd(value: number | null | undefined): string {
    if (value === null || value === undefined) {
        return '';
    }

    return value < 0.01 && value > 0 ? '<$0.01' : `$${value.toFixed(2)}`;
}

export function hostOf(url: string): string {
    try {
        return new URL(url).host;
    } catch {
        return url;
    }
}

/**
 * Laravel's XSRF-TOKEN cookie, for fetch requests outside Inertia.
 */
export function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
}
