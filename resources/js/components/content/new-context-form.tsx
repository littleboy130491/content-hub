import { Form } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { useTeamSlug } from '@/hooks/use-team';
import context from '@/routes/context';

type Kind = 'text' | 'image' | 'video';

/**
 * Creates a library item. Given a chat or project, the item is also included
 * there, and the page stays where it is.
 */
export function NewContextForm({
    chatId,
    projectId,
    submitLabel = 'Add to library',
    onSuccess,
}: {
    chatId?: number;
    projectId?: number;
    submitLabel?: string;
    onSuccess?: () => void;
}) {
    const team = useTeamSlug();
    const [kind, setKind] = useState<Kind>('text');

    return (
        <Tabs value={kind} onValueChange={(value) => setKind(value as Kind)}>
            <TabsList className="w-full">
                <TabsTrigger value="text">Text</TabsTrigger>
                <TabsTrigger value="image">Image</TabsTrigger>
                <TabsTrigger value="video">Video</TabsTrigger>
            </TabsList>
            {(['text', 'image', 'video'] as const).map((type) => (
                <TabsContent key={type} value={type}>
                    <Form
                        {...context.store.form(team)}
                        options={{ preserveScroll: true }}
                        className="space-y-4 pt-2"
                        onSuccess={onSuccess}
                        resetOnSuccess
                    >
                        {({ errors, processing }) => (
                            <>
                                <input type="hidden" name="type" value={type} />
                                {chatId && (
                                    <input
                                        type="hidden"
                                        name="chat_id"
                                        value={chatId}
                                    />
                                )}
                                {projectId && (
                                    <input
                                        type="hidden"
                                        name="project_id"
                                        value={projectId}
                                    />
                                )}
                                <div className="grid gap-2">
                                    <Label htmlFor={`${type}-title`}>
                                        Title
                                    </Label>
                                    <Input
                                        id={`${type}-title`}
                                        name="title"
                                        required
                                        placeholder={
                                            type === 'text'
                                                ? 'Brand voice'
                                                : type === 'image'
                                                  ? 'Product photo'
                                                  : 'Sample ad'
                                        }
                                    />
                                    <InputError message={errors.title} />
                                </div>
                                {type === 'text' ? (
                                    <div className="grid gap-2">
                                        <Label htmlFor="text-body">Text</Label>
                                        <Textarea
                                            id="text-body"
                                            name="body"
                                            rows={8}
                                            required
                                            placeholder="Paste notes, guidelines, product details, past posts..."
                                        />
                                        <InputError message={errors.body} />
                                    </div>
                                ) : (
                                    <div className="grid gap-2">
                                        <Label htmlFor={`${type}-file`}>
                                            File
                                        </Label>
                                        <Input
                                            id={`${type}-file`}
                                            name="file"
                                            type="file"
                                            required
                                            accept={
                                                type === 'image'
                                                    ? 'image/png,image/jpeg,image/webp,image/gif'
                                                    : 'video/mp4,video/quicktime,video/webm'
                                            }
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            {type === 'video'
                                                ? 'Chat models get a description made from sampled frames.'
                                                : 'Models that cannot read images get a description instead.'}
                                        </p>
                                        <InputError message={errors.file} />
                                    </div>
                                )}
                                <div className="flex justify-end">
                                    <Button type="submit" disabled={processing}>
                                        {submitLabel}
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                </TabsContent>
            ))}
        </Tabs>
    );
}
