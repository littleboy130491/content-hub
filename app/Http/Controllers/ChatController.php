<?php

namespace App\Http\Controllers;

use App\Enums\PromptMode;
use App\Http\Resources\ChatMessageResource;
use App\Http\Resources\ChatResource;
use App\Http\Resources\MediaUrl;
use App\Http\Resources\OutputResource;
use App\Http\Resources\WebScrapeResource;
use App\Models\Chat;
use App\Models\Project;
use App\Models\Team;
use App\Services\Context\ChatContextBuilder;
use App\Services\Context\SettingsResolver;
use App\Services\OpenRouter\ModelCatalog;
use App\Services\Usage\UsageMeter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ChatController extends Controller
{
    public function index(Request $request, Team $currentTeam): Response
    {
        $filter = $request->string('filter', 'all')->toString();

        $chats = $currentTeam->chats()
            ->with(['project', 'user'])
            ->withCount('messages')
            ->when($filter === 'free', fn ($q) => $q->whereNull('project_id')->whereNull('archived_at'))
            ->when($filter === 'project', fn ($q) => $q->whereNotNull('project_id')->whereNull('archived_at'))
            ->when($filter === 'archived', fn ($q) => $q->whereNotNull('archived_at'))
            ->when($filter === 'all', fn ($q) => $q->whereNull('archived_at'))
            ->when($request->filled('search'), fn ($q) => $q->where('title', 'like', '%'.$request->string('search').'%'))
            ->latest('updated_at')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('chats/index', [
            'chats' => ChatResource::collection($chats),
            'filter' => $filter,
            'search' => $request->string('search')->toString(),
            'projects' => $currentTeam->projects()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Start a free chat, with zero context.
     */
    public function store(Request $request, Team $currentTeam): RedirectResponse
    {
        $chat = $currentTeam->chats()->create([
            'user_id' => $request->user()->id,
            'title' => 'New chat',
        ]);

        return to_route('chats.show', ['current_team' => $currentTeam, 'chat' => $chat]);
    }

    /**
     * Start a chat from a project; its context comes from the project.
     */
    public function storeForProject(Request $request, Team $currentTeam, Project $project): RedirectResponse
    {
        $chat = $currentTeam->chats()->create([
            'user_id' => $request->user()->id,
            'project_id' => $project->id,
            'title' => 'New chat',
        ]);

        return to_route('chats.show', ['current_team' => $currentTeam, 'chat' => $chat]);
    }

    public function show(
        Request $request,
        Team $currentTeam,
        Chat $chat,
        ChatContextBuilder $context,
        SettingsResolver $settings,
        ModelCatalog $catalog,
        UsageMeter $usage,
    ): Response {
        $chat->load(['project', 'user', 'team']);

        $effective = $settings->models($chat);

        return Inertia::render('chats/show', [
            'chat' => (new ChatResource($chat))->resolve(),
            'messages' => ChatMessageResource::collection($chat->messages()->with('user')->get())->resolve(),
            'outputs' => fn () => OutputResource::collection($chat->outputs()->with(['media', 'team', 'chat', 'user'])->oldest('id')->get())->resolve(),
            'context' => fn () => $context->inspect($chat),
            'attachments' => fn () => $chat->getMedia('attachments')->map(fn (Media $media) => [
                ...MediaUrl::of($media, $currentTeam->slug),
                'described' => filled($media->getCustomProperty('description')),
            ])->values(),
            'scrapes' => fn () => WebScrapeResource::collection($chat->scrapes()->with('chat')->latest()->get())->resolve(),
            'effective' => [
                'models' => collect($effective)->map(fn (string $id) => $catalog->find($id)?->toOption() ?? ['id' => $id, 'name' => $id, 'input' => [], 'output' => [], 'contextLength' => null, 'supportsTools' => true]),
                'promptLayers' => $settings->promptLayers($chat),
            ],
            'modelOptions' => fn () => [
                'text' => $catalog->producing('text')->map->toOption(),
                'image' => $catalog->producing('image')->map->toOption(),
                'video' => $catalog->producing('video')->map->toOption(),
            ],
            'projects' => $currentTeam->projects()->orderBy('name')->get(['id', 'name']),
            'usage' => $usage->summary($request->user()),
        ]);
    }

    /**
     * Title, archive state, per-chat overrides and excluded context items.
     */
    public function update(Request $request, Team $currentTeam, Chat $chat): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'archived' => ['sometimes', 'boolean'],
            'system_prompt' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'prompt_mode' => ['sometimes', Rule::enum(PromptMode::class)],
            'text_model' => ['sometimes', 'nullable', 'string', 'max:255'],
            'image_model' => ['sometimes', 'nullable', 'string', 'max:255'],
            'video_model' => ['sometimes', 'nullable', 'string', 'max:255'],
            'media_model' => ['sometimes', 'nullable', 'string', 'max:255'],
            'excluded_context_item_ids' => ['sometimes', 'array'],
            'excluded_context_item_ids.*' => ['integer'],
        ]);

        if (array_key_exists('archived', $data)) {
            $data['archived_at'] = $data['archived'] ? now() : null;
            unset($data['archived']);
        }

        $chat->update($data);

        return back();
    }

    /**
     * Move a free chat into a project, where it picks up the project's context
     * and instructions from the next message on.
     */
    public function move(Request $request, Team $currentTeam, Chat $chat): RedirectResponse
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer', Rule::exists('projects', 'id')->where('team_id', $currentTeam->id)],
        ]);

        $chat->update(['project_id' => $data['project_id']]);

        $this->toast('Chat moved. It now uses the project\'s context and instructions.');

        return back();
    }

    public function destroy(Team $currentTeam, Chat $chat): RedirectResponse
    {
        $chat->delete();

        $this->toast('Chat deleted.');

        return to_route('chats.index', ['current_team' => $currentTeam]);
    }
}
