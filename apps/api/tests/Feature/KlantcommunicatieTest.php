<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\QuoteKind;
use App\Mail\AppointmentMail;
use App\Mail\ChaseMail;
use App\Mail\OwnerNotificationMail;
use App\Mail\QuoteMail;
use App\Models\Lead;
use App\Services\AppointmentScheduler;
use App\Services\CompanyProfile;
use App\Services\IcsBuilder;
use App\Services\QuoteBuilder;
use App\Services\QuotePdfRenderer;
use App\Services\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Alles wat de klant onder ogen krijgt, moet ook echt te renderen zijn. De
 * Mailer vangt fouten af en logt ze, dus een kapotte template zou anders pas
 * opvallen als er niets aankomt.
 */
class KlantcommunicatieTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDomain();
        Carbon::setTestNow(Carbon::parse('2026-09-01 10:00:00', 'Europe/Amsterdam'));
        Mail::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function de_offertemail_toont_bedrag_nummer_en_bedrijfsgegevens(): void
    {
        $lead = Lead::factory()->create(['name' => 'Mevrouw J. de Vries']);
        $quote = app(QuoteBuilder::class)->createForLead($lead, QuoteKind::Final);

        $html = (new QuoteMail($lead, $quote))->render();

        $this->assertStringContainsString('Mevrouw J. de Vries', $html);
        $this->assertStringContainsString($quote->number, $html);
        $this->assertStringContainsString('Totaal incl. btw', $html);
        $this->assertStringContainsString(
            number_format($quote->total_cents / 100, 2, ',', '.'),
            $html,
        );
        $this->assertStringContainsString(app(CompanyProfile::class)->all()['name'], $html);
        $this->assertStringNotContainsString('{{', $html, 'Geen onopgeloste template-plaatshouders.');
    }

    #[Test]
    public function de_prijsindicatie_zegt_in_de_mail_dat_het_geen_offerte_is(): void
    {
        $lead = Lead::factory()->create();
        $indicatie = app(QuoteBuilder::class)->createForLead($lead);

        $html = (new QuoteMail($lead, $indicatie))->render();

        $this->assertStringContainsString('prijsindicatie', mb_strtolower($html));
        $this->assertStringContainsString('geen rechten aan ontlenen', $html);
        $this->assertStringContainsString('Richtbedrag incl. btw', $html);
        $this->assertStringNotContainsString('Totaal incl. btw', $html, 'Een richtbedrag is geen eindtotaal.');
        $this->assertStringNotContainsString('{{', $html);
    }

    #[Test]
    public function elke_klantmail_heeft_een_platte_tekstversie_die_rendert(): void
    {
        $lead = Lead::factory()->create(['status' => 'follow_up']);
        $quote = app(QuoteBuilder::class)->createForLead($lead);
        $appointment = app(AppointmentScheduler::class)->book($lead, $quote);

        $mails = [
            new QuoteMail($lead, $quote),
            new QuoteMail($lead, app(QuoteBuilder::class)->createForLead($lead, QuoteKind::Final)),
            new AppointmentMail($lead, $appointment, app(IcsBuilder::class)->forAppointment($appointment)),
            new ChaseMail($lead, 'quote_without_call', $quote),
            new OwnerNotificationMail($lead, 'Nieuwe aanvraag', ['Regel een.']),
        ];

        foreach ($mails as $mail) {
            $content = $mail->content();

            $this->assertNotNull($content->text, $mail::class.' heeft geen tekstversie.');

            $tekst = view($content->text, $content->with)->render();

            $this->assertNotSame('', trim($tekst));
            $this->assertStringContainsString($lead->name, $tekst);
        }
    }

    #[Test]
    public function de_opvolgmails_renderen_in_alle_varianten(): void
    {
        $lead = Lead::factory()->create();
        $quote = app(QuoteBuilder::class)->createForLead($lead);

        foreach (['missed_call' => null, 'quote_without_call' => $quote, 'last_chance' => null] as $variant => $bijlage) {
            $html = (new ChaseMail($lead, $variant, $bijlage))->render();

            $this->assertStringContainsString($lead->name, $html);
            $this->assertStringNotContainsString('{{', $html);
        }
    }

    #[Test]
    public function de_afspraakbevestiging_noemt_datum_tijd_en_locatie(): void
    {
        $lead = Lead::factory()->create(['status' => 'follow_up']);
        $quote = app(QuoteBuilder::class)->createForLead($lead);
        $appointment = app(AppointmentScheduler::class)->book($lead, $quote);
        $ics = app(IcsBuilder::class)->forAppointment($appointment);

        $html = (new AppointmentMail($lead, $appointment, $ics))->render();

        $start = $appointment->starts_at->timezone($appointment->timezone);
        $this->assertStringContainsString($start->format('H:i'), $html);
        $this->assertStringContainsString($appointment->location ?? $lead->displayLocation(), $html);
        $this->assertStringNotContainsString('{{', $html);
    }

    #[Test]
    public function de_bevestiging_van_de_opname_gaat_over_meten_en_niet_over_monteren(): void
    {
        $lead = Lead::factory()->create(['status' => 'indicated']);
        $indicatie = app(QuoteBuilder::class)->createForLead($lead);
        $opname = app(AppointmentScheduler::class)->book($lead, $indicatie, null, 'survey');

        $html = (new AppointmentMail($lead, $opname, app(IcsBuilder::class)->forAppointment($opname)))->render();

        $this->assertStringContainsString('opname', mb_strtolower($html));
        $this->assertStringContainsString('opmeten', $html);
        $this->assertStringNotContainsString('Bedankt voor uw akkoord', $html, 'Er is nog nergens mee ingestemd.');
        $this->assertStringNotContainsString('{{', $html);
    }

    #[Test]
    public function de_ondernemersmelding_rendert_met_de_leadgegevens(): void
    {
        $lead = Lead::factory()->create();

        $html = (new OwnerNotificationMail($lead, 'Nieuwe aanvraag', ['Regel een.']))->render();

        $this->assertStringContainsString('Nieuwe aanvraag', $html);
        $this->assertStringContainsString($lead->uuid, $html);
        $this->assertStringNotContainsString('{{', $html);
    }

    #[Test]
    public function de_offerte_pdf_wordt_gerenderd(): void
    {
        $lead = Lead::factory()->create();
        $quote = app(QuoteBuilder::class)->createForLead($lead);

        $pdf = app(QuotePdfRenderer::class)->render($quote);

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertGreaterThan(5000, strlen($pdf));
    }

    #[Test]
    public function bedrijfsgegevens_uit_het_dashboard_winnen_van_de_config(): void
    {
        $settings = app(SettingsRepository::class);
        $settings->set('agent.company.name', 'Koel & Co');
        $settings->set('agent.company.address', 'Keizersgracht 241');
        $settings->set('agent.company.kvk', '87654321');

        $company = app(CompanyProfile::class)->all();

        $this->assertSame('Koel & Co', $company['name']);
        $this->assertStringContainsString('Keizersgracht 241', $company['address_line']);
        $this->assertStringContainsString('KvK 87654321', $company['legal_line']);
    }
}
