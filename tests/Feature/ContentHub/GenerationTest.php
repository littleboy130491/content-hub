<?php

use App\Ai\Generation;
use App\Ai\Tools\GenerateImage;
use App\Ai\Tools\GenerateVideo;
use App\Enums\OutputStatus;
use App\Enums\OutputType;
use App\Jobs\ProcessVideoOutput;
use App\Models\UsageRecord;
use App\Services\Context\ChatContext;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Image;
use Laravel\Ai\Tools\Request;

function generation(bool $untrusted = false): Generation
{
    [$user, $team] = member();
    $chat = $team->chats()->create(['title' => 'C', 'user_id' => $user->id]);
    $assistant = $chat->messages()->create(['role' => 'assistant', 'content' => '']);

    return new Generation($chat, $user, $assistant, new ChatContext([], '', [], [], $untrusted), [
        'text' => 'text/model', 'image' => 'image/model', 'video' => 'minimax/hailuo-3', 'media' => 'media/model',
    ]);
}

it('generates an image and saves it as an output with its file', function () {
    Image::fake();
    $generation = generation();

    $result = (new GenerateImage($generation))->handle(new Request(['prompt' => 'A red fox', 'title' => 'Fox', 'aspect_ratio' => '16:9']));

    $output = $generation->chat->outputs()->sole();

    expect($result)->toContain("output #{$output->id}")
        ->and($output->type)->toBe(OutputType::Image)
        ->and($output->chat_message_id)->toBe($generation->assistantMessage->id)
        ->and($output->file())->not->toBeNull()
        ->and(UsageRecord::count())->toBe(1);

    Image::assertGenerated(fn ($prompt) => $prompt->prompt === 'A red fox' && $prompt->size === '16:9');
});

it('queues a video, keeping options within what the model supports', function () {
    Queue::fake();
    catalogModel('minimax/hailuo-3', ['text', 'image'], ['video'], false, [
        'video_options' => ['durations' => [5, 6, 10], 'aspect_ratios' => ['16:9', '9:16']],
        'pricing' => ['video_duration_seconds' => 0.13],
    ]);
    $generation = generation();

    (new GenerateVideo($generation))->handle(new Request(['prompt' => 'Waves', 'title' => 'Sea', 'duration' => 99, 'aspect_ratio' => '1:1']));

    $output = $generation->chat->outputs()->sole();

    expect($output->status)->toBe(OutputStatus::Pending)
        ->and($output->options['duration'])->toBe(5)
        ->and($output->options['aspect_ratio'])->toBe('16:9')
        ->and((float) $output->cost)->toBe(0.65);

    Queue::assertPushed(ProcessVideoOutput::class);
});

it('waits for confirmation before a video while the chat holds web content', function () {
    Queue::fake();
    $generation = generation(untrusted: true);

    $result = (new GenerateVideo($generation))->handle(new Request(['prompt' => 'Waves', 'title' => 'Sea']));

    expect($generation->chat->outputs()->sole()->status)->toBe(OutputStatus::AwaitingConfirmation)
        ->and($result)->toContain('Start video');

    Queue::assertNothingPushed();
});

it('runs a video job through submit, poll and download', function () {
    Http::fake([
        'openrouter.ai/api/v1/videos/job-1/content*' => Http::response('MP4BYTES', 200, ['Content-Type' => 'video/mp4']),
        'openrouter.ai/api/v1/videos/job-1' => Http::sequence()
            ->push(['id' => 'job-1', 'status' => 'in_progress'])
            ->push(['id' => 'job-1', 'status' => 'completed', 'usage' => ['cost' => 0.65]]),
        'openrouter.ai/api/v1/videos' => Http::response(['id' => 'job-1', 'status' => 'pending'], 202),
    ]);

    [$user, $team] = member();
    $output = $team->outputs()->create([
        'user_id' => $user->id, 'type' => OutputType::Video, 'title' => 'Sea', 'prompt' => 'Waves',
        'model' => 'minimax/hailuo-3', 'status' => OutputStatus::Pending, 'options' => ['duration' => 5],
    ]);

    ProcessVideoOutput::dispatch($output);

    $output->refresh();

    expect($output->status)->toBe(OutputStatus::Completed)
        ->and($output->provider_job_id)->toBe('job-1')
        ->and((float) $output->cost)->toBe(0.65)
        ->and(file_get_contents($output->file()->getPath()))->toBe('MP4BYTES');

    Http::assertSent(fn ($request) => $request->url() === 'https://openrouter.ai/api/v1/videos'
        && $request['model'] === 'minimax/hailuo-3' && $request['duration'] === 5);
});

it('marks a video failed when the provider fails', function () {
    Http::fake([
        'openrouter.ai/api/v1/videos/job-2' => Http::response(['id' => 'job-2', 'status' => 'failed', 'error' => 'Content policy violation']),
        'openrouter.ai/api/v1/videos' => Http::response(['id' => 'job-2'], 202),
    ]);

    [$user, $team] = member();
    $output = $team->outputs()->create([
        'user_id' => $user->id, 'type' => OutputType::Video, 'title' => 'Sea', 'prompt' => 'Waves',
        'model' => 'minimax/hailuo-3', 'status' => OutputStatus::Pending,
    ]);

    ProcessVideoOutput::dispatch($output);

    expect($output->fresh())
        ->status->toBe(OutputStatus::Failed)
        ->error->toBe('Content policy violation');
});
