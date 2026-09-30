<?php

namespace App\Http\Controllers;

use App\Ai\Agents\ScrapeScopeAgent;
use App\Enums\ContextType;
use App\Enums\ScrapeStatus;
use App\Enums\UsageKind;
use App\Http\Resources\WebScrapeResource;
use App\Jobs\RunWebScrape;
use App\Models\Chat;
use App\Models\ContextItem;
use App\Models\Team;
use App\Models\User;
use App\Models\WebScrape;
use App\Services\Firecrawl\FirecrawlClient;
use App\Services\Safety\ContentSanitizer;
use App\Services\Usage\UsageLimitReached;
use App\Services\Usage\UsageMeter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;

/**
 * User-invoked scraping. The agent never scrapes on its own: a scrape starts
 * only here, from the /scrape command or the Scrape button.
 */
class WebScrapeController extends Controller
{
    public function __construct(
        protected ContentSanitizer $sanitizer,
        protected UsageMeter $usage,
        protected FirecrawlClient $firecrawl,
    ) {}

    public function store(Request $request, Team $currentTeam): RedirectResponse
    {
        $data = $request->validate([
            'url' => ['required', 'string', 'max:2048'],
            'instruction' => ['nullable', 'string', 'max:1000'],
            'chat_id' => ['nullable', 'integer', Rule::exists('chats', 'id')->where('team_id', $currentTeam->id)],
        ]);

        $url = $this->validatedUrl($data['url']);

        if (! $this->firecrawl->configured()) {
            throw ValidationException::withMessages(['url' => 'Scraping is not set up: add FIRECRAWL_API_KEY to the server\'s .env file.']);
        }

        try {
            $this->usage->ensureCanScrape($request->user());
        } catch (UsageLimitReached $e) {
            throw ValidationException::withMessages(['url' => $e->getMessage()]);
        }

        [$mode, $reason] = $this->decideScope($url, $data['instruction'] ?? null, $request->user(), $currentTeam);

        $scrape = $currentTeam->webScrapes()->create([
            'user_id' => $request->user()->id,
            'chat_id' => $data['chat_id'] ?? null,
            'url' => $url,
            'instruction' => $data['instruction'] ?? null,
            'mode' => $mode,
            'mode_reason' => $reason,
            'status' => ScrapeStatus::Pending,
        ]);

        RunWebScrape::dispatch($scrape);

        return to_route('scrapes.show', ['current_team' => $currentTeam, 'webScrape' => $scrape]);
    }

    /**
     * The preview: the user reviews the cleaned text before it is used anywhere.
     */
    public function show(Team $currentTeam, WebScrape $webScrape): Response
    {
        $webScrape->load(['chat', 'team']);

        return Inertia::render('scrapes/show', [
            'scrape' => (new WebScrapeResource($webScrape))->resolve(),
        ]);
    }

    public function saveToLibrary(Request $request, Team $currentTeam, WebScrape $webScrape): RedirectResponse
    {
        abort_unless($webScrape->status === ScrapeStatus::Completed, 422);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $attributes = [
            'title' => $data['title'],
            'body' => $webScrape->markdown,
            'source_url' => $webScrape->url,
            'fetched_at' => $webScrape->updated_at,
            'untrusted' => true,
        ];

        // A re-scrape refreshes the item it came from.
        $item = $webScrape->contextItem
            ? tap($webScrape->contextItem)->update($attributes)
            : $currentTeam->contextItems()->create([...$attributes, 'user_id' => $request->user()->id, 'type' => ContextType::Web]);

        $webScrape->update(['context_item_id' => $item->id]);

        $this->toast('Saved to the context library as untrusted web content.');

        return to_route('context.show', ['current_team' => $currentTeam, 'contextItem' => $item]);
    }

    /**
     * Use the page in its chat only, without saving it to the library.
     */
    public function useInChat(Team $currentTeam, WebScrape $webScrape): RedirectResponse
    {
        abort_unless($webScrape->status === ScrapeStatus::Completed && $webScrape->chat_id, 422);

        $webScrape->update(['attached_to_chat' => true]);

        $this->toast('Added to this chat only.');

        return to_route('chats.show', ['current_team' => $currentTeam, 'chat' => $webScrape->chat_id]);
    }

    /**
     * Refresh a saved web page. The result goes through the same preview.
     */
    public function rescrape(Request $request, Team $currentTeam, ContextItem $contextItem): RedirectResponse
    {
        abort_unless($contextItem->type === ContextType::Web && $contextItem->source_url, 422);

        try {
            $this->usage->ensureCanScrape($request->user());
        } catch (UsageLimitReached $e) {
            $this->toast($e->getMessage(), 'error');

            return back();
        }

        $scrape = $currentTeam->webScrapes()->create([
            'user_id' => $request->user()->id,
            'context_item_id' => $contextItem->id,
            'url' => $contextItem->source_url,
            'mode' => 'page',
            'mode_reason' => 'Refreshing a saved page.',
            'status' => ScrapeStatus::Pending,
        ]);

        RunWebScrape::dispatch($scrape);

        return to_route('scrapes.show', ['current_team' => $currentTeam, 'webScrape' => $scrape]);
    }

    protected function validatedUrl(string $url): string
    {
        // Add https:// only when no scheme was typed, so file:// and others are rejected.
        $url = trim($url);
        $url = preg_match('#^[a-z][a-z0-9+.-]*:#i', $url) ? $url : 'https://'.$url;

        try {
            return $this->sanitizer->assertScrapableUrl($url);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['url' => $e->getMessage()]);
        }
    }

    /**
     * Let the agent pick page or crawl. It only sees the URL and the user's
     * note. If it cannot run, a single page is the safe default.
     *
     * @return array{0: 'page'|'crawl', 1: string}
     */
    protected function decideScope(string $url, ?string $instruction, User $user, Team $team): array
    {
        $model = $team->workspaceSettings()->model('text');

        try {
            $this->usage->ensureCanSpend($user);

            $response = (new ScrapeScopeAgent)->prompt(
                "URL: {$url}\nUser's note: ".($instruction ?: '(none)'),
                provider: config('contenthub.provider'),
                model: $model,
            );

            $this->usage->recordTokens($user, $team, UsageKind::Text, $model, $response->usage, ['purpose' => 'scrape_scope']);

            if (! $response instanceof StructuredAgentResponse) {
                return ['page', 'Defaulted to a single page.'];
            }

            $mode = $response['mode'] === 'crawl' ? 'crawl' : 'page';

            return [$mode, Str::limit((string) ($response['reason'] ?? ''), 250)];
        } catch (Throwable $e) {
            report($e);

            return ['page', 'Defaulted to a single page.'];
        }
    }
}
