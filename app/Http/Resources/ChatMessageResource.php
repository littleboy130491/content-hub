<?php

namespace App\Http\Resources;

use App\Models\ChatMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ChatMessage
 */
class ChatMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role,
            'content' => $this->content,
            'author' => $this->user?->name,
            'model' => $this->meta['model'] ?? null,
            'notes' => $this->meta['notes'] ?? [],
            'failed' => $this->meta['failed'] ?? false,
            'savedOutputId' => $this->meta['saved_output_id'] ?? null,
            'createdAt' => $this->created_at->toIso8601String(),
        ];
    }
}
