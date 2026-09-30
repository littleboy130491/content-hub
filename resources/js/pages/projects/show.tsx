import { Form, Head, Link, router } from '@inertiajs/react';
import { MessageSquarePlus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { EmptyState, PageHeader } from '@/components/content/page-header';
import { PromptModeField } from '@/components/content/prompt-mode-field';
import { UntrustedBadge } from '@/components/content/status-badge';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTeamSlug } from '@/hooks/use-team';
import { timeAgo } from '@/lib/format';
import chats from '@/routes/chats';
import context from '@/routes/context';
import projects from '@/routes/projects';
import type { Chat, ContextItem, PromptMode } from '@/types';

type Props = {
    project: {
        id: number;
        name: string;
        description: string | null;
        instructions: string | null;
        instructionsMode: PromptMode;
        contextItemIds: number[];
    };
    workspacePrompt: string | null;
    library: ContextItem[];
    chats: Chat[];
};

export default function ProjectShow({
    project,
    workspacePrompt,
    library,
    chats: projectChats,
}: Props) {
    const team = useTeamSlug();
    const [selected, setSelected] = useState<number[]>(project.contextItemIds);
    const [mode, setMode] = useState<PromptMode>(project.instructionsMode);

    const dirty = useMemo(
        () =>
            selected.length !== project.contextItemIds.length ||
            selected.some((id) => !project.contextItemIds.includes(id)),
        [selected, project.contextItemIds],
    );

    const toggle = (id: number, on: boolean) =>
        setSelected((current) =>
            on ? [...current, id] : current.filter((x) => x !== id),
        );

    return (
        <>
            <Head title={project.name} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={project.name}
                    description={project.description ?? undefined}
                    actions={
                        <>
                            <Button
                                onClick={() =>
                                    router.post(
                                        projects.chats.store.url([
                                            team,
                                            project.id,
                                        ]),
                                    )
                                }
                            >
                                <MessageSquarePlus /> New chat in project
                            </Button>
                            <Button
                                variant="ghost"
                                onClick={() =>
                                    router.delete(
                                        projects.destroy.url([
                                            team,
                                            project.id,
                                        ]),
                                        {
                                            onBefore: () =>
                                                window.confirm(
                                                    'Delete this project? Its chats become free chats.',
                                                ),
                                        },
                                    )
                                }
                            >
                                <Trash2 /> Delete
                            </Button>
                        </>
                    }
                />

                <div className="grid gap-6 xl:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Instructions</CardTitle>
                            <CardDescription>
                                The system prompt for every chat in this
                                project. A chat can add to it or replace it.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Form
                                {...projects.update.form([team, project.id])}
                                options={{ preserveScroll: true }}
                                className="space-y-4"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="name">Name</Label>
                                            <Input
                                                id="name"
                                                name="name"
                                                defaultValue={project.name}
                                                required
                                            />
                                            <InputError message={errors.name} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="description">
                                                Description
                                            </Label>
                                            <Input
                                                id="description"
                                                name="description"
                                                defaultValue={
                                                    project.description ?? ''
                                                }
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="instructions">
                                                Instructions
                                            </Label>
                                            <Textarea
                                                id="instructions"
                                                name="instructions"
                                                rows={8}
                                                defaultValue={
                                                    project.instructions ?? ''
                                                }
                                                placeholder="For example: Write for a young, urban audience. Short sentences. Always end with a call to action."
                                            />
                                            <InputError
                                                message={errors.instructions}
                                            />
                                        </div>
                                        <PromptModeField
                                            name="instructions_mode"
                                            value={mode}
                                            onChange={setMode}
                                            above="the workspace prompt"
                                        />
                                        {workspacePrompt && (
                                            <p className="rounded-md bg-muted p-3 text-xs text-muted-foreground">
                                                <span className="font-medium text-foreground">
                                                    Workspace prompt
                                                    {mode === 'replace'
                                                        ? ' (replaced): '
                                                        : ' (kept): '}
                                                </span>
                                                {workspacePrompt}
                                            </p>
                                        )}
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Save
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Context</CardTitle>
                            <CardDescription>
                                Pick library items for this project. Chats
                                started here get them automatically; nothing is
                                added on its own.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {library.length === 0 ? (
                                <EmptyState
                                    title="The library is empty"
                                    action={
                                        <Button variant="outline" asChild>
                                            <Link href={context.index(team)}>
                                                Add context
                                            </Link>
                                        </Button>
                                    }
                                />
                            ) : (
                                <div className="max-h-[420px] space-y-1 overflow-y-auto rounded-md border p-2">
                                    {library.map((item) => (
                                        <label
                                            key={item.id}
                                            className="flex cursor-pointer items-center gap-3 rounded-md p-2 hover:bg-accent/50"
                                        >
                                            <Checkbox
                                                checked={selected.includes(
                                                    item.id,
                                                )}
                                                onCheckedChange={(on) =>
                                                    toggle(item.id, on === true)
                                                }
                                            />
                                            <span className="min-w-0 flex-1 truncate text-sm">
                                                {item.title}
                                            </span>
                                            {item.untrusted && (
                                                <UntrustedBadge />
                                            )}
                                            <Badge variant="secondary">
                                                {item.typeLabel}
                                            </Badge>
                                        </label>
                                    ))}
                                </div>
                            )}
                            <div className="flex items-center gap-3">
                                <Button
                                    disabled={!dirty}
                                    onClick={() =>
                                        router.put(
                                            projects.context.url([
                                                team,
                                                project.id,
                                            ]),
                                            { context_item_ids: selected },
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    Save context ({selected.length})
                                </Button>
                                {dirty && (
                                    <span className="text-sm text-muted-foreground">
                                        Unsaved changes
                                    </span>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <section className="space-y-3">
                    <h2 className="font-medium">Chats in this project</h2>
                    {projectChats.length === 0 ? (
                        <EmptyState
                            title="No chats yet"
                            description="Start one to create content with this project's context."
                        />
                    ) : (
                        <div className="divide-y rounded-xl border">
                            {projectChats.map((chat) => (
                                <Link
                                    key={chat.id}
                                    href={chats.show([team, chat.id])}
                                    className="flex items-center justify-between p-3 text-sm hover:bg-accent/50"
                                >
                                    <span className="truncate font-medium">
                                        {chat.title}
                                    </span>
                                    <span className="shrink-0 text-xs text-muted-foreground">
                                        {chat.messagesCount ?? 0} messages ·{' '}
                                        {timeAgo(chat.updatedAt)}
                                    </span>
                                </Link>
                            ))}
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}
