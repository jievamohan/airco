<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\DispatchCallJob;
use App\Models\Call;
use App\Services\CallingWindow;
use App\Services\SequenceRunner;
use Illuminate\Console\Command;

/**
 * De hartslag van de workflow: zet ingeplande gesprekken door en voert
 * openstaande cadans-stappen uit.
 */
class RunDueActionsCommand extends Command
{
    protected $signature = 'agent:tick';

    protected $description = 'Voert alle acties uit die nu aan de beurt zijn (gesprekken en opvolgstappen).';

    public function handle(SequenceRunner $sequences, CallingWindow $window): int
    {
        $dueCalls = Call::where('status', 'queued')
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for', '<=', now())
            ->limit(50)
            ->get();

        $dispatched = 0;
        $deferred = 0;

        foreach ($dueCalls as $call) {
            if (! $window->isOpenAt(now())) {
                $call->forceFill(['scheduled_for' => $window->nextOpening(now())])->save();
                $deferred++;

                continue;
            }

            DispatchCallJob::dispatch($call->id);
            $dispatched++;
        }

        $steps = $sequences->runDue();

        $this->info(sprintf('%d gesprekken gestart, %d uitgesteld buiten belvenster, %d opvolgstappen uitgevoerd.', $dispatched, $deferred, $steps));

        return self::SUCCESS;
    }
}
