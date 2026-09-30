<?php

use App\Enums\OutputType;
use App\Enums\ScrapeStatus;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;

it('renders every Content Hub page', function () {
    [$user, $team] = member();
    catalogModel('anthropic/claude-sonnet-5', ['text', 'image']);

    $item = $team->contextItems()->create(['type' => 'text', 'title' => 'Brand', 'body' => 'Warm tone']);
    $project = $team->projects()->create(['name' => 'Launch']);
    $project->contextItems()->attach($item);
    $chat = $team->chats()->create(['title' => 'C', 'project_id' => $project->id, 'user_id' => $user->id]);
    $chat->messages()->create(['role' => 'user', 'content' => 'Hi', 'user_id' => $user->id]);
    $team->outputs()->create(['type' => OutputType::Text, 'title' => 'Post', 'content' => 'x', 'chat_id' => $chat->id]);
    $scrape = $team->webScrapes()->create(['url' => 'https://example.com', 'status' => ScrapeStatus::Completed, 'markdown' => 'Page']);

    $this->actingAs($user);

    $pages = [
        [route('dashboard', ['current_team' => $team]), 'dashboard', 'counts'],
        [route('context.index', ['current_team' => $team]), 'context/index', 'items'],
        [route('context.show', ['current_team' => $team, 'contextItem' => $item]), 'context/show', 'item'],
        [route('projects.index', ['current_team' => $team]), 'projects/index', 'projects'],
        [route('projects.show', ['current_team' => $team, 'project' => $project]), 'projects/show', 'library'],
        [route('chats.index', ['current_team' => $team]), 'chats/index', 'chats'],
        [route('chats.show', ['current_team' => $team, 'chat' => $chat]), 'chats/show', 'context'],
        [route('outputs.index', ['current_team' => $team]), 'outputs/index', 'outputs'],
        [route('scrapes.show', ['current_team' => $team, 'webScrape' => $scrape]), 'scrapes/show', 'scrape'],
        [route('workspace.settings', ['current_team' => $team]), 'workspace/settings', 'modelOptions'],
    ];

    foreach ($pages as [$url, $component, $prop]) {
        $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page->component($component)->has($prop));
    }
});

it('shows how each context item reaches the chat model', function () {
    [$user, $team] = member();
    catalogModel('anthropic/claude-sonnet-5', ['text', 'image']);

    $item = $team->contextItems()->create(['type' => 'video', 'title' => 'Ad']);
    $item->addMedia(UploadedFile::fake()->create('ad.mp4', 10, 'video/mp4'))->toMediaCollection('file');
    $project = $team->projects()->create(['name' => 'Launch']);
    $project->contextItems()->attach($item);
    $chat = $team->chats()->create(['title' => 'C', 'project_id' => $project->id]);

    $this->actingAs($user)
        ->get(route('chats.show', ['current_team' => $team, 'chat' => $chat]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('context.0.handling', 'convert')
            ->where('context.0.type', 'video')
            ->where('effective.models.text.id', 'anthropic/claude-sonnet-5'));
});
