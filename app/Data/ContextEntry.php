<?php

namespace App\Data;

use Spatie\LaravelData\Data;

/**
 * One piece of context in a chat, and how it will reach the chat model.
 */
class ContextEntry extends Data
{
    public function __construct(
        public string $key,
        public string $title,
        /** text, image, video or web */
        public string $type,
        /** project, attachment or scrape */
        public string $source,
        /** text, image (sent as is), convert (described by the media model), excluded or unsupported */
        public string $handling,
        public bool $untrusted = false,
        public bool $cached = false,
        public ?int $contextItemId = null,
        public ?int $mediaId = null,
        public ?int $scrapeId = null,
    ) {}
}
