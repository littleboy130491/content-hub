import { Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    FolderPlus,
    Globe,
    Library,
    MessageSquarePlus,
} from 'lucide-react';
import { useState } from 'react';
import { OutputCard } from '@/components/content/output-card';
import { EmptyState, PageHeader } from '@/components/content/page-header';
import { ScrapeDialog } from '@/components/content/scrape-dialog';
import { StatusBadge } from '@/components/content/status-badge';
import PendingInvitationsModal from '@/components/pending-invitations-modal';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { usePollWhile } from '@/hooks/use-poll-while';
import { useTeamSlug } from '@/hooks/use-team';
import { formatUsd, hostOf, timeAgo } from '@/lib/format';
import { dashboard } from '@/routes';
import chats from '@/routes/chats';
import context from '@/routes/context';
import outputs from '@/routes/outputs';
import projects from '@/routes/projects';
import scrapes from '@/routes/scrapes';
import workspace from '@/routes/workspace';
import type {
    Chat,
    DashboardInvitation,
    Output,
    UsageSummary,
    WebScrape,
} from '@/types';

type Props = {
    pendingInvitations?: DashboardInvitation[];
    counts: {
        context: number;
        projects: number;
        chats: number;
        outputs: number;
    };
    recentChats: Chat[];
    recentOutputs: Output[];
    jobs: Output[];
    scrapes: WebScrape[];
    activity: {
        id: number;
        description: string;
        subject: string;
        causer: string | null;
        createdAt: string;
    }[];
    usage: UsageSummary;
    setup: { openrouter: boolean; firecrawl: boolean };
};

export default function Dashboard({
    pendingInvitations = [],
    counts,
    recentChats,
    recentOutputs,
    jobs,
    scrapes: pendingScrapes,
    activity,
    usage,
    setup,
}: Props) {
    const team = useTeamSlug();
    const [showInvitations, setShowInvitations] = useState(
        pendingInvitations.length > 0,
    );

    const running =
        jobs.some(
            (job) => job.status === 'pending' || job.status === 'processing',
        ) ||
        pendingScrapes.some(
            (s) => s.status === 'pending' || s.status === 'processing',
        );

    usePollWhile(running, ['jobs', 'recentOutputs', 'scrapes', 'counts']);

    const stats = [
        {
            label: 'Context items',
            value: counts.context,
            href: context.index(team),
        },
        {
            label: 'Projects',
            value: counts.projects,
            href: projects.index(team),
        },
        { label: 'Chats', value: counts.chats, href: chats.index(team) },
        {
            label: 'Finished outputs',
            value: counts.outputs,
            href: outputs.index(team),
        },
    ];

    return (
        <>
            <Head title="Dashboard" />
            <PendingInvitationsModal
                invitations={pendingInvitations}
                open={pendingInvitations.length > 0 && showInvitations}
                onOpenChange={setShowInvitations}
            />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Dashboard"
                    description="Chat to create content. Add context once and reuse it across projects."
                    actions={
                        <>
                            <Button
                                onClick={() =>
                                    router.post(chats.store.url(team))
                                }
                            >
                                <MessageSquarePlus /> New chat
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={projects.index(team)}>
                                    <FolderPlus /> Projects
                                </Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={context.index(team)}>
                                    <Library /> Add context
                                </Link>
                            </Button>
                            <ScrapeDialog>
                                <Button variant="outline">
                                    <Globe /> Scrape a page
                                </Button>
                            </ScrapeDialog>
                        </>
                    }
                />

                {(!setup.openrouter || !setup.firecrawl) && (
                    <Alert>
                        <AlertTriangle />
                        <AlertTitle>Finish setting up</AlertTitle>
                        <AlertDescription>
                            {!setup.openrouter &&
                                'Add OPENROUTER_API_KEY to the server’s .env file to enable chat, images and video. '}
                            {!setup.firecrawl &&
                                'Add FIRECRAWL_API_KEY to enable web scraping. '}
                            <Link
                                href={workspace.settings(team)}
                                className="underline underline-offset-4"
                            >
                                Workspace settings
                            </Link>
                        </AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    {stats.map((stat) => (
                        <Link key={stat.label} href={stat.href}>
                            <Card className="h-full gap-1 py-4 transition-colors hover:bg-accent/50">
                                <CardHeader className="px-4">
                                    <CardDescription>
                                        {stat.label}
                                    </CardDescription>
                                    <CardTitle className="text-2xl">
                                        {stat.value}
                                    </CardTitle>
                                </CardHeader>
                            </Card>
                        </Link>
                    ))}
                    <Card className="gap-2 py-4">
                        <CardHeader className="px-4">
                            <CardDescription>
                                Your spend this month
                            </CardDescription>
                            <CardTitle className="text-2xl">
                                {formatUsd(usage.spent) || '$0.00'}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-1 px-4">
                            <Progress
                                value={
                                    usage.limit > 0
                                        ? Math.min(
                                              100,
                                              (usage.spent / usage.limit) * 100,
                                          )
                                        : 0
                                }
                            />
                            <p className="text-xs text-muted-foreground">
                                Limit {formatUsd(usage.limit)} · scraping{' '}
                                {usage.scrapeCredits}/{usage.scrapeLimit}{' '}
                                credits
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="space-y-6 lg:col-span-2">
                        {(jobs.length > 0 || pendingScrapes.length > 0) && (
                            <section className="space-y-3">
                                <h2 className="font-medium">
                                    Running and waiting
                                </h2>
                                <div className="divide-y rounded-xl border">
                                    {jobs.map((job) => (
                                        <div
                                            key={`job-${job.id}`}
                                            className="flex items-center justify-between gap-3 p-3 text-sm"
                                        >
                                            <div className="min-w-0">
                                                <p className="truncate font-medium">
                                                    {job.title}
                                                </p>
                                                <p className="truncate text-xs text-muted-foreground">
                                                    {job.type} · {job.model}
                                                    {job.error &&
                                                        ` · ${job.error}`}
                                                </p>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <StatusBadge
                                                    status={job.status}
                                                />
                                                {job.chatId && (
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={chats.show([
                                                                team,
                                                                job.chatId,
                                                            ])}
                                                        >
                                                            Open
                                                        </Link>
                                                    </Button>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                    {pendingScrapes.map((scrape) => (
                                        <div
                                            key={`scrape-${scrape.id}`}
                                            className="flex items-center justify-between gap-3 p-3 text-sm"
                                        >
                                            <div className="min-w-0">
                                                <p className="truncate font-medium">
                                                    {scrape.title ??
                                                        hostOf(scrape.url)}
                                                </p>
                                                <p className="truncate text-xs text-muted-foreground">
                                                    Scrape ·{' '}
                                                    {scrape.mode ?? 'page'} ·{' '}
                                                    {scrape.status ===
                                                    'completed'
                                                        ? 'waiting for your review'
                                                        : scrape.url}
                                                </p>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <StatusBadge
                                                    status={scrape.status}
                                                />
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    asChild
                                                >
                                                    <Link
                                                        href={scrapes.show([
                                                            team,
                                                            scrape.id,
                                                        ])}
                                                    >
                                                        Review
                                                    </Link>
                                                </Button>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </section>
                        )}

                        <section className="space-y-3">
                            <div className="flex items-center justify-between">
                                <h2 className="font-medium">Recent outputs</h2>
                                <Link
                                    href={outputs.index(team)}
                                    className="text-sm text-muted-foreground hover:underline"
                                >
                                    All outputs
                                </Link>
                            </div>
                            {recentOutputs.length === 0 ? (
                                <EmptyState
                                    title="No outputs yet"
                                    description="Start a chat and ask for a post, an image or a video. Finished pieces land here."
                                />
                            ) : (
                                <div className="grid gap-4 sm:grid-cols-2">
                                    {recentOutputs.map((output) => (
                                        <OutputCard
                                            key={output.id}
                                            output={output}
                                            compact
                                            showChat
                                        />
                                    ))}
                                </div>
                            )}
                        </section>
                    </div>

                    <div className="space-y-6">
                        <section className="space-y-3">
                            <div className="flex items-center justify-between">
                                <h2 className="font-medium">Recent chats</h2>
                                <Link
                                    href={chats.index(team)}
                                    className="text-sm text-muted-foreground hover:underline"
                                >
                                    All chats
                                </Link>
                            </div>
                            {recentChats.length === 0 ? (
                                <EmptyState title="No chats yet" />
                            ) : (
                                <div className="divide-y rounded-xl border">
                                    {recentChats.map((chat) => (
                                        <Link
                                            key={chat.id}
                                            href={chats.show([team, chat.id])}
                                            className="block p-3 text-sm hover:bg-accent/50"
                                        >
                                            <p className="truncate font-medium">
                                                {chat.title}
                                            </p>
                                            <p className="truncate text-xs text-muted-foreground">
                                                {chat.project
                                                    ? chat.project.name
                                                    : 'Free chat'}{' '}
                                                · {timeAgo(chat.updatedAt)}
                                            </p>
                                        </Link>
                                    ))}
                                </div>
                            )}
                        </section>

                        <section className="space-y-3">
                            <h2 className="font-medium">Workspace activity</h2>
                            {activity.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    Nothing yet.
                                </p>
                            ) : (
                                <ul className="space-y-2 text-sm">
                                    {activity.map((entry) => (
                                        <li
                                            key={entry.id}
                                            className="text-muted-foreground"
                                        >
                                            <span className="text-foreground">
                                                {entry.causer ?? 'Someone'}
                                            </span>{' '}
                                            {entry.description}{' '}
                                            {entry.subject
                                                .replace(
                                                    /([a-z])([A-Z])/g,
                                                    '$1 $2',
                                                )
                                                .toLowerCase()}{' '}
                                            · {timeAgo(entry.createdAt)}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>
                    </div>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: props.currentTeam ? dashboard(props.currentTeam.slug) : '/',
        },
    ],
});
