import { router } from '@inertiajs/react';
import {
    ArrowUp,
    BookmarkPlus,
    FileText,
    Film,
    Globe,
    Loader2,
    Paperclip,
    Square,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { ClipboardEvent, DragEvent } from 'react';
import { toast } from 'sonner';
import { ScrapeDialog } from '@/components/content/scrape-dialog';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { useTeamSlug } from '@/hooks/use-team';
import { cn } from '@/lib/utils';
import chats from '@/routes/chats';
import type { MediaFile } from '@/types';

/**
 * Pasted text longer than this becomes an attached text file, like in
 * ChatGPT and Claude, so the message itself stays readable.
 */
const PASTE_AS_FILE_CHARS = 2000;

const ACCEPT =
    'image/png,image/jpeg,image/webp,image/gif,video/mp4,video/quicktime,video/webm,text/plain,text/markdown,text/csv,application/json';

type Uploading = { key: string; name: string; preview: string | null };

export function Composer({
    chatId,
    drafts,
    busy,
    onSend,
    onStop,
    onScrape,
}: {
    chatId: number;
    drafts: MediaFile[];
    busy: boolean;
    onSend: (text: string, attachments: MediaFile[]) => void;
    onStop: () => void;
    onScrape: (url: string, instruction: string | null) => void;
}) {
    const team = useTeamSlug();
    const [input, setInput] = useState('');
    const [uploading, setUploading] = useState<Uploading[]>([]);
    const [sent, setSent] = useState<number[]>([]);
    const [dragging, setDragging] = useState(false);
    const fileRef = useRef<HTMLInputElement>(null);
    const textRef = useRef<HTMLTextAreaElement>(null);

    // Drafts that went out with a message disappear once the page reloads.
    const visible = drafts.filter((draft) => !sent.includes(draft.id));

    useEffect(() => {
        setSent((current) =>
            current.filter((id) => drafts.some((d) => d.id === id)),
        );
    }, [drafts]);

    function upload(files: File[]) {
        if (files.length === 0) {
            return;
        }

        const batch = files.map((file) => ({
            key: `${file.name}-${file.size}-${Math.random()}`,
            name: file.name,
            preview: file.type.startsWith('image/')
                ? URL.createObjectURL(file)
                : null,
        }));

        setUploading((current) => [...current, ...batch]);

        router.post(
            chats.attachments.store.url([team, chatId]),
            { files },
            {
                forceFormData: true,
                preserveScroll: true,
                preserveState: true,
                async: true,
                only: ['drafts'],
                onError: (errors) =>
                    toast.error(
                        Object.values(errors)[0] ??
                            'The file could not be attached.',
                    ),
                onFinish: () => {
                    setUploading((current) =>
                        current.filter(
                            (item) => !batch.some((b) => b.key === item.key),
                        ),
                    );
                    batch.forEach(
                        (item) =>
                            item.preview && URL.revokeObjectURL(item.preview),
                    );
                },
            },
        );
    }

    function remove(id: number) {
        router.delete(chats.attachments.destroy.url([team, chatId, id]), {
            preserveScroll: true,
            preserveState: true,
            async: true,
            only: ['drafts'],
        });
    }

    function onPaste(event: ClipboardEvent<HTMLTextAreaElement>) {
        const files = Array.from(event.clipboardData.files);

        if (files.length > 0) {
            event.preventDefault();
            upload(files);

            return;
        }

        const text = event.clipboardData.getData('text/plain');

        if (text.length > PASTE_AS_FILE_CHARS) {
            event.preventDefault();
            upload([
                new File([text], 'pasted-text.txt', { type: 'text/plain' }),
            ]);
        }
    }

    function onDrop(event: DragEvent<HTMLDivElement>) {
        event.preventDefault();
        setDragging(false);
        upload(Array.from(event.dataTransfer.files));
    }

    function submit() {
        const text = input.trim();

        if (busy || uploading.length > 0 || (!text && visible.length === 0)) {
            return;
        }

        // /scrape <url> [what you need]: scraping only starts when the user asks.
        const scrape = text.match(/^\/scrape\s+(\S+)\s*([\s\S]*)$/i);

        if (scrape) {
            onScrape(scrape[1], scrape[2] || null);
            setInput('');

            return;
        }

        onSend(text, visible);
        setSent((current) => [...current, ...visible.map((draft) => draft.id)]);
        setInput('');
        textRef.current?.focus();
    }

    const canSend =
        !busy &&
        uploading.length === 0 &&
        (input.trim() !== '' || visible.length > 0);

    return (
        <div
            className={cn(
                'rounded-xl border bg-background focus-within:ring-2 focus-within:ring-ring/30',
                dragging && 'border-primary ring-2 ring-primary/30',
            )}
            onDragOver={(event) => {
                event.preventDefault();
                setDragging(true);
            }}
            onDragLeave={() => setDragging(false)}
            onDrop={onDrop}
        >
            {(visible.length > 0 || uploading.length > 0) && (
                <div className="flex flex-wrap gap-2 px-3 pt-3">
                    {visible.map((file) => (
                        <AttachmentChip
                            key={file.id}
                            name={file.name}
                            mime={file.mime}
                            preview={
                                file.mime?.startsWith('image/')
                                    ? file.url
                                    : null
                            }
                            onRemove={() => remove(file.id)}
                        />
                    ))}
                    {uploading.map((item) => (
                        <AttachmentChip
                            key={item.key}
                            name={item.name}
                            mime={item.preview ? 'image/*' : null}
                            preview={item.preview}
                            loading
                        />
                    ))}
                </div>
            )}

            <Textarea
                ref={textRef}
                value={input}
                onChange={(event) => setInput(event.target.value)}
                onPaste={onPaste}
                onKeyDown={(event) => {
                    if (event.key === 'Enter' && !event.shiftKey) {
                        event.preventDefault();
                        submit();
                    }
                }}
                rows={3}
                placeholder={
                    dragging
                        ? 'Drop files to attach them'
                        : 'Describe what you need, paste or drop files, or /scrape https://example.com'
                }
                className="resize-none border-0 shadow-none focus-visible:ring-0"
            />

            <div className="flex items-center justify-between gap-2 px-2 pb-2">
                <div className="flex items-center gap-1">
                    <input
                        ref={fileRef}
                        type="file"
                        multiple
                        className="hidden"
                        accept={ACCEPT}
                        onChange={(event) => {
                            upload(Array.from(event.target.files ?? []));
                            event.target.value = '';
                        }}
                    />
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => fileRef.current?.click()}
                        title="Attach files to this message"
                    >
                        <Paperclip /> Attach
                    </Button>
                    <ScrapeDialog chatId={chatId}>
                        <Button variant="ghost" size="sm">
                            <Globe /> Scrape
                        </Button>
                    </ScrapeDialog>
                </div>
                {busy ? (
                    <Button size="sm" variant="secondary" onClick={onStop}>
                        <Square /> Stop
                    </Button>
                ) : (
                    <Button size="sm" onClick={submit} disabled={!canSend}>
                        <ArrowUp /> Send
                    </Button>
                )}
            </div>
        </div>
    );
}

export function AttachmentChip({
    name,
    mime,
    preview,
    href,
    loading = false,
    onRemove,
    onSave,
}: {
    name: string;
    mime: string | null;
    preview?: string | null;
    href?: string;
    loading?: boolean;
    onRemove?: () => void;
    onSave?: () => void;
}) {
    const Icon = mime?.startsWith('video/') ? Film : FileText;

    const body = preview ? (
        <img
            src={preview}
            alt={name}
            className="size-16 rounded-lg border object-cover"
        />
    ) : (
        <div className="flex h-16 max-w-52 items-center gap-2 rounded-lg border bg-muted/40 px-3">
            <Icon className="size-5 shrink-0 text-muted-foreground" />
            <span className="truncate text-xs">{name}</span>
        </div>
    );

    return (
        <div className="group relative" title={name}>
            {href ? (
                <a href={href} target="_blank" rel="noreferrer">
                    {body}
                </a>
            ) : (
                body
            )}
            {loading && (
                <div className="absolute inset-0 flex items-center justify-center rounded-lg bg-background/70">
                    <Loader2 className="size-4 animate-spin" />
                </div>
            )}
            {onSave && (
                <button
                    type="button"
                    onClick={onSave}
                    className="absolute -top-1.5 -right-1.5 hidden size-5 items-center justify-center rounded-full border bg-background text-muted-foreground shadow-sm group-hover:flex hover:text-foreground"
                    title="Save to the context library"
                    aria-label={`Save ${name} to the context library`}
                >
                    <BookmarkPlus className="size-3" />
                </button>
            )}
            {onRemove && !loading && (
                <button
                    type="button"
                    onClick={onRemove}
                    className="absolute -top-1.5 -right-1.5 flex size-5 items-center justify-center rounded-full border bg-background text-muted-foreground shadow-sm hover:text-foreground"
                    aria-label={`Remove ${name}`}
                >
                    <X className="size-3" />
                </button>
            )}
        </div>
    );
}
