<?php

namespace App\Services\Context;

use App\Enums\ContextType;
use App\Models\ContextItem;
use App\Models\WebScrape;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * One piece of context a chat can see, from any of its three sources.
 */
final class ContextSource
{
    /**
     * @param  'project'|'attachment'|'scrape'  $source
     */
    public function __construct(
        public readonly string $key,
        public readonly string $title,
        public readonly ContextType $type,
        public readonly string $source,
        public readonly ?string $text = null,
        public readonly ?Media $media = null,
        public readonly ?ContextItem $item = null,
        public readonly ?WebScrape $scrape = null,
        public readonly bool $untrusted = false,
    ) {}

    public function cachedDescription(): ?string
    {
        return $this->item
            ? $this->item->description
            : $this->media?->getCustomProperty('description');
    }
}
