import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

export function PageHeader({
    title,
    description,
    actions,
}: {
    title: string;
    description?: string;
    actions?: ReactNode;
}) {
    return (
        <div className="flex flex-wrap items-start justify-between gap-4">
            <div className="space-y-1">
                <h1 className="text-xl font-semibold tracking-tight">
                    {title}
                </h1>
                {description && (
                    <p className="text-sm text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
            {actions && (
                <div className="flex flex-wrap items-center gap-2">
                    {actions}
                </div>
            )}
        </div>
    );
}

export function EmptyState({
    title,
    description,
    action,
}: {
    title: string;
    description?: string;
    action?: ReactNode;
}) {
    return (
        <div className="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed p-10 text-center">
            <p className="font-medium">{title}</p>
            {description && (
                <p className="max-w-md text-sm text-muted-foreground">
                    {description}
                </p>
            )}
            {action && <div className="mt-2">{action}</div>}
        </div>
    );
}

export function Pagination({
    links,
}: {
    links: { prev: string | null; next: string | null };
}) {
    if (!links.prev && !links.next) {
        return null;
    }

    return (
        <div className="flex justify-between text-sm">
            {links.prev ? (
                <Link
                    href={links.prev}
                    className="underline-offset-4 hover:underline"
                >
                    Previous
                </Link>
            ) : (
                <span />
            )}
            {links.next && (
                <Link
                    href={links.next}
                    className="underline-offset-4 hover:underline"
                >
                    Next
                </Link>
            )}
        </div>
    );
}
