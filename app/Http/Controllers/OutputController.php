<?php

namespace App\Http\Controllers;

use App\Enums\ContextType;
use App\Enums\OutputStatus;
use App\Enums\OutputType;
use App\Http\Resources\OutputResource;
use App\Jobs\ProcessVideoOutput;
use App\Models\Output;
use App\Models\Team;
use App\Services\Usage\UsageLimitReached;
use App\Services\Usage\UsageMeter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class OutputController extends Controller
{
    public function index(Request $request, Team $currentTeam): Response
    {
        $outputs = QueryBuilder::for($currentTeam->outputs()->with(['media', 'team', 'chat', 'user']))
            ->allowedFilters(
                AllowedFilter::exact('type'),
                AllowedFilter::exact('status'),
                AllowedFilter::callback('search', fn ($query, $value) => $query->where(fn ($q) => $q
                    ->where('title', 'like', "%{$value}%")
                    ->orWhere('content', 'like', "%{$value}%"))),
            )
            ->allowedSorts('created_at', 'title')
            ->defaultSort('-created_at')
            ->paginate(24)
            ->withQueryString();

        return Inertia::render('outputs/index', [
            'outputs' => OutputResource::collection($outputs),
            'filters' => [
                'type' => $request->input('filter.type'),
                'status' => $request->input('filter.status'),
                'search' => $request->input('filter.search'),
            ],
            'hasRunning' => $currentTeam->outputs()->whereIn('status', [OutputStatus::Pending, OutputStatus::Processing])->exists(),
        ]);
    }

    /**
     * Add a finished output to the context library, so it can be included later.
     */
    public function addToContext(Request $request, Team $currentTeam, Output $output): RedirectResponse
    {
        abort_unless($output->status === OutputStatus::Completed, 422, 'Only finished outputs can be added to context.');

        if ($output->context_item_id) {
            $this->toast('This output is already in the context library.', 'info');

            return back();
        }

        $item = $currentTeam->contextItems()->create([
            'user_id' => $request->user()->id,
            'type' => match ($output->type) {
                OutputType::Text => ContextType::Text,
                OutputType::Image => ContextType::Image,
                OutputType::Video => ContextType::Video,
            },
            'title' => $output->title,
            'body' => $output->type === OutputType::Text ? $output->content : null,
        ]);

        if ($file = $output->file()) {
            $file->copy($item, 'file');
        }

        $output->update(['context_item_id' => $item->id]);

        $this->toast('Added to the context library.');

        return back();
    }

    /**
     * Start a video the user confirmed. Videos started while a chat holds
     * scraped web content wait here for this step.
     */
    public function start(Request $request, Team $currentTeam, Output $output, UsageMeter $usage): RedirectResponse
    {
        abort_unless($output->type === OutputType::Video && $output->status === OutputStatus::AwaitingConfirmation, 422);

        $data = $request->validate(['prompt' => ['sometimes', 'required', 'string', 'max:5000']]);

        try {
            $usage->ensureCanSpend($request->user());
        } catch (UsageLimitReached $e) {
            $this->toast($e->getMessage(), 'error');

            return back();
        }

        $output->update([...$data, 'status' => OutputStatus::Pending]);

        ProcessVideoOutput::dispatch($output);

        $this->toast('Video started. It will appear when ready.');

        return back();
    }

    public function retry(Request $request, Team $currentTeam, Output $output, UsageMeter $usage): RedirectResponse
    {
        abort_unless($output->type === OutputType::Video && $output->status === OutputStatus::Failed, 422);

        try {
            $usage->ensureCanSpend($request->user());
        } catch (UsageLimitReached $e) {
            $this->toast($e->getMessage(), 'error');

            return back();
        }

        $output->update(['status' => OutputStatus::Pending, 'provider_job_id' => null, 'error' => null]);

        ProcessVideoOutput::dispatch($output);

        $this->toast('Retrying the video.');

        return back();
    }

    public function destroy(Team $currentTeam, Output $output): RedirectResponse
    {
        $output->delete();

        $this->toast('Output deleted.');

        return back();
    }
}
