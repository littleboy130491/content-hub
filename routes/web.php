<?php

use App\Http\Controllers\ChatAttachmentController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ChatMessageController;
use App\Http\Controllers\ContextItemController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\OutputController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Controllers\WebScrapeController;
use App\Http\Controllers\WorkspaceSettingsController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

// Everything in a workspace lives under its slug. Scoped bindings resolve each
// record through the workspace, so another workspace's record is a 404.
Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->scopeBindings()
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::get('context', [ContextItemController::class, 'index'])->name('context.index');
        Route::post('context', [ContextItemController::class, 'store'])->name('context.store');
        Route::get('context/{contextItem}', [ContextItemController::class, 'show'])->name('context.show');
        Route::patch('context/{contextItem}', [ContextItemController::class, 'update'])->name('context.update');
        Route::delete('context/{contextItem}', [ContextItemController::class, 'destroy'])->name('context.destroy');
        Route::post('context/{contextItem}/rescrape', [WebScrapeController::class, 'rescrape'])->name('context.rescrape');

        Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
        Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');
        Route::get('projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
        Route::patch('projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
        Route::delete('projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');
        Route::put('projects/{project}/context', [ProjectController::class, 'syncContext'])->name('projects.context');
        Route::post('projects/{project}/chats', [ChatController::class, 'storeForProject'])->name('projects.chats.store');

        Route::get('chats', [ChatController::class, 'index'])->name('chats.index');
        Route::post('chats', [ChatController::class, 'store'])->name('chats.store');
        Route::get('chats/{chat}', [ChatController::class, 'show'])->name('chats.show');
        Route::patch('chats/{chat}', [ChatController::class, 'update'])->name('chats.update');
        Route::delete('chats/{chat}', [ChatController::class, 'destroy'])->name('chats.destroy');
        Route::post('chats/{chat}/move', [ChatController::class, 'move'])->name('chats.move');
        Route::post('chats/{chat}/context', [ChatController::class, 'addContext'])->name('chats.context.store');
        Route::delete('chats/{chat}/context/{contextItem}', [ChatController::class, 'removeContext'])->name('chats.context.destroy');
        Route::post('chats/{chat}/messages', [ChatMessageController::class, 'store'])->name('chats.messages.store');
        Route::post('chats/{chat}/messages/{message}/output', [ChatMessageController::class, 'saveAsOutput'])->name('chats.messages.output');
        Route::post('chats/{chat}/attachments', [ChatAttachmentController::class, 'store'])->name('chats.attachments.store');
        Route::delete('chats/{chat}/attachments/{media}', [ChatAttachmentController::class, 'destroy'])->name('chats.attachments.destroy');
        Route::post('chats/{chat}/attachments/{media}/library', [ChatAttachmentController::class, 'saveToLibrary'])->name('chats.attachments.library');

        Route::get('outputs', [OutputController::class, 'index'])->name('outputs.index');
        Route::delete('outputs/{output}', [OutputController::class, 'destroy'])->name('outputs.destroy');
        Route::post('outputs/{output}/context', [OutputController::class, 'addToContext'])->name('outputs.context');
        Route::post('outputs/{output}/start', [OutputController::class, 'start'])->name('outputs.start');
        Route::post('outputs/{output}/retry', [OutputController::class, 'retry'])->name('outputs.retry');

        Route::post('scrapes', [WebScrapeController::class, 'store'])->name('scrapes.store');
        Route::get('scrapes/{webScrape}', [WebScrapeController::class, 'show'])->name('scrapes.show');
        Route::post('scrapes/{webScrape}/library', [WebScrapeController::class, 'saveToLibrary'])->name('scrapes.library');
        Route::post('scrapes/{webScrape}/chat', [WebScrapeController::class, 'useInChat'])->name('scrapes.chat');

        Route::get('workspace/settings', [WorkspaceSettingsController::class, 'edit'])->name('workspace.settings');
        Route::put('workspace/settings', [WorkspaceSettingsController::class, 'update'])->name('workspace.settings.update');
        Route::post('workspace/models/sync', [WorkspaceSettingsController::class, 'syncModels'])->name('workspace.models.sync');

        // Media belongs to several model types, so ownership is checked in the controller.
        Route::get('media/{media}', MediaController::class)->withoutScopedBindings()->name('media.show');
    });

Route::middleware(['auth'])->group(function () {
    Route::post('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';
