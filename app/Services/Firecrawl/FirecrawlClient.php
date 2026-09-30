<?php

namespace App\Services\Firecrawl;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * A thin client for Firecrawl's v2 API over Laravel's HTTP client.
 *
 * The official PHP SDK requires Guzzle 7, while Laravel 13 ships Guzzle 8,
 * so it cannot be installed alongside the framework.
 */
class FirecrawlClient
{
    /**
     * Scrape a single page as markdown, main content only.
     *
     * @return array{url: string, title: string|null, markdown: string, credits: int}
     */
    public function scrape(string $url): array
    {
        $response = $this->client()->post('/scrape', [
            'url' => $url,
            'formats' => ['markdown'],
            'onlyMainContent' => true,
            'removeBase64Images' => true,
            'blockAds' => true,
            'excludeTags' => ['script', 'style', 'noscript', 'iframe'],
        ]);

        if ($response->failed() || ! $response->json('success', true)) {
            throw new RuntimeException('Firecrawl could not scrape the page: '.($response->json('error') ?? $response->status()));
        }

        return [
            'url' => $response->json('data.metadata.sourceURL') ?? $url,
            'title' => $this->title($response->json('data.metadata.title')),
            'markdown' => (string) $response->json('data.markdown', ''),
            // Firecrawl charges one credit per scraped page.
            'credits' => 1,
        ];
    }

    /**
     * Start a small crawl on the same domain. Returns the Firecrawl job ID.
     */
    public function startCrawl(string $url, int $limit, int $depth): string
    {
        $response = $this->client()->post('/crawl', [
            'url' => $url,
            'limit' => $limit,
            'maxDiscoveryDepth' => $depth,
            'allowExternalLinks' => false,
            'allowSubdomains' => false,
            'scrapeOptions' => [
                'formats' => ['markdown'],
                'onlyMainContent' => true,
                'removeBase64Images' => true,
                'blockAds' => true,
            ],
        ]);

        if ($response->failed() || ! $response->json('id')) {
            throw new RuntimeException('Firecrawl could not start the crawl: '.($response->json('error') ?? $response->status()));
        }

        return $response->json('id');
    }

    /**
     * @return array{status: string, pages: list<array{url: string, title: string|null, markdown: string}>, credits: int}
     */
    public function crawlStatus(string $id): array
    {
        $response = $this->client()->get("/crawl/{$id}");

        if ($response->failed()) {
            throw new RuntimeException('Firecrawl crawl status failed: '.$response->status());
        }

        $data = $response->json('data');
        $pages = [];

        foreach (is_array($data) ? $data : [] as $page) {
            $pages[] = [
                'url' => (string) ($page['metadata']['sourceURL'] ?? $page['metadata']['url'] ?? ''),
                'title' => $this->title($page['metadata']['title'] ?? null),
                'markdown' => (string) ($page['markdown'] ?? ''),
            ];
        }

        return [
            'status' => (string) $response->json('status'),
            'pages' => $pages,
            'credits' => (int) ($response->json('creditsUsed') ?? count($pages)),
        ];
    }

    protected function title(mixed $title): ?string
    {
        return is_array($title) ? ($title[0] ?? null) : $title;
    }

    public function configured(): bool
    {
        return filled(config('contenthub.firecrawl.key'));
    }

    protected function client(): PendingRequest
    {
        return Http::baseUrl(config('contenthub.firecrawl.url'))
            ->withToken((string) config('contenthub.firecrawl.key'))
            ->acceptJson()
            ->asJson()
            ->timeout(90);
    }
}
