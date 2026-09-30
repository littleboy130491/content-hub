<?php

namespace App\Ai;

use App\Data\ContextEntry;
use App\Models\Chat;
use App\Models\ChatMessage;
use App\Models\User;
use App\Services\Context\ChatContext;

/**
 * The state of one chat turn, shared by the agent and its tools.
 */
class Generation
{
    /**
     * @param  array{text: string, image: string, video: string, media: string}  $models
     */
    public function __construct(
        public Chat $chat,
        public User $user,
        public ChatMessage $assistantMessage,
        public ChatContext $context,
        public array $models,
    ) {}

    /**
     * Media IDs of images in the context that tools may use as references.
     *
     * @return list<int>
     */
    public function referenceImageMediaIds(): array
    {
        return array_values(collect($this->context->entries)
            ->filter(fn (ContextEntry $entry) => $entry->type === 'image' && $entry->mediaId && $entry->handling !== 'excluded')
            ->map(fn (ContextEntry $entry) => (int) $entry->mediaId)
            ->all());
    }
}
