<?php

namespace App\Http\Controllers;

use App\Ai\Agents\ContentAgent;
use App\Ai\Generation;
use App\Enums\OutputStatus;
use App\Enums\OutputType;
use App\Enums\UsageKind;
use App\Models\Chat;
use App\Models\ChatMessage;
use App\Models\Team;
use App\Services\Context\ChatContextBuilder;
use App\Services\Context\SettingsResolver;
use App\Services\Usage\UsageLimitReached;
use App\Services\Usage\UsageMeter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Laravel\Ai\Responses\StreamedAgentResponse;
use Throwable;

class ChatMessageController extends Controller
{
    /**
     * How many earlier messages are sent with each turn.
     */
    protected const HISTORY_LIMIT = 30;

    /**
     * Send a message and stream the agent's reply in the Vercel AI SDK protocol.
     */
    public function store(
        Request $request,
        Team $currentTeam,
        Chat $chat,
        ChatContextBuilder $builder,
        SettingsResolver $settings,
        UsageMeter $usage,
    ): StreamableAgentResponse|JsonResponse {
        $data = $request->validate([
            'text' => ['nullable', 'required_without:attachment_ids', 'string', 'max:20000'],
            'attachment_ids' => ['nullable', 'array', 'max:10'],
            'attachment_ids.*' => ['integer'],
        ]);

        $text = trim((string) ($data['text'] ?? ''));

        // Only this chat's unsent drafts can go with the message.
        $drafts = $chat->draftAttachments()->whereIn('id', $data['attachment_ids'] ?? [])->values();

        if ($text === '' && $drafts->isEmpty()) {
            return response()->json(['message' => 'Write a message or attach a file.'], 422);
        }

        $user = $request->user();

        if (blank(config('contenthub.openrouter.key'))) {
            return response()->json(['message' => 'Chat is not set up: add OPENROUTER_API_KEY to the server\'s .env file.'], 503);
        }

        try {
            $usage->ensureCanSpend($user);
        } catch (UsageLimitReached $e) {
            return response()->json(['message' => $e->getMessage()], 429);
        }

        $userMessage = $chat->messages()->create([
            'user_id' => $user->id,
            'role' => 'user',
            'content' => $text,
        ]);

        foreach ($drafts as $media) {
            $media->setCustomProperty('message_id', $userMessage->id)->save();
        }

        $chat->unsetRelation('media');

        if ($chat->title === 'New chat') {
            $chat->update(['title' => Str::limit(Str::squish($text !== '' ? $text : $drafts->pluck('file_name')->implode(', ')), 60)]);
        }

        try {
            $context = $builder->build($chat, $user);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'The chat context could not be prepared: '.$e->getMessage()], 422);
        }

        $models = $settings->models($chat);

        $assistant = $chat->messages()->create([
            'role' => 'assistant',
            'content' => '',
            'meta' => ['model' => $models['text'], 'notes' => $context->notes],
        ]);

        $generation = new Generation($chat, $user, $assistant, $context, $models);

        // Context goes in the user message, never the system prompt, so web
        // content cannot pose as instructions. History is stored without it.
        $ask = $text !== '' ? $text : 'The user sent the attached files without a message. Look at them and ask what they would like to create.';

        if ($drafts->isNotEmpty()) {
            $ask .= "\n\n(Attached with this message: ".$drafts->pluck('file_name')->implode(', ').'.)';
        }

        $prompt = $context->text === ''
            ? $ask
            : $context->text."\n\n---\n\nUser request:\n".$ask;

        return (new ContentAgent($generation))
            ->withMessages($this->history($chat, $userMessage))
            ->stream($prompt, attachments: $context->images, provider: config('contenthub.provider'), model: $models['text'])
            ->then(function (StreamedAgentResponse $response) use ($assistant, $usage, $user, $chat, $models) {
                $assistant->update([
                    'content' => $response->text !== '' ? $response->text : '(No text reply.)',
                ]);

                $usage->recordTokens($user, $chat->team, UsageKind::Text, $models['text'], $response->usage, ['chat_id' => $chat->id]);

                $chat->touch();
            })
            ->catch(function (Throwable $e) use ($assistant) {
                report($e);

                $assistant->update([
                    'content' => 'The reply failed: '.$e->getMessage(),
                    'meta' => [...($assistant->meta ?? []), 'failed' => true],
                ]);
            })
            ->usingVercelDataProtocol();
    }

    /**
     * Save an assistant reply as a finished text output.
     */
    public function saveAsOutput(Request $request, Team $currentTeam, Chat $chat, ChatMessage $message): RedirectResponse
    {
        abort_unless($message->role === 'assistant' && filled($message->content), 422);

        if ($id = $message->meta['saved_output_id'] ?? null) {
            $this->toast('This reply is already saved as an output.', 'info');

            return back();
        }

        $firstLine = Str::of($message->content)->explode("\n")->map(fn ($line) => trim($line, "# *\t"))->first(fn ($line) => $line !== '');

        $output = $currentTeam->outputs()->create([
            'user_id' => $request->user()->id,
            'chat_id' => $chat->id,
            'chat_message_id' => $message->id,
            'type' => OutputType::Text,
            'title' => Str::limit($firstLine ?: $chat->title, 60),
            'content' => $message->content,
            'model' => $message->meta['model'] ?? null,
            'status' => OutputStatus::Completed,
        ]);

        $message->update(['meta' => [...($message->meta ?? []), 'saved_output_id' => $output->id]]);

        $this->toast('Saved to outputs.');

        return back();
    }

    /**
     * Earlier turns, oldest first, without failed replies.
     *
     * @return list<Message>
     */
    protected function history(Chat $chat, ChatMessage $current): array
    {
        return array_values($chat->messages()
            ->where('id', '<', $current->id)
            ->where('content', '!=', '')
            ->reorder('id', 'desc')
            ->take(self::HISTORY_LIMIT)
            ->get()
            ->reject(fn (ChatMessage $message) => $message->meta['failed'] ?? false)
            ->reverse()
            ->map(fn (ChatMessage $message) => new Message($message->role, $message->content))
            ->all());
    }
}
