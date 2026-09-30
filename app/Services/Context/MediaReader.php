<?php

namespace App\Services\Context;

use App\Ai\Agents\MediaReaderAgent;
use App\Enums\UsageKind;
use App\Models\Team;
use App\Models\User;
use App\Services\Usage\UsageMeter;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\FFMpeg;
use FFMpeg\FFProbe;
use FFMpeg\Media\Video;
use Illuminate\Support\Facades\File;
use Laravel\Ai\Files\Image;
use RuntimeException;
use Spatie\TemporaryDirectory\TemporaryDirectory;

/**
 * Turns images and videos into text descriptions with the media reading model,
 * for chat models that cannot read them. Callers cache the result.
 */
class MediaReader
{
    public function __construct(protected UsageMeter $usage) {}

    public function describeImage(string $path, ?string $mime, string $model, User $user, ?Team $team): string
    {
        return $this->describe(
            'Describe this image.',
            [Image::fromPath($path, $mime)],
            $model, $user, $team,
        );
    }

    public function describeVideo(string $path, string $model, User $user, ?Team $team): string
    {
        $frames = $this->frames($path);

        try {
            return $this->describe(
                sprintf('These are %d frames sampled evenly from one video, in order. Describe the video.', count($frames)),
                array_map(fn (string $frame) => Image::fromPath($frame, 'image/jpeg'), $frames),
                $model, $user, $team,
            );
        } finally {
            collect($frames)->each(fn (string $frame) => File::delete($frame));
        }
    }

    /**
     * Sample evenly spaced JPEG frames from a video. The first frame is also
     * used as a reference image for video generation.
     *
     * @return list<string> Paths to temporary JPEG files.
     */
    public function frames(string $path, ?int $count = null): array
    {
        $count ??= (int) config('contenthub.media_reading.video_frames');

        $binaries = [
            'ffmpeg.binaries' => config('contenthub.media_reading.ffmpeg'),
            'ffprobe.binaries' => config('contenthub.media_reading.ffprobe'),
        ];

        $duration = (float) FFProbe::create($binaries)->format($path)->get('duration');
        $video = FFMpeg::create($binaries)->open($path);

        if (! $video instanceof Video) {
            throw new RuntimeException('The file has no video stream to sample.');
        }

        $directory = (new TemporaryDirectory)->deleteWhenDestroyed(false)->create();

        return array_values(collect(range(0, $count - 1))
            ->map(function (int $i) use ($video, $duration, $count, $directory) {
                $seconds = $duration > 0 ? $duration * ($i + 0.5) / $count : 0;
                $frame = $directory->path("frame-{$i}.jpg");
                $video->frame(TimeCode::fromSeconds($seconds))->save($frame);

                return $frame;
            })
            ->filter(fn (string $frame) => File::exists($frame))
            ->all());
    }

    /**
     * @param  array<int, Image>  $images
     */
    protected function describe(string $prompt, array $images, string $model, User $user, ?Team $team): string
    {
        $this->usage->ensureCanSpend($user);

        $response = (new MediaReaderAgent)->prompt($prompt, attachments: $images, provider: config('contenthub.provider'), model: $model);

        $this->usage->recordTokens($user, $team, UsageKind::MediaReading, $model, $response->usage);

        return trim($response->text);
    }
}
