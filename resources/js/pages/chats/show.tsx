import { useChat } from '@ai-sdk/react';
import { Head, Link, router } from '@inertiajs/react';
import { DefaultChatTransport } from 'ai';
import type { FileUIPart, UIMessage } from 'ai';
import {
    AlertTriangle,
    BookmarkPlus,
    Check,
    ImageIcon,
    Loader2,
    PanelRightClose,
    PanelRightOpen,
    Video,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';
import { AttachmentChip, Composer } from '@/components/chat/composer';
import { ChatSidePanel } from '@/components/chat/side-panel';
import { OutputCard } from '@/components/content/output-card';
import { SafeMarkdown } from '@/components/content/safe-markdown';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
    LibraryOption,
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
    drafts: MediaFile[];
    library: LibraryOption[];
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

const PANEL_KEY = 'content-hub.chat-panel-open';

function toUiMessages(messages: ChatMessage[]): Message[] {
    return messages.map((message) => ({
        id: `db-${message.id}`,
        role: message.role,
        metadata: { dbId: message.id },
        parts: [{ type: 'text', text: message.content }],
    }));
}

function readPanelOpen(): boolean {
    try {
        return localStorage.getItem(PANEL_KEY) !== 'false';
    } catch {
        return true;
    }
}

export default function ChatShow(props: ChatPageProps) {
    const { chat, messages: stored, outputs } = props;
    const team = useTeamSlug();
    const bottomRef = useRef<HTMLDivElement>(null);
    const [panelOpen, setPanelOpen] = useState(readPanelOpen);

    const togglePanel = () => {
        setPanelOpen((open) => {
            try {
                localStorage.setItem(PANEL_KEY, String(!open));
            } catch {
                // Storage can be unavailable; the panel still toggles.
            }

            return !open;
        });
    };

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
                // The server keeps the history; send only the new text and the
                // IDs of the files attached to it.
                prepareSendMessagesRequest: ({ messages: all, body }) => {
                    const last = all[all.length - 1];
                    const text = last.parts
                        .map((part) => (part.type === 'text' ? part.text : ''))
                        .join('');

                    return {
                        body: {
                            text,
                            attachment_ids: body?.attachment_ids ?? [],
                        },
                    };
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
            only: ['chat', 'messages', 'outputs', 'context', 'drafts', 'usage'],
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

    usePollWhile(running, ['outputs', 'scrapes', 'usage', 'context']);

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

    function send(text: string, attachments: MediaFile[]) {
        // Shown on the message right away; the server stores the real link.
        const files: FileUIPart[] = attachments.map((file) => ({
            type: 'file',
            mediaType: file.mime ?? 'application/octet-stream',
            url: file.url,
            filename: file.name,
        }));

        const options = {
            body: { attachment_ids: attachments.map((file) => file.id) },
        };

        void (text
            ? sendMessage({ text, files }, options)
            : sendMessage({ files }, options));
    }

    function scrape(url: string, instruction: string | null) {
        router.post(scrapes.store.url(team), {
            url,
            instruction,
            chat_id: chat.id,
        });
    }

    return (
        <>
            <Head title={chat.title} />
            <div
                className={cn(
                    'grid min-h-0 flex-1 gap-4 p-4 lg:p-6',
                    panelOpen && 'lg:grid-cols-[minmax(0,1fr)_380px]',
                )}
            >
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
                                    'Free chat'
                                )}
                                {' · '}
                                {props.effective.models.text.name}
                            </p>
                        </div>
                        <div className="flex shrink-0 items-center gap-2">
                            {chat.archived && (
                                <Badge variant="outline">Archived</Badge>
                            )}
                            <Button
                                variant="ghost"
                                size="icon"
                                onClick={togglePanel}
                                title={
                                    panelOpen
                                        ? 'Hide the side panel'
                                        : 'Show context and settings'
                                }
                                aria-label={
                                    panelOpen
                                        ? 'Hide the side panel'
                                        : 'Show context and settings'
                                }
                                aria-expanded={panelOpen}
                            >
                                {panelOpen ? (
                                    <PanelRightClose />
                                ) : (
                                    <PanelRightOpen />
                                )}
                            </Button>
                        </div>
                    </header>

                    <div className="flex-1 space-y-6 overflow-y-auto p-4">
                        {messages.length === 0 && (
                            <div className="mx-auto max-w-md space-y-2 py-16 text-center text-sm text-muted-foreground">
                                <p className="text-base font-medium text-foreground">
                                    What do you want to create?
                                </p>
                                <p>
                                    Ask for a post, a script, an image or a
                                    short video. Paste or drop files to attach
                                    them, or type{' '}
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
                                    onSaveFile={(mediaId) =>
                                        router.post(
                                            chats.attachments.library.url([
                                                team,
                                                chat.id,
                                                mediaId,
                                            ]),
                                            {},
                                            {
                                                preserveScroll: true,
                                                only: ['library'],
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
                        <Composer
                            chatId={chat.id}
                            drafts={props.drafts}
                            busy={busy}
                            onSend={send}
                            onStop={() => void stop()}
                            onScrape={scrape}
                        />
                    </footer>
                </section>

                {panelOpen && <ChatSidePanel {...props} />}
            </div>
        </>
    );
}

function MessageBubble({
    message,
    record,
    outputs,
    onSave,
    onSaveFile,
}: {
    message: Message;
    record?: ChatMessage;
    outputs: Output[];
    onSave: () => void;
    onSaveFile: (mediaId: number) => void;
}) {
    const isUser = message.role === 'user';
    const text = message.parts
        .map((part) => (part.type === 'text' ? part.text : ''))
        .join('');

    const tools = message.parts.filter((part) => part.type.startsWith('tool-'));

    // Stored attachments once saved; the optimistic file parts while sending.
    const files = record
        ? record.attachments.map((file) => ({
              id: file.id,
              name: file.name,
              mime: file.mime,
              url: file.url,
          }))
        : message.parts
              .filter((part): part is FileUIPart => part.type === 'file')
              .map((part) => ({
                  id: null,
                  name: part.filename ?? 'file',
                  mime: part.mediaType,
                  url: part.url,
              }));

    return (
        <div
            className={cn(
                'flex flex-col gap-2',
                isUser ? 'items-end' : 'items-start',
            )}
        >
            {files.length > 0 && (
                <div
                    className={cn(
                        'flex max-w-[85%] flex-wrap gap-2',
                        isUser && 'justify-end',
                    )}
                >
                    {files.map((file) => (
                        <AttachmentChip
                            key={file.url}
                            name={file.name}
                            mime={file.mime}
                            preview={
                                file.mime?.startsWith('image/')
                                    ? file.url
                                    : null
                            }
                            href={file.url}
                            onSave={
                                file.id ? () => onSaveFile(file.id) : undefined
                            }
                        />
                    ))}
                </div>
            )}

            {(text || !isUser) && (
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
            )}

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
