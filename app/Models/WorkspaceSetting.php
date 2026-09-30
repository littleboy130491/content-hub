<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Defaults shared by every member of a workspace.
 *
 * @property int $id
 * @property int $team_id
 * @property string|null $system_prompt
 * @property string|null $text_model
 * @property string|null $image_model
 * @property string|null $video_model
 * @property string|null $media_model
 */
#[Fillable(['system_prompt', 'text_model', 'image_model', 'video_model', 'media_model'])]
class WorkspaceSetting extends Model
{
    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * The workspace's model for the given kind, falling back to the app default.
     */
    public function model(string $kind): string
    {
        return $this->{$kind.'_model'} ?: config("contenthub.default_models.$kind");
    }
}
