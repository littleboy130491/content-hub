import { useChat } from '@ai-sdk/react';
import { Head, Link, router } from '@inertiajs/react';
import { DefaultChatTransport } from 'ai';
import type { UIMessage } from 'ai';
import {
    AlertTriangle,
    ArrowUp,
    BookmarkPlus,
    Check,
    Globe,
    ImageIcon,
    Loader2,
    Paperclip,
    Square,
    Video,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';
import { ChatSidePanel } from '@/components/chat/side-panel';
import { OutputCard } from '@/components/content/output-card';
import { SafeMarkdown } from '@/components/content/safe-markdown';
import { ScrapeDialog } from '@/components/content/scrape-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { usePollWhile } from '@/hooks/use-poll-while';
import { useTeamSlug } from '@/hooks/use-team';
import { xsrfToken } from '@/lib/format';
import { cn } from '@/lib/utils';
import chats from '@/routes/chats';
import projects from '@/routes/projects';
import scrapes from '@/routes/scrapes';
import type {
    Chat,
    ChatMessage,
    ContextEntry,
    MediaFile,
    ModelKind,
    ModelOption,
    Output,
    PromptLayer,
    UsageSummary,
    WebScrape,
} from '@/types';

export type ChatPageProps = {
    chat: Chat;
    messages: ChatMessage[];
    outputs: Output[];
    context: ContextEntry[];
    attachments: (MediaFile & { described: boolean })[];
    scrapes: WebScrape[];
    effective: {
        models: Record<ModelKind, ModelOption>;
        promptLayers: PromptLayer[];
    };
    modelOptions: {
        text: ModelOption[];
        image: ModelOption[];
        video: ModelOption[];
    };
    projects: { id: number; name: string }[];
    usage: UsageSummary;
};

type Meta = { dbId?: number };
type Message = UIMessage<Meta>;

function toUiMessages(messages: ChatMessage[]): Message[] {
    return messages.map((message) => ({
        id: `db-${message.id}`,
        role: message.role,
        metadata: { dbId: message.id },
        parts: [{ type: 'text', text: message.content }],
    }));
}

export default function ChatShow(props: ChatPageProps) {
    const { chat, messages: stored, outputs } = props;
    const team = useTeamSlug();
    const [input, setInput] = useState('');
    const bottomRef = useRef<HTMLDivElement>(null);
    const fileRef = useRef<HTMLInputElement>(null);

    const { messages, sendMessage, status, setMessages, stop } =
        useChat<Message>({
            id: `chat-${chat.id}`,
            messages: toUiMessages(stored),
            transport: new DefaultChatTransport<Message>({
                api: chats.messages.store.url([team, chat.id]),
                credentials: 'same-origin',
                headers: () => ({
                    'X-XSRF-TOKEN': xsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                }),
                // The server keeps the history; send only the new text.
                prepareSendMessagesRequest: ({ messages: all }) => {
                    const last = all[all.length - 1];
                    const text = last.parts
                        .map((part) => (part.type === 'text' ? part.text : ''))
                        .join('');

                    return { body: { text } };
                },
            }),
            onError: (error) => {
                let message = error.message;

                try {
                    message = JSON.parse(message).message ?? message;
                } catch {
                    // Not JSON; show as is.
                }

                toast.error(message || 'The reply failed.');
                reloadAfterTurn();
            },
            onFinish: () => reloadAfterTurn(),
        });

    function reloadAfterTurn() {
        router.reload({
            only: [
                'chat',
                'messages',
                'outputs',
                'context',
                'attachments',
                'usage',
            ],
        });
    }

    // Stored messages are the source of truth once a turn has finished.
    useEffect(() => {
        if (status === 'ready' || status === 'error') {
            setMessages(toUiMessages(stored));
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [stored]);

    useEffect(() => {
        bottomRef.current?.scrollIntoView({ behavior: 'smooth', block: 'end' });
    }, [messages, outputs.length]);

    const running =
        outputs.some(
            (o) => o.status === 'pending' || o.status === 'processing',
        ) ||
        props.scrapes.some(
            (s) => s.status === 'pending' || s.status === 'processing',
        );

    usePollWhile(running, ['outputs', 'scrapes', 'usage']);

    const busy = status === 'submitted' || status === 'streaming';

    const outputsByMessage = useMemo(() => {
        const map = new Map<number, Output[]>();

        for (const output of outputs) {
            if (output.messageId) {
                map.set(output.messageId, [
                    ...(map.get(output.messageId) ?? []),
                    output,
                ]);
            }
        }

        return map;
    }, [outputs]);

    const storedById = useMemo(
        () => new Map(stored.map((message) => [message.id, message])),
        [stored],
    );

    function submit() {
        const text = input.trim();

        if (!text || busy) {
            return;
        }

        // /scrape <url> [what you need] — scraping only starts when the user asks.
        const scrape = text.match(/^\/scrape\s+(\S+)\s*([\s\S]*)$/i);

        if (scrape) {
            router.post(scrapes.store.url(team), {
                url: scrape[1],
                instruction: scrape[2] || null,
                chat_id: chat.id,
            });
            setInput('');

            return;
        }

        void sendMessage({ text });
        setInput('');
    }

    function upload(files: FileList | null) {
        if (!files?.length) {
            return;
        }

        router.post(
            chats.attachments.store.url([team, chat.id]),
            { files: Array.from(files) },
            {
                forceFormData: true,
                preserveScroll: true,
                only: ['attachments', 'context'],
                onError: (errors) =>
                    toast.error(Object.values(errors)[0] ?? 'Upload failed.'),
            },
        );

        if (fileRef.current) {
            fileRef.current.value = '';
        }
    }

    return (
        <>
            <Head title={chat.title} />
            <div className="grid min-h-0 flex-1 gap-4 p-4 lg:grid-cols-[minmax(0,1fr)_380px] lg:p-6">
                <section className="flex min-h-[70vh] min-w-0 flex-col rounded-xl border">
                    <header className="flex items-center justify-between gap-3 border-b px-4 py-3">
                        <div className="min-w-0">
                            <h1 className="truncate font-semibold">
                                {chat.title}
                            </h1>
                            <p className="text-xs text-muted-foreground">
                                {chat.project ? (
                                    <>
                                        Project chat ·{' '}
                                        <Link
                                            href={projects.show([
                                                team,
                                                chat.project.id,
                                            ])}
                                            className="hover:underline"
                                        >
                                            {chat.project.name}
                                        </Link>
                                    </>
                                ) : (
                                    'Free chat · starts with zero context'
                                )}
                                {' · '}
                                {props.effective.models.text.name}
                            </p>
                        </div>
                        {chat.archived && (
                            <Badge variant="outline">Archived</Badge>
                        )}
                    </header>

                    <div className="flex-1 space-y-6 overflow-y-auto p-4">
                        {messages.length === 0 && (
                            <div className="mx-auto max-w-md space-y-2 py-16 text-center text-sm text-muted-foreground">
                                <p className="text-base font-medium text-foreground">
                                    What do you want to create?
                                </p>
                                <p>
                                    Ask for a post, a script, an image or a
                                    short video. The agent picks the workflow.
                                    Type{' '}
                                    <code className="rounded bg-muted px-1">
                                        /scrape https://…
                                    </code>{' '}
                                    to pull in a web page.
                                </p>
                            </div>
                        )}

                        {messages.map((message) => {
                            const dbId = message.metadata?.dbId;
                            const record = dbId
                                ? storedById.get(dbId)
                                : undefined;

                            return (
                                <MessageBubble
                                    key={message.id}
                                    message={message}
                                    record={record}
                                    outputs={
                                        dbId
                                            ? (outputsByMessage.get(dbId) ?? [])
                                            : []
                                    }
                                    onSave={() =>
                                        record &&
                                        router.post(
                                            chats.messages.output.url([
                                                team,
                                                chat.id,
                                                record.id,
                                            ]),
                                            {},
                                            {
                                                preserveScroll: true,
                                                only: ['messages', 'outputs'],
                                            },
                                        )
                                    }
                                />
                            );
                        })}

                        {status === 'submitted' && (
                            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                <Loader2 className="size-4 animate-spin" />{' '}
                                Preparing context and thinking…
                            </div>
                        )}
                        <div ref={bottomRef} />
                    </div>

                    <footer className="border-t p-3">
                        <div className="rounded-xl border bg-background focus-within:ring-2 focus-within:ring-ring/30">
                            <Textarea
                                value={input}
                                onChange={(event) =>
                                    setInput(event.target.value)
                                }
                                onKeyDown={(event) => {
                                    if (
                                        event.key === 'Enter' &&
                                        !event.shiftKey
                                    ) {
                                        event.preventDefault();
                                        submit();
                                    }
                                }}
                                rows={3}
                                placeholder="Describe what you need, or /scrape https://example.com"
                                className="resize-none border-0 shadow-none focus-visible:ring-0"
                            />
                            <div className="flex items-center justify-between gap-2 px-2 pb-2">
                                <div className="flex items-center gap-1">
                                    <input
                                        ref={fileRef}
                                        type="file"
                                        multiple
                                        className="hidden"
                                        accept="image/*,video/mp4,video/quicktime,video/webm,text/plain,text/markdown,text/csv,application/json"
                                        onChange={(event) =>
                                            upload(event.target.files)
                                        }
                                    />
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => fileRef.current?.click()}
                                        title="Attach files to this chat only"
                                    >
                                        <Paperclip /> Attach
                                    </Button>
                                    <ScrapeDialog chatId={chat.id}>
                                        <Button variant="ghost" size="sm">
                                            <Globe /> Scrape
                                        </Button>
                                    </ScrapeDialog>
                                </div>
                                {busy ? (
                                    <Button
                                        size="sm"
                                        variant="secondary"
                                        onClick={() => void stop()}
                                    >
                                        <Square /> Stop
                                    </Button>
                                ) : (
                                    <Button
                                        size="sm"
                                        onClick={submit}
                                        disabled={!input.trim()}
                                    >
                                        <ArrowUp /> Send
                                    </Button>
                                )}
                            </div>
                        </div>
                    </footer>
                </section>

                <ChatSidePanel {...props} />
            </div>
        </>
    );
}

function MessageBubble({
    message,
    record,
    outputs,
    onSave,
}: {
    message: Message;
    record?: ChatMessage;
    outputs: Output[];
    onSave: () => void;
}) {
    const isUser = message.role === 'user';
    const text = message.parts
        .map((part) => (part.type === 'text' ? part.text : ''))
        .join('');

    const tools = message.parts.filter((part) => part.type.startsWith('tool-'));

    return (
        <div
            className={cn(
                'flex flex-col gap-2',
                isUser ? 'items-end' : 'items-start',
            )}
        >
            <div
                className={cn(
                    'max-w-[85%] rounded-2xl px-4 py-3 text-sm',
                    isUser
                        ? 'bg-primary text-primary-foreground'
                        : 'bg-muted/50',
                    record?.failed &&
                        'border border-destructive/50 bg-destructive/5',
                )}
            >
                {isUser ? (
                    <p className="whitespace-pre-wrap">{text}</p>
                ) : text ? (
                    <SafeMarkdown>{text}</SafeMarkdown>
                ) : (
                    <span className="text-muted-foreground">…</span>
                )}
            </div>

            {tools.map((part) => {
                const state = 'state' in part ? String(part.state) : '';
                const done = state === 'output-available';
                const isImage = part.type === 'tool-generate_image';
                const Icon = isImage ? ImageIcon : Video;

                return (
                    <div
                        key={
                            'toolCallId' in part
                                ? String(part.toolCallId)
                                : part.type
                        }
                        className="flex items-center gap-2 rounded-lg border px-3 py-2 text-xs text-muted-foreground"
                    >
                        <Icon className="size-4" />
                        {done ? (
                            <Check className="size-3.5" />
                        ) : (
                            <Loader2 className="size-3.5 animate-spin" />
                        )}
                        {isImage
                            ? done
                                ? 'Image generated'
                                : 'Generating image…'
                            : done
                              ? 'Video job submitted'
                              : 'Starting video…'}
                    </div>
                );
            })}

            {record?.notes && record.notes.length > 0 && (
                <div className="max-w-[85%] space-y-1">
                    {record.notes.map((note) => (
                        <p
                            key={note}
                            className="flex items-start gap-1.5 text-xs text-muted-foreground"
                        >
                            <AlertTriangle className="mt-0.5 size-3 shrink-0" />{' '}
                            {note}
                        </p>
                    ))}
                </div>
            )}

            {outputs.length > 0 && (
                <div className="grid w-full max-w-[85%] gap-3 sm:grid-cols-2">
                    {outputs.map((output) => (
                        <OutputCard key={output.id} output={output} compact />
                    ))}
                </div>
            )}

            {!isUser && record && record.content && !record.failed && (
                <div className="flex items-center gap-2 text-xs text-muted-foreground">
                    {record.model && <span>{record.model}</span>}
                    {record.savedOutputId ? (
                        <span className="flex items-center gap-1">
                            <Check className="size-3" /> Saved as output
                        </span>
                    ) : (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-7 text-xs"
                            onClick={onSave}
                        >
                            <BookmarkPlus /> Save as output
                        </Button>
                    )}
                </div>
            )}
        </div>
    );
}
