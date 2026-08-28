<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\QuoteMail;
use App\Models\Lead;
use App\Services\LeadWorkflow;
use App\Services\Mailer;
use App\Services\QuotePdfRenderer;
use App\Services\SettingsRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Stap 4: offerte opstellen, als pdf renderen en direct mailen. Daarna plant
 * de workflow zelf het conversiegesprek op T+1 uur in.
 */
class SendQuoteJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $leadId) {}

    public function handle(
        LeadWorkflow $workflow,
        Mailer $mailer,
        QuotePdfRenderer $renderer,
        SettingsRepository $settings,
    ): void {
        $lead = Lead::find($this->leadId);

        if ($lead === null || $lead->email === null) {
            return;
        }

        $quote = $workflow->buildQuote($lead);

        $pdf = null;

        if (! $settings->bool('agent.dry_run', false)) {
            try {
                $pdf = $renderer->render($quote);
            } catch (Throwable $e) {
                // Zonder pdf gaat de offerte alsnog als mail de deur uit.
                Log::warning('Offerte-pdf kon niet gerenderd worden', ['quote_id' => $quote->id, 'exception' => $e->getMessage()]);
            }
        }

        $mailer->send($lead, $lead->email, 'quote', new QuoteMail($lead, $quote, $pdf));

        $workflow->markQuoteSent($lead, $quote);
    }
}
