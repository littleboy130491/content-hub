<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A model from the provider's catalog, cached locally by `ai:sync-models`.
 *
 * @property string $id
 * @property string $name
 * @property list<string> $input_modalities
 * @property list<string> $output_modalities
 * @property int|null $context_length
 * @property bool $supports_tools
 * @property array<string, mixed>|null $pricing
 * @property array<string, mixed>|null $video_options
 */
class AiModel extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'input_modalities' => 'array',
            'output_modalities' => 'array',
            'pricing' => 'array',
            'video_options' => 'array',
            'supports_tools' => 'boolean',
            'synced_at' => 'datetime',
        ];
    }

    public function reads(string $modality): bool
    {
        return in_array($modality, $this->input_modalities, true);
    }

    public function produces(string $modality): bool
    {
        return in_array($modality, $this->output_modalities, true);
    }

    /**
     * @param  Builder<AiModel>  $query
     */
    public function scopeProducing(Builder $query, string $modality): void
    {
        $query->whereJsonContains('output_modalities', $modality);
    }

    /**
     * Price per token (or per unit) for the given pricing key, in USD.
     */
    public function price(string $key): float
    {
        return (float) ($this->pricing[$key] ?? 0);
    }

    /**
     * @return array<string, mixed>
     */
    public function toOption(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'input' => $this->input_modalities,
            'output' => $this->output_modalities,
            'contextLength' => $this->context_length,
            'supportsTools' => $this->supports_tools,
        ];
    }
}
