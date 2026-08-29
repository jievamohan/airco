<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CallPurpose;
use App\Mail\ChaseMail;
use App\Models\LeadSequenceRun;
use App\Models\SequenceStep;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Voert de chase-cadans uit: per due run de volgende stap, en daarna de
 * volgende stap inplannen. Stopt zodra de lead reageert of de rij op is.
 */
class SequenceRunner
{
    public function __construct(
        private readonly LeadWorkflow $workflow,
        private readonly LeadTimeline $timeline,
        private readonly Mailer $mailer,
        private readonly QuoteBuilder $quotes,
    ) {}

    /**
     * @return int aantal uitgevoerde stappen
     */
    public function runDue(): int
    {
        $runs = LeadSequenceRun::with(['lead', 'sequence'])
            ->where('status', 'active')
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now())
            ->limit(100)
            ->get();

        $executed = 0;

        foreach ($runs as $run) {
            try {
                if ($this->runStep($run)) {
                    $executed++;
                }
            } catch (Throwable $e) {
                Log::error('Uitvoeren van een opvolgstap mislukt', ['run_id' => $run->id, 'exception' => $e->getMessage()]);
                $run->forceFill(['next_run_at' => now()->addHour()])->save();
            }
        }

        return $executed;
    }

    public function runStep(LeadSequenceRun $run): bool
    {
        $lead = $run->lead;

        if (! $lead->isContactable()) {
            $run->forceFill(['status' => 'stopped', 'stop_reason' => 'Lead mag niet meer benaderd worden.', 'next_run_at' => null])->save();

            return false;
        }

        $step = $run->sequence->steps()
            ->where('active', true)
            ->where('position', '>=', $run->next_position)
            ->orderBy('position')
            ->first();

        if ($step === null) {
            $run->forceFill(['status' => 'completed', 'completed_at' => now(), 'next_run_at' => null])->save();
            $this->workflow->markUnreachable($lead);

            return false;
        }

        $this->execute($run, $step);

        $next = $run->sequence->steps()
            ->where('active', true)
            ->where('position', '>', $step->position)
            ->orderBy('position')
            ->first();

        if ($next === null) {
            $run->forceFill([
                'next_position' => $step->position + 1,
                'status' => 'completed',
                'completed_at' => now(),
                'next_run_at' => null,
            ])->save();

            $this->workflow->markUnreachable($lead);

            return true;
        }

        $run->forceFill([
            'next_position' => $next->position,
            'next_run_at' => now()->addMinutes($next->delay_minutes),
        ])->save();

        return true;
    }

    private function execute(LeadSequenceRun $run, SequenceStep $step): void
    {
        $lead = $run->lead;

        if ($step->channel === 'call') {
            $purpose = $step->action === 'final' ? CallPurpose::Final : CallPurpose::Chase;
            $this->workflow->scheduleCall($lead, $purpose);

            return;
        }

        if ($lead->email === null) {
            $this->timeline->record($lead, 'email_skipped', 'Mailstap overgeslagen', 'Er is geen e-mailadres bekend.');

            return;
        }

        $quote = $lead->latestQuote()->first();

        if ($step->action === 'quote_without_call' && $quote === null) {
            // Nog geen offerte: op basis van de bekende gegevens alsnog een
            // indicatie opstellen, zodat de mail iets concreets bevat.
            $quote = $this->quotes->createForLead($lead);
            $this->timeline->record(
                $lead,
                'quote_created',
                'Indicatie opgesteld zonder gesprek',
                sprintf('%s — € %s incl. btw.', $quote->number, number_format($quote->total_cents / 100, 2, ',', '.')),
                ['quote_id' => $quote->id],
            );
        }

        $this->mailer->send($lead, $lead->email, 'chase_'.$step->action, new ChaseMail($lead, $step->action, $quote));

        $lead->forceFill(['email_attempts' => $lead->email_attempts + 1, 'last_contact_at' => now()])->save();

        $this->timeline->record(
            $lead,
            'chase_email_sent',
            $step->label,
            sprintf('Verstuurd naar %s.', $lead->email),
            ['step' => $step->position, 'action' => $step->action],
        );
    }
}
