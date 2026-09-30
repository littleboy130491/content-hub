<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Services\OpenRouter\ModelCatalog;
use App\Services\Usage\UsageMeter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Defaults shared by all members of a workspace: the system prompt and the
 * text, image, video and media reading models.
 */
class WorkspaceSettingsController extends Controller
{
    public function edit(Request $request, Team $currentTeam, ModelCatalog $catalog, UsageMeter $usage): Response
    {
        $settings = $currentTeam->workspaceSettings();

        return Inertia::render('workspace/settings', [
            'settings' => [
                'systemPrompt' => $settings->system_prompt,
                'textModel' => $settings->text_model,
                'imageModel' => $settings->image_model,
                'videoModel' => $settings->video_model,
                'mediaModel' => $settings->media_model,
            ],
            'defaults' => config('contenthub.default_models'),
            'modelOptions' => [
                'text' => $catalog->producing('text')->map->toOption(),
                'image' => $catalog->producing('image')->map->toOption(),
                'video' => $catalog->producing('video')->map->toOption(),
            ],
            'catalogEmpty' => $catalog->isEmpty(),
            'keys' => [
                'openrouter' => filled(config('contenthub.openrouter.key')),
                'firecrawl' => filled(config('contenthub.firecrawl.key')),
            ],
            'usage' => $usage->summary($request->user()),
        ]);
    }

    public function update(Request $request, Team $currentTeam): RedirectResponse
    {
        $data = $request->validate([
            'system_prompt' => ['nullable', 'string', 'max:20000'],
            'text_model' => ['nullable', 'string', 'max:255'],
            'image_model' => ['nullable', 'string', 'max:255'],
            'video_model' => ['nullable', 'string', 'max:255'],
            'media_model' => ['nullable', 'string', 'max:255'],
        ]);

        $currentTeam->workspaceSettings()->update($data);

        $this->toast('Workspace settings saved.');

        return back();
    }

    public function syncModels(ModelCatalog $catalog): RedirectResponse
    {
        try {
            $count = $catalog->sync();
            $this->toast("Model list updated: {$count} models.");
        } catch (Throwable $e) {
            report($e);
            $this->toast('Could not load the model list: '.$e->getMessage(), 'error');
        }

        return back();
    }
}
