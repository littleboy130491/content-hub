<?php

namespace App\Http\Controllers;

use App\Enums\ContextType;
use App\Http\Resources\ContextItemResource;
use App\Models\ContextItem;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * The workspace's context library: text, images, video and saved web pages.
 */
class ContextItemController extends Controller
{
    public function index(Request $request, Team $currentTeam): Response
    {
        $items = QueryBuilder::for($currentTeam->contextItems()->with(['media', 'user', 'team'])->withCount('projects'))
            ->allowedFilters(
                AllowedFilter::exact('type'),
                AllowedFilter::callback('search', fn ($query, $value) => $query->where(fn ($q) => $q
                    ->where('title', 'like', "%{$value}%")
                    ->orWhere('body', 'like', "%{$value}%"))),
            )
            ->allowedSorts('title', 'created_at', 'updated_at')
            ->defaultSort('-updated_at')
            ->paginate(24)
            ->withQueryString();

        return Inertia::render('context/index', [
            'items' => ContextItemResource::collection($items),
            'filters' => [
                'type' => $request->input('filter.type'),
                'search' => $request->input('filter.search'),
            ],
            'types' => collect(ContextType::cases())->map(fn (ContextType $type) => ['value' => $type->value, 'label' => $type->label()]),
        ]);
    }

    public function store(Request $request, Team $currentTeam): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['text', 'image', 'video'])],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required_if:type,text', 'nullable', 'string', 'max:500000'],
            'file' => [
                'required_unless:type,text', 'nullable', 'file',
                $request->input('type') === 'image' ? 'mimetypes:image/png,image/jpeg,image/webp,image/gif' : 'mimetypes:video/mp4,video/quicktime,video/webm',
                'max:'.(int) (config('media-library.max_file_size') / 1024),
            ],
            // Created from a chat or project: also include it there.
            'chat_id' => ['nullable', 'integer', Rule::exists('chats', 'id')->where('team_id', $currentTeam->id)],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')->where('team_id', $currentTeam->id)],
        ]);

        $item = $currentTeam->contextItems()->create([
            'user_id' => $request->user()->id,
            'type' => $data['type'],
            'title' => $data['title'],
            'body' => $data['type'] === 'text' ? $data['body'] : null,
        ]);

        if ($request->hasFile('file') && $data['type'] !== 'text') {
            $item->addMediaFromRequest('file')->toMediaCollection('file');
        }

        if (isset($data['chat_id']) || isset($data['project_id'])) {
            if (isset($data['chat_id'])) {
                $currentTeam->chats()->findOrFail($data['chat_id'])->contextItems()->attach($item);
            }

            if (isset($data['project_id'])) {
                $currentTeam->projects()->findOrFail($data['project_id'])->contextItems()->attach($item);
            }

            $this->toast('Added to the library and included here.');

            return back();
        }

        $this->toast('Added to the context library.');

        return to_route('context.show', ['current_team' => $currentTeam, 'contextItem' => $item]);
    }

    public function show(Team $currentTeam, ContextItem $contextItem): Response
    {
        $contextItem->load(['media', 'user', 'team', 'projects']);

        return Inertia::render('context/show', [
            'item' => (new ContextItemResource($contextItem))->withBody()->resolve(),
            'projects' => $contextItem->projects->map(fn ($project) => ['id' => $project->id, 'name' => $project->name]),
        ]);
    }

    public function update(Request $request, Team $currentTeam, ContextItem $contextItem): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:500000'],
            'description' => ['nullable', 'string', 'max:100000'],
        ]);

        $contextItem->update([
            'title' => $data['title'],
            'body' => in_array($contextItem->type, [ContextType::Text, ContextType::Web], true) ? ($data['body'] ?? '') : null,
            // A description edited by hand replaces the generated one.
            'description' => $data['description'] ?? $contextItem->description,
            'description_model' => ($data['description'] ?? null) !== $contextItem->description ? 'edited' : $contextItem->description_model,
        ]);

        $this->toast('Context item saved.');

        return back();
    }

    public function destroy(Team $currentTeam, ContextItem $contextItem): RedirectResponse
    {
        $contextItem->delete();

        $this->toast('Context item deleted.');

        return to_route('context.index', ['current_team' => $currentTeam]);
    }
}
