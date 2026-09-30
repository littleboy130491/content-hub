<?php

use App\Enums\OutputType;
use Illuminate\Http\UploadedFile;

it('keeps every workspace record out of other workspaces', function () {
    [$alice, $aliceTeam] = member();
    [$bob, $bobTeam] = member();

    $item = $bobTeam->contextItems()->create(['type' => 'text', 'title' => 'Bob notes', 'body' => 'secret']);
    $project = $bobTeam->projects()->create(['name' => 'Bob project']);
    $chat = $bobTeam->chats()->create(['title' => 'Bob chat']);
    $output = $bobTeam->outputs()->create(['type' => OutputType::Text, 'title' => 'Bob output', 'content' => 'x']);

    $this->actingAs($alice);

    // Bob's records through Alice's workspace URL resolve to nothing.
    $this->get(route('context.show', ['current_team' => $aliceTeam, 'contextItem' => $item]))->assertNotFound();
    $this->get(route('projects.show', ['current_team' => $aliceTeam, 'project' => $project]))->assertNotFound();
    $this->get(route('chats.show', ['current_team' => $aliceTeam, 'chat' => $chat]))->assertNotFound();
    $this->delete(route('outputs.destroy', ['current_team' => $aliceTeam, 'output' => $output]))->assertNotFound();

    // And Alice cannot open Bob's workspace at all.
    $this->get(route('context.index', ['current_team' => $bobTeam]))->assertForbidden();
});

it('only serves files inside the workspace that owns them', function () {
    [$alice, $aliceTeam] = member();
    [$bob, $bobTeam] = member();

    $item = $bobTeam->contextItems()->create(['type' => 'image', 'title' => 'Logo']);
    $media = $item->addMedia(UploadedFile::fake()->image('logo.png'))->toMediaCollection('file');

    $this->actingAs($bob)->get(route('media.show', ['current_team' => $bobTeam, 'media' => $media]))->assertOk();
    $this->actingAs($alice)->get(route('media.show', ['current_team' => $aliceTeam, 'media' => $media]))->assertNotFound();
});

it('rejects project context from another workspace', function () {
    [$alice, $aliceTeam] = member();
    [, $bobTeam] = member();

    $project = $aliceTeam->projects()->create(['name' => 'Launch']);
    $foreign = $bobTeam->contextItems()->create(['type' => 'text', 'title' => 'Other', 'body' => 'x']);

    $this->actingAs($alice)
        ->put(route('projects.context', ['current_team' => $aliceTeam, 'project' => $project]), ['context_item_ids' => [$foreign->id]])
        ->assertSessionHasErrors('context_item_ids.0');

    expect($project->contextItems()->count())->toBe(0);
});
