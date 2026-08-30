<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\QuoteKind;
use App\Models\Lead;
use App\Services\AppointmentScheduler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Stap 6: afspraak vastleggen en in de gekoppelde agenda zetten.
 *
 * Twee soorten: eerst de opname ter plaatse, en pas na de offerte de montage.
 */
class BookAppointmentJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $leadId,
        public readonly ?string $preferredStart = null,
        public readonly string $kind = 'survey',
    ) {}

    public function handle(AppointmentScheduler $scheduler): void
    {
        $lead = Lead::find($this->leadId);

        if ($lead === null) {
            return;
        }

        // Bij de montage hoort de offerte, niet het richtbedrag dat daarvoor
        // de deur uit ging: die afspraak accepteert de offerte namelijk.
        $quote = $this->kind === 'installation'
            ? $lead->quotes()->where('kind', QuoteKind::Final->value)->latest('id')->first()
            : $lead->latestQuote()->first();

        $scheduler->book(
            $lead,
            $quote ?? $lead->latestQuote()->first(),
            $scheduler->parseLocal($this->preferredStart),
            $this->kind,
        );
    }
}
