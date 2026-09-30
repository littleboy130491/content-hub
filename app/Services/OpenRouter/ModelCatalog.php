<?php

namespace App\Services\OpenRouter;

use App\Models\AiModel;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

/**
 * Keeps a local copy of OpenRouter's model list so pickers and capability
 * checks never wait on the network.
 *
 * Video models are not in the default list; they come from the video filter,
 * with extra options (durations, aspect ratios, price per second) from
 * /videos/models.
 */
class ModelCatalog
{
    public function sync(): int
    {
        $now = now();

        $models = collect($this->rows($this->client()->get('/models')->throw()));

        $videoModels = collect($this->rows($this->client()->get('/models', ['output_modalities' => 'video'])->throw()));

        $videoOptions = collect($this->rows($this->client()->get('/videos/models')))->keyBy('id');

        $rows = $models->merge($videoModels)
            ->unique('id')
            ->map(fn (array $model) => [
                'id' => $model['id'],
                'name' => $model['name'] ?? $model['id'],
                'input_modalities' => json_encode($model['architecture']['input_modalities'] ?? ['text']),
                'output_modalities' => json_encode($model['architecture']['output_modalities'] ?? ['text']),
                'context_length' => $model['context_length'] ?? null,
                'supports_tools' => in_array('tools', $model['supported_parameters'] ?? [], true),
                'pricing' => json_encode($this->pricing($model, $videoOptions->get($model['id']))),
                'video_options' => ($options = $videoOptions->get($model['id'])) ? json_encode([
                    'durations' => $options['supported_durations'] ?? null,
                    'aspect_ratios' => $options['supported_aspect_ratios'] ?? null,
                    'resolutions' => $options['supported_resolutions'] ?? null,
                    'frame_images' => $options['supported_frame_images'] ?? null,
                ]) : null,
                'synced_at' => $now,
            ])
            ->values();

        $rows->chunk(100)->each(fn (Collection $chunk) => AiModel::upsert(
            $chunk->all(),
            ['id'],
            ['name', 'input_modalities', 'output_modalities', 'context_length', 'supports_tools', 'pricing', 'video_options', 'synced_at'],
        ));

        return $rows->count();
    }

    public function find(?string $id): ?AiModel
    {
        return $id ? AiModel::find($id) : null;
    }

    /**
     * Models that can produce the given output, for the settings and chat pickers.
     *
     * @return Collection<int, AiModel>
     */
    public function producing(string $modality): Collection
    {
        return AiModel::query()->producing($modality)->orderBy('name')->get();
    }

    public function isEmpty(): bool
    {
        return ! AiModel::query()->exists();
    }

    /**
     * @param  array<string, mixed>  $model
     * @param  array<string, mixed>|null  $videoOptions
     * @return array<string, float>
     */
    protected function pricing(array $model, ?array $videoOptions): array
    {
        $pricing = [];

        foreach (['prompt', 'completion', 'image', 'request'] as $key) {
            if (isset($model['pricing'][$key])) {
                $pricing[$key] = (float) $model['pricing'][$key];
            }
        }

        foreach ($videoOptions['pricing_skus'] ?? [] as $sku => $price) {
            $pricing['video_'.$sku] = (float) $price;
        }

        return $pricing;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function rows(Response $response): array
    {
        $data = $response->json('data');

        return is_array($data) ? array_values(array_filter($data, 'is_array')) : [];
    }

    protected function client(): PendingRequest
    {
        // The model list is public, so a key is optional here.
        return Http::baseUrl(config('contenthub.openrouter.url'))
            ->when(filled($key = config('contenthub.openrouter.key')), fn (PendingRequest $http) => $http->withToken($key))
            ->acceptJson()
            ->timeout(30)
            ->retry(2, 500, throw: false);
    }
}
