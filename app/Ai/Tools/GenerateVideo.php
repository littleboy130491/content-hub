<?php

namespace App\Ai\Tools;

use App\Ai\Generation;
use App\Enums\OutputStatus;
use App\Enums\OutputType;
use App\Jobs\ProcessVideoOutput;
use App\Services\OpenRouter\ModelCatalog;
use App\Services\Usage\UsageMeter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Throwable;

class GenerateVideo implements Tool
{
    public function __construct(protected Generation $generation) {}

    public function name(): string
    {
        return 'generate_video';
    }

    public function description(): string
    {
        return 'Start generating a short video clip. It runs in the background and is saved as an output when finished. Write a detailed prompt covering scene, subjects, motion and camera.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'prompt' => $schema->string()->description('Scene, subjects, actions, camera movement, style and mood.')->required(),
            'title' => $schema->string()->description('A short title for the output, under 60 characters.')->required(),
            'duration' => $schema->integer()->description('Length in seconds. Defaults to the shortest the model supports.'),
            'aspect_ratio' => $schema->string()->enum(['16:9', '9:16', '1:1', '4:3', '3:4', '21:9'])->description('Aspect ratio. Defaults to 16:9.'),
            'use_first_frame_image' => $schema->boolean()->description('Start the video from the first image in the chat context.'),
        ];
    }

    public function handle(Request $request): string
    {
        $generation = $this->generation;
        $model = $generation->models['video'];

        try {
            app(UsageMeter::class)->ensureCanSpend($generation->user);

            $catalogModel = app(ModelCatalog::class)->find($model);

            if ($catalogModel && ! $catalogModel->produces('video')) {
                return "The chosen video model ({$model}) cannot make videos. Ask the user to pick a video model in the chat settings.";
            }

            $options = $this->options($request, $catalogModel->video_options ?? []);
            $reference = $request->boolean('use_first_frame_image') ? ($generation->referenceImageMediaIds()[0] ?? null) : null;

            // Scraped pages may carry injected instructions, so an expensive job
            // started while one is in the chat waits for the user to confirm.
            $needsConfirmation = $generation->context->hasUntrusted;

            $output = $generation->chat->team->outputs()->create([
                'user_id' => $generation->user->id,
                'chat_id' => $generation->chat->id,
                'chat_message_id' => $generation->assistantMessage->id,
                'type' => OutputType::Video,
                'title' => Str::limit((string) $request->string('title', 'Video'), 60),
                'prompt' => (string) $request->string('prompt'),
                'model' => $model,
                'status' => $needsConfirmation ? OutputStatus::AwaitingConfirmation : OutputStatus::Pending,
                'options' => [...$options, 'reference_media_id' => $reference],
                'cost' => app(UsageMeter::class)->estimateVideoCost($model, $options['duration'] ?? 5),
            ]);

            if ($needsConfirmation) {
                return "Video output #{$output->id} is prepared but not started, because this chat contains untrusted web content. Tell the user to review the prompt and press \"Start video\" on the output card to run it.";
            }

            ProcessVideoOutput::dispatch($output);

            return "Video output #{$output->id} (\"{$output->title}\") is generating in the background. Tell the user it will appear here and on the dashboard when it is ready, usually within a few minutes.";
        } catch (Throwable $e) {
            report($e);

            return 'The video could not be started: '.$e->getMessage().' Tell the user briefly.';
        }
    }

    /**
     * Keep options within what the model supports.
     *
     * @param  array<string, mixed>  $supported
     * @return array{duration?: int, aspect_ratio?: string}
     */
    protected function options(Request $request, array $supported): array
    {
        $durations = $supported['durations'] ?? null;
        $ratios = $supported['aspect_ratios'] ?? null;

        $duration = $request->integer('duration') ?: null;

        if ($durations) {
            $duration = $duration && in_array($duration, $durations, true) ? $duration : min($durations);
        }

        $ratio = $request->string('aspect_ratio')->toString() ?: '16:9';

        if ($ratios && ! in_array($ratio, $ratios, true)) {
            $ratio = $ratios[0];
        }

        return array_filter(['duration' => $duration, 'aspect_ratio' => $ratio]);
    }
}
