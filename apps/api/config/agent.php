<?php

return [

    /*
    |---------------------------------------------------------------------------
    | Bedrijfsgegevens
    |---------------------------------------------------------------------------
    | Worden in offertes, mails en gespreksinstructies gebruikt. Overschrijfbaar
    | via de settings-tabel (Dashboard -> Instellingen).
    */
    'company' => [
        'name' => env('COMPANY_NAME', 'KlimaatX'),
        'email' => env('COMPANY_EMAIL', 'info@klimaatx.nl'),
        'phone' => env('COMPANY_PHONE', '+31201234567'),
        'website' => env('COMPANY_WEBSITE', 'https://klimaatx.nl'),
        'postcode' => env('COMPANY_POSTCODE', '1011 AB'),
        'city' => env('COMPANY_CITY', 'Amsterdam'),
        'kvk' => env('COMPANY_KVK', ''),
        'vat_number' => env('COMPANY_VAT_NUMBER', ''),
    ],

    /*
    |---------------------------------------------------------------------------
    | Ondernemer-notificaties
    |---------------------------------------------------------------------------
    */
    'owner' => [
        'email' => env('OWNER_NOTIFICATION_EMAIL', 'info@klimaatx.nl'),
        'name' => env('OWNER_NAME', 'KlimaatX'),
        'initial_password' => env('OWNER_INITIAL_PASSWORD', 'wachtwoord-wijzigen'),
    ],

    /*
    |---------------------------------------------------------------------------
    | Werkings-modus
    |---------------------------------------------------------------------------
    | dry_run laat de volledige workflow lopen maar verstuurt geen echte
    | telefoongesprekken, mails of agenda-afspraken. Alles wordt wel vastgelegd,
    | zodat het dashboard de flow toont.
    */
    'dry_run' => (bool) env('AGENT_DRY_RUN', false),

    /*
    |---------------------------------------------------------------------------
    | Mailbox-intake (IMAP)
    |---------------------------------------------------------------------------
    */
    'mailbox' => [
        'enabled' => (bool) env('MAILBOX_ENABLED', false),
        'host' => env('MAILBOX_HOST'),
        'port' => (int) env('MAILBOX_PORT', 993),
        'encryption' => env('MAILBOX_ENCRYPTION', 'ssl'),
        'validate_cert' => (bool) env('MAILBOX_VALIDATE_CERT', true),
        'username' => env('MAILBOX_USERNAME'),
        'password' => env('MAILBOX_PASSWORD'),
        'folder' => env('MAILBOX_FOLDER', 'INBOX'),
        'processed_folder' => env('MAILBOX_PROCESSED_FOLDER', 'INBOX.Verwerkt'),
        'max_per_run' => (int) env('MAILBOX_MAX_PER_RUN', 25),
    ],

    /*
    |---------------------------------------------------------------------------
    | ElevenLabs Conversational AI
    |---------------------------------------------------------------------------
    */
    'elevenlabs' => [
        'enabled' => (bool) env('ELEVENLABS_ENABLED', false),
        'base_url' => env('ELEVENLABS_BASE_URL', 'https://api.elevenlabs.io'),
        'api_key' => env('ELEVENLABS_API_KEY'),
        'agent_id' => env('ELEVENLABS_AGENT_ID'),
        'agent_phone_number_id' => env('ELEVENLABS_AGENT_PHONE_NUMBER_ID'),
        'webhook_secret' => env('ELEVENLABS_WEBHOOK_SECRET'),
        'webhook_tolerance_seconds' => (int) env('ELEVENLABS_WEBHOOK_TOLERANCE', 1800),
        'timeout' => (int) env('ELEVENLABS_TIMEOUT', 20),
    ],

    /*
    |---------------------------------------------------------------------------
    | Agenda
    |---------------------------------------------------------------------------
    | provider: google | apple | none. Bij "none" wordt alleen een interne
    | afspraak met ICS-bijlage vastgelegd.
    */
    'calendar' => [
        'provider' => env('CALENDAR_PROVIDER', 'none'),
        'timezone' => env('CALENDAR_TIMEZONE', 'Europe/Amsterdam'),
        'slot_lead_time_hours' => (int) env('CALENDAR_LEAD_TIME_HOURS', 48),
        'slot_horizon_days' => (int) env('CALENDAR_HORIZON_DAYS', 21),
        'travel_buffer_minutes' => (int) env('CALENDAR_TRAVEL_BUFFER_MINUTES', 30),
        'workday' => [
            'start' => env('CALENDAR_WORKDAY_START', '08:00'),
            'end' => env('CALENDAR_WORKDAY_END', '17:00'),
            'days' => [1, 2, 3, 4, 5],
        ],
        'google' => [
            'calendar_id' => env('GOOGLE_CALENDAR_ID', 'primary'),
            'client_id' => env('GOOGLE_CLIENT_ID'),
            'client_secret' => env('GOOGLE_CLIENT_SECRET'),
            'refresh_token' => env('GOOGLE_REFRESH_TOKEN'),
        ],
        'apple' => [
            'base_url' => env('APPLE_CALDAV_URL', 'https://caldav.icloud.com'),
            'username' => env('APPLE_CALDAV_USERNAME'),
            'app_password' => env('APPLE_CALDAV_APP_PASSWORD'),
            'calendar_path' => env('APPLE_CALDAV_CALENDAR_PATH'),
        ],
    ],

    /*
    |---------------------------------------------------------------------------
    | Belvensters
    |---------------------------------------------------------------------------
    | Sleutel is ISO-weekdag (1 = maandag). Buiten deze vensters wordt een
    | belactie doorgeschoven naar het eerstvolgende geldige moment.
    */
    'calling_windows' => [
        1 => ['09:00', '20:00'],
        2 => ['09:00', '20:00'],
        3 => ['09:00', '20:00'],
        4 => ['09:00', '20:00'],
        5 => ['09:00', '20:00'],
        6 => ['10:00', '17:00'],
    ],

    /*
    |---------------------------------------------------------------------------
    | Workflow-timing
    |---------------------------------------------------------------------------
    */
    'workflow' => [
        'conversion_call_delay_minutes' => (int) env('CONVERSION_CALL_DELAY_MINUTES', 60),
        'max_call_attempts' => (int) env('MAX_CALL_ATTEMPTS', 4),
        'quote_valid_days' => (int) env('QUOTE_VALID_DAYS', 21),
        'first_call_delay_minutes' => (int) env('FIRST_CALL_DELAY_MINUTES', 3),
    ],

    /*
    |---------------------------------------------------------------------------
    | Prijsstelling (startwaarden, zie docs/research/pricing-baseline.md)
    |---------------------------------------------------------------------------
    */
    'pricing' => [
        'vat_rate' => (float) env('PRICING_VAT_RATE', 21.0),
        'labour_sell_rate_cents' => (int) env('PRICING_LABOUR_RATE_CENTS', 7500),
        'crew_size' => (int) env('PRICING_CREW_SIZE', 2),
        // De geadverteerde "vanaf"-prijs, inclusief btw en in dezelfde termen
        // als de advertentie. Geen offerte komt hieronder uit.
        'entry_price_cents' => (int) env('PRICING_ENTRY_PRICE_CENTS', 89900),
        // Zet je dit aan, dan wordt een eenvoudige instapklus afgetopt op de
        // vanaf-prijs, ook als dat onder de kostprijs uitkomt. Elke offerte die
        // daardoor onder de margedrempel zakt, wordt gemarkeerd en gemeld.
        'entry_package_enabled' => (bool) env('PRICING_ENTRY_PACKAGE_ENABLED', false),
        'entry_package_max_kw' => (float) env('PRICING_ENTRY_PACKAGE_MAX_KW', 2.5),
        // Onder deze brutomarge wordt een offerte gemarkeerd als margewaarschuwing.
        'minimum_margin_pct' => (float) env('PRICING_MINIMUM_MARGIN_PCT', 15.0),
        // Kostprijs van een monteursuur; nodig om de marge te kunnen bepalen.
        'labour_cost_rate_cents' => (int) env('PRICING_LABOUR_COST_CENTS', 6500),
        'default_tier' => env('PRICING_DEFAULT_TIER', 'mid'),
        'direct_agreement_discount_pct' => (float) env('PRICING_DIRECT_DISCOUNT_PCT', 3.0),
        'direct_agreement_discount_max_cents' => (int) env('PRICING_DIRECT_DISCOUNT_MAX_CENTS', 15000),
        'free_travel_km' => (int) env('PRICING_FREE_TRAVEL_KM', 30),
        'travel_rate_cents_per_km' => (int) env('PRICING_TRAVEL_RATE_CENTS_PER_KM', 55),
    ],

    /*
    |---------------------------------------------------------------------------
    | Publieke offertepagina op de website
    |---------------------------------------------------------------------------
    */
    'quote_public_url' => env('QUOTE_PUBLIC_URL', 'https://klimaatx.nl/offerte'),
];
