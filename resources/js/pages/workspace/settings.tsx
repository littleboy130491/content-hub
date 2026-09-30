import { Head, router, useForm } from '@inertiajs/react';
import { CheckCircle2, RefreshCw, XCircle } from 'lucide-react';
import { ModelPicker } from '@/components/content/model-picker';
import { PageHeader } from '@/components/content/page-header';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Progress } from '@/components/ui/progress';
import { Textarea } from '@/components/ui/textarea';
import { useTeamSlug } from '@/hooks/use-team';
import { formatUsd } from '@/lib/format';
import workspace from '@/routes/workspace';
import type { ModelOption, UsageSummary } from '@/types';

type Props = {
    settings: {
        systemPrompt: string | null;
        textModel: string | null;
        imageModel: string | null;
        videoModel: string | null;
        mediaModel: string | null;
    };
    defaults: { text: string; image: string; video: string; media: string };
    modelOptions: {
        text: ModelOption[];
        image: ModelOption[];
        video: ModelOption[];
    };
    catalogEmpty: boolean;
    keys: { openrouter: boolean; firecrawl: boolean };
    usage: UsageSummary;
};

export default function WorkspaceSettings({
    settings,
    defaults,
    modelOptions,
    catalogEmpty,
    keys,
    usage,
}: Props) {
    const team = useTeamSlug();
    const form = useForm({
        system_prompt: settings.systemPrompt ?? '',
        text_model: settings.textModel,
        image_model: settings.imageModel,
        video_model: settings.videoModel,
        media_model: settings.mediaModel,
    });

    const models: [keyof typeof form.data, string, string, ModelOption[]][] = [
        [
            'text_model',
            'Chat and text',
            'Writes replies and decides the workflow. Pick one that supports tool calling to allow images and video.',
            modelOptions.text,
        ],
        ['image_model', 'Images', 'Generates images.', modelOptions.image],
        [
            'video_model',
            'Video',
            'Generates videos in the background.',
            modelOptions.video,
        ],
        [
            'media_model',
            'Media reading',
            'Describes images and video for chat models that cannot read them.',
            modelOptions.text.filter((m) => m.input.includes('image')),
        ],
    ];

    const defaultFor = (field: string) =>
        defaults[field.replace('_model', '') as keyof typeof defaults];

    return (
        <>
            <Head title="Workspace settings" />
            <div className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Workspace settings"
                    description="Defaults for every member of this workspace. Projects and chats can override them."
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Connections</CardTitle>
                        <CardDescription>
                            API keys are shared and live in the server&apos;s
                            .env file. They are never sent to the browser.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3 text-sm">
                        {(
                            [
                                [
                                    'OpenRouter',
                                    'OPENROUTER_API_KEY',
                                    keys.openrouter,
                                    'chat, images, video',
                                ],
                                [
                                    'Firecrawl',
                                    'FIRECRAWL_API_KEY',
                                    keys.firecrawl,
                                    'web scraping',
                                ],
                            ] as const
                        ).map(([name, env, ok, use]) => (
                            <div key={name} className="flex items-center gap-2">
                                {ok ? (
                                    <CheckCircle2 className="size-4 text-emerald-600" />
                                ) : (
                                    <XCircle className="size-4 text-destructive" />
                                )}
                                <span className="font-medium">{name}</span>
                                <span className="text-muted-foreground">
                                    ({use}) ·{' '}
                                    {ok ? 'connected' : `add ${env} to .env`}
                                </span>
                            </div>
                        ))}
                        <div className="flex items-center gap-3 pt-2">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    router.post(
                                        workspace.models.sync.url(team),
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <RefreshCw />{' '}
                                {catalogEmpty
                                    ? 'Load model list'
                                    : 'Refresh model list'}
                            </Button>
                            <span className="text-xs text-muted-foreground">
                                {catalogEmpty
                                    ? 'Needed for model pickers and capability checks.'
                                    : `${modelOptions.text.length} text, ${modelOptions.image.length} image and ${modelOptions.video.length} video models. Refreshed daily.`}
                            </span>
                        </div>
                    </CardContent>
                </Card>

                <form
                    className="space-y-6"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.put(workspace.settings.update.url(team), {
                            preserveScroll: true,
                        });
                    }}
                >
                    <Card>
                        <CardHeader>
                            <CardTitle>Default system prompt</CardTitle>
                            <CardDescription>
                                Applies to every chat. A project or chat prompt
                                adds to it by default, or can replace it.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Textarea
                                value={form.data.system_prompt}
                                onChange={(event) =>
                                    form.setData(
                                        'system_prompt',
                                        event.target.value,
                                    )
                                }
                                rows={8}
                                placeholder="For example: We are Acme, a bike brand. Friendly, practical, no jargon. Use British English."
                            />
                            <InputError message={form.errors.system_prompt} />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Default models</CardTitle>
                            <CardDescription>
                                Each chat can switch to another model.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-5">
                            {models.map(([field, label, help, options]) => (
                                <div key={field} className="grid gap-1.5">
                                    <Label htmlFor={field}>{label}</Label>
                                    <ModelPicker
                                        id={field}
                                        value={
                                            form.data[field] as string | null
                                        }
                                        options={options}
                                        defaultLabel={`App default (${defaultFor(field)})`}
                                        onChange={(value) =>
                                            form.setData(field, value)
                                        }
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        {help}
                                    </p>
                                </div>
                            ))}
                        </CardContent>
                    </Card>

                    <Button
                        type="submit"
                        disabled={form.processing || !form.isDirty}
                    >
                        Save settings
                    </Button>
                </form>

                <Card>
                    <CardHeader>
                        <CardTitle>Your usage this month</CardTitle>
                        <CardDescription>
                            Each account has a monthly limit, since the keys are
                            shared.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-2 text-sm">
                        <div className="flex justify-between">
                            <span>Spend</span>
                            <span>
                                {formatUsd(usage.spent) || '$0.00'} of{' '}
                                {formatUsd(usage.limit)}
                            </span>
                        </div>
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
                        <div className="flex justify-between pt-2">
                            <span>Scraping credits</span>
                            <span>
                                {usage.scrapeCredits} of {usage.scrapeLimit}
                            </span>
                        </div>
                        <Progress
                            value={
                                usage.scrapeLimit > 0
                                    ? Math.min(
                                          100,
                                          (usage.scrapeCredits /
                                              usage.scrapeLimit) *
                                              100,
                                      )
                                    : 0
                            }
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
