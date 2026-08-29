<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Registreert welke instellingen via het dashboard bewerkbaar zijn.
 * De waarde blijft leeg zolang de config-standaard voldoet; zodra de
 * ondernemer iets invult, wint de databasewaarde.
 */
class SettingsSeeder extends Seeder
{
    /** @var list<array{key: string, group: string, type: string, label: string, description?: string, secret?: bool}> */
    private const DEFINITIONS = [
        // Bedrijf
        ['key' => 'agent.company.name', 'group' => 'bedrijf', 'type' => 'string', 'label' => 'Bedrijfsnaam'],
        ['key' => 'agent.company.email', 'group' => 'bedrijf', 'type' => 'string', 'label' => 'Algemeen e-mailadres'],
        ['key' => 'agent.company.phone', 'group' => 'bedrijf', 'type' => 'string', 'label' => 'Telefoonnummer'],
        ['key' => 'agent.company.postcode', 'group' => 'bedrijf', 'type' => 'string', 'label' => 'Postcode vestiging', 'description' => 'Vertrekpunt voor de voorrijberekening.'],
        ['key' => 'agent.company.city', 'group' => 'bedrijf', 'type' => 'string', 'label' => 'Plaats vestiging'],
        ['key' => 'agent.owner.email', 'group' => 'bedrijf', 'type' => 'string', 'label' => 'E-mailadres voor notificaties', 'description' => 'Hier komen alle meldingen over leads binnen.'],

        // Werking
        ['key' => 'agent.dry_run', 'group' => 'werking', 'type' => 'bool', 'label' => 'Proefmodus', 'description' => 'Workflow loopt volledig, maar er wordt niet echt gebeld, gemaild of geboekt.'],
        ['key' => 'agent.auth.session_lifetime_minutes', 'group' => 'werking', 'type' => 'int', 'label' => 'Sessieduur dashboard (minuten)', 'description' => 'Hoe lang je ingelogd blijft zonder "onthoud mij". Standaard een werkdag.'],
        ['key' => 'agent.auth.remember_lifetime_days', 'group' => 'werking', 'type' => 'int', 'label' => 'Duur van "onthoud mij" (dagen)'],
        ['key' => 'agent.workflow.first_call_delay_minutes', 'group' => 'werking', 'type' => 'int', 'label' => 'Wachttijd voor eerste belpoging (minuten)'],
        ['key' => 'agent.workflow.conversion_call_delay_minutes', 'group' => 'werking', 'type' => 'int', 'label' => 'Wachttijd tot het conversiegesprek (minuten)', 'description' => 'Standaard 60 minuten na het versturen van de offerte.'],
        ['key' => 'agent.workflow.max_call_attempts', 'group' => 'werking', 'type' => 'int', 'label' => 'Maximaal aantal belpogingen'],
        ['key' => 'agent.workflow.quote_valid_days', 'group' => 'werking', 'type' => 'int', 'label' => 'Geldigheid offerte (dagen)'],

        // Prijsstelling
        ['key' => 'agent.pricing.vat_rate', 'group' => 'prijsstelling', 'type' => 'float', 'label' => 'Btw-tarief (%)'],
        ['key' => 'agent.pricing.labour_sell_rate_cents', 'group' => 'prijsstelling', 'type' => 'int', 'label' => 'Uurtarief arbeid (centen, excl. btw)'],
        ['key' => 'agent.pricing.crew_size', 'group' => 'prijsstelling', 'type' => 'int', 'label' => 'Aantal monteurs per ploeg'],
        ['key' => 'agent.pricing.entry_price_cents', 'group' => 'prijsstelling', 'type' => 'int', 'label' => 'Geadverteerde vanaf-prijs (centen, incl. btw)', 'description' => 'Ondergrens voor elke offerte. Zet hier hetzelfde bedrag als in de advertentie.'],
        ['key' => 'agent.pricing.entry_package_enabled', 'group' => 'prijsstelling', 'type' => 'bool', 'label' => 'Instappakket tegen de vanaf-prijs aanbieden', 'description' => 'Topt een eenvoudige instapklus af op de vanaf-prijs, ook als dat onder de kostprijs uitkomt. Elke offerte die daardoor onder de margedrempel zakt, wordt gemarkeerd.'],
        ['key' => 'agent.pricing.entry_package_max_kw', 'group' => 'prijsstelling', 'type' => 'float', 'label' => 'Maximaal vermogen voor het instappakket (kW)'],
        ['key' => 'agent.pricing.minimum_margin_pct', 'group' => 'prijsstelling', 'type' => 'float', 'label' => 'Margedrempel (%)', 'description' => 'Onder deze brutomarge krijgt een offerte een waarschuwing en wordt de ondernemer gewaarschuwd.'],
        ['key' => 'agent.pricing.labour_cost_rate_cents', 'group' => 'prijsstelling', 'type' => 'int', 'label' => 'Kostprijs per monteursuur (centen, excl. btw)', 'description' => 'Nodig om de marge op een offerte te kunnen berekenen.'],
        ['key' => 'agent.pricing.default_tier', 'group' => 'prijsstelling', 'type' => 'string', 'label' => 'Standaard kwaliteitsklasse', 'description' => 'budget, mid of premium.'],
        ['key' => 'agent.pricing.direct_agreement_discount_pct', 'group' => 'prijsstelling', 'type' => 'float', 'label' => 'Korting bij direct akkoord (%)'],
        ['key' => 'agent.pricing.direct_agreement_discount_max_cents', 'group' => 'prijsstelling', 'type' => 'int', 'label' => 'Maximale korting (centen)'],

        // Mailbox
        ['key' => 'agent.mailbox.enabled', 'group' => 'mailbox', 'type' => 'bool', 'label' => 'Mailbox uitlezen actief'],
        ['key' => 'agent.mailbox.host', 'group' => 'mailbox', 'type' => 'string', 'label' => 'IMAP-server'],
        ['key' => 'agent.mailbox.port', 'group' => 'mailbox', 'type' => 'int', 'label' => 'IMAP-poort'],
        ['key' => 'agent.mailbox.username', 'group' => 'mailbox', 'type' => 'string', 'label' => 'IMAP-gebruikersnaam'],
        ['key' => 'agent.mailbox.password', 'group' => 'mailbox', 'type' => 'string', 'label' => 'IMAP-wachtwoord', 'secret' => true],
        ['key' => 'agent.mailbox.folder', 'group' => 'mailbox', 'type' => 'string', 'label' => 'Map om uit te lezen'],
        ['key' => 'agent.mailbox.processed_folder', 'group' => 'mailbox', 'type' => 'string', 'label' => 'Map voor verwerkte berichten'],

        // Voice agent
        ['key' => 'agent.elevenlabs.enabled', 'group' => 'voice', 'type' => 'bool', 'label' => 'Voice agent actief'],
        ['key' => 'agent.elevenlabs.api_key', 'group' => 'voice', 'type' => 'string', 'label' => 'ElevenLabs API-sleutel', 'secret' => true],
        ['key' => 'agent.elevenlabs.agent_id', 'group' => 'voice', 'type' => 'string', 'label' => 'ElevenLabs agent-id'],
        ['key' => 'agent.elevenlabs.agent_phone_number_id', 'group' => 'voice', 'type' => 'string', 'label' => 'Uitgaand telefoonnummer-id'],
        ['key' => 'agent.elevenlabs.webhook_secret', 'group' => 'voice', 'type' => 'string', 'label' => 'Webhook-secret', 'secret' => true],

        // Agenda
        ['key' => 'agent.calendar.provider', 'group' => 'agenda', 'type' => 'string', 'label' => 'Agendakoppeling', 'description' => 'google, apple of none.'],
        ['key' => 'agent.calendar.timezone', 'group' => 'agenda', 'type' => 'string', 'label' => 'Tijdzone'],
        ['key' => 'agent.calendar.slot_lead_time_hours', 'group' => 'agenda', 'type' => 'int', 'label' => 'Minimale voorbereidingstijd (uren)'],
        ['key' => 'agent.calendar.slot_horizon_days', 'group' => 'agenda', 'type' => 'int', 'label' => 'Aantal dagen vooruit plannen'],
        ['key' => 'agent.calendar.workday.start', 'group' => 'agenda', 'type' => 'string', 'label' => 'Begin werkdag'],
        ['key' => 'agent.calendar.workday.end', 'group' => 'agenda', 'type' => 'string', 'label' => 'Einde werkdag'],
        ['key' => 'agent.calendar.travel_buffer_minutes', 'group' => 'agenda', 'type' => 'int', 'label' => 'Reis- en opruimtijd per afspraak (minuten)'],
        ['key' => 'agent.calendar.google.calendar_id', 'group' => 'agenda', 'type' => 'string', 'label' => 'Google agenda-id'],
        ['key' => 'agent.calendar.google.refresh_token', 'group' => 'agenda', 'type' => 'string', 'label' => 'Google refresh-token', 'secret' => true],
        ['key' => 'agent.calendar.apple.username', 'group' => 'agenda', 'type' => 'string', 'label' => 'Apple ID voor CalDAV'],
        ['key' => 'agent.calendar.apple.app_password', 'group' => 'agenda', 'type' => 'string', 'label' => 'App-specifiek wachtwoord', 'secret' => true],
        ['key' => 'agent.calendar.apple.calendar_path', 'group' => 'agenda', 'type' => 'string', 'label' => 'CalDAV-pad van de agenda'],
    ];

    public function run(): void
    {
        foreach (self::DEFINITIONS as $definition) {
            Setting::firstOrCreate(
                ['key' => $definition['key']],
                [
                    'group' => $definition['group'],
                    'type' => $definition['type'],
                    'label' => $definition['label'],
                    'description' => $definition['description'] ?? null,
                    'is_secret' => $definition['secret'] ?? false,
                    'value' => null,
                ],
            );
        }
    }
}
