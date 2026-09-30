<?php

namespace App\Jobs;

use App\Enums\ScrapeStatus;
use App\Models\WebScrape;
use App\Services\Firecrawl\FirecrawlClient;
use App\Services\Safety\ContentSanitizer;
use App\Services\Usage\UsageMeter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Runs a user-invoked scrape and cleans the result. The cleaned markdown waits
 * on the WebScrape for the user to review; nothing reaches a chat or the
 * library until they choose to save or use it.
 */
class RunWebScrape implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public WebScrape $scrape, public int $poll = 0) {}

    public function handle(FirecrawlClient $firecrawl, ContentSanitizer $sanitizer, UsageMeter $usage): void
    {
        $scrape = $this->scrape->fresh();

        if (! $scrape || in_array($scrape->status, [ScrapeStatus::Completed, ScrapeStatus::Failed], true)) {
            return;
        }

        try {
            $scrape->mode === 'crawl'
                ? $this->crawl($scrape, $firecrawl, $sanitizer, $usage)
                : $this->page($scrape, $firecrawl, $sanitizer, $usage);
        } catch (Throwable $e) {
            report($e);

            $scrape->update(['status' => ScrapeStatus::Failed, 'error' => $e->getMessage()]);
        }
    }

    protected function page(WebScrape $scrape, FirecrawlClient $firecrawl, ContentSanitizer $sanitizer, UsageMeter $usage): void
    {
        $result = $firecrawl->scrape($scrape->url);

        $usage->recordScrape($scrape->user, $scrape->team, $result['credits'], ['scrape_id' => $scrape->id]);

        $scrape->update([
            'status' => ScrapeStatus::Completed,
            'title' => $result['title'] ?: $scrape->url,
            'markdown' => $sanitizer->clean($result['markdown']),
            'pages' => [['url' => $result['url'], 'title' => $result['title']]],
            'credits' => $result['credits'],
        ]);
    }

    protected function crawl(WebScrape $scrape, FirecrawlClient $firecrawl, ContentSanitizer $sanitizer, UsageMeter $usage): void
    {
        if (! $scrape->firecrawl_job_id) {
            $scrape->update([
                'status' => ScrapeStatus::Processing,
                'firecrawl_job_id' => $firecrawl->startCrawl(
                    $scrape->url,
                    (int) config('contenthub.scraping.max_crawl_pages'),
                    (int) config('contenthub.scraping.max_crawl_depth'),
                ),
            ]);

            $this->pollAgain();

            return;
        }

        $result = $firecrawl->crawlStatus($scrape->firecrawl_job_id);

        if ($result['status'] === 'failed') {
            $scrape->update(['status' => ScrapeStatus::Failed, 'error' => 'Firecrawl reported that the crawl failed.']);

            return;
        }

        if ($result['status'] !== 'completed') {
            $this->poll >= 60
                ? $scrape->update(['status' => ScrapeStatus::Failed, 'error' => 'The crawl took too long.'])
                : $this->pollAgain();

            return;
        }

        $usage->recordScrape($scrape->user, $scrape->team, $result['credits'], ['scrape_id' => $scrape->id]);

        // Each page is cleaned on its own, then the whole is capped.
        $markdown = collect($result['pages'])
            ->map(fn (array $page) => '# '.($page['title'] ?: $page['url'])."\nSource: ".$page['url']."\n\n".$sanitizer->clean($page['markdown']))
            ->implode("\n\n---\n\n");

        $scrape->update([
            'status' => ScrapeStatus::Completed,
            'title' => ($result['pages'][0]['title'] ?? null) ?: $scrape->url,
            'markdown' => $sanitizer->clean($markdown),
            'pages' => collect($result['pages'])->map(fn (array $page) => ['url' => $page['url'], 'title' => $page['title']])->all(),
            'credits' => $result['credits'],
        ]);
    }

    protected function pollAgain(): void
    {
        static::dispatch($this->scrape, $this->poll + 1)->delay(now()->addSeconds(5));
    }
}
