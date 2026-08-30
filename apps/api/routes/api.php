<?php

use App\Http\Controllers\Api\Admin\AnalyticsController;
use App\Http\Controllers\Api\Admin\AuthController;
use App\Http\Controllers\Api\Admin\CatalogController;
use App\Http\Controllers\Api\Admin\LeadActionController;
use App\Http\Controllers\Api\Admin\LeadController;
use App\Http\Controllers\Api\Admin\SequenceController;
use App\Http\Controllers\Api\Admin\SettingController;
use App\Http\Controllers\Api\Admin\VoiceAgentController;
use App\Http\Controllers\Api\ElevenLabsWebhookController;
use App\Http\Controllers\Api\LeadIntakeController;
use App\Http\Controllers\Api\PublicQuoteController;
use Illuminate\Support\Facades\Route;

// ---------------------------------------------------------------------------
// Publiek
// ---------------------------------------------------------------------------

Route::post('/leads', [LeadIntakeController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('leads.store');

Route::get('/quotes/{token}', [PublicQuoteController::class, 'show'])
    ->middleware('throttle:60,1')
    ->whereAlphaNumeric('token')
    ->name('quotes.public');

// ---------------------------------------------------------------------------
// Webhooks (handtekening geverifieerd, geen sessie)
// ---------------------------------------------------------------------------

Route::post('/webhooks/elevenlabs/post-call', ElevenLabsWebhookController::class)
    ->middleware('elevenlabs.signature')
    ->name('webhooks.elevenlabs');

// ---------------------------------------------------------------------------
// Dashboard
// ---------------------------------------------------------------------------

Route::post('/admin/login', [AuthController::class, 'login'])
    ->middleware('throttle:10,1')
    ->name('admin.login');

Route::middleware('auth:sanctum')->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/me', [AuthController::class, 'me'])->name('me');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('/password', [AuthController::class, 'updatePassword'])
        ->middleware('throttle:10,1')
        ->name('password.update');

    Route::get('/leads', [LeadController::class, 'index'])->name('leads.index');
    Route::get('/leads/statuses', [LeadController::class, 'statuses'])->name('leads.statuses');
    Route::get('/leads/{uuid}', [LeadController::class, 'show'])->name('leads.show');
    Route::patch('/leads/{uuid}', [LeadController::class, 'update'])->name('leads.update');
    Route::post('/leads/{uuid}/actions', LeadActionController::class)->name('leads.actions');

    Route::get('/analytics', AnalyticsController::class)->name('analytics');

    Route::get('/catalog', [CatalogController::class, 'index'])->name('catalog.index');
    Route::patch('/catalog/{id}', [CatalogController::class, 'update'])->whereNumber('id')->name('catalog.update');

    Route::get('/sequences', [SequenceController::class, 'index'])->name('sequences.index');
    Route::patch('/sequences/steps/{id}', [SequenceController::class, 'updateStep'])->whereNumber('id')->name('sequences.steps.update');

    Route::post('/voice/agent-sync', [VoiceAgentController::class, 'sync'])
        ->middleware('throttle:6,1')
        ->name('voice.agent-sync');

    Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
    Route::patch('/settings', [SettingController::class, 'update'])->name('settings.update');
});
