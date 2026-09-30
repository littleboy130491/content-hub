<?php

namespace App\Models;

use App\Enums\UsageKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int|null $team_id
 * @property int $user_id
 * @property UsageKind $kind
 * @property string|null $model
 * @property int $input_tokens
 * @property int $output_tokens
 * @property int $credits
 * @property string $cost
 * @property array<string, mixed>|null $meta
 */
#[Fillable(['team_id', 'user_id', 'kind', 'model', 'input_tokens', 'output_tokens', 'credits', 'cost', 'meta'])]
class UsageRecord extends Model
{
    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'kind' => UsageKind::class,
            'meta' => 'array',
        ];
    }
}
