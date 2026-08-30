<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\LeadStatus;
use App\Mail\AppointmentMail;
use App\Models\Appointment;
use App\Models\Lead;
use App\Models\Quote;
use App\Services\Calendar\CalendarClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Kiest een montagemoment, legt het vast en zet het in de gekoppelde agenda.
 */
class AppointmentScheduler
{
    public function __construct(
        private readonly CalendarClient $calendar,
        private readonly SettingsRepository $settings,
        private readonly LeadTimeline $timeline,
        private readonly Notifier $notifier,
        private readonly Mailer $mailer,
        private readonly IcsBuilder $ics,
    ) {}

    /**
     * Eerstvolgende vrije werkdagmomenten waarop een klus van $minutes past.
     *
     * @return list<Carbon>
     */
    public function availableSlots(int $minutes, int $limit = 5): array
    {
        $timezone = $this->settings->string('agent.calendar.timezone', 'Europe/Amsterdam');
        $leadTime = $this->settings->int('agent.calendar.slot_lead_time_hours', 48);
        $horizon = $this->settings->int('agent.calendar.slot_horizon_days', 21);

        [$startHour, $startMinute] = array_map('intval', explode(':', $this->settings->string('agent.calendar.workday.start', '08:00')));
        [$endHour, $endMinute] = array_map('intval', explode(':', $this->settings->string('agent.calendar.workday.end', '17:00')));

        /** @var list<int> $workdays */
        $workdays = config('agent.calendar.workday.days', [1, 2, 3, 4, 5]);

        $earliest = now($timezone)->addHours($leadTime);
        $slots = [];

        for ($day = 0; $day <= $horizon && count($slots) < $limit; $day++) {
            $date = now($timezone)->addDays($day)->startOfDay();

            if (! in_array($date->dayOfWeekIso, $workdays, true)) {
                continue;
            }

            $dayStart = $date->copy()->setTime($startHour, $startMinute);
            $dayEnd = $date->copy()->setTime($endHour, $endMinute);

            if ($dayStart->lessThan($earliest)) {
                continue;
            }

            if ($dayStart->copy()->addMinutes($minutes)->greaterThan($dayEnd)) {
                // Klus past niet binnen één werkdag: toch aanbieden, de planning
                // van meerdaags werk gebeurt handmatig.
                $slots[] = $dayStart;

                continue;
            }

            $slots[] = $dayStart;

            $afternoon = $dayEnd->copy()->subMinutes($minutes);

            if ($afternoon->greaterThan($dayStart->copy()->addMinutes($minutes)) && count($slots) < $limit) {
                $slots[] = $afternoon;
            }
        }

        return array_slice($slots, 0, $limit);
    }

    /**
     * Boekt een afspraak, koppelt hem aan de agenda en informeert klant en ondernemer.
     *
     * @param  string  $kind  `survey` voor de opname ter plaatse, `installation`
     *                        voor de montage. De opname komt eerst: zonder dat
     *                        bezoek is er geen offerte en dus niets om te
     *                        installeren.
     */
    public function book(Lead $lead, ?Quote $quote, ?Carbon $preferredStart = null, string $kind = 'installation'): Appointment
    {
        $survey = $kind === 'survey';
        $timezone = $this->settings->string('agent.calendar.timezone', 'Europe/Amsterdam');
        $minutes = $survey
            ? max(15, $this->settings->int('agent.calendar.survey_minutes', 45))
            : ($quote?->onsite_minutes ?: 240);

        $start = $this->resolveStart($preferredStart, $minutes, $timezone);

        $appointment = $lead->appointments()->create([
            'quote_id' => $quote?->id,
            'provider' => $this->calendar->provider(),
            'ics_uid' => Str::uuid()->toString().'@klimaatx',
            'kind' => $survey ? 'survey' : 'installation',
            'title' => sprintf($survey ? 'Opname airco — %s' : 'Airco-installatie — %s', $lead->name),
            'location' => $lead->displayLocation(),
            'notes' => $this->buildNotes($lead, $quote),
            // Altijd in UTC opslaan; de kolom `timezone` bepaalt hoe het getoond wordt.
            'starts_at' => $start->copy()->utc(),
            'ends_at' => $start->copy()->addMinutes($minutes)->utc(),
            'timezone' => $timezone,
            'status' => 'scheduled',
        ]);

        $result = $this->calendar->createEvent($appointment);

        $appointment->forceFill([
            'provider_event_id' => $result['event_id'],
            'calendar_ref' => $result['calendar_ref'],
            'sync_error' => $result['error'],
        ])->save();

        $this->timeline->record(
            $lead,
            $survey ? 'survey_booked' : 'appointment_booked',
            $survey ? 'Opname ingepland' : 'Installatie ingepland',
            sprintf(
                '%s, %s uur%s.',
                $start->translatedFormat('l j F Y'),
                $start->format('H:i'),
                $result['created'] ? ' — toegevoegd aan de agenda' : ' — nog niet in een externe agenda',
            ),
            ['appointment_id' => $appointment->id, 'provider' => $appointment->provider, 'kind' => $appointment->kind],
        );

        if ($lead->email !== null) {
            $this->mailer->send($lead, $lead->email, $survey ? 'survey_confirmation' : 'appointment_confirmation', new AppointmentMail($lead, $appointment, $this->ics->forAppointment($appointment)));
        }

        $status = $survey ? LeadStatus::SurveyScheduled : LeadStatus::AppointmentScheduled;

        $lead->forceFill([
            'status' => $status->value,
            // Een opname is nog geen opdracht: pas de installatie telt als
            // gewonnen, want daarvoor heeft de klant de offerte aanvaard.
            'won_at' => $survey ? $lead->won_at : now(),
            'next_action_at' => null,
        ])->save();

        $this->timeline->record($lead, 'status_changed', 'Status: '.$status->label(), null, ['to' => $status->value]);

        // Een prijsindicatie kan niet aanvaard worden; alleen een offerte is
        // een aanbod. De indicatie blijft dus gewoon op "verstuurd" staan.
        if (! $survey && $quote !== null && $quote->isBinding() && $quote->status !== 'accepted') {
            $quote->forceFill(['status' => 'accepted', 'accepted_at' => now()])->save();
        }

        $this->notifier->appointmentBooked($lead, $appointment);

        return $appointment;
    }

    /**
     * Zet een tijd die als Nederlandse kloktijd is opgegeven om naar een Carbon.
     *
     * De voice agent en het dashboard geven "2026-09-15 08:00" door: acht uur
     * 's ochtends bij de klant, niet acht uur UTC. Draagt de waarde wel een
     * expliciete zone of offset, dan wint die.
     */
    public function parseLocal(?string $value): ?Carbon
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $timezone = $this->settings->string('agent.calendar.timezone', 'Europe/Amsterdam');
        $hasOffset = preg_match('/(Z|[+-]\d{2}:?\d{2})$/', trim($value)) === 1;

        return $hasOffset ? Carbon::parse($value) : Carbon::parse($value, $timezone);
    }

    private function resolveStart(?Carbon $preferred, int $minutes, string $timezone): Carbon
    {
        if ($preferred !== null) {
            return $preferred->copy()->setTimezone($timezone);
        }

        $slots = $this->availableSlots($minutes, 1);

        return $slots[0] ?? now($timezone)->addWeekday()->setTime(8, 0);
    }

    private function buildNotes(Lead $lead, ?Quote $quote): string
    {
        $lines = [
            'Klant: '.$lead->name,
            'Telefoon: '.($lead->phone ?? 'onbekend'),
            'E-mail: '.($lead->email ?? 'onbekend'),
        ];

        if ($quote !== null) {
            $lines[] = $quote->kind->label().': '.$quote->number.' — € '.number_format($quote->total_cents / 100, 2, ',', '.').' incl. btw';
            $lines[] = 'Systeem: '.($quote->system_type === 'multi_split' ? 'Multisplit' : 'Single split').', '.number_format((float) $quote->total_kw, 1, ',', '.').' kW';
        }

        if ($lead->notes !== null && $lead->notes !== '') {
            $lines[] = 'Opmerkingen: '.$lead->notes;
        }

        return implode("\n", $lines);
    }
}
