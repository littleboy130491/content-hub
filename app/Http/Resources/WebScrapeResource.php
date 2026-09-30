<?php

namespace App\Http\Resources;

use App\Models\WebScrape;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WebScrape
 */
class WebScrapeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'instruction' => $this->instruction,
            'mode' => $this->mode,
            'modeReason' => $this->mode_reason,
            'status' => $this->status->value,
            'title' => $this->title,
            'markdown' => $this->markdown,
            'pages' => $this->pages ?? [],
            'credits' => $this->credits,
            'error' => $this->error,
            'chat' => $this->chat ? ['id' => $this->chat->id, 'title' => $this->chat->title] : null,
            'attachedToChat' => $this->attached_to_chat,
            'contextItemId' => $this->context_item_id,
            'createdAt' => $this->created_at->toIso8601String(),
        ];
    }
}
