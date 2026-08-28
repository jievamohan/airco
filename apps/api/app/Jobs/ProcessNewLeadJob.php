<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\CallPurpose;
use App\Models\Lead;
use App\Services\LeadWorkflow;
use App\Services\Notifier;
use App\Services\SettingsRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Stap 1 en 2: verrijken en de eerste belpoging inplannen.
 */
class ProcessNewLeadJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $leadId) {}

    public function handle(LeadWorkflow $workflow, Notifier $notifier, SettingsRepository $settings): void
    {
        $lead = Lead::find($this->leadId);

        if ($lead === null) {
            return;
        }

        $workflow->enrich($lead);
        $notifier->leadReceived($lead);

        $delay = $settings->int('agent.workflow.first_call_delay_minutes', 3);
        $workflow->scheduleCall($lead->refresh(), CallPurpose::Qualification, now()->addMinutes($delay));
    }
}
