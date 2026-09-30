<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Content Hub's own tables. A workspace is a Team from the starter kit, so every
 * workspace-owned table carries a team_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('system_prompt')->nullable();
            $table->string('text_model')->nullable();
            $table->string('image_model')->nullable();
            $table->string('video_model')->nullable();
            $table->string('media_model')->nullable();
            $table->timestamps();
        });

        // Cached copy of the provider's model list, used for pickers and capability checks.
        Schema::create('ai_models', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->json('input_modalities');
            $table->json('output_modalities');
            $table->unsignedInteger('context_length')->nullable();
            $table->boolean('supports_tools')->default(false);
            $table->json('pricing')->nullable();
            $table->json('video_options')->nullable();
            $table->timestamp('synced_at')->nullable();
        });

        Schema::create('context_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type'); // text, image, video, web
            $table->string('title');
            $table->longText('body')->nullable();
            $table->string('source_url', 2048)->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->boolean('untrusted')->default(false);
            $table->longText('description')->nullable();
            $table->string('description_model')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'type']);
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('instructions')->nullable();
            $table->string('instructions_mode')->default('append');
            $table->timestamps();
        });

        Schema::create('context_item_project', function (Blueprint $table) {
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('context_item_id')->constrained()->cascadeOnDelete();
            $table->primary(['project_id', 'context_item_id']);
        });

        Schema::create('chats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('system_prompt')->nullable();
            $table->string('prompt_mode')->default('append');
            $table->string('text_model')->nullable();
            $table->string('image_model')->nullable();
            $table->string('video_model')->nullable();
            $table->string('media_model')->nullable();
            $table->json('excluded_context_item_ids')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'updated_at']);
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role'); // user, assistant
            $table->longText('content');
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('web_scrapes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('chat_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('context_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('url', 2048);
            $table->text('instruction')->nullable();
            $table->string('mode')->nullable(); // page, crawl
            $table->string('mode_reason')->nullable();
            $table->string('status')->default('pending');
            $table->string('firecrawl_job_id')->nullable();
            $table->string('title')->nullable();
            $table->longText('markdown')->nullable();
            $table->json('pages')->nullable();
            $table->unsignedInteger('credits')->default(0);
            $table->boolean('attached_to_chat')->default(false);
            $table->text('error')->nullable();
            $table->timestamps();
        });

        Schema::create('outputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('chat_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('chat_message_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('context_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type'); // text, image, video
            $table->string('title');
            $table->text('prompt')->nullable();
            $table->longText('content')->nullable();
            $table->string('model')->nullable();
            $table->string('status')->default('completed');
            $table->string('provider_job_id')->nullable();
            $table->json('options')->nullable();
            $table->decimal('cost', 12, 6)->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'type', 'status']);
        });

        Schema::create('usage_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind'); // text, image, video, media_reading, scrape
            $table->string('model')->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('credits')->default(0);
            $table->decimal('cost', 12, 6)->default(0);
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_records');
        Schema::dropIfExists('outputs');
        Schema::dropIfExists('web_scrapes');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chats');
        Schema::dropIfExists('context_item_project');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('context_items');
        Schema::dropIfExists('ai_models');
        Schema::dropIfExists('workspace_settings');
    }
};
