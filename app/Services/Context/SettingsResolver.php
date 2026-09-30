<?php

namespace App\Services\Context;

use App\Enums\PromptMode;
use App\Models\Chat;

/**
 * Resolves the settings a chat runs with. The most specific setting wins:
 * chat over project over workspace settings.
 */
class SettingsResolver
{
    /**
     * The model a chat uses for text, image, video or media reading.
     *
     * @param  'text'|'image'|'video'|'media'  $kind
     */
    public function model(Chat $chat, string $kind): string
    {
        return $chat->{$kind.'_model'} ?: $chat->team->workspaceSettings()->model($kind);
    }

    /**
     * @return array{text: string, image: string, video: string, media: string}
     */
    public function models(Chat $chat): array
    {
        return [
            'text' => $this->model($chat, 'text'),
            'image' => $this->model($chat, 'image'),
            'video' => $this->model($chat, 'video'),
            'media' => $this->model($chat, 'media'),
        ];
    }

    /**
     * The user-facing system prompt for a chat.
     *
     * Each layer either adds to the layers above it or replaces them. Adding is
     * the default, and added prompts are joined in order: settings, project, chat.
     * A free chat never takes project instructions.
     */
    public function systemPrompt(Chat $chat): string
    {
        return collect($this->promptLayers($chat))
            ->where('active', true)
            ->pluck('text')
            ->implode("\n\n");
    }

    /**
     * Every layer with whether it is in effect, for display in the chat.
     *
     * @return list<array{source: string, text: string, mode: string, active: bool}>
     */
    public function promptLayers(Chat $chat): array
    {
        $layers = [];

        if (filled($default = $chat->team->workspaceSettings()->system_prompt)) {
            $layers[] = ['source' => 'settings', 'text' => $default, 'mode' => PromptMode::Append->value];
        }

        if ($chat->project && filled($chat->project->instructions)) {
            $layers[] = ['source' => 'project', 'text' => $chat->project->instructions, 'mode' => $chat->project->instructions_mode->value];
        }

        if (filled($chat->system_prompt)) {
            $layers[] = ['source' => 'chat', 'text' => $chat->system_prompt, 'mode' => $chat->prompt_mode->value];
        }

        // A replacing layer switches off every layer before it.
        $firstActive = 0;

        foreach ($layers as $index => $layer) {
            if ($layer['mode'] === PromptMode::Replace->value) {
                $firstActive = $index;
            }
        }

        $result = [];

        foreach ($layers as $index => $layer) {
            $result[] = [...$layer, 'active' => $index >= $firstActive];
        }

        return $result;
    }
}
