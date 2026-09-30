<?php

use App\Models\TeamInvitation;
use App\Services\OpenRouter\ModelCatalog;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    TeamInvitation::query()
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->delete();
})->daily()->description('Delete expired team invitations');

Artisan::command('ai:sync-models', function (ModelCatalog $catalog) {
    $this->info("Synced {$catalog->sync()} models from OpenRouter.");
})->purpose('Refresh the local copy of the OpenRouter model list');

Schedule::command('ai:sync-models')->daily()->description('Refresh the OpenRouter model list');
Schedule::command('backup:clean')->daily()->at('01:00');
Schedule::command('backup:run')->daily()->at('01:30');
Schedule::command('queue:prune-failed --hours=168')->daily();
