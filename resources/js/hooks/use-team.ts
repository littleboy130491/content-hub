import { usePage } from '@inertiajs/react';

/**
 * The current workspace's slug, for building workspace routes.
 */
export function useTeamSlug(): string {
    const { currentTeam } = usePage().props;

    return currentTeam?.slug ?? '';
}
