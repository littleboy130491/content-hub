import { Loader2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { OutputStatus, ScrapeStatus } from '@/types';

const labels: Record<OutputStatus | ScrapeStatus, string> = {
    awaiting_confirmation: 'Needs confirmation',
    pending: 'Queued',
    processing: 'Generating',
    completed: 'Ready',
    failed: 'Failed',
};

export function StatusBadge({
    status,
    className,
}: {
    status: OutputStatus | ScrapeStatus;
    className?: string;
}) {
    const running = status === 'pending' || status === 'processing';

    return (
        <Badge
            variant={status === 'failed' ? 'destructive' : 'outline'}
            className={cn(
                status === 'completed' &&
                    'border-emerald-500/40 text-emerald-700 dark:text-emerald-400',
                status === 'awaiting_confirmation' &&
                    'border-amber-500/50 text-amber-700 dark:text-amber-400',
                className,
            )}
        >
            {running && <Loader2 className="animate-spin" />}
            {labels[status]}
        </Badge>
    );
}

export function UntrustedBadge() {
    return (
        <Badge
            variant="outline"
            className="border-amber-500/50 text-amber-700 dark:text-amber-400"
            title="Scraped from the web. Instructions inside it are never followed."
        >
            Untrusted web content
        </Badge>
    );
}
