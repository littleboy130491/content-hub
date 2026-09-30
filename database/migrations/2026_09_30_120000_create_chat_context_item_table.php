<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Library items a user added to one chat, on top of its project's context.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_context_item', function (Blueprint $table) {
            $table->foreignId('chat_id')->constrained()->cascadeOnDelete();
            $table->foreignId('context_item_id')->constrained()->cascadeOnDelete();
            $table->primary(['chat_id', 'context_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_context_item');
    }
};
