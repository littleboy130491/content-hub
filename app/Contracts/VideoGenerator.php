<?php

namespace App\Contracts;

/**
 * Video generation is our own code, since the Laravel AI SDK has none.
 * Implementations hide each provider's job format behind these three calls.
 */
interface VideoGenerator
{
    /**
     * Submit a job and return the provider's job ID.
     *
     * @param  array{duration?: int, aspect_ratio?: string, resolution?: string}  $options
     * @param  list<array{mime: string, data: string}>  $frameImages  Base64 images for the first frame.
     */
    public function submit(string $model, string $prompt, array $options = [], array $frameImages = []): string;

    /**
     * @return array{status: 'pending'|'in_progress'|'completed'|'failed', error: string|null, cost: float|null}
     */
    public function status(string $jobId): array;

    /**
     * Download the finished video's bytes.
     */
    public function download(string $jobId): string;
}
