<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Models\Team;
use App\Services\Context\ChatContextBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * One-off files attached to a single chat. They are not in the library
 * unless the user saves them there.
 */
class ChatAttachmentController extends Controller
{
    public function store(Request $request, Team $currentTeam, Chat $chat): RedirectResponse
    {
        $request->validate([
            'files' => ['required', 'array', 'max:10'],
            'files.*' => [
                'file',
                'mimetypes:image/png,image/jpeg,image/webp,image/gif,video/mp4,video/quicktime,video/webm,text/plain,text/markdown,text/csv,application/json',
                'max:'.(int) (config('media-library.max_file_size') / 1024),
            ],
        ]);

        foreach ($request->file('files') as $file) {
            $chat->addMedia($file)->toMediaCollection('attachments');
        }

        return back();
    }

    public function destroy(Team $currentTeam, Chat $chat, Media $media): RedirectResponse
    {
        $media->delete();

        return back();
    }

    /**
     * Copy an attachment into the context library.
     */
    public function saveToLibrary(Request $request, Team $currentTeam, Chat $chat, Media $media, ChatContextBuilder $builder): RedirectResponse
    {
        $type = $builder->typeForMime($media->mime_type);
        $isText = $builder->isTextMime($media->mime_type);

        $item = $currentTeam->contextItems()->create([
            'user_id' => $request->user()->id,
            'type' => $type,
            'title' => pathinfo($media->file_name, PATHINFO_FILENAME),
            'body' => $isText ? file_get_contents($media->getPath()) : null,
            'description' => $media->getCustomProperty('description'),
        ]);

        if (! $isText) {
            $media->copy($item, 'file');
        }

        $this->toast('Saved to the context library.');

        return back();
    }
}
