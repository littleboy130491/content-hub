<?php

namespace App\Http\Resources;

use App\Models\Chat;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Chat
 */
class ChatResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'project' => $this->project ? ['id' => $this->project->id, 'name' => $this->project->name] : null,
            'systemPrompt' => $this->system_prompt,
            'promptMode' => $this->prompt_mode->value,
            'models' => [
                'text' => $this->text_model,
                'image' => $this->image_model,
                'video' => $this->video_model,
                'media' => $this->media_model,
            ],
            'excludedContextItemIds' => $this->excluded_context_item_ids ?? [],
            'archived' => $this->archived_at !== null,
            'author' => $this->user?->name,
            'messagesCount' => $this->whenCounted('messages'),
            'updatedAt' => $this->updated_at->toIso8601String(),
        ];
    }
}
