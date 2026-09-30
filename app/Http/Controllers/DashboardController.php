<?php

namespace App\Http\Controllers;

use App\Enums\OutputStatus;
use App\Http\Resources\ChatResource;
use App\Http\Resources\OutputResource;
use App\Http\Resources\WebScrapeResource;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Services\Usage\UsageMeter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class DashboardController extends Controller
{
    public function __invoke(Request $request, Team $currentTeam, UsageMeter $usage): Response
    {
        $email = strtolower($request->user()->email);

        $pendingInvitations = TeamInvitation::query()
            ->with(['inviter', 'team'])
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNull('accepted_at')
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now()))
            ->latest()
            ->get()
            ->map(fn (TeamInvitation $invitation) => [
                'code' => $invitation->code,
                'inviterName' => $invitation->inviter->name,
                'team' => [
                    'name' => $invitation->team->name,
                    'slug' => $invitation->team->slug,
                ],
            ]);

        $running = [OutputStatus::Pending, OutputStatus::Processing, OutputStatus::AwaitingConfirmation];

        return Inertia::render('dashboard', [
            'pendingInvitations' => $pendingInvitations,
            'counts' => [
                'context' => $currentTeam->contextItems()->count(),
                'projects' => $currentTeam->projects()->count(),
                'chats' => $currentTeam->chats()->whereNull('archived_at')->count(),
                'outputs' => $currentTeam->outputs()->where('status', OutputStatus::Completed)->count(),
            ],
            'recentChats' => ChatResource::collection(
                $currentTeam->chats()->with(['project', 'user'])->withCount('messages')->whereNull('archived_at')->latest('updated_at')->take(6)->get()
            )->resolve(),
            'recentOutputs' => OutputResource::collection(
                $currentTeam->outputs()->with(['media', 'team', 'chat', 'user'])->where('status', OutputStatus::Completed)->latest()->take(8)->get()
            )->resolve(),
            'jobs' => OutputResource::collection(
                $currentTeam->outputs()->with(['media', 'team', 'chat', 'user'])
                    ->where(fn ($q) => $q->whereIn('status', $running)->orWhere(fn ($q) => $q->where('status', OutputStatus::Failed)->where('updated_at', '>=', now()->subDay())))
                    ->latest()->take(10)->get()
            )->resolve(),
            'scrapes' => WebScrapeResource::collection(
                $currentTeam->webScrapes()->with(['chat', 'team'])->whereNull('context_item_id')->where('attached_to_chat', false)->latest()->take(5)->get()
            )->resolve(),
            'activity' => Activity::query()
                ->where('log_name', 'workspace-'.$currentTeam->id)
                ->with('causer')
                ->latest()
                ->take(10)
                ->get()
                ->map(fn (Activity $activity) => [
                    'id' => $activity->id,
                    'description' => $activity->description,
                    'subject' => class_basename((string) $activity->subject_type),
                    'causer' => $activity->causer?->getAttribute('name'),
                    'createdAt' => $activity->created_at?->toIso8601String(),
                ]),
            'usage' => $usage->summary($request->user()),
            'setup' => [
                'openrouter' => filled(config('contenthub.openrouter.key')),
                'firecrawl' => filled(config('contenthub.firecrawl.key')),
            ],
        ]);
    }
}
