import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { OutputCard } from '@/components/content/output-card';
import {
    EmptyState,
    PageHeader,
    Pagination,
} from '@/components/content/page-header';
import { Input } from '@/components/ui/input';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { usePollWhile } from '@/hooks/use-poll-while';
import { useTeamSlug } from '@/hooks/use-team';
import outputsRoutes from '@/routes/outputs';
import type { Output, Paginated } from '@/types';

type Props = {
    outputs: Paginated<Output>;
    filters: {
        type: string | null;
        status: string | null;
        search: string | null;
    };
    hasRunning: boolean;
};

export default function OutputsIndex({ outputs, filters, hasRunning }: Props) {
    const team = useTeamSlug();
    const [search, setSearch] = useState(filters.search ?? '');

    usePollWhile(hasRunning, ['outputs', 'hasRunning']);

    const go = (next: Partial<Props['filters']>) => {
        const merged = { ...filters, search, ...next };

        router.get(
            outputsRoutes.index.url(team),
            {
                'filter[type]': merged.type || undefined,
                'filter[status]': merged.status || undefined,
                'filter[search]': merged.search || undefined,
            },
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Outputs" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Outputs"
                    description="Finished text, images and videos from every chat in this workspace. Add any of them to the context library to reuse them."
                />

                <div className="flex flex-wrap items-center gap-3">
                    <Tabs
                        value={filters.type ?? 'all'}
                        onValueChange={(value) =>
                            go({ type: value === 'all' ? null : value })
                        }
                    >
                        <TabsList>
                            <TabsTrigger value="all">All</TabsTrigger>
                            <TabsTrigger value="text">Text</TabsTrigger>
                            <TabsTrigger value="image">Images</TabsTrigger>
                            <TabsTrigger value="video">Videos</TabsTrigger>
                        </TabsList>
                    </Tabs>
                    <Tabs
                        value={filters.status ?? 'all'}
                        onValueChange={(value) =>
                            go({ status: value === 'all' ? null : value })
                        }
                    >
                        <TabsList>
                            <TabsTrigger value="all">Any status</TabsTrigger>
                            <TabsTrigger value="completed">Ready</TabsTrigger>
                            <TabsTrigger value="processing">
                                Generating
                            </TabsTrigger>
                            <TabsTrigger value="failed">Failed</TabsTrigger>
                        </TabsList>
                    </Tabs>
                    <form
                        className="flex-1"
                        onSubmit={(event) => {
                            event.preventDefault();
                            go({});
                        }}
                    >
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search outputs"
                            className="max-w-sm"
                        />
                    </form>
                </div>

                {outputs.data.length === 0 ? (
                    <EmptyState
                        title="No outputs match"
                        description="Images and videos are saved here automatically. Save a text reply from a chat to add it."
                    />
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {outputs.data.map((output) => (
                            <OutputCard
                                key={output.id}
                                output={output}
                                showChat
                            />
                        ))}
                    </div>
                )}

                <Pagination links={outputs.links} />
            </div>
        </>
    );
}
