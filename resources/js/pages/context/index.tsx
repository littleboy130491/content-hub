import { Head, Link, router } from '@inertiajs/react';
import { FileText, Film, Globe, Image as ImageIcon, Plus } from 'lucide-react';
import { useState } from 'react';
import {
    EmptyState,
    PageHeader,
    Pagination,
} from '@/components/content/page-header';
import { NewContextForm } from '@/components/content/new-context-form';
import { ScrapeDialog } from '@/components/content/scrape-dialog';
import { UntrustedBadge } from '@/components/content/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useTeamSlug } from '@/hooks/use-team';
import { timeAgo } from '@/lib/format';
import context from '@/routes/context';
import type { ContextItem, ContextType, Paginated } from '@/types';

type Props = {
    items: Paginated<ContextItem>;
    filters: { type: string | null; search: string | null };
    types: { value: ContextType; label: string }[];
};

const icons = { text: FileText, image: ImageIcon, video: Film, web: Globe };

export default function ContextIndex({ items, filters, types }: Props) {
    const team = useTeamSlug();
    const [search, setSearch] = useState(filters.search ?? '');

    const filter = (type: string | null, term = search) =>
        router.get(
            context.index.url(team),
            {
                'filter[type]': type || undefined,
                'filter[search]': term || undefined,
            },
            { preserveState: true, replace: true },
        );

    return (
        <>
            <Head title="Context library" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Context library"
                    description="Reference material for this workspace. Include items in a project and every chat in it can use them."
                    actions={
                        <>
                            <AddContextDialog />
                            <ScrapeDialog>
                                <Button variant="outline">
                                    <Globe /> Scrape a page
                                </Button>
                            </ScrapeDialog>
                        </>
                    }
                />

                <div className="flex flex-wrap items-center gap-3">
                    <Tabs
                        value={filters.type ?? 'all'}
                        onValueChange={(value) =>
                            filter(value === 'all' ? null : value)
                        }
                    >
                        <TabsList>
                            <TabsTrigger value="all">All</TabsTrigger>
                            {types.map((type) => (
                                <TabsTrigger
                                    key={type.value}
                                    value={type.value}
                                >
                                    {type.label}
                                </TabsTrigger>
                            ))}
                        </TabsList>
                    </Tabs>
                    <form
                        className="flex-1"
                        onSubmit={(event) => {
                            event.preventDefault();
                            filter(filters.type);
                        }}
                    >
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search titles and text"
                            className="max-w-sm"
                        />
                    </form>
                </div>

                {items.data.length === 0 ? (
                    <EmptyState
                        title="Nothing here yet"
                        description="Add brand notes, reference images, sample videos or web pages. Nothing is used in a chat until you include it in a project."
                        action={<AddContextDialog />}
                    />
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {items.data.map((item) => {
                            const Icon = icons[item.type];

                            return (
                                <Link
                                    key={item.id}
                                    href={context.show([team, item.id])}
                                >
                                    <Card className="h-full gap-3 py-4 transition-colors hover:bg-accent/50">
                                        <CardHeader className="px-4">
                                            <CardTitle className="flex items-center gap-2 text-sm">
                                                <Icon className="size-4 shrink-0 text-muted-foreground" />
                                                <span className="truncate">
                                                    {item.title}
                                                </span>
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent className="space-y-3 px-4">
                                            {item.type === 'image' &&
                                                item.file && (
                                                    <img
                                                        src={item.file.url}
                                                        alt={item.title}
                                                        loading="lazy"
                                                        className="aspect-video w-full rounded-md border bg-muted object-cover"
                                                    />
                                                )}
                                            {item.excerpt && (
                                                <p className="line-clamp-3 text-sm text-muted-foreground">
                                                    {item.excerpt}
                                                </p>
                                            )}
                                            <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                                <Badge variant="secondary">
                                                    {item.typeLabel}
                                                </Badge>
                                                {item.untrusted && (
                                                    <UntrustedBadge />
                                                )}
                                                <span>
                                                    in {item.projectsCount ?? 0}{' '}
                                                    project
                                                    {item.projectsCount === 1
                                                        ? ''
                                                        : 's'}
                                                </span>
                                                <span>
                                                    · {timeAgo(item.updatedAt)}
                                                </span>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </Link>
                            );
                        })}
                    </div>
                )}

                <Pagination links={items.links} />
            </div>
        </>
    );
}

function AddContextDialog() {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>
                    <Plus /> Add context
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Add context</DialogTitle>
                    <DialogDescription>
                        Saved to the workspace library. Include it in a project
                        to use it in chats.
                    </DialogDescription>
                </DialogHeader>
                <NewContextForm onSuccess={() => setOpen(false)} />
            </DialogContent>
        </Dialog>
    );
}
