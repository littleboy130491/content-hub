<?php

use App\Models\AiModel;
use App\Services\OpenRouter\ModelCatalog;
use Illuminate\Support\Facades\Http;

it('syncs text, image and video models with capabilities and prices', function () {
    Http::fake([
        'openrouter.ai/api/v1/models?output_modalities=video' => Http::response(['data' => [
            ['id' => 'minimax/hailuo-3', 'name' => 'MiniMax: H3', 'architecture' => ['input_modalities' => ['text', 'image'], 'output_modalities' => ['video']], 'pricing' => ['prompt' => '0', 'completion' => '0']],
        ]]),
        'openrouter.ai/api/v1/videos/models' => Http::response(['data' => [
            ['id' => 'minimax/hailuo-3', 'supported_durations' => [5, 10], 'supported_aspect_ratios' => ['16:9'], 'pricing_skus' => ['duration_seconds' => '0.13']],
        ]]),
        'openrouter.ai/api/v1/models' => Http::response(['data' => [
            ['id' => 'anthropic/claude-sonnet-5', 'name' => 'Claude Sonnet 5', 'context_length' => 1000000, 'supported_parameters' => ['tools'],
                'architecture' => ['input_modalities' => ['text', 'image'], 'output_modalities' => ['text']], 'pricing' => ['prompt' => '0.000002', 'completion' => '0.00001']],
        ]]),
    ]);

    expect(app(ModelCatalog::class)->sync())->toBe(2);

    $text = AiModel::find('anthropic/claude-sonnet-5');
    $video = AiModel::find('minimax/hailuo-3');

    expect($text->supports_tools)->toBeTrue()
        ->and($text->reads('image'))->toBeTrue()
        ->and($text->price('prompt'))->toBe(0.000002)
        ->and($video->produces('video'))->toBeTrue()
        ->and($video->video_options['durations'])->toBe([5, 10])
        ->and($video->price('video_duration_seconds'))->toBe(0.13)
        ->and(app(ModelCatalog::class)->producing('video')->pluck('id')->all())->toBe(['minimax/hailuo-3']);
});
