<?php

namespace App\Services\Context;

use App\Data\ContextEntry;
use Laravel\Ai\Files\Image;

/**
 * Everything the chat model receives besides the conversation itself.
 */
class ChatContext
{
    /**
     * @param  list<ContextEntry>  $entries
     * @param  list<Image>  $images
     * @param  list<string>  $notes  What was converted, trimmed or left out, shown in the chat.
     */
    public function __construct(
        public array $entries,
        public string $text,
        public array $images,
        public array $notes,
        public bool $hasUntrusted,
    ) {}

    public function isEmpty(): bool
    {
        return $this->text === '' && $this->images === [];
    }
}
