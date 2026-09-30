<?php

namespace App\Models;

use App\Concerns\BelongsToWorkspace;
use App\Enums\ScrapeStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user-invoked Firecrawl run. The cleaned result waits here for review
 * before it becomes a context item or is used once in a chat.
 *
 * @property int $id
 * @property int|null $user_id
 * @property int|null $chat_id
 * @property int|null $context_item_id
 * @property string $url
 * @property string|null $instruction
 * @property string|null $mode
 * @property string|null $mode_reason
 * @property ScrapeStatus $status
 * @property string|null $firecrawl_job_id
 * @property string|null $title
 * @property string|null $markdown
 * @property list<array{url: string, title: string|null}>|null $pages
 * @property int $credits
 * @property bool $attached_to_chat
 * @property string|null $error
 */
#[Fillable([
    'user_id', 'chat_id', 'context_item_id', 'url', 'instruction', 'mode', 'mode_reason',
    'status', 'firecrawl_job_id', 'title', 'markdown', 'pages', 'credits', 'attached_to_chat', 'error',
])]
class WebScrape extends Model
{
    use BelongsToWorkspace;

    protected function casts(): array
    {
        return [
            'status' => ScrapeStatus::class,
            'pages' => 'array',
            'attached_to_chat' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Chat, $this>
     */
    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class);
    }

    /**
     * @return BelongsTo<ContextItem, $this>
     */
    public function contextItem(): BelongsTo
    {
        return $this->belongsTo(ContextItem::class);
    }

    /**
     * @return list<string>
     */
    protected function activityAttributes(): array
    {
        return ['url', 'mode', 'status'];
    }
}
