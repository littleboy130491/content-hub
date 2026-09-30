<?php

namespace App\Concerns;

use App\Models\Team;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Shared by every model that lives inside a workspace (a starter-kit Team).
 *
 * Isolation comes from scoped route bindings: routes are nested under
 * {current_team}, so a record from another workspace resolves to a 404.
 *
 * @property int $team_id
 * @property-read Team $team
 */
trait BelongsToWorkspace
{
    use LogsActivity;

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('workspace-'.$this->team_id)
            ->logOnly($this->activityAttributes())
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * Attributes worth recording in the workspace activity feed.
     *
     * @return list<string>
     */
    protected function activityAttributes(): array
    {
        return ['title'];
    }
}
