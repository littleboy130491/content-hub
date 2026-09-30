import { Head, Link, router } from '@inertiajs/react';
import { MessageSquarePlus } from 'lucide-react';
import { useState } from 'react';
import {
    EmptyState,
    PageHeader,
    Pagination,
} from '@/components/content/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useTeamSlug } from '@/hooks/use-team';
import { timeAgo } from '@/lib/format';
import chatsRoutes from '@/routes/chats';
import type { Chat, Paginated } from '@/types';

type Props = {
    chats: Paginated<Chat>;
    filter: 'all' | 'free' | 'project' | 'archived';
    search: string;
};

export default function ChatsIndex({
    chats,
    filter,
    search: initialSearch,
}: Props) {
    const team = useTeamSlug();
    const [search, setSearch] = useState(initialSearch);

    const go = (next: string, term = search) =>
        router.get(
            chatsRoutes.index.url(team),
            { filter: next, search: term || undefined },
            { preserveState: true, replace: true },
        );

    return (
        <>
            <Head title="Chats" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Chats"
                    description="A free chat starts with zero context. A project chat gets its project's context and instructions."
                    actions={
                        <Button
                            onClick={() =>
                                router.post(chatsRoutes.store.url(team))
                            }
                        >
                            <MessageSquarePlus /> New free chat
                        </Button>
                    }
                />

                <div className="flex flex-wrap items-center gap-3">
                    <Tabs value={filter} onValueChange={(value) => go(value)}>
                        <TabsList>
                            <TabsTrigger value="all">All</TabsTrigger>
                            <TabsTrigger value="free">Free</TabsTrigger>
                            <TabsTrigger value="project">
                                In projects
                            </TabsTrigger>
                            <TabsTrigger value="archived">Archived</TabsTrigger>
                        </TabsList>
                    </Tabs>
                    <form
                        className="flex-1"
                        onSubmit={(event) => {
                            event.preventDefault();
                            go(filter);
                        }}
                    >
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search chat titles"
                            className="max-w-sm"
                        />
                    </form>
                </div>

                {chats.data.length === 0 ? (
                    <EmptyState title="No chats here" />
                ) : (
                    <div className="divide-y rounded-xl border">
                        {chats.data.map((chat) => (
                            <Link
                                key={chat.id}
                                href={chatsRoutes.show([team, chat.id])}
                                className="flex items-center justify-between gap-4 p-4 hover:bg-accent/50"
                            >
                                <div className="min-w-0">
                                    <p className="truncate font-medium">
                                        {chat.title}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {chat.author ?? 'Someone'} ·{' '}
                                        {chat.messagesCount ?? 0} messages ·{' '}
                                        {timeAgo(chat.updatedAt)}
                                    </p>
                                </div>
                                <div className="flex shrink-0 gap-2">
                                    {chat.archived && (
                                        <Badge variant="outline">
                                            Archived
                                        </Badge>
                                    )}
                                    <Badge
                                        variant={
                                            chat.project
                                                ? 'secondary'
                                                : 'outline'
                                        }
                                    >
                                        {chat.project
                                            ? chat.project.name
                                            : 'Free chat'}
                                    </Badge>
                                </div>
                            </Link>
                        ))}
                    </div>
                )}

                <Pagination links={chats.links} />
            </div>
        </>
    );
}
