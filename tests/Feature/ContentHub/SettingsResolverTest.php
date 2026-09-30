<?php

use App\Enums\PromptMode;
use App\Services\Context\SettingsResolver;

beforeEach(function () {
    [$this->user, $this->team] = member();
    $this->resolver = app(SettingsResolver::class);
});

it('adds project and chat prompts to the workspace default by default', function () {
    $this->team->workspaceSettings()->update(['system_prompt' => 'Workspace voice.']);
    $project = $this->team->projects()->create(['name' => 'P', 'instructions' => 'Project rules.']);
    $chat = $this->team->chats()->create(['title' => 'C', 'project_id' => $project->id, 'system_prompt' => 'Chat rules.']);

    expect($this->resolver->systemPrompt($chat->fresh()))->toBe("Workspace voice.\n\nProject rules.\n\nChat rules.");
});

it('lets a project prompt replace the workspace default', function () {
    $this->team->workspaceSettings()->update(['system_prompt' => 'Workspace voice.']);
    $project = $this->team->projects()->create(['name' => 'P', 'instructions' => 'Only this.', 'instructions_mode' => PromptMode::Replace]);
    $chat = $this->team->chats()->create(['title' => 'C', 'project_id' => $project->id, 'system_prompt' => 'And this.']);

    expect($this->resolver->systemPrompt($chat->fresh()))->toBe("Only this.\n\nAnd this.");
});

it('lets a chat prompt replace everything above it', function () {
    $this->team->workspaceSettings()->update(['system_prompt' => 'Workspace voice.']);
    $project = $this->team->projects()->create(['name' => 'P', 'instructions' => 'Project rules.']);
    $chat = $this->team->chats()->create(['title' => 'C', 'project_id' => $project->id, 'system_prompt' => 'Just the chat.', 'prompt_mode' => PromptMode::Replace]);

    expect($this->resolver->systemPrompt($chat->fresh()))->toBe('Just the chat.');
});

it('never gives a free chat project instructions', function () {
    $this->team->workspaceSettings()->update(['system_prompt' => 'Workspace voice.']);
    $chat = $this->team->chats()->create(['title' => 'C']);

    expect($this->resolver->systemPrompt($chat->fresh()))->toBe('Workspace voice.');
});

it('picks the most specific model: chat, then workspace, then app default', function () {
    $chat = $this->team->chats()->create(['title' => 'C']);

    expect($this->resolver->model($chat->fresh(), 'text'))->toBe(config('contenthub.default_models.text'));

    $this->team->workspaceSettings()->update(['text_model' => 'workspace/model']);
    expect($this->resolver->model($chat->fresh(), 'text'))->toBe('workspace/model');

    $chat->update(['text_model' => 'chat/model']);
    expect($this->resolver->model($chat->fresh(), 'text'))->toBe('chat/model');
});
