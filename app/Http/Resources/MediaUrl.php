<?php

namespace App\Http\Resources;

use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Files are private and served through an authorised route in the workspace.
 */
class MediaUrl
{
    /**
     * @return array{id: int, name: string, mime: string|null, size: int, url: string}|null
     */
    public static function for(?Media $media, string $teamSlug): ?array
    {
        return $media ? static::of($media, $teamSlug) : null;
    }

    /**
     * @return array{id: int, name: string, mime: string|null, size: int, url: string}
     */
    public static function of(Media $media, string $teamSlug): array
    {
        return [
            'id' => $media->id,
            'name' => $media->file_name,
            'mime' => $media->mime_type,
            'size' => (int) $media->size,
            'url' => route('media.show', ['current_team' => $teamSlug, 'media' => $media->id]),
        ];
    }
}
