import { router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useMemo, useState } from 'react';
import { NewContextForm } from '@/components/content/new-context-form';
import { UntrustedBadge } from '@/components/content/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useTeamSlug } from '@/hooks/use-team';
import chats from '@/routes/chats';
import type { LibraryOption } from '@/types';

/**
 * Adds context to one chat: pick items from the library, or create a new one
 * (which is saved to the library too).
 */
export function AddContextDialog({
    chatId,
    library,
    includedIds,
}: {
    chatId: number;
    library: LibraryOption[];
    includedIds: number[];
}) {
    const team = useTeamSlug();
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');
    const [selected, setSelected] = useState<number[]>([]);

    const available = useMemo(
        () =>
            library.filter(
                (item) =>
                    !includedIds.includes(item.id) &&
                    item.title.toLowerCase().includes(search.toLowerCase()),
            ),
        [library, includedIds, search],
    );

    const close = () => {
        setOpen(false);
        setSelected([]);
        setSearch('');
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => (next ? setOpen(true) : close())}
        >
            <DialogTrigger asChild>
                <Button variant="outline" size="sm" className="w-full">
                    <Plus /> Add context
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Add context to this chat</DialogTitle>
                    <DialogDescription>
                        Only this chat uses it. To share context across chats,
                        add it to a project.
                    </DialogDescription>
                </DialogHeader>
                <Tabs defaultValue="library">
                    <TabsList className="w-full">
                        <TabsTrigger value="library">From library</TabsTrigger>
                        <TabsTrigger value="new">New item</TabsTrigger>
                    </TabsList>
                    <TabsContent value="library" className="space-y-3 pt-2">
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search the library"
                        />
                        <div className="max-h-80 space-y-1 overflow-y-auto rounded-md border p-2">
                            {available.length === 0 ? (
                                <p className="p-2 text-sm text-muted-foreground">
                                    {library.length === 0
                                        ? 'The library is empty. Create an item in the other tab.'
                                        : 'Nothing else to add.'}
                                </p>
                            ) : (
                                available.map((item) => (
                                    <label
                                        key={item.id}
                                        className="flex cursor-pointer items-center gap-3 rounded-md p-2 hover:bg-accent/50"
                                    >
                                        <Checkbox
                                            checked={selected.includes(item.id)}
                                            onCheckedChange={(on) =>
                                                setSelected((current) =>
                                                    on === true
                                                        ? [...current, item.id]
                                                        : current.filter(
                                                              (id) =>
                                                                  id !==
                                                                  item.id,
                                                          ),
                                                )
                                            }
                                        />
                                        <span className="min-w-0 flex-1 truncate text-sm">
                                            {item.title}
                                        </span>
                                        {item.untrusted && <UntrustedBadge />}
                                        <Badge variant="secondary">
                                            {item.typeLabel}
                                        </Badge>
                                    </label>
                                ))
                            )}
                        </div>
                        <div className="flex justify-end">
                            <Button
                                disabled={selected.length === 0}
                                onClick={() =>
                                    router.post(
                                        chats.context.store.url([team, chatId]),
                                        { context_item_ids: selected },
                                        {
                                            preserveScroll: true,
                                            onSuccess: close,
                                        },
                                    )
                                }
                            >
                                Add {selected.length > 0 ? selected.length : ''}{' '}
                                to chat
                            </Button>
                        </div>
                    </TabsContent>
                    <TabsContent value="new">
                        <NewContextForm
                            chatId={chatId}
                            submitLabel="Save and add to chat"
                            onSuccess={close}
                        />
                    </TabsContent>
                </Tabs>
            </DialogContent>
        </Dialog>
    );
}
