<?php

namespace App\Models;

use App\Concerns\BelongsToWorkspace;
use App\Enums\PromptMode;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $name
 * @property string|null $description
 * @property string|null $instructions
 * @property PromptMode $instructions_mode
 */
#[Fillable(['user_id', 'name', 'description', 'instructions', 'instructions_mode'])]
class Project extends Model
{
    use BelongsToWorkspace;

    protected function casts(): array
    {
        return [
            'instructions_mode' => PromptMode::class,
        ];
    }

    /**
     * The context items the user chose to include in this project.
     *
     * @return BelongsToMany<ContextItem, $this>
     */
    public function contextItems(): BelongsToMany
    {
        return $this->belongsToMany(ContextItem::class);
    }

    /**
     * @return HasMany<Chat, $this>
     */
    public function chats(): HasMany
    {
        return $this->hasMany(Chat::class);
    }

    /**
     * @return list<string>
     */
    protected function activityAttributes(): array
    {
        return ['name', 'instructions', 'instructions_mode'];
    }
}
