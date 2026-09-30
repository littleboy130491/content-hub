<?php

use App\Ai\Agents\MediaReaderAgent;
use App\Services\Context\ChatContextBuilder;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    [$this->user, $this->team] = member();
    $this->builder = app(ChatContextBuilder::class);
});

it('gives a free chat zero context', function () {
    $this->team->contextItems()->create(['type' => 'text', 'title' => 'Brand', 'body' => 'Warm tone']);
    $chat = $this->team->chats()->create(['title' => 'Free']);

    expect($this->builder->build($chat, $this->user)->isEmpty())->toBeTrue();
});

it('fills a project chat with the project context only', function () {
    $included = $this->team->contextItems()->create(['type' => 'text', 'title' => 'Brand', 'body' => 'Warm tone']);
    $this->team->contextItems()->create(['type' => 'text', 'title' => 'Other', 'body' => 'Not included']);
    $project = $this->team->projects()->create(['name' => 'Launch']);
    $project->contextItems()->attach($included);

    $chat = $this->team->chats()->create(['title' => 'C', 'project_id' => $project->id]);
    $context = $this->builder->build($chat->fresh(), $this->user);

    expect($context->text)->toContain('Warm tone')->not->toContain('Not included');
});

it('leaves out items the chat excludes', function () {
    $item = $this->team->contextItems()->create(['type' => 'text', 'title' => 'Brand', 'body' => 'Warm tone']);
    $project = $this->team->projects()->create(['name' => 'Launch']);
    $project->contextItems()->attach($item);
    $chat = $this->team->chats()->create(['title' => 'C', 'project_id' => $project->id, 'excluded_context_item_ids' => [$item->id]]);

    expect($this->builder->build($chat->fresh(), $this->user)->text)->not->toContain('Warm tone');
});

it('sends images as is to a model that reads images', function () {
    catalogModel('vision/model', ['text', 'image']);
    $this->team->workspaceSettings()->update(['text_model' => 'vision/model']);

    $item = $this->team->contextItems()->create(['type' => 'image', 'title' => 'Logo']);
    $item->addMedia(UploadedFile::fake()->image('logo.png'))->toMediaCollection('file');
    $project = $this->team->projects()->create(['name' => 'P']);
    $project->contextItems()->attach($item);
    $chat = $this->team->chats()->create(['title' => 'C', 'project_id' => $project->id]);

    MediaReaderAgent::fake()->preventStrayPrompts();

    $context = $this->builder->build($chat->fresh(), $this->user);

    expect($context->images)->toHaveCount(1)->and($context->notes)->toBe([]);
    MediaReaderAgent::assertNeverPrompted();
});

it('describes images for a text-only model and caches the description', function () {
    catalogModel('text/only', ['text']);
    $this->team->workspaceSettings()->update(['text_model' => 'text/only']);

    $item = $this->team->contextItems()->create(['type' => 'image', 'title' => 'Logo']);
    $item->addMedia(UploadedFile::fake()->image('logo.png'))->toMediaCollection('file');
    $project = $this->team->projects()->create(['name' => 'P']);
    $project->contextItems()->attach($item);
    $chat = $this->team->chats()->create(['title' => 'C', 'project_id' => $project->id]);

    MediaReaderAgent::fake(['A blue fox logo on white.']);

    $context = $this->builder->build($chat->fresh(), $this->user);

    expect($context->images)->toBe([])
        ->and($context->text)->toContain('A blue fox logo on white.')
        ->and($context->notes[0])->toContain('described by')
        ->and($item->fresh()->description)->toBe('A blue fox logo on white.');

    // Built again, the cached description is reused.
    $this->builder->build($chat->fresh(), $this->user);
    MediaReaderAgent::assertPromptedTimes(1);
});

it('wraps web content as untrusted data', function () {
    $item = $this->team->contextItems()->create([
        'type' => 'web', 'title' => 'Competitor', 'body' => 'Their pricing page.',
        'source_url' => 'https://example.com', 'untrusted' => true,
    ]);
    $project = $this->team->projects()->create(['name' => 'P']);
    $project->contextItems()->attach($item);
    $chat = $this->team->chats()->create(['title' => 'C', 'project_id' => $project->id]);

    $context = $this->builder->build($chat->fresh(), $this->user);

    expect($context->hasUntrusted)->toBeTrue()
        ->and($context->text)->toMatch('/<untrusted_web_content id="[a-z0-9]{12}" source="Competitor">\nTheir pricing page\./');
});
