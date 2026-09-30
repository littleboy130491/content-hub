<?php

namespace App\Services\OpenRouter;

use App\Contracts\VideoGenerator;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * OpenRouter's asynchronous video API: submit, poll, then download.
 */
class OpenRouterVideoGenerator implements VideoGenerator
{
    public function submit(string $model, string $prompt, array $options = [], array $frameImages = []): string
    {
        $body = array_filter([
            'model' => $model,
            'prompt' => $prompt,
            'duration' => $options['duration'] ?? null,
            'aspect_ratio' => $options['aspect_ratio'] ?? null,
            'resolution' => $options['resolution'] ?? null,
            'frame_images' => collect($frameImages)->take(1)->map(fn (array $image) => [
                'type' => 'image_url',
                'image_url' => ['url' => 'data:'.$image['mime'].';base64,'.$image['data']],
                'frame_type' => 'first_frame',
            ])->all() ?: null,
        ], fn ($value) => $value !== null);

        $response = $this->client()->post('/videos', $body);

        if ($response->failed() || ! $response->json('id')) {
            throw new RuntimeException('The video job could not be started: '.($response->json('error.message') ?? $response->status()));
        }

        return $response->json('id');
    }

    public function status(string $jobId): array
    {
        $response = $this->client()->get("/videos/{$jobId}");

        if ($response->failed()) {
            throw new RuntimeException('Could not read the video job status: '.$response->status());
        }

        return [
            'status' => $response->json('status', 'pending'),
            'error' => is_array($error = $response->json('error')) ? ($error['message'] ?? json_encode($error)) : $error,
            'cost' => $response->json('usage.cost') !== null ? (float) $response->json('usage.cost') : null,
        ];
    }

    public function download(string $jobId): string
    {
        $response = $this->client()->timeout(300)->get("/videos/{$jobId}/content", ['index' => 0]);

        if ($response->failed()) {
            throw new RuntimeException('Could not download the finished video: '.$response->status());
        }

        return $response->body();
    }

    protected function client(): PendingRequest
    {
        return Http::baseUrl(config('contenthub.openrouter.url'))
            ->withToken((string) config('contenthub.openrouter.key'))
            ->acceptJson()
            ->timeout(60);
    }
}
