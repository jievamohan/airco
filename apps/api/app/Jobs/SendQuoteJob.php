<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\QuoteKind;
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
 * Stap 4: het prijsdocument opstellen, als pdf renderen en mailen.
 *
 * Standaard is dat de vrijblijvende prijsindicatie; daarna plant de workflow
 * zelf het conversiegesprek in om een opname af te spreken. De bindende
 * offerte volgt pas als die opname is geweest — deze job weigert hem eerder,
 * want een aanbod op basis van een telefoongesprek is een aanbod dat we
 * moeten waarmaken.
 */
class SendQuoteJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $leadId,
        public readonly QuoteKind $kind = QuoteKind::Indication,
    ) {}

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

        if ($this->kind->isBinding() && ! $workflow->surveyDone($lead)) {
            Log::warning('Offerte geweigerd: er is nog geen opname geweest', ['lead_id' => $lead->id]);

            return;
        }

        $quote = $workflow->buildQuote($lead, $this->kind);

        $pdf = null;

        if (! $settings->bool('agent.dry_run', false)) {
            try {
                $pdf = $renderer->render($quote);
            } catch (Throwable $e) {
                // Zonder pdf gaat het document alsnog als mail de deur uit.
                Log::warning('Pdf kon niet gerenderd worden', ['quote_id' => $quote->id, 'exception' => $e->getMessage()]);
            }
        }

        $mailer->send($lead, $lead->email, $this->kind->isBinding() ? 'quote' : 'indication', new QuoteMail($lead, $quote, $pdf));

        if ($this->kind->isBinding()) {
            $workflow->markQuoteSent($lead, $quote);

            return;
        }

        $workflow->markIndicationSent($lead, $quote);
    }
}
