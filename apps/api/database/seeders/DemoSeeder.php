<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CallOutcome;
use App\Enums\CallPurpose;
use App\Enums\LeadStatus;
use App\Enums\QuoteKind;
use App\Models\Lead;
use App\Services\AppointmentScheduler;
use App\Services\LeadIntake;
use App\Services\LeadWorkflow;
use App\Services\SequenceRunner;
use App\Services\Voice\FakeVoiceAgentClient;
use App\Services\Voice\VoiceAgentClient;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Vult het CRM met een realistische set leads verspreid over de funnel, zodat
 * het dashboard iets te laten zien heeft.
 *
 * Alleen voor demonstratie en lokaal ontwikkelen. Deze seeder staat bewust niet
 * in DatabaseSeeder en hoort nooit op productie te draaien:
 *
 *     php artisan db:seed --class=Database\\Seeders\\DemoSeeder
 */
class DemoSeeder extends Seeder
{
    /** @var list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string, 6: int, 7: int, 8: int, 9: string}> */
    private const MENSEN = [
        ['Sanne de Vries', 'sanne@example.nl', '0612345678', 'Dorpsstraat 12', '3811 AB', 'Amersfoort', 34, 1998, 8, 'won'],
        ['Peter Bakker', 'peter@example.nl', '0623456789', 'Zijlweg 88', '2013 DK', 'Haarlem', 96, 1975, 12, 'appointment'],
        ['Fatima El Amrani', 'fatima@example.nl', '0634567890', 'Kanaalstraat 4', '3531 CJ', 'Utrecht', 28, 2016, 6, 'quoted'],
        ['Joost Hendriks', 'joost@example.nl', '0645678901', 'Marktplein 21', '1211 CX', 'Hilversum', 45, 1988, 9, 'survey'],
        ['Anouk Willemsen', 'anouk@example.nl', '0656789012', 'Beeklaan 7', '2562 AC', 'Den Haag', 22, 2005, 5, 'indicated'],
        ['Ruben Smit', 'ruben@example.nl', '0667890123', 'Havenkade 33', '1013 BB', 'Amsterdam', 18, 2019, 5, 'chase'],
        ['Wouter Jansen', 'wouter@example.nl', '0678901234', 'Kerkpad 2', '6511 AB', 'Nijmegen', 60, 1965, 15, 'calling'],
        ['Lisa Mulder', 'lisa@example.nl', '0689012345', 'Parkweg 19', '9718 CV', 'Groningen', 26, 2010, 7, 'new'],
    ];

    public function run(): void
    {
        // Nooit echt bellen tijdens een demo.
        $this->container->instance(VoiceAgentClient::class, new FakeVoiceAgentClient);

        // Vast ijkpunt: binnen de lus verspringt de testklok, dus `now()` mag
        // daar niet meer als vertrekpunt dienen. Anders schuift elke volgende
        // lead verder het verleden in en valt hij buiten het analysevenster.
        $vandaag = Carbon::now()->startOfDay();

        $intake = $this->container->make(LeadIntake::class);
        $workflow = $this->container->make(LeadWorkflow::class);

        foreach (self::MENSEN as $i => [$naam, $mail, $tel, $adres, $pc, $plaats, $m2, $jaar, $leiding, $doel]) {
            Carbon::setTestNow($vandaag->copy()->subDays(12 - $i)->setTime(10, 15));

            $lead = $intake->capture([
                'name' => $naam,
                'email' => $mail,
                'phone' => $tel,
                'address' => $adres,
                'postcode' => $pc,
                'city' => $plaats,
                'space_size' => $m2,
                'space_unit' => 'm2',
                'building_year' => $jaar,
                'pipe_length_m' => $leiding,
                'rooms_count' => $m2 > 80 ? 3 : 1,
            ], $i % 3 === 0 ? 'mailbox' : 'web_form')['lead'];

            $workflow->enrich($lead);

            if ($doel === 'new') {
                continue;
            }

            Carbon::setTestNow(now()->addMinutes(20));
            $call = $workflow->scheduleCall($lead->refresh(), CallPurpose::Qualification);

            if ($call === null) {
                continue;
            }

            $workflow->dispatchCall($call);

            if ($doel === 'calling') {
                continue;
            }

            if ($doel === 'chase') {
                $workflow->completeCall($call->refresh(), CallOutcome::NoAnswer);
                $run = $lead->sequenceRuns()->first();

                // Bewust stap voor stap voor deze ene lead: runDue() zou ook de
                // cadans van eerder aangemaakte demo-leads vooruitspoelen, en dan
                // eindigt iedereen op "onbereikbaar".
                for ($stap = 0; $stap < 2 && $run !== null && $run->refresh()->status === 'active'; $stap++) {
                    Carbon::setTestNow($run->next_run_at?->copy()->addMinute());
                    $this->container->make(SequenceRunner::class)->runStep($run);
                }

                continue;
            }

            $workflow->completeCall(
                $call->refresh(),
                CallOutcome::Answered,
                "Agent: Goedemiddag, u spreekt met KlimaatX over uw aanvraag.\n"
                ."Klant: Ja klopt, voor de woonkamer.\n"
                ."Agent: Op welke verdieping komt de binnenunit?\n"
                ."Klant: Begane grond. De buitenunit kan achter aan de gevel.\n"
                .'Agent: Prima, dan stuur ik u zo een vrijblijvende prijsindicatie.',
                'Klant wil airco in de woonkamer. Buitenunit aan de achtergevel, begane grond.',
                ['floor_level' => 0, 'outdoor_unit_placement' => 'achtergevel', 'insulation' => $jaar < 1990 ? 'poor' : 'average'],
            );

            Carbon::setTestNow(now()->addMinutes(4));
            $workflow->markIndicationSent($lead->refresh(), $workflow->buildQuote($lead->refresh()));

            if ($doel === 'indicated') {
                continue;
            }

            // Het conversiegesprek gaat over de indicatie en levert een opname
            // op, geen installatiedatum: er ligt nog geen offerte.
            Carbon::setTestNow(now()->addHour());
            $conversie = $lead->calls()
                ->where('purpose', CallPurpose::Conversion->value)
                ->where('status', 'queued')
                ->first();

            if ($conversie === null) {
                continue;
            }

            $workflow->dispatchCall($conversie);
            $workflow->completeCall(
                $conversie->refresh(),
                CallOutcome::AppointmentBooked,
                "Agent: Heeft u de prijsindicatie kunnen bekijken?\n"
                ."Klant: Ja, dat bedrag valt mee. Wat nu?\n"
                ."Agent: We komen eerst kort langs om te meten, daarna krijgt u de offerte.\n"
                .'Klant: Prima, volgende week dinsdag.',
                'Klant wil verder; opname ingepland voor dinsdag.',
                ['outcome' => 'appointment_booked'],
            );

            $scheduler = $this->container->make(AppointmentScheduler::class);
            $scheduler->book(
                $lead->refresh(),
                $lead->latestQuote()->first(),
                $scheduler->parseLocal(now()->addDays(5)->setTime(9, 0)->toDateTimeString()),
                'survey',
            );

            if ($doel === 'survey') {
                continue;
            }

            // Opname geweest: nu pas mag er een offerte uit.
            Carbon::setTestNow(now()->addDays(5)->setTime(10, 0));
            $workflow->markSurveyed($lead->refresh(), 'Leidingroute langs de bijkeuken, meterkast heeft ruimte voor een extra groep.');

            Carbon::setTestNow(now()->addHours(2));
            $workflow->markQuoteSent($lead->refresh(), $workflow->buildQuote($lead->refresh(), QuoteKind::Final));

            if ($doel === 'quoted') {
                continue;
            }

            Carbon::setTestNow(now()->addHour());
            $afsluiting = $lead->calls()
                ->where('purpose', CallPurpose::Close->value)
                ->where('status', 'queued')
                ->first();

            if ($afsluiting === null) {
                continue;
            }

            $workflow->dispatchCall($afsluiting);
            $workflow->completeCall(
                $afsluiting->refresh(),
                CallOutcome::AppointmentBooked,
                "Agent: Heeft u de offerte kunnen bekijken?\n"
                ."Klant: Ja, ziet er goed uit. Wanneer kunnen jullie?\n"
                ."Agent: Volgende week donderdag in de ochtend.\n"
                .'Klant: Doen we.',
                'Klant gaat akkoord met de offerte en kiest donderdagochtend.',
                ['outcome' => 'appointment_booked'],
            );

            $scheduler->book(
                $lead->refresh(),
                $lead->quotes()->where('kind', QuoteKind::Final->value)->latest('id')->first(),
                $scheduler->parseLocal(now()->addDays(9)->setTime(8, 0)->toDateTimeString()),
                'installation',
            );

            if ($doel === 'won') {
                $lead->refresh()->forceFill(['won_at' => now()])->save();
                $workflow->transition($lead->refresh(), LeadStatus::Won);
            }
        }

        Carbon::setTestNow();

        $this->command?->info(sprintf('%d demo-leads aangemaakt.', Lead::count()));
    }
}
