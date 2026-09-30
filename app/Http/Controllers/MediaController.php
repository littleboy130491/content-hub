<?php

namespace App\Http\Controllers;

use App\Models\Team;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves private files. A file is only served inside the workspace that owns
 * the record it is attached to.
 */
class MediaController extends Controller
{
    public function __invoke(Team $currentTeam, Media $media): BinaryFileResponse
    {
        /** @var Model|null $owner */
        $owner = $media->model;

        abort_unless($owner && (int) $owner->getAttribute('team_id') === $currentTeam->id, 404);

        return response()->file($media->getPath(), [
            'Content-Type' => $media->mime_type,
            'Content-Disposition' => 'inline; filename="'.addslashes($media->file_name).'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
