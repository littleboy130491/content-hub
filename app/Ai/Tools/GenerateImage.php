<?php

namespace App\Ai\Tools;

use App\Ai\Generation;
use App\Enums\OutputStatus;
use App\Enums\OutputType;
use App\Enums\UsageKind;
use App\Services\OpenRouter\ModelCatalog;
use App\Services\Usage\UsageMeter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Files\Image as ImageFile;
use Laravel\Ai\Image;
use Laravel\Ai\Tools\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

class GenerateImage implements Tool
{
    public function __construct(protected Generation $generation) {}

    public function name(): string
    {
        return 'generate_image';
    }

    public function description(): string
    {
        return 'Generate a finished image and save it as an output. Use for any request for a picture, illustration, graphic, photo, banner or thumbnail. Write a detailed visual prompt.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'prompt' => $schema->string()->description('A detailed description of the image: subject, style, composition, colours, any text to render.')->required(),
            'title' => $schema->string()->description('A short title for the output, under 60 characters.')->required(),
            'aspect_ratio' => $schema->string()->enum(['1:1', '16:9', '9:16', '4:3', '3:4', '3:2', '2:3'])->description('Aspect ratio. Defaults to 1:1.'),
            'use_reference_images' => $schema->boolean()->description('Send the images in the chat context to the image model as references, for example to keep a product or brand style.'),
        ];
    }

    public function handle(Request $request): string
    {
        $generation = $this->generation;
        $model = $generation->models['image'];
        $meter = app(UsageMeter::class);

        try {
            $meter->ensureCanSpend($generation->user);

            $references = [];
            $catalogModel = app(ModelCatalog::class)->find($model);

            if ($request->boolean('use_reference_images') && (! $catalogModel || $catalogModel->reads('image'))) {
                $references = Media::query()
                    ->whereIn('id', $generation->referenceImageMediaIds())
                    ->get()
                    ->map(fn (Media $media) => ImageFile::fromPath($media->getPath(), $media->mime_type))
                    ->all();
            }

            $response = Image::of((string) $request->string('prompt'))
                ->attachments($references)
                ->size($request->string('aspect_ratio')->toString() ?: '1:1')
                ->generate(config('contenthub.provider'), $model);

            $image = $response->firstImage();

            $output = $generation->chat->team->outputs()->create([
                'user_id' => $generation->user->id,
                'chat_id' => $generation->chat->id,
                'chat_message_id' => $generation->assistantMessage->id,
                'type' => OutputType::Image,
                'title' => Str::limit((string) $request->string('title', 'Image'), 60),
                'prompt' => (string) $request->string('prompt'),
                'model' => $model,
                'status' => OutputStatus::Completed,
                'options' => ['aspect_ratio' => $request->string('aspect_ratio')->toString() ?: '1:1', 'references' => count($references)],
            ]);

            $extension = str_contains($image->mime(), 'jpeg') ? 'jpg' : (str_contains($image->mime(), 'webp') ? 'webp' : 'png');

            $output->addMediaFromString($image->content())
                ->usingFileName("image-{$output->id}.{$extension}")
                ->usingName($output->title)
                ->toMediaCollection('file');

            $record = $meter->recordTokens($generation->user, $generation->chat->team, UsageKind::Image, $model, $response->usage, ['output_id' => $output->id]);
            $output->update(['cost' => $record->cost]);

            return "Image created and saved as output #{$output->id} (\"{$output->title}\"). It is shown to the user below your reply; do not repeat the image or its URL.";
        } catch (Throwable $e) {
            report($e);

            return 'The image could not be generated: '.$e->getMessage().' Tell the user briefly.';
        }
    }
}
