<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default models
    |--------------------------------------------------------------------------
    |
    | Used when a workspace has not chosen its own models in settings. All model
    | IDs are OpenRouter slugs, since OpenRouter is the default provider.
    |
    */

    'provider' => 'openrouter',

    'default_models' => [
        'text' => env('CONTENTHUB_TEXT_MODEL', 'anthropic/claude-sonnet-5'),
        'image' => env('CONTENTHUB_IMAGE_MODEL', 'google/gemini-3.1-flash-image-preview'),
        'video' => env('CONTENTHUB_VIDEO_MODEL', 'minimax/hailuo-3'),
        'media' => env('CONTENTHUB_MEDIA_MODEL', 'google/gemini-3.5-flash'),
    ],

    'openrouter' => [
        'url' => env('OPENROUTER_URL', 'https://openrouter.ai/api/v1'),
        'key' => env('OPENROUTER_API_KEY'),
    ],

    'firecrawl' => [
        'url' => env('FIRECRAWL_URL', 'https://api.firecrawl.dev/v2'),
        'key' => env('FIRECRAWL_API_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Usage limits
    |--------------------------------------------------------------------------
    |
    | Every account shares the same provider keys, so each user has a monthly
    | ceiling. Spend is counted in USD, because model prices vary widely.
    |
    */

    'limits' => [
        'monthly_spend_usd' => (float) env('CONTENTHUB_MONTHLY_SPEND_LIMIT', 20),
        'monthly_scrape_credits' => (int) env('CONTENTHUB_MONTHLY_SCRAPE_CREDITS', 200),
    ],

    /*
    |--------------------------------------------------------------------------
    | Scraping
    |--------------------------------------------------------------------------
    |
    | Fixed ceilings for user-invoked crawls. The agent picks page or crawl, but
    | never beyond these limits, and only on the same domain.
    |
    */

    'scraping' => [
        'max_crawl_pages' => 10,
        'max_crawl_depth' => 2,
        'max_markdown_chars' => 60_000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Media reading
    |--------------------------------------------------------------------------
    */

    'media_reading' => [
        'video_frames' => 6,
        'ffmpeg' => env('FFMPEG_BINARIES', 'ffmpeg'),
        'ffprobe' => env('FFPROBE_BINARIES', 'ffprobe'),
    ],

    'video' => [
        'poll_seconds' => 15,
        'max_polls' => 120,
    ],

];
