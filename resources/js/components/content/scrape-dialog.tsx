import { Form } from '@inertiajs/react';
import { Globe } from 'lucide-react';
import type { PropsWithChildren } from 'react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTeamSlug } from '@/hooks/use-team';
import scrapes from '@/routes/scrapes';

/**
 * The Scrape button. Scraping only ever starts from here or the /scrape
 * command; the agent never scrapes on its own.
 */
export function ScrapeDialog({
    chatId,
    children,
}: PropsWithChildren<{ chatId?: number }>) {
    const team = useTeamSlug();
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{children}</DialogTrigger>
            <DialogContent>
                <Form
                    key={String(open)}
                    {...scrapes.store.form(team)}
                    className="space-y-6"
                    onSuccess={() => setOpen(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle className="flex items-center gap-2">
                                    <Globe className="size-5" /> Scrape a web
                                    page
                                </DialogTitle>
                                <DialogDescription>
                                    Firecrawl fetches the page and the agent
                                    decides between one page and a small crawl
                                    of the same site. You review the cleaned
                                    text before it is used anywhere.
                                </DialogDescription>
                            </DialogHeader>

                            {chatId && (
                                <input
                                    type="hidden"
                                    name="chat_id"
                                    value={chatId}
                                />
                            )}

                            <div className="grid gap-2">
                                <Label htmlFor="scrape-url">Web address</Label>
                                <Input
                                    id="scrape-url"
                                    name="url"
                                    placeholder="https://example.com/page"
                                    required
                                    autoFocus
                                />
                                <InputError message={errors.url} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="scrape-instruction">
                                    What do you need from it? (optional)
                                </Label>
                                <Textarea
                                    id="scrape-instruction"
                                    name="instruction"
                                    rows={2}
                                    placeholder="For example: the whole site, to learn about the brand"
                                />
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">Cancel</Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    Scrape
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
