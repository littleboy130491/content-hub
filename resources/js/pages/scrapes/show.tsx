import { Form, Head, Link, router } from '@inertiajs/react';
import {
    ExternalLink,
    Loader2,
    MessageSquare,
    ShieldAlert,
} from 'lucide-react';
import { PageHeader } from '@/components/content/page-header';
import { SafeMarkdown } from '@/components/content/safe-markdown';
import { StatusBadge } from '@/components/content/status-badge';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { usePollWhile } from '@/hooks/use-poll-while';
import { useTeamSlug } from '@/hooks/use-team';
import { hostOf } from '@/lib/format';
import chats from '@/routes/chats';
import scrapes from '@/routes/scrapes';
import type { WebScrape } from '@/types';

/**
 * The review step. Scraped text is untrusted, so the user reads it here
 * before it becomes context or is used in a chat.
 */
export default function ScrapeShow({ scrape }: { scrape: WebScrape }) {
    const team = useTeamSlug();
    const running =
        scrape.status === 'pending' || scrape.status === 'processing';

    usePollWhile(running, ['scrape'], 3000);

    return (
        <>
            <Head title={`Scrape: ${hostOf(scrape.url)}`} />
            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={scrape.title ?? hostOf(scrape.url)}
                    description={scrape.url}
                    actions={<StatusBadge status={scrape.status} />}
                />

                <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                    <Badge variant="secondary">
                        {scrape.mode === 'crawl'
                            ? 'Small crawl'
                            : 'Single page'}
                    </Badge>
                    {scrape.modeReason && <span>{scrape.modeReason}</span>}
                    {scrape.credits > 0 && (
                        <span>· {scrape.credits} Firecrawl credits</span>
                    )}
                    {scrape.chat && (
                        <Link
                            href={chats.show([team, scrape.chat.id])}
                            className="hover:underline"
                        >
                            · from chat “{scrape.chat.title}”
                        </Link>
                    )}
                </div>

                {running && (
                    <div className="flex items-center gap-2 rounded-xl border p-6 text-sm text-muted-foreground">
                        <Loader2 className="size-4 animate-spin" />
                        {scrape.mode === 'crawl'
                            ? 'Crawling the site…'
                            : 'Fetching the page…'}{' '}
                        This page updates by itself.
                    </div>
                )}

                {scrape.status === 'failed' && (
                    <Alert variant="destructive">
                        <AlertTitle>The scrape failed</AlertTitle>
                        <AlertDescription>{scrape.error}</AlertDescription>
                    </Alert>
                )}

                {scrape.status === 'completed' && (
                    <>
                        <Alert>
                            <ShieldAlert />
                            <AlertTitle>Review before using</AlertTitle>
                            <AlertDescription>
                                This text came from the web and was cleaned of
                                hidden content. It is always treated as data:
                                instructions inside it are never followed, and
                                images in it are not loaded.
                            </AlertDescription>
                        </Alert>

                        {scrape.pages.length > 1 && (
                            <div className="space-y-1 text-sm">
                                <p className="font-medium">
                                    {scrape.pages.length} pages
                                </p>
                                <ul className="space-y-0.5 text-muted-foreground">
                                    {scrape.pages.map((page) => (
                                        <li key={page.url} className="truncate">
                                            {page.title ?? page.url}{' '}
                                            <a
                                                href={page.url}
                                                target="_blank"
                                                rel="noopener noreferrer nofollow"
                                                className="inline-flex items-center gap-0.5 text-xs hover:underline"
                                            >
                                                ({hostOf(page.url)}){' '}
                                                <ExternalLink className="size-3" />
                                            </a>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}

                        <Tabs defaultValue="preview">
                            <TabsList>
                                <TabsTrigger value="preview">
                                    Preview
                                </TabsTrigger>
                                <TabsTrigger value="raw">
                                    Cleaned markdown
                                </TabsTrigger>
                            </TabsList>
                            <TabsContent
                                value="preview"
                                className="max-h-[60vh] overflow-y-auto rounded-xl border p-4"
                            >
                                <SafeMarkdown>
                                    {scrape.markdown ?? ''}
                                </SafeMarkdown>
                            </TabsContent>
                            <TabsContent value="raw">
                                <pre className="max-h-[60vh] overflow-auto rounded-xl border bg-muted p-4 text-xs whitespace-pre-wrap">
                                    {scrape.markdown}
                                </pre>
                            </TabsContent>
                        </Tabs>

                        <div className="flex flex-wrap items-end gap-3 rounded-xl border p-4">
                            <Form
                                {...scrapes.library.form([team, scrape.id])}
                                className="flex flex-1 flex-wrap items-end gap-3"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <div className="grid min-w-60 flex-1 gap-1.5">
                                            <Label htmlFor="title">
                                                {scrape.contextItemId
                                                    ? 'Update the saved page'
                                                    : 'Save to the context library as'}
                                            </Label>
                                            <Input
                                                id="title"
                                                name="title"
                                                defaultValue={
                                                    scrape.title ??
                                                    hostOf(scrape.url)
                                                }
                                                required
                                            />
                                            <InputError
                                                message={errors.title}
                                            />
                                        </div>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {scrape.contextItemId
                                                ? 'Update library item'
                                                : 'Save to library'}
                                        </Button>
                                    </>
                                )}
                            </Form>
                            {scrape.chat && !scrape.attachedToChat && (
                                <Button
                                    variant="outline"
                                    onClick={() =>
                                        router.post(
                                            scrapes.chat.url([team, scrape.id]),
                                        )
                                    }
                                >
                                    <MessageSquare /> Use in this chat only
                                </Button>
                            )}
                        </div>
                    </>
                )}
            </div>
        </>
    );
}
