<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\DispatchCallJob;
use App\Jobs\QueueHeartbeatJob;
use App\Models\Call;
use App\Services\CallingWindow;
use App\Services\SequenceRunner;
use App\Services\SystemHeartbeat;
use Illuminate\Console\Command;

/**
 * De hartslag van de workflow: zet ingeplande gesprekken door en voert
 * openstaande cadans-stappen uit.
 */
class RunDueActionsCommand extends Command
{
    protected $signature = 'agent:tick';

    protected $description = 'Voert alle acties uit die nu aan de beurt zijn (gesprekken en opvolgstappen).';

    public function handle(SequenceRunner $sequences, CallingWindow $window, SystemHeartbeat $heartbeat): int
    {
        // De planner meldt zich hier, de worker zodra hij deze job oppakt. Zo
        // laat het dashboard zien welk van de twee stilgevallen is.
        $heartbeat->record('scheduler');
        QueueHeartbeatJob::dispatch();

        $dueCalls = Call::where('status', 'queued')
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for', '<=', now())
            ->limit(50)
            ->get();

        $dispatched = 0;
        $deferred = 0;

        foreach ($dueCalls as $call) {
            // Een gesprek dat iemand bewust buiten het venster heeft klaargezet
            // mag hier niet alsnog vooruitgeschoven worden.
            if (! $call->ignores_calling_window && ! $window->isOpenAt(now())) {
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
