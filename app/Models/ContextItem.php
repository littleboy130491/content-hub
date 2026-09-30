<?php

namespace App\Models;

use App\Concerns\BelongsToWorkspace;
use App\Enums\ContextType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * An item in the workspace's context library.
 *
 * @property int $id
 * @property int|null $user_id
 * @property ContextType $type
 * @property string $title
 * @property string|null $body
 * @property string|null $source_url
 * @property Carbon|null $fetched_at
 * @property bool $untrusted
 * @property string|null $description Cached text description of an image or video.
 * @property string|null $description_model
 */
#[Fillable(['user_id', 'type', 'title', 'body', 'source_url', 'fetched_at', 'untrusted', 'description', 'description_model'])]
class ContextItem extends Model implements HasMedia
{
    use BelongsToWorkspace, InteractsWithMedia;

    protected function casts(): array
    {
        return [
            'type' => ContextType::class,
            'fetched_at' => 'datetime',
            'untrusted' => 'boolean',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('file')->singleFile();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsToMany<Project, $this>
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class);
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
        return ['title', 'type', 'source_url'];
    }
}
