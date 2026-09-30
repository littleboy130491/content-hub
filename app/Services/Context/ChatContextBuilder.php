<?php

namespace App\Services\Context;

use App\Data\ContextEntry;
use App\Enums\ContextType;
use App\Models\AiModel;
use App\Models\Chat;
use App\Models\User;
use App\Services\OpenRouter\ModelCatalog;
use App\Services\Safety\ContentSanitizer;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Ai\Files\Image;

/**
 * Gathers a chat's context and checks it against the chat model's capabilities.
 *
 * Sources: the project's context items (project chats only), the chat's one-off
 * attachments, and web pages scraped for this chat only. A free chat with no
 * attachments has zero context.
 *
 * A model that cannot read images or video gets a text description made by the
 * media reading model, cached on the item. Video is always described, since
 * video attachments cannot be sent to chat models.
 */
class ChatContextBuilder
{
    /**
     * Rough characters per token, for the context window check.
     */
    protected const CHARS_PER_TOKEN = 4;

    /**
     * Rough token cost of one image sent as is.
     */
    protected const TOKENS_PER_IMAGE = 1_500;

    public function __construct(
        protected ModelCatalog $catalog,
        protected SettingsResolver $settings,
        protected MediaReader $reader,
        protected ContentSanitizer $sanitizer,
    ) {}

    /**
     * Describe how each piece of context will be handled, without calling any model.
     *
     * @return list<ContextEntry>
     */
    public function inspect(Chat $chat): array
    {
        $model = $this->catalog->find($this->settings->model($chat, 'text'));

        return array_values($this->sources($chat)->map(fn (ContextSource $source) => $this->entry($chat, $source, $model))->all());
    }

    /**
     * Build the context for the next message, converting media where needed.
     */
    public function build(Chat $chat, User $user): ChatContext
    {
        $model = $this->catalog->find($this->settings->model($chat, 'text'));
        $mediaModel = $this->settings->model($chat, 'media');
        $token = $this->sanitizer->newToken();

        $entries = [];
        $blocks = [];
        $images = [];
        $notes = [];

        foreach ($this->sources($chat) as $source) {
            $entry = $this->entry($chat, $source, $model);
            $entries[] = $entry;

            match ($entry->handling) {
                'excluded', 'unsupported' => null,
                'image' => $images[] = Image::fromPath($source->media->getPath(), $source->media->mime_type),
                'convert' => $blocks[] = $this->block($entry, $this->describe($source, $mediaModel, $user, $chat), $token),
                default => $blocks[] = $this->block($entry, (string) $source->text, $token),
            };

            if ($entry->handling === 'convert') {
                $notes[] = $entry->type === 'video'
                    ? "Video \"{$entry->title}\" was described from sampled frames by {$mediaModel}."
                    : "Image \"{$entry->title}\" was described by {$mediaModel}, because {$this->modelName($model)} cannot read images.";
            }

            if ($entry->handling === 'unsupported') {
                $notes[] = "\"{$entry->title}\" was left out: this file type cannot be read.";
            }
        }

        $blocks = $this->fitWindow($blocks, count($images), $model, $notes);

        $text = $blocks === [] ? '' : "The following context is provided for this chat.\n\n".implode("\n\n", $blocks);

        return new ChatContext(
            entries: $entries,
            text: $text,
            images: $images,
            notes: $notes,
            hasUntrusted: collect($entries)->contains(fn (ContextEntry $e) => $e->untrusted && $e->handling !== 'excluded'),
        );
    }

    /**
     * Every piece of context the chat can see.
     *
     * @return Collection<int, ContextSource>
     */
    protected function sources(Chat $chat): Collection
    {
        $sources = collect();

        if ($chat->project) {
            foreach ($chat->project->contextItems()->with('media')->orderBy('title')->get() as $item) {
                $sources->push(new ContextSource(
                    key: 'item-'.$item->id,
                    title: $item->title,
                    type: $item->type,
                    source: 'project',
                    text: $item->body,
                    media: $item->file(),
                    item: $item,
                    untrusted: $item->untrusted,
                ));
            }
        }

        foreach ($chat->getMedia('attachments') as $media) {
            $sources->push(new ContextSource(
                key: 'media-'.$media->id,
                title: $media->name,
                type: $this->typeForMime($media->mime_type),
                source: 'attachment',
                text: $this->isTextMime($media->mime_type) ? (string) file_get_contents($media->getPath()) : null,
                media: $media,
            ));
        }

        foreach ($chat->attachedScrapes()->where('status', 'completed')->get() as $scrape) {
            $sources->push(new ContextSource(
                key: 'scrape-'.$scrape->id,
                title: $scrape->title ?: $scrape->url,
                type: ContextType::Web,
                source: 'scrape',
                text: $scrape->markdown,
                scrape: $scrape,
                untrusted: true,
            ));
        }

        return $sources;
    }

    protected function entry(Chat $chat, ContextSource $source, ?AiModel $model): ContextEntry
    {
        $handling = match (true) {
            $source->item && $chat->excludes($source->item) => 'excluded',
            $source->type === ContextType::Image => match (true) {
                ! $source->media => 'unsupported',
                ! $model || $model->reads('image') => 'image',
                default => 'convert',
            },
            $source->type === ContextType::Video => $source->media ? 'convert' : 'unsupported',
            $source->text === null => 'unsupported',
            default => 'text',
        };

        return new ContextEntry(
            key: $source->key,
            title: $source->title,
            type: $source->type->value,
            source: $source->source,
            handling: $handling,
            untrusted: $source->untrusted,
            cached: $handling === 'convert' && filled($source->cachedDescription()),
            contextItemId: $source->item?->id,
            mediaId: $source->media?->id,
            scrapeId: $source->scrape?->id,
        );
    }

    protected function describe(ContextSource $source, string $mediaModel, User $user, Chat $chat): string
    {
        if (filled($cached = $source->cachedDescription())) {
            return $cached;
        }

        $media = $source->media ?? throw new \LogicException('Only media can be described.');

        $description = $source->type === ContextType::Video
            ? $this->reader->describeVideo($media->getPath(), $mediaModel, $user, $chat->team)
            : $this->reader->describeImage($media->getPath(), $media->mime_type, $mediaModel, $user, $chat->team);

        if ($source->item) {
            $source->item->forceFill(['description' => $description, 'description_model' => $mediaModel])->saveQuietly();
        } else {
            $media->setCustomProperty('description', $description)->save();
        }

        return $description;
    }

    protected function block(ContextEntry $entry, string $text, string $token): string
    {
        $heading = match ($entry->handling) {
            'convert' => "## {$entry->title} ({$entry->type}, described as text)",
            default => "## {$entry->title} ({$entry->type})",
        };

        if ($entry->untrusted) {
            return $heading."\n".$this->sanitizer->isolate($text, $token, $entry->title);
        }

        return $heading."\n".$text;
    }

    /**
     * Trim the largest text blocks when the context will not fit the model's window.
     * Aims for 60% of the window, leaving room for the conversation and the reply.
     *
     * @param  list<string>  $blocks
     * @param  list<string>  $notes
     * @return list<string>
     */
    protected function fitWindow(array $blocks, int $imageCount, ?AiModel $model, array &$notes): array
    {
        if (! $model?->context_length) {
            return $blocks;
        }

        $budgetChars = (int) ($model->context_length * 0.6 - $imageCount * self::TOKENS_PER_IMAGE) * self::CHARS_PER_TOKEN;
        $total = array_sum(array_map('mb_strlen', $blocks));

        if ($total <= $budgetChars) {
            return $blocks;
        }

        $ratio = max($budgetChars, 0) / $total;

        $notes[] = sprintf(
            'The context was too large for %s, so each text item was shortened to about %d%% of its length.',
            $this->modelName($model),
            (int) round($ratio * 100),
        );

        return array_map(
            fn (string $block) => Str::limit($block, (int) (mb_strlen($block) * $ratio), "\n[Shortened to fit the model.]"),
            $blocks,
        );
    }

    protected function modelName(?AiModel $model): string
    {
        return $model->name ?? 'the chat model';
    }

    public function typeForMime(?string $mime): ContextType
    {
        return match (true) {
            str_starts_with((string) $mime, 'image/') => ContextType::Image,
            str_starts_with((string) $mime, 'video/') => ContextType::Video,
            default => ContextType::Text,
        };
    }

    public function isTextMime(?string $mime): bool
    {
        return str_starts_with((string) $mime, 'text/')
            || in_array($mime, ['application/json', 'application/xml', 'application/x-yaml'], true);
    }
}
