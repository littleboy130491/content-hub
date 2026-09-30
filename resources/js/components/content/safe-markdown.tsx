import { ExternalLink, ImageOff } from 'lucide-react';
import type { ComponentProps } from 'react';
import Markdown from 'react-markdown';
import rehypeSanitize from 'rehype-sanitize';
import remarkGfm from 'remark-gfm';
import { hostOf } from '@/lib/format';
import { cn } from '@/lib/utils';

/**
 * Renders model output and scraped pages without loading anything remote.
 *
 * A remote image in markdown would be fetched by the browser on render, which
 * is how injected text can leak context through a URL. So images are never
 * loaded: they become a labelled placeholder. Links show their domain and
 * open in a new tab without a referrer.
 */
function BlockedImage({ src, alt }: ComponentProps<'img'>) {
    const url = typeof src === 'string' ? src : '';

    return (
        <span className="not-prose my-2 inline-flex items-center gap-2 rounded-md border border-dashed px-2 py-1 text-xs text-muted-foreground">
            <ImageOff className="size-3.5" />
            <span>
                Image not loaded{alt ? `: ${alt}` : ''}
                {url && ` (${hostOf(url)})`}
            </span>
        </span>
    );
}

function SafeLink({ href, children }: ComponentProps<'a'>) {
    if (!href) {
        return <span>{children}</span>;
    }

    const external = /^https?:\/\//i.test(href);

    return (
        <a
            href={href}
            target="_blank"
            rel="noopener noreferrer nofollow"
            className="inline-flex items-baseline gap-1"
        >
            {children}
            {external && (
                <span className="text-xs text-muted-foreground no-underline">
                    ({hostOf(href)})
                    <ExternalLink className="ml-0.5 inline size-3" />
                </span>
            )}
        </a>
    );
}

export function SafeMarkdown({
    children,
    className,
}: {
    children: string;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'prose prose-sm max-w-none dark:prose-invert prose-pre:bg-muted prose-pre:text-foreground',
                className,
            )}
        >
            <Markdown
                remarkPlugins={[remarkGfm]}
                rehypePlugins={[rehypeSanitize]}
                components={{ img: BlockedImage, a: SafeLink }}
            >
                {children}
            </Markdown>
        </div>
    );
}
