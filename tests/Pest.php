<?php

use App\Models\AiModel;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => Http::preventStrayRequests())
    ->in('Feature/ContentHub');

/**
 * A verified user with their personal workspace.
 *
 * @return array{0: User, 1: Team}
 */
function member(): array
{
    $user = User::factory()->create();

    return [$user, $user->currentTeam];
}

/**
 * Put a model into the local catalog so capability checks have data.
 *
 * @param  list<string>  $input
 * @param  list<string>  $output
 */
function catalogModel(string $id, array $input = ['text'], array $output = ['text'], bool $tools = true, array $extra = []): AiModel
{
    return AiModel::create([
        'id' => $id,
        'name' => $id,
        'input_modalities' => $input,
        'output_modalities' => $output,
        'context_length' => 100_000,
        'supports_tools' => $tools,
        'pricing' => ['prompt' => 0.000001, 'completion' => 0.000002],
        ...$extra,
    ]);
}
