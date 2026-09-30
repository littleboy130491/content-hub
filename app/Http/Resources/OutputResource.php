<?php

namespace App\Http\Resources;

use App\Models\Output;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Output
 */
class OutputResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'title' => $this->title,
            'prompt' => $this->prompt,
            'content' => $this->content,
            'model' => $this->model,
            'status' => $this->status->value,
            'error' => $this->error,
            'options' => $this->options,
            'cost' => $this->cost !== null ? (float) $this->cost : null,
            'file' => MediaUrl::for($this->file(), $this->team->slug),
            'chatId' => $this->chat_id,
            'chatTitle' => $this->chat?->title,
            'messageId' => $this->chat_message_id,
            'contextItemId' => $this->context_item_id,
            'author' => $this->user?->name,
            'createdAt' => $this->created_at->toIso8601String(),
        ];
    }
}
