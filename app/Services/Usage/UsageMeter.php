<?php

namespace App\Services\Usage;

use App\Enums\UsageKind;
use App\Models\AiModel;
use App\Models\Team;
use App\Models\UsageRecord;
use App\Models\User;
use Laravel\Ai\Responses\Data\Usage;

/**
 * Records what each account spends on the shared keys and enforces the
 * monthly limits. Cost is estimated from the catalog's per-token prices,
 * since the SDK does not pass through the provider's billed cost.
 */
class UsageMeter
{
    public function ensureCanSpend(User $user): void
    {
        $limit = (float) config('contenthub.limits.monthly_spend_usd');

        if ($limit > 0 && $this->spentThisMonth($user) >= $limit) {
            throw new UsageLimitReached(sprintf(
                'You have reached this month\'s spend limit of $%s. It resets on the 1st.',
                number_format($limit, 2),
            ));
        }
    }

    public function ensureCanScrape(User $user, int $credits = 1): void
    {
        $limit = (int) config('contenthub.limits.monthly_scrape_credits');

        if ($limit > 0 && $this->scrapeCreditsThisMonth($user) + $credits > $limit) {
            throw new UsageLimitReached(
                "You have used this month's {$limit} scraping credits. They reset on the 1st.",
            );
        }
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function recordTokens(User $user, ?Team $team, UsageKind $kind, string $model, Usage $usage, array $meta = []): UsageRecord
    {
        $catalog = AiModel::find($model);

        $cost = $catalog
            ? $usage->inputTokens * $catalog->price('prompt') + $usage->outputTokens * $catalog->price('completion')
            : 0;

        return $this->record($user, $team, $kind, $model, $cost, [
            'input_tokens' => $usage->inputTokens,
            'output_tokens' => $usage->outputTokens,
        ], $meta);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function recordVideo(User $user, ?Team $team, string $model, float $cost, array $meta = []): UsageRecord
    {
        return $this->record($user, $team, UsageKind::Video, $model, $cost, [], $meta);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function recordScrape(User $user, ?Team $team, int $credits, array $meta = []): UsageRecord
    {
        return $this->record($user, $team, UsageKind::Scrape, null, 0, ['credits' => $credits], $meta);
    }

    /**
     * Estimate a video's cost from the catalog's per-second price.
     */
    public function estimateVideoCost(string $model, int $seconds): float
    {
        return $seconds * (AiModel::find($model)?->price('video_duration_seconds') ?? 0);
    }

    public function spentThisMonth(User $user): float
    {
        return (float) UsageRecord::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('cost');
    }

    public function scrapeCreditsThisMonth(User $user): int
    {
        return (int) UsageRecord::query()
            ->where('user_id', $user->id)
            ->where('kind', UsageKind::Scrape)
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('credits');
    }

    /**
     * @return array{spent: float, limit: float, scrapeCredits: int, scrapeLimit: int}
     */
    public function summary(User $user): array
    {
        return [
            'spent' => round($this->spentThisMonth($user), 4),
            'limit' => (float) config('contenthub.limits.monthly_spend_usd'),
            'scrapeCredits' => $this->scrapeCreditsThisMonth($user),
            'scrapeLimit' => (int) config('contenthub.limits.monthly_scrape_credits'),
        ];
    }

    /**
     * @param  array{input_tokens?: int, output_tokens?: int, credits?: int}  $counts
     * @param  array<string, mixed>  $meta
     */
    protected function record(User $user, ?Team $team, UsageKind $kind, ?string $model, float $cost, array $counts, array $meta): UsageRecord
    {
        return UsageRecord::create([
            'team_id' => $team?->id,
            'user_id' => $user->id,
            'kind' => $kind,
            'model' => $model,
            'input_tokens' => $counts['input_tokens'] ?? 0,
            'output_tokens' => $counts['output_tokens'] ?? 0,
            'credits' => $counts['credits'] ?? 0,
            'cost' => round($cost, 6),
            'meta' => $meta ?: null,
        ]);
    }
}
