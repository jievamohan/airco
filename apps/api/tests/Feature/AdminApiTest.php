<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CallPurpose;
use App\Enums\PriceSource;
use App\Enums\QuoteKind;
use App\Jobs\ProcessNewLeadJob;
use App\Jobs\SendQuoteJob;
use App\Models\CatalogItem;
use App\Models\Lead;
use App\Models\SequenceStep;
use App\Models\Setting;
use App\Models\User;
use App\Services\AppointmentScheduler;
use App\Services\LeadWorkflow;
use App\Services\QuoteBuilder;
use App\Services\Voice\FakeVoiceAgentClient;
use App\Services\Voice\VoiceAgentClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDomain();
        $this->app->instance(VoiceAgentClient::class, new FakeVoiceAgentClient);
    }

    private function actingAsOwner(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    // ---------------------------------------------------------------- intake

    #[Test]
    public function het_websiteformulier_neemt_een_aanvraag_aan(): void
    {
        Queue::fake();

        $this->postJson('/api/leads', [
            'name' => 'Sanne de Vries',
            'email' => 'sanne@example.nl',
            'phone' => '06 12345678',
            'address' => 'Dorpsstraat 2',
            'postcode' => '3811 ab',
            'city' => 'Amersfoort',
            'space_size' => 28,
            'space_unit' => 'm2',
        ])->assertStatus(202)->assertExactJson(['ok' => true]);

        $lead = Lead::firstOrFail();
        $this->assertSame('+31612345678', $lead->phone, 'Het nummer hoort genormaliseerd te zijn.');
        $this->assertSame('3811 AB', $lead->postcode);
        $this->assertSame('web_form', $lead->source);

        Queue::assertPushed(ProcessNewLeadJob::class);
    }

    #[Test]
    public function het_tweede_formulier_komt_als_eigen_bron_binnen(): void
    {
        Queue::fake();

        $this->postJson('/api/leads', [
            'name' => 'Joris Bakker',
            'email' => 'joris@example.nl',
            'phone' => '0612345678',
            'rooms_count' => 3,
            'source' => 'web_form_v2',
        ])->assertStatus(202);

        $lead = Lead::firstOrFail();
        $this->assertSame('web_form_v2', $lead->source, 'De landingspagina hoort in het dashboard herkenbaar te zijn.');
        $this->assertSame(3, $lead->rooms_count);

        // En het dashboard kan er ook op filteren.
        $this->actingAsOwner();
        $this->getJson('/api/admin/leads?source=web_form_v2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Joris Bakker')
            ->assertJsonPath('data.0.source', 'web_form_v2');

        $this->getJson('/api/admin/leads?source=web_form')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function het_formulier_weigert_een_verzonnen_bron(): void
    {
        // `source` gaat ongefilterd het dashboard in; een bezoeker mag daar
        // geen eigen tekst in kunnen zetten.
        $this->postJson('/api/leads', [
            'name' => 'Sanne', 'email' => 'sanne@example.nl', 'phone' => '0612345678',
            'source' => 'gratis-bezoek-deze-site',
        ])->assertStatus(422)->assertJsonValidationErrors(['source']);
    }

    #[Test]
    public function het_formulier_weigert_onbekende_velden(): void
    {
        $this->postJson('/api/leads', [
            'name' => 'Sanne', 'email' => 'sanne@example.nl', 'phone' => '0612345678',
            'status' => 'won',
        ])->assertStatus(422)->assertJsonPath('errors.payload.0', 'Onbekende velden in het verzoek: status.');
    }

    #[Test]
    public function het_formulier_valideert_verplichte_velden(): void
    {
        $this->postJson('/api/leads', ['name' => 'X'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'phone']);
    }

    // ------------------------------------------------------------------ auth

    #[Test]
    public function het_dashboard_is_afgeschermd(): void
    {
        $this->getJson('/api/admin/leads')->assertStatus(401);
        $this->getJson('/api/admin/analytics')->assertStatus(401);
        $this->getJson('/api/admin/settings')->assertStatus(401);
    }

    #[Test]
    public function een_verzoek_zonder_json_header_krijgt_ook_een_nette_401(): void
    {
        // Zonder Accept-header probeerde Laravel naar een inlogpagina te sturen
        // die in deze API niet bestaat; dat gaf een 500 in plaats van een 401.
        $this->get('/api/admin/leads')->assertStatus(401);
    }

    #[Test]
    public function inloggen_levert_een_token_op(): void
    {
        $user = User::factory()->create(['email' => 'beheer@klimaatx.nl']);

        $this->postJson('/api/admin/login', ['email' => $user->email, 'password' => 'geheim-wachtwoord'])
            ->assertOk()
            ->assertJsonStructure(['token', 'user' => ['name', 'email', 'role']]);
    }

    /**
     * Een verzoek met een bearertoken.
     *
     * De guard cachet de opgeloste gebruiker binnen een test, dus zonder
     * forgetGuards() blijft een ingetrokken of verlopen token "geldig" lijken.
     */
    private function metToken(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }

    #[Test]
    public function zonder_onthoud_mij_verloopt_de_sessie_na_een_werkdag(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/admin/login', [
            'email' => $user->email,
            'password' => 'geheim-wachtwoord',
        ])->assertOk()->assertJsonPath('remembered', false);

        $verval = Carbon::parse($response->json('expires_at'));

        $this->assertEqualsWithDelta(480, now()->diffInMinutes($verval), 2);
        $this->assertNotNull($user->tokens()->first()?->expires_at, 'Een token zonder vervaldatum blijft eeuwig geldig.');
    }

    #[Test]
    public function met_onthoud_mij_blijft_de_sessie_dertig_dagen_staan(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/admin/login', [
            'email' => $user->email,
            'password' => 'geheim-wachtwoord',
            'remember' => true,
        ])->assertOk()->assertJsonPath('remembered', true);

        $this->assertEqualsWithDelta(30, now()->diffInDays(Carbon::parse($response->json('expires_at'))), 1);
    }

    #[Test]
    public function een_verlopen_token_geeft_geen_toegang_meer(): void
    {
        $user = User::factory()->create();

        $token = $this->postJson('/api/admin/login', [
            'email' => $user->email,
            'password' => 'geheim-wachtwoord',
        ])->json('token');

        $this->metToken($token)->getJson('/api/admin/leads')->assertOk();

        $this->travel(9)->hours();

        $this->metToken($token)->getJson('/api/admin/leads')->assertStatus(401);
    }

    #[Test]
    public function inloggen_op_een_tweede_apparaat_verbreekt_de_eerste_sessie_niet(): void
    {
        $user = User::factory()->create();
        $credentials = ['email' => $user->email, 'password' => 'geheim-wachtwoord'];

        $laptop = $this->postJson('/api/admin/login', $credentials + ['remember' => true])->json('token');
        $telefoon = $this->postJson('/api/admin/login', $credentials)->json('token');

        foreach (['laptop' => $laptop, 'telefoon' => $telefoon] as $apparaat => $token) {
            $this->assertSame(
                200,
                $this->metToken($token)->getJson('/api/admin/me')->getStatusCode(),
                sprintf('De sessie op de %s hoort te blijven werken.', $apparaat),
            );
        }
    }

    #[Test]
    public function verlopen_tokens_worden_bij_het_inloggen_opgeruimd(): void
    {
        $user = User::factory()->create();
        $credentials = ['email' => $user->email, 'password' => 'geheim-wachtwoord'];

        $this->postJson('/api/admin/login', $credentials)->assertOk();
        $this->travel(9)->hours();
        $this->postJson('/api/admin/login', $credentials)->assertOk();

        $this->assertSame(1, $user->tokens()->count(), 'Het verlopen token had opgeruimd moeten worden.');
    }

    #[Test]
    public function uitloggen_raakt_alleen_het_eigen_apparaat(): void
    {
        $user = User::factory()->create();
        $credentials = ['email' => $user->email, 'password' => 'geheim-wachtwoord'];

        $laptop = $this->postJson('/api/admin/login', $credentials)->json('token');
        $telefoon = $this->postJson('/api/admin/login', $credentials)->json('token');

        $this->metToken($telefoon)->postJson('/api/admin/logout')->assertOk();

        $this->metToken($telefoon)->getJson('/api/admin/me')->assertStatus(401);
        $this->metToken($laptop)->getJson('/api/admin/me')->assertOk();
    }

    #[Test]
    public function een_verkeerd_wachtwoord_geeft_geen_token(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/admin/login', ['email' => $user->email, 'password' => 'fout'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    // ----------------------------------------------------------------- leads

    #[Test]
    public function de_leadlijst_is_filterbaar_en_bevat_geen_overbodige_velden(): void
    {
        $this->actingAsOwner();

        Lead::factory()->create(['name' => 'Piet Pieters', 'status' => 'quoted', 'city' => 'Utrecht']);
        Lead::factory()->create(['name' => 'Klaas Klaassen', 'status' => 'lost', 'email' => 'klaas@example.nl']);

        $response = $this->getJson('/api/admin/leads?status=quoted')->assertOk();

        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Piet Pieters');

        $row = $response->json('data.0');
        $this->assertArrayNotHasKey('email', $row, 'De lijst hoort geen contactgegevens te lekken.');
        $this->assertArrayNotHasKey('notes', $row);
        $this->assertArrayNotHasKey('id', $row);

        $this->getJson('/api/admin/leads?search=Klaassen')->assertOk()->assertJsonCount(1, 'data');
    }

    #[Test]
    public function het_leaddetail_toont_tijdlijn_gesprekken_en_offertes(): void
    {
        $this->actingAsOwner();

        $lead = Lead::factory()->create(['status' => 'enriched']);
        $workflow = app(LeadWorkflow::class);
        $workflow->enrich($lead);
        $quote = $workflow->buildQuote($lead->refresh());
        $workflow->markQuoteSent($lead, $quote);

        $this->getJson('/api/admin/leads/'.$lead->uuid)
            ->assertOk()
            ->assertJsonPath('data.uuid', $lead->uuid)
            ->assertJsonPath('data.quotes.0.number', $quote->number)
            ->assertJsonStructure(['data' => ['events', 'calls', 'quotes', 'appointments', 'emails']]);
    }

    #[Test]
    public function een_afspraak_wordt_met_zijn_eigen_tijdzone_teruggegeven(): void
    {
        $this->actingAsOwner();

        $lead = Lead::factory()->create(['status' => 'follow_up']);
        app(AppointmentScheduler::class)->book($lead, null);

        $this->getJson('/api/admin/leads/'.$lead->uuid)
            ->assertOk()
            // Zonder deze zone toont het dashboard de tijd van de kijker
            // in plaats van die van de klus.
            ->assertJsonPath('data.appointments.0.timezone', 'Europe/Amsterdam');
    }

    #[Test]
    public function gegevens_van_een_lead_zijn_aanpasbaar_en_dat_komt_in_de_tijdlijn(): void
    {
        $this->actingAsOwner();
        $lead = Lead::factory()->create();

        $this->patchJson('/api/admin/leads/'.$lead->uuid, [
            'rooms_count' => 3,
            'pipe_length_m' => 12,
            'needs_condensate_pump' => true,
        ])->assertOk()->assertJsonPath('data.rooms_count', 3);

        $lead->refresh();
        $this->assertSame(12, $lead->pipe_length_m);
        $this->assertTrue($lead->needs_condensate_pump);
        $this->assertContains('lead_updated', $lead->events()->pluck('type')->all());
    }

    #[Test]
    public function statusvelden_kunnen_niet_via_het_bewerkformulier_gezet_worden(): void
    {
        $this->actingAsOwner();
        $lead = Lead::factory()->create();

        $this->patchJson('/api/admin/leads/'.$lead->uuid, ['status' => 'won'])
            ->assertStatus(422)
            ->assertJsonPath('errors.payload.0', 'Onbekende velden in het verzoek: status.');
    }

    // --------------------------------------------------------------- acties

    #[Test]
    public function elke_stap_kan_opnieuw_afgetrapt_worden(): void
    {
        $this->actingAsOwner();
        $lead = Lead::factory()->create(['status' => 'enriched']);

        $this->postJson('/api/admin/leads/'.$lead->uuid.'/actions', ['action' => 'call_qualification'])
            ->assertOk()
            ->assertJsonStructure(['message']);

        $this->assertSame(1, $lead->calls()->where('purpose', CallPurpose::Qualification->value)->count());

        $this->postJson('/api/admin/leads/'.$lead->uuid.'/actions', ['action' => 'enrich'])->assertOk();
        $this->assertContains('action_triggered', $lead->events()->pluck('type')->all());
    }

    #[Test]
    public function de_offerte_gaat_niet_de_deur_uit_voordat_er_een_opname_is_geweest(): void
    {
        Queue::fake();
        $this->actingAsOwner();

        $lead = Lead::factory()->create(['status' => 'indicated']);

        $antwoord = $this->postJson('/api/admin/leads/'.$lead->uuid.'/actions', ['action' => 'send_quote'])->assertOk();
        $this->assertStringContainsString('opname', strtolower((string) $antwoord->json('message')));
        Queue::assertNotPushed(SendQuoteJob::class);

        // De prijsindicatie mag wel: die belooft niets.
        $this->postJson('/api/admin/leads/'.$lead->uuid.'/actions', ['action' => 'send_indication'])->assertOk();
        Queue::assertPushed(SendQuoteJob::class, static fn (SendQuoteJob $job): bool => $job->kind === QuoteKind::Indication);

        // Na de opname wel.
        $this->postJson('/api/admin/leads/'.$lead->uuid.'/actions', ['action' => 'mark_surveyed', 'notes' => 'Buitenunit kan op het platte dak.'])->assertOk();
        $this->postJson('/api/admin/leads/'.$lead->uuid.'/actions', ['action' => 'send_quote'])->assertOk();

        Queue::assertPushed(SendQuoteJob::class, static fn (SendQuoteJob $job): bool => $job->kind === QuoteKind::Final);
    }

    #[Test]
    public function een_onbekende_actie_wordt_geweigerd(): void
    {
        $this->actingAsOwner();
        $lead = Lead::factory()->create();

        $this->postJson('/api/admin/leads/'.$lead->uuid.'/actions', ['action' => 'verwijder_alles'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['action']);
    }

    #[Test]
    public function een_heropende_lead_begint_weer_met_lege_tellers(): void
    {
        $this->actingAsOwner();
        $lead = Lead::factory()->create(['status' => 'unreachable', 'call_attempts' => 4, 'do_not_contact' => true]);

        $this->postJson('/api/admin/leads/'.$lead->uuid.'/actions', ['action' => 'reopen'])->assertOk();

        $lead->refresh();
        $this->assertSame(0, $lead->call_attempts);
        $this->assertFalse($lead->do_not_contact);
        $this->assertSame('enriched', $lead->status->value);
    }

    // ------------------------------------------------------------- analytics

    #[Test]
    public function de_funnel_telt_bereikte_stappen_niet_alleen_de_huidige_status(): void
    {
        $this->actingAsOwner();

        Lead::factory()->count(2)->create(['status' => 'new']);
        Lead::factory()->create(['status' => 'quoted']);
        Lead::factory()->create(['status' => 'won']);

        $response = $this->getJson('/api/admin/analytics')->assertOk();

        /** @var list<array{status: string, count: int}> $rows */
        $rows = $response->json('funnel');
        $funnel = array_column($rows, null, 'status');

        $this->assertSame(4, $funnel['new']['count']);
        $this->assertSame(2, $funnel['enriched']['count'], 'De gewonnen en geoffreerde lead hebben deze stap ook gehad.');
        $this->assertSame(2, $funnel['quoted']['count']);
        $this->assertSame(1, $funnel['won']['count']);
        $this->assertSame(4, $response->json('totals.leads'));
    }

    // --------------------------------------------------------------- catalog

    #[Test]
    public function catalogusprijzen_zijn_aanpasbaar_en_werken_door_in_de_offerte(): void
    {
        $this->actingAsOwner();

        // Dezelfde regel die de offerte zelf pakt voor een 3,5 kW-klus in de
        // middenklasse. Op artikelnummer zoeken zou breken bij elke nieuwe
        // prijslijst; op productlijn en klasse blijft het staan.
        $item = CatalogItem::query()
            ->where('series', config('agent.pricing.series.mid.equipment_set'))
            ->where('capacity_class_kw', 3.5)
            ->firstOrFail();
        $lead = Lead::factory()->create();
        $voor = app(QuoteBuilder::class)->calculate($lead)['total_cents'];

        $this->patchJson('/api/admin/catalog/'.$item->id, ['cost_cents' => 120000, 'margin_pct' => 30])
            ->assertOk()
            ->assertJsonPath('item.sell_price_cents', 156000);

        $na = app(QuoteBuilder::class)->calculate($lead->refresh())['total_cents'];
        $this->assertGreaterThan($voor, $na);

        $this->assertStringContainsString('Aangepast in het dashboard', (string) $item->refresh()->source_note);
        // Vanaf nu is dit eigen invoer, en laat een volgende prijslijstimport
        // de regel met rust.
        $this->assertSame(PriceSource::Dashboard, $item->price_source);
    }

    #[Test]
    public function de_catalogus_weigert_velden_die_niet_bewerkt_mogen_worden(): void
    {
        $this->actingAsOwner();
        $item = CatalogItem::firstOrFail();

        $this->patchJson('/api/admin/catalog/'.$item->id, ['sku' => 'GEHACKT'])
            ->assertStatus(422);
    }

    // -------------------------------------------------------------- settings

    #[Test]
    public function instellingen_zijn_aanpasbaar_en_geheimen_gaan_niet_over_de_lijn(): void
    {
        $this->actingAsOwner();

        Setting::where('key', 'agent.elevenlabs.api_key')->update(['value' => 'sk-geheim']);

        $response = $this->getJson('/api/admin/settings')->assertOk();
        /** @var list<array{key: string, is_set: bool, value: mixed}> $voiceSettings */
        $voiceSettings = $response->json('groups.voice');
        $voice = array_column($voiceSettings, null, 'key')['agent.elevenlabs.api_key'];

        $this->assertTrue($voice['is_set']);
        $this->assertNull($voice['value'], 'Een geheim hoort nooit teruggestuurd te worden.');

        $this->patchJson('/api/admin/settings', ['values' => ['agent.pricing.vat_rate' => 9]])->assertOk();
        $this->assertSame('9', Setting::where('key', 'agent.pricing.vat_rate')->value('value'));

        $this->patchJson('/api/admin/settings', ['values' => ['bestaat.niet' => 1]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['values']);
    }

    #[Test]
    public function de_opvolgcadans_is_aanpasbaar(): void
    {
        $this->actingAsOwner();

        $this->getJson('/api/admin/sequences')->assertOk()->assertJsonCount(6, 'sequences.0.steps');

        $step = SequenceStep::where('position', 1)->firstOrFail();

        $this->patchJson('/api/admin/sequences/steps/'.$step->id, ['delay_minutes' => 45])
            ->assertOk()
            ->assertJsonPath('step.delay_minutes', 45);
    }
}
