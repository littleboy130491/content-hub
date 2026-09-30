<?php

namespace App\Jobs;

use App\Contracts\VideoGenerator;
use App\Enums\OutputStatus;
use App\Models\Output;
use App\Services\Usage\UsageMeter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * Runs one video output: submit the job, poll until it finishes, then store
 * the file. Each poll is a new delayed dispatch, so no worker sits waiting.
 */
class ProcessVideoOutput implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public Output $output, public int $poll = 0) {}

    public function handle(VideoGenerator $videos, UsageMeter $usage): void
    {
        $output = $this->output->fresh();

        if (! $output || ! in_array($output->status, [OutputStatus::Pending, OutputStatus::Processing], true)) {
            return;
        }

        try {
            if (! $output->provider_job_id) {
                $jobId = $videos->submit($output->model, (string) $output->prompt, $output->options ?? [], $this->frameImages($output));

                $output->update(['provider_job_id' => $jobId, 'status' => OutputStatus::Processing]);

                $this->pollAgain();

                return;
            }

            $status = $videos->status($output->provider_job_id);

            if ($status['status'] === 'failed') {
                $this->markFailed($output, $status['error'] ?? 'The provider reported a failure.');

                return;
            }

            if ($status['status'] !== 'completed') {
                $this->poll >= (int) config('contenthub.video.max_polls')
                    ? $this->markFailed($output, 'The video took too long to generate.')
                    : $this->pollAgain();

                return;
            }

            $output->addMediaFromString($videos->download($output->provider_job_id))
                ->usingFileName("video-{$output->id}.mp4")
                ->usingName($output->title)
                ->toMediaCollection('file');

            $cost = $status['cost'] ?? $usage->estimateVideoCost($output->model, (int) ($output->options['duration'] ?? 5));

            $usage->recordVideo($output->user, $output->team, $output->model, $cost, ['output_id' => $output->id]);

            $output->update(['status' => OutputStatus::Completed, 'cost' => $cost, 'error' => null]);
        } catch (Throwable $e) {
            report($e);

            $this->markFailed($output, $e->getMessage());
        }
    }

    protected function pollAgain(): void
    {
        static::dispatch($this->output, $this->poll + 1)
            ->delay(now()->addSeconds((int) config('contenthub.video.poll_seconds')));
    }

    protected function markFailed(Output $output, string $error): void
    {
        $output->update(['status' => OutputStatus::Failed, 'error' => $error]);
    }

    /**
     * @return list<array{mime: string, data: string}>
     */
    protected function frameImages(Output $output): array
    {
        $id = $output->options['reference_media_id'] ?? null;
        $media = $id ? Media::query()->whereKey($id)->first() : null;

        if (! $media || ! str_starts_with((string) $media->mime_type, 'image/')) {
            return [];
        }

        $data = file_get_contents($media->getPath());

        return $data === false ? [] : [['mime' => $media->mime_type, 'data' => base64_encode($data)]];
    }
}
