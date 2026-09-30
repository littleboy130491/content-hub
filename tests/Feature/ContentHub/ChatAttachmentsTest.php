<?php

use App\Ai\Agents\ContentAgent;
use App\Services\Context\ChatContextBuilder;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    [$this->user, $this->team] = member();
    catalogModel(config('contenthub.default_models.text'), ['text', 'image']);
});

it('adds library items to a free chat and removes them again', function () {
    $item = $this->team->contextItems()->create(['type' => 'text', 'title' => 'Brand', 'body' => 'Warm tone']);
    $chat = $this->team->chats()->create(['title' => 'C']);

    $this->actingAs($this->user)
        ->post(route('chats.context.store', ['current_team' => $this->team, 'chat' => $chat]), ['context_item_ids' => [$item->id]])
        ->assertRedirect();

    $context = app(ChatContextBuilder::class)->build($chat->fresh(), $this->user);
    expect($context->text)->toContain('Warm tone')
        ->and($context->entries[0]->source)->toBe('chat');

    $this->actingAs($this->user)
        ->delete(route('chats.context.destroy', ['current_team' => $this->team, 'chat' => $chat, 'contextItem' => $item]));

    expect(app(ChatContextBuilder::class)->build($chat->fresh(), $this->user)->isEmpty())->toBeTrue();
});

it('keeps drafts out of context until they are sent with a message', function () {
    ContentAgent::fake(['Nice photo.']);
    $chat = $this->team->chats()->create(['title' => 'New chat']);

    $this->actingAs($this->user)
        ->post(route('chats.attachments.store', ['current_team' => $this->team, 'chat' => $chat]), [
            'files' => [UploadedFile::fake()->image('shoe.png')],
        ])->assertRedirect();

    $draft = $chat->fresh()->draftAttachments()->sole();
    expect(app(ChatContextBuilder::class)->build($chat->fresh(), $this->user)->isEmpty())->toBeTrue();

    $this->actingAs($this->user)
        ->postJson(route('chats.messages.store', ['current_team' => $this->team, 'chat' => $chat]), ['attachment_ids' => [$draft->id]])
        ->assertOk()
        ->streamedContent();

    $message = $chat->messages()->where('role', 'user')->sole();
    expect($draft->fresh()->getCustomProperty('message_id'))->toBe($message->id)
        ->and($chat->fresh()->title)->toBe('shoe.png');

    ContentAgent::assertPrompted(fn ($prompt) => count($prompt->attachments) === 1);

    $this->actingAs($this->user)
        ->get(route('chats.show', ['current_team' => $this->team, 'chat' => $chat]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('drafts', 0)
            ->where('messages.0.attachments.0.name', 'shoe.png'));
});

it('ignores attachment ids from another chat', function () {
    ContentAgent::fake(['Ok.']);
    $chat = $this->team->chats()->create(['title' => 'C']);
    $other = $this->team->chats()->create(['title' => 'Other']);
    $foreign = $other->addMedia(UploadedFile::fake()->image('x.png'))->toMediaCollection('attachments');

    $this->actingAs($this->user)
        ->postJson(route('chats.messages.store', ['current_team' => $this->team, 'chat' => $chat]), ['text' => 'Hi', 'attachment_ids' => [$foreign->id]])
        ->streamedContent();

    expect($foreign->fresh()->getCustomProperty('message_id'))->toBeNull();
});

it('creates a context item straight into a chat or a project', function () {
    $chat = $this->team->chats()->create(['title' => 'C']);
    $project = $this->team->projects()->create(['name' => 'P']);

    $this->actingAs($this->user)
        ->post(route('context.store', ['current_team' => $this->team]), ['type' => 'text', 'title' => 'Tone', 'body' => 'Calm', 'chat_id' => $chat->id])
        ->assertRedirect();
    $this->actingAs($this->user)
        ->post(route('context.store', ['current_team' => $this->team]), ['type' => 'text', 'title' => 'Facts', 'body' => 'Est. 2020', 'project_id' => $project->id])
        ->assertRedirect();

    expect($chat->contextItems()->pluck('title')->all())->toBe(['Tone'])
        ->and($project->contextItems()->pluck('title')->all())->toBe(['Facts']);
});
