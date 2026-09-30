import type { RequestPayload } from '@inertiajs/core';
import { Link, router } from '@inertiajs/react';
import {
    Archive,
    ArchiveRestore,
    BookmarkPlus,
    FileText,
    Film,
    FolderInput,
    Globe,
    ImageIcon,
    Trash2,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { ModelPicker } from '@/components/content/model-picker';
import { PromptModeField } from '@/components/content/prompt-mode-field';
import { StatusBadge, UntrustedBadge } from '@/components/content/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useTeamSlug } from '@/hooks/use-team';
import { hostOf } from '@/lib/format';
import chats from '@/routes/chats';
import scrapes from '@/routes/scrapes';
import type { ContextEntry, ModelKind, PromptMode } from '@/types';
import type { ChatPageProps } from '@/pages/chats/show';

const typeIcons = { text: FileText, image: ImageIcon, video: Film, web: Globe };

const handlingLabels: Record<ContextEntry['handling'], string> = {
    text: 'sent as text',
    image: 'sent as image',
    convert: 'described as text',
    excluded: 'excluded',
    unsupported: 'cannot be read',
};

export function ChatSidePanel({
    chat,
    context,
    attachments,
    scrapes: chatScrapes,
    effective,
    modelOptions,
    projects,
}: ChatPageProps) {
    const team = useTeamSlug();

    const patch = (data: RequestPayload, only: string[] = []) =>
        router.patch(chats.update.url([team, chat.id]), data, {
            preserveScroll: true,
            preserveState: true,
            only: ['chat', 'context', 'effective', ...only],
        });

    const toggleItem = (id: number, include: boolean) =>
        patch({
            excluded_context_item_ids: include
                ? chat.excludedContextItemIds.filter((x) => x !== id)
                : [...chat.excludedContextItemIds, id],
        });

    const holds = Array.from(
        new Set(
            context
                .filter((entry) => entry.handling !== 'excluded')
                .map((entry) => (entry.type === 'web' ? 'text' : entry.type)),
        ),
    );

    const textModel = effective.models.text;

    return (
        <aside className="space-y-4">
            <Card className="gap-4">
                <CardHeader>
                    <CardTitle className="text-base">Context</CardTitle>
                    <p className="text-xs text-muted-foreground">
                        {chat.project
                            ? `From the project, plus anything attached to this chat.`
                            : 'A free chat starts empty. Attachments and scraped pages here are for this chat only.'}
                    </p>
                </CardHeader>
                <CardContent className="space-y-3">
                    {context.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            Zero context.
                        </p>
                    )}

                    {context.map((entry) => {
                        const Icon = typeIcons[entry.type];
                        const attachment =
                            entry.source === 'attachment'
                                ? attachments.find(
                                      (file) => file.id === entry.mediaId,
                                  )
                                : undefined;

                        return (
                            <div
                                key={entry.key}
                                className="space-y-1.5 rounded-lg border p-2.5"
                            >
                                <div className="flex items-center gap-2">
                                    <Icon className="size-4 shrink-0 text-muted-foreground" />
                                    <span className="min-w-0 flex-1 truncate text-sm">
                                        {entry.title}
                                    </span>
                                    {entry.contextItemId !== null &&
                                        entry.source === 'project' && (
                                            <Switch
                                                checked={
                                                    entry.handling !==
                                                    'excluded'
                                                }
                                                onCheckedChange={(on) =>
                                                    toggleItem(
                                                        entry.contextItemId!,
                                                        on,
                                                    )
                                                }
                                                aria-label="Use in this chat"
                                            />
                                        )}
                                    {attachment && (
                                        <>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="size-7"
                                                title="Save to library"
                                                onClick={() =>
                                                    router.post(
                                                        chats.attachments.library.url(
                                                            [
                                                                team,
                                                                chat.id,
                                                                attachment.id,
                                                            ],
                                                        ),
                                                        {},
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                <BookmarkPlus />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="size-7"
                                                title="Remove from this chat"
                                                onClick={() =>
                                                    router.delete(
                                                        chats.attachments.destroy.url(
                                                            [
                                                                team,
                                                                chat.id,
                                                                attachment.id,
                                                            ],
                                                        ),
                                                        {
                                                            preserveScroll: true,
                                                            only: [
                                                                'attachments',
                                                                'context',
                                                            ],
                                                        },
                                                    )
                                                }
                                            >
                                                <X />
                                            </Button>
                                        </>
                                    )}
                                </div>
                                <div className="flex flex-wrap items-center gap-1.5">
                                    <Badge
                                        variant={
                                            entry.handling === 'convert'
                                                ? 'outline'
                                                : 'secondary'
                                        }
                                        className={
                                            entry.handling === 'convert'
                                                ? 'border-amber-500/50 text-amber-700 dark:text-amber-400'
                                                : undefined
                                        }
                                    >
                                        {handlingLabels[entry.handling]}
                                        {entry.handling === 'convert' &&
                                            entry.cached &&
                                            ' (ready)'}
                                    </Badge>
                                    <Badge variant="outline">
                                        {entry.source === 'project'
                                            ? 'project'
                                            : entry.source === 'attachment'
                                              ? 'this chat'
                                              : 'scraped, this chat'}
                                    </Badge>
                                    {entry.untrusted && <UntrustedBadge />}
                                </div>
                            </div>
                        );
                    })}

                    {chatScrapes.filter((s) => !s.attachedToChat).length >
                        0 && (
                        <div className="space-y-2 pt-1">
                            <p className="text-xs font-medium text-muted-foreground">
                                Scrapes to review
                            </p>
                            {chatScrapes
                                .filter((s) => !s.attachedToChat)
                                .map((scrape) => (
                                    <Link
                                        key={scrape.id}
                                        href={scrapes.show([team, scrape.id])}
                                        className="flex items-center justify-between gap-2 rounded-lg border p-2 text-sm hover:bg-accent/50"
                                    >
                                        <span className="truncate">
                                            {scrape.title ?? hostOf(scrape.url)}
                                        </span>
                                        <StatusBadge status={scrape.status} />
                                    </Link>
                                ))}
                        </div>
                    )}

                    {context.some((entry) => entry.handling === 'convert') && (
                        <p className="text-xs text-muted-foreground">
                            {textModel.name} cannot read some of this directly,
                            so {effective.models.media.name} describes it as
                            text. Descriptions are saved and reused. Switch to a
                            model that reads images to send them as is.
                        </p>
                    )}
                </CardContent>
            </Card>

            <Card className="gap-4">
                <CardHeader>
                    <CardTitle className="text-base">
                        Models for this chat
                    </CardTitle>
                    {!textModel.supportsTools && (
                        <p className="text-xs text-amber-700 dark:text-amber-400">
                            {textModel.name} cannot call tools, so this chat can
                            only write text.
                        </p>
                    )}
                </CardHeader>
                <CardContent className="space-y-3">
                    {(
                        [
                            ['text', 'Chat and text', modelOptions.text, holds],
                            ['image', 'Images', modelOptions.image, undefined],
                            ['video', 'Video', modelOptions.video, undefined],
                            [
                                'media',
                                'Media reading',
                                modelOptions.text.filter((m) =>
                                    m.input.includes('image'),
                                ),
                                undefined,
                            ],
                        ] as [
                            ModelKind,
                            string,
                            typeof modelOptions.text,
                            string[] | undefined,
                        ][]
                    ).map(([kind, label, options, warnFor]) => (
                        <div key={kind} className="grid gap-1.5">
                            <Label
                                htmlFor={`model-${kind}`}
                                className="text-xs"
                            >
                                {label}
                            </Label>
                            <ModelPicker
                                id={`model-${kind}`}
                                value={chat.models[kind]}
                                options={options}
                                defaultLabel={`Workspace default (${effective.models[kind].name})`}
                                warnFor={warnFor}
                                onChange={(value) =>
                                    patch({ [`${kind}_model`]: value })
                                }
                            />
                        </div>
                    ))}
                    {modelOptions.text.length === 0 && (
                        <p className="text-xs text-muted-foreground">
                            The model list is empty. Load it from workspace
                            settings.
                        </p>
                    )}
                </CardContent>
            </Card>

            <SystemPromptCard
                chatId={chat.id}
                prompt={chat.systemPrompt}
                mode={chat.promptMode}
                layers={effective.promptLayers}
            />

            <Card className="gap-4">
                <CardHeader>
                    <CardTitle className="text-base">Chat</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <form
                        className="flex gap-2"
                        onSubmit={(event) => {
                            event.preventDefault();
                            const title = new FormData(event.currentTarget).get(
                                'title',
                            );
                            patch({ title }, ['messages']);
                        }}
                    >
                        <Input
                            name="title"
                            defaultValue={chat.title}
                            key={chat.title}
                            aria-label="Title"
                        />
                        <Button type="submit" variant="outline">
                            Rename
                        </Button>
                    </form>

                    {!chat.project && projects.length > 0 && (
                        <MoveToProject chatId={chat.id} projects={projects} />
                    )}

                    <div className="flex flex-wrap gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => patch({ archived: !chat.archived })}
                        >
                            {chat.archived ? <ArchiveRestore /> : <Archive />}
                            {chat.archived ? 'Unarchive' : 'Archive'}
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() =>
                                router.delete(
                                    chats.destroy.url([team, chat.id]),
                                    {
                                        onBefore: () =>
                                            window.confirm(
                                                'Delete this chat? Its outputs are kept.',
                                            ),
                                    },
                                )
                            }
                        >
                            <Trash2 /> Delete
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </aside>
    );
}

function SystemPromptCard({
    chatId,
    prompt,
    mode: initialMode,
    layers,
}: {
    chatId: number;
    prompt: string | null;
    mode: PromptMode;
    layers: ChatPageProps['effective']['promptLayers'];
}) {
    const team = useTeamSlug();
    const [text, setText] = useState(prompt ?? '');
    const [mode, setMode] = useState<PromptMode>(initialMode);
    const inherited = layers.filter((layer) => layer.source !== 'chat');

    return (
        <Card className="gap-4">
            <CardHeader>
                <CardTitle className="text-base">System prompt</CardTitle>
                <p className="text-xs text-muted-foreground">
                    Added in order: workspace, project, chat. A layer set to
                    replace switches off the ones above it.
                </p>
            </CardHeader>
            <CardContent className="space-y-3">
                {inherited.map((layer) => (
                    <div
                        key={layer.source}
                        className={`rounded-md border p-2 text-xs ${layer.active ? '' : 'line-through opacity-50'}`}
                    >
                        <p className="mb-1 font-medium capitalize">
                            {layer.source === 'settings'
                                ? 'Workspace'
                                : 'Project'}
                            {layer.mode === 'replace' &&
                                layer.source !== 'settings' &&
                                ' (replaces workspace)'}
                        </p>
                        <p className="line-clamp-4 whitespace-pre-wrap text-muted-foreground">
                            {layer.text}
                        </p>
                    </div>
                ))}
                <Textarea
                    value={text}
                    onChange={(event) => setText(event.target.value)}
                    rows={4}
                    placeholder="Instructions for this chat only (optional)"
                    aria-label="Chat system prompt"
                />
                <PromptModeField
                    name="prompt_mode"
                    value={mode}
                    onChange={setMode}
                    above="the prompts above"
                />
                <Button
                    size="sm"
                    onClick={() =>
                        router.patch(
                            chats.update.url([team, chatId]),
                            { system_prompt: text || null, prompt_mode: mode },
                            {
                                preserveScroll: true,
                                preserveState: true,
                                only: ['chat', 'effective'],
                            },
                        )
                    }
                >
                    Save prompt
                </Button>
            </CardContent>
        </Card>
    );
}

function MoveToProject({
    chatId,
    projects,
}: {
    chatId: number;
    projects: { id: number; name: string }[];
}) {
    const team = useTeamSlug();
    const [projectId, setProjectId] = useState<string>('');

    return (
        <div className="space-y-2">
            <Label className="text-xs">Move into a project</Label>
            <div className="flex gap-2">
                <Select value={projectId} onValueChange={setProjectId}>
                    <SelectTrigger className="flex-1">
                        <SelectValue placeholder="Choose a project" />
                    </SelectTrigger>
                    <SelectContent>
                        {projects.map((project) => (
                            <SelectItem
                                key={project.id}
                                value={String(project.id)}
                            >
                                {project.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <Button
                    variant="outline"
                    disabled={!projectId}
                    onClick={() =>
                        router.post(
                            chats.move.url([team, chatId]),
                            { project_id: Number(projectId) },
                            { preserveScroll: true },
                        )
                    }
                >
                    <FolderInput /> Move
                </Button>
            </div>
            <p className="text-xs text-muted-foreground">
                The chat then uses the project&apos;s context and instructions.
            </p>
        </div>
    );
}
