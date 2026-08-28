<?php

use App\Models\Quote;
use Illuminate\Support\Facades\Schedule;

// Mailbox uitlezen: elke vijf minuten, zodat een lead binnen minuten opgepakt wordt.
Schedule::command('leads:poll-mailbox')->everyFiveMinutes()->withoutOverlapping();

// Hartslag van de workflow: ingeplande gesprekken en opvolgstappen uitvoeren.
Schedule::command('agent:tick')->everyMinute()->withoutOverlapping();

// Verlopen offertes opruimen.
Schedule::call(function (): void {
    Quote::whereIn('status', ['draft', 'sent', 'viewed'])
        ->whereNotNull('valid_until')
        ->whereDate('valid_until', '<', now()->toDateString())
        ->update(['status' => 'expired']);
})->dailyAt('03:15')->name('offertes-verlopen')->withoutOverlapping();
