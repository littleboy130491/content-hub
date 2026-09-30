<?php

namespace App\Models;

use App\Concerns\BelongsToWorkspace;
use App\Enums\OutputStatus;
use App\Enums\OutputType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Finished content: text, an image or a video.
 *
 * @property int $id
 * @property int|null $user_id
 * @property int|null $chat_id
 * @property int|null $chat_message_id
 * @property int|null $context_item_id
 * @property OutputType $type
 * @property string $title
 * @property string|null $prompt
 * @property string|null $content
 * @property string|null $model
 * @property OutputStatus $status
 * @property string|null $provider_job_id
 * @property array<string, mixed>|null $options
 * @property string|null $cost
 * @property string|null $error
 * @property-read Chat|null $chat
 */
#[Fillable([
    'user_id', 'chat_id', 'chat_message_id', 'context_item_id', 'type', 'title', 'prompt',
    'content', 'model', 'status', 'provider_job_id', 'options', 'cost', 'error',
])]
class Output extends Model implements HasMedia
{
    use BelongsToWorkspace, InteractsWithMedia;

    protected function casts(): array
    {
        return [
            'type' => OutputType::class,
            'status' => OutputStatus::class,
            'options' => 'array',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('file')->singleFile();
    }

    /**
     * @return BelongsTo<Chat, $this>
     */
    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<ContextItem, $this>
     */
    public function contextItem(): BelongsTo
    {
        return $this->belongsTo(ContextItem::class);
    }

    public function file(): ?Media
    {
        return $this->getFirstMedia('file');
    }

    /**
     * @return list<string>
     */
    protected function activityAttributes(): array
    {
        return ['title', 'type', 'status'];
    }
}
