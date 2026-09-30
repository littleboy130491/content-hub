<?php

namespace App\Models;

use App\Concerns\BelongsToWorkspace;
use App\Enums\PromptMode;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A conversation. With no project it is a free chat and starts with zero context;
 * with a project it gets the project's context and instructions.
 *
 * One-off attachments are stored as media on the chat and are not in the library.
 *
 * @property int $id
 * @property int|null $user_id
 * @property int|null $project_id
 * @property string $title
 * @property string|null $system_prompt
 * @property PromptMode $prompt_mode
 * @property string|null $text_model
 * @property string|null $image_model
 * @property string|null $video_model
 * @property string|null $media_model
 * @property list<int>|null $excluded_context_item_ids
 * @property Carbon|null $archived_at
 * @property-read Project|null $project
 */
#[Fillable([
    'user_id', 'project_id', 'title', 'system_prompt', 'prompt_mode',
    'text_model', 'image_model', 'video_model', 'media_model',
    'excluded_context_item_ids', 'archived_at',
])]
class Chat extends Model implements HasMedia
{
    use BelongsToWorkspace, InteractsWithMedia;

    protected function casts(): array
    {
        return [
            'prompt_mode' => PromptMode::class,
            'excluded_context_item_ids' => 'array',
            'archived_at' => 'datetime',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments');
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<ChatMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class)->oldest('id');
    }

    /**
     * @return HasMany<Output, $this>
     */
    public function outputs(): HasMany
    {
        return $this->hasMany(Output::class);
    }

    /**
     * Every scrape started from this chat.
     *
     * @return HasMany<WebScrape, $this>
     */
    public function scrapes(): HasMany
    {
        return $this->hasMany(WebScrape::class);
    }

    /**
     * Web pages the user scraped and chose to use in this chat only.
     *
     * @return HasMany<WebScrape, $this>
     */
    public function attachedScrapes(): HasMany
    {
        return $this->hasMany(WebScrape::class)->where('attached_to_chat', true);
    }

    public function isFree(): bool
    {
        return $this->project_id === null;
    }

    public function excludes(ContextItem $item): bool
    {
        return in_array($item->id, $this->excluded_context_item_ids ?? [], true);
    }

    /**
     * @return list<string>
     */
    protected function activityAttributes(): array
    {
        return ['title', 'project_id', 'archived_at'];
    }
}
