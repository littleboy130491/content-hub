import { Link, router } from '@inertiajs/react';
import {
    BookmarkPlus,
    Download,
    FileText,
    Play,
    RotateCcw,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { SafeMarkdown } from '@/components/content/safe-markdown';
import { StatusBadge } from '@/components/content/status-badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import { useTeamSlug } from '@/hooks/use-team';
import { formatUsd, timeAgo } from '@/lib/format';
import chats from '@/routes/chats';
import context from '@/routes/context';
import outputs from '@/routes/outputs';
import type { Output } from '@/types';

export function OutputCard({
    output,
    compact = false,
    showChat = false,
}: {
    output: Output;
    compact?: boolean;
    showChat?: boolean;
}) {
    const team = useTeamSlug();
    const [prompt, setPrompt] = useState(output.prompt ?? '');
    const done = output.status === 'completed';

    const post = (url: string, data: Record<string, string> = {}) =>
        router.post(url, data, { preserveScroll: true });

    return (
        <Card className="gap-3 overflow-hidden py-4">
            <CardHeader className="flex flex-row items-start justify-between gap-2 px-4">
                <div className="min-w-0 space-y-1">
                    <CardTitle className="truncate text-sm">
                        {output.title}
                    </CardTitle>
                    <p className="truncate text-xs text-muted-foreground">
                        {output.type} · {output.model ?? 'no model'}
                        {output.cost !== null && ` · ${formatUsd(output.cost)}`}
                        {' · '}
                        {timeAgo(output.createdAt)}
                    </p>
                </div>
                <StatusBadge status={output.status} />
            </CardHeader>

            <CardContent className="px-4">
                {output.type === 'image' && output.file && (
                    <a href={output.file.url} target="_blank" rel="noreferrer">
                        <img
                            src={output.file.url}
                            alt={output.title}
                            className="w-full rounded-md border bg-muted object-contain"
                            loading="lazy"
                        />
                    </a>
                )}

                {output.type === 'video' && output.file && (
                    <video
                        src={output.file.url}
                        controls
                        preload="metadata"
                        className="w-full rounded-md border bg-black"
                    />
                )}

                {output.type === 'text' && output.content && (
                    <div
                        className={
                            compact
                                ? 'max-h-40 overflow-hidden'
                                : 'max-h-96 overflow-auto'
                        }
                    >
                        <SafeMarkdown>{output.content}</SafeMarkdown>
                    </div>
                )}

                {output.type === 'video' &&
                    !done &&
                    output.status !== 'failed' && (
                        <div className="space-y-2 text-sm text-muted-foreground">
                            {output.status === 'awaiting_confirmation' ? (
                                <>
                                    <p>
                                        This chat contains untrusted web
                                        content, so the video waits for you.
                                        Review the prompt, then start it.
                                    </p>
                                    <Textarea
                                        value={prompt}
                                        onChange={(event) =>
                                            setPrompt(event.target.value)
                                        }
                                        rows={4}
                                    />
                                </>
                            ) : (
                                <p>
                                    Generating in the background. This page
                                    updates when it is ready.
                                </p>
                            )}
                        </div>
                    )}

                {output.status === 'failed' && output.error && (
                    <p className="text-sm text-destructive">{output.error}</p>
                )}
            </CardContent>

            <CardFooter className="flex flex-wrap gap-2 px-4">
                {output.status === 'awaiting_confirmation' && (
                    <Button
                        size="sm"
                        onClick={() =>
                            post(outputs.start.url([team, output.id]), {
                                prompt,
                            })
                        }
                    >
                        <Play /> Start video
                    </Button>
                )}
                {output.status === 'failed' && output.type === 'video' && (
                    <Button
                        size="sm"
                        variant="outline"
                        onClick={() =>
                            post(outputs.retry.url([team, output.id]))
                        }
                    >
                        <RotateCcw /> Retry
                    </Button>
                )}
                {done &&
                    (output.contextItemId ? (
                        <Button size="sm" variant="outline" asChild>
                            <Link
                                href={context.show([
                                    team,
                                    output.contextItemId,
                                ])}
                            >
                                <FileText /> In context
                            </Link>
                        </Button>
                    ) : (
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() =>
                                post(outputs.context.url([team, output.id]))
                            }
                        >
                            <BookmarkPlus /> Add to context
                        </Button>
                    ))}
                {done && output.file && (
                    <Button size="sm" variant="ghost" asChild>
                        <a href={output.file.url} download={output.file.name}>
                            <Download /> Download
                        </a>
                    </Button>
                )}
                {showChat && output.chatId && (
                    <Button size="sm" variant="ghost" asChild>
                        <Link href={chats.show([team, output.chatId])}>
                            {output.chatTitle ?? 'Open chat'}
                        </Link>
                    </Button>
                )}
                <Button
                    size="sm"
                    variant="ghost"
                    className="ml-auto text-muted-foreground"
                    onClick={() =>
                        router.delete(outputs.destroy.url([team, output.id]), {
                            preserveScroll: true,
                            onBefore: () =>
                                window.confirm('Delete this output?'),
                        })
                    }
                >
                    <Trash2 />
                    <span className="sr-only">Delete</span>
                </Button>
            </CardFooter>
        </Card>
    );
}
