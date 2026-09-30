<?php

namespace App\Http\Controllers;

use App\Enums\PromptMode;
use App\Http\Resources\ChatResource;
use App\Http\Resources\ContextItemResource;
use App\Models\Project;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(Team $currentTeam): Response
    {
        $projects = $currentTeam->projects()
            ->withCount(['contextItems', 'chats'])
            ->latest('updated_at')
            ->get()
            ->map(fn (Project $project) => [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'hasInstructions' => filled($project->instructions),
                'contextItemsCount' => $project->context_items_count,
                'chatsCount' => $project->chats_count,
                'updatedAt' => $project->updated_at->toIso8601String(),
            ]);

        return Inertia::render('projects/index', ['projects' => $projects]);
    }

    public function store(Request $request, Team $currentTeam): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $project = $currentTeam->projects()->create([...$data, 'user_id' => $request->user()->id]);

        return to_route('projects.show', ['current_team' => $currentTeam, 'project' => $project]);
    }

    public function show(Team $currentTeam, Project $project): Response
    {
        $project->load('contextItems');

        return Inertia::render('projects/show', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'instructions' => $project->instructions,
                'instructionsMode' => $project->instructions_mode->value,
                'contextItemIds' => $project->contextItems->pluck('id'),
            ],
            'workspacePrompt' => $currentTeam->workspaceSettings()->system_prompt,
            'library' => ContextItemResource::collection(
                $currentTeam->contextItems()->with(['media', 'team'])->orderBy('title')->get()
            )->resolve(),
            'chats' => ChatResource::collection(
                $project->chats()->with(['project', 'user'])->withCount('messages')->whereNull('archived_at')->latest('updated_at')->get()
            )->resolve(),
        ]);
    }

    public function update(Request $request, Team $currentTeam, Project $project): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'instructions' => ['nullable', 'string', 'max:20000'],
            'instructions_mode' => ['required', Rule::enum(PromptMode::class)],
        ]);

        $project->update($data);

        $this->toast('Project saved.');

        return back();
    }

    /**
     * Replace the set of library items included in the project.
     */
    public function syncContext(Request $request, Team $currentTeam, Project $project): RedirectResponse
    {
        $data = $request->validate([
            'context_item_ids' => ['present', 'array'],
            'context_item_ids.*' => ['integer', Rule::exists('context_items', 'id')->where('team_id', $currentTeam->id)],
        ]);

        $project->contextItems()->sync($data['context_item_ids']);
        $project->touch();

        $this->toast('Project context updated.');

        return back();
    }

    public function destroy(Team $currentTeam, Project $project): RedirectResponse
    {
        $project->delete();

        $this->toast('Project deleted. Its chats are kept as free chats.');

        return to_route('projects.index', ['current_team' => $currentTeam]);
    }
}
