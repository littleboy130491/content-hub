<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * Decides whether a user-invoked scrape needs one page or a small crawl.
 * It only sees the URL and the user's note, never page content.
 */
class ScrapeScopeAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        $pages = config('contenthub.scraping.max_crawl_pages');

        return <<<PROMPT
        A user wants to pull a web page into their content workspace as reference material.
        Decide the scope from the URL and the user's note:
        - "page": one page. Choose this for articles, blog posts, product pages, and whenever the user refers to "this page".
        - "crawl": a small crawl of up to {$pages} pages on the same site. Choose this only when the user asks for a whole site, a section, several pages, or the URL is a site's home page and the user wants to learn about the company or brand.
        When unsure, choose "page".
        PROMPT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'mode' => $schema->string()->enum(['page', 'crawl'])->required(),
            'reason' => $schema->string()->description('One short sentence explaining the choice.')->required(),
        ];
    }
}
