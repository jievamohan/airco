<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Lead;
use App\Services\AppointmentScheduler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Stap 6: afspraak vastleggen en in de gekoppelde agenda zetten.
 */
class BookAppointmentJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $leadId,
        public readonly ?string $preferredStart = null,
    ) {}

    public function handle(AppointmentScheduler $scheduler): void
    {
        $lead = Lead::find($this->leadId);

        if ($lead === null) {
            return;
        }

        $scheduler->book(
            $lead,
            $lead->latestQuote()->first(),
            $scheduler->parseLocal($this->preferredStart),
        );
    }
}
