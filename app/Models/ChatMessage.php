<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $chat_id
 * @property int|null $user_id
 * @property string $role
 * @property string $content
 * @property array<string, mixed>|null $meta
 */
#[Fillable(['chat_id', 'user_id', 'role', 'content', 'meta'])]
class ChatMessage extends Model
{
    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
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
     * @return HasMany<Output, $this>
     */
    public function outputs(): HasMany
    {
        return $this->hasMany(Output::class);
    }
}
