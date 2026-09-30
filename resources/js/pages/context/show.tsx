import { Form, Head, Link, router } from '@inertiajs/react';
import { ExternalLink, RefreshCw, Trash2 } from 'lucide-react';
import { PageHeader } from '@/components/content/page-header';
import { SafeMarkdown } from '@/components/content/safe-markdown';
import { UntrustedBadge } from '@/components/content/status-badge';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { useTeamSlug } from '@/hooks/use-team';
import { formatDate, hostOf } from '@/lib/format';
import context from '@/routes/context';
import projects from '@/routes/projects';
import type { ContextItem } from '@/types';

type Props = {
    item: ContextItem;
    projects: { id: number; name: string }[];
};

export default function ContextShow({ item, projects: usedIn }: Props) {
    const team = useTeamSlug();
    const hasBody = item.type === 'text' || item.type === 'web';

    return (
        <>
            <Head title={item.title} />
            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={item.title}
                    description={`${item.typeLabel} · added by ${item.author ?? 'someone'} · updated ${formatDate(item.updatedAt)}`}
                    actions={
                        <>
                            {item.type === 'web' && item.sourceUrl && (
                                <Button
                                    variant="outline"
                                    onClick={() =>
                                        router.post(
                                            context.rescrape.url([
                                                team,
                                                item.id,
                                            ]),
                                        )
                                    }
                                >
                                    <RefreshCw /> Re-scrape
                                </Button>
                            )}
                            <Button
                                variant="ghost"
                                onClick={() =>
                                    router.delete(
                                        context.destroy.url([team, item.id]),
                                        {
                                            onBefore: () =>
                                                window.confirm(
                                                    'Delete this item? Projects that include it will lose it.',
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

                <div className="flex flex-wrap items-center gap-2 text-sm">
                    {item.untrusted && <UntrustedBadge />}
                    {item.sourceUrl && (
                        <a
                            href={item.sourceUrl}
                            target="_blank"
                            rel="noopener noreferrer nofollow"
                            className="inline-flex items-center gap-1 text-muted-foreground hover:underline"
                        >
                            {hostOf(item.sourceUrl)}{' '}
                            <ExternalLink className="size-3" />
                        </a>
                    )}
                    {item.fetchedAt && (
                        <span className="text-muted-foreground">
                            · fetched {formatDate(item.fetchedAt)}
                        </span>
                    )}
                    <span className="text-muted-foreground">· used in</span>
                    {usedIn.length === 0 ? (
                        <span className="text-muted-foreground">
                            no projects
                        </span>
                    ) : (
                        usedIn.map((project) => (
                            <Link
                                key={project.id}
                                href={projects.show([team, project.id])}
                            >
                                <Badge variant="secondary">
                                    {project.name}
                                </Badge>
                            </Link>
                        ))
                    )}
                </div>

                {item.type === 'image' && item.file && (
                    <img
                        src={item.file.url}
                        alt={item.title}
                        className="max-h-[480px] w-full rounded-xl border bg-muted object-contain"
                    />
                )}
                {item.type === 'video' && item.file && (
                    <video
                        src={item.file.url}
                        controls
                        className="max-h-[480px] w-full rounded-xl border bg-black"
                    />
                )}

                <Form
                    {...context.update.form([team, item.id])}
                    options={{ preserveScroll: true }}
                    className="space-y-6"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="title">Title</Label>
                                <Input
                                    id="title"
                                    name="title"
                                    defaultValue={item.title}
                                    required
                                />
                                <InputError message={errors.title} />
                            </div>

                            {hasBody && (
                                <Tabs
                                    defaultValue={
                                        item.type === 'web' ? 'preview' : 'edit'
                                    }
                                >
                                    <TabsList>
                                        <TabsTrigger value="preview">
                                            Preview
                                        </TabsTrigger>
                                        <TabsTrigger value="edit">
                                            Edit
                                        </TabsTrigger>
                                    </TabsList>
                                    <TabsContent
                                        value="preview"
                                        className="rounded-xl border p-4"
                                    >
                                        <SafeMarkdown>
                                            {item.body ?? ''}
                                        </SafeMarkdown>
                                    </TabsContent>
                                    <TabsContent
                                        value="edit"
                                        forceMount
                                        className="data-[state=inactive]:hidden"
                                    >
                                        <Textarea
                                            name="body"
                                            defaultValue={item.body ?? ''}
                                            rows={18}
                                            className="font-mono text-xs"
                                        />
                                        <InputError message={errors.body} />
                                    </TabsContent>
                                </Tabs>
                            )}

                            {(item.type === 'image' ||
                                item.type === 'video') && (
                                <div className="grid gap-2">
                                    <Label htmlFor="description">
                                        Description for text-only models
                                    </Label>
                                    <Textarea
                                        id="description"
                                        name="description"
                                        rows={6}
                                        defaultValue={item.description ?? ''}
                                        placeholder="Written automatically the first time a model that cannot read this file needs it. You can also write or correct it yourself."
                                    />
                                    {item.descriptionModel && (
                                        <p className="text-xs text-muted-foreground">
                                            {item.descriptionModel === 'edited'
                                                ? 'Edited by hand.'
                                                : `Written by ${item.descriptionModel}.`}
                                        </p>
                                    )}
                                </div>
                            )}

                            <Button type="submit" disabled={processing}>
                                Save
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
