import { usePoll } from '@inertiajs/react';
import { useEffect } from 'react';

/**
 * Reload the given props every few seconds, but only while something is
 * running in the background (a video, a crawl).
 */
export function usePollWhile(
    active: boolean,
    only: string[],
    interval = 5000,
): void {
    const { start, stop } = usePoll(interval, { only }, { autoStart: false });

    useEffect(() => {
        if (active) {
            start();
        } else {
            stop();
        }

        return stop;
    }, [active, start, stop]);
}
