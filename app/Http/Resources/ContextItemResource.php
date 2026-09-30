<?php

namespace App\Http\Resources;

use App\Models\ContextItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * @mixin ContextItem
 */
class ContextItemResource extends JsonResource
{
    public bool $withBody = false;

    public function withBody(): static
    {
        $this->withBody = true;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'typeLabel' => $this->type->label(),
            'title' => $this->title,
            'excerpt' => Str::limit(strip_tags((string) ($this->body ?? $this->description)), 160),
            'body' => $this->when($this->withBody, $this->body),
            'description' => $this->when($this->withBody, $this->description),
            'descriptionModel' => $this->when($this->withBody, $this->description_model),
            'sourceUrl' => $this->source_url,
            'fetchedAt' => $this->fetched_at?->toIso8601String(),
            'untrusted' => $this->untrusted,
            'file' => MediaUrl::for($this->file(), $this->team->slug),
            'author' => $this->user?->name,
            'projectsCount' => $this->whenCounted('projects'),
            'createdAt' => $this->created_at->toIso8601String(),
            'updatedAt' => $this->updated_at->toIso8601String(),
        ];
    }
}
