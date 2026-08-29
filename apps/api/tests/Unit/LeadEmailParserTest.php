<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Mailbox\LeadEmailParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class LeadEmailParserTest extends TestCase
{
    private LeadEmailParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new LeadEmailParser;
    }

    #[Test]
    public function het_leest_een_bericht_van_het_eigen_formulier(): void
    {
        $body = <<<'TXT'
        Nieuwe offerteaanvraag via klimaatx.nl

        Naam: Sanne de Vries
        Adres: Dorpsstraat 12
        Postcode: 3811ab
        Plaats: Amersfoort
        E-mailadres: sanne@example.nl
        Telefoonnummer: 06-12345678
        Ruimtemaat: 34 m2
        Opmerkingen: Woonkamer met open keuken, graag zo snel mogelijk.
        TXT;

        $lead = $this->parser->parse('Nieuwe aanvraag', $body);

        $this->assertTrue($lead->isUsable());
        $this->assertSame('Sanne de Vries', $lead->name);
        $this->assertSame('sanne@example.nl', $lead->email);
        $this->assertSame('06-12345678', $lead->phone);
        $this->assertSame('Dorpsstraat 12', $lead->address);
        $this->assertSame('3811 AB', $lead->postcode);
        $this->assertSame('Amersfoort', $lead->city);
        $this->assertSame(34.0, $lead->spaceSize);
        $this->assertSame('m2', $lead->spaceUnit);
        $this->assertStringContainsString('open keuken', (string) $lead->notes);
    }

    #[Test]
    public function het_leest_een_html_bericht_van_een_leadportaal(): void
    {
        $body = '<html><body><table>'
            .'<tr><td>Naam</td><td>Peter Bakker</td></tr>'
            .'<tr><td>Telefoon</td><td>0623456789</td></tr>'
            .'<tr><td>E-mail</td><td>peter@example.nl</td></tr>'
            .'<tr><td>Woonplaats</td><td>Haarlem</td></tr>'
            .'<tr><td>Aantal ruimtes</td><td>3 kamers</td></tr>'
            .'<tr><td>Inhoud</td><td>210 m&sup3;</td></tr>'
            .'</table></body></html>';

        $lead = $this->parser->parse('Nieuwe lead — Peter Bakker', $body);

        $this->assertSame('Peter Bakker', $lead->name);
        $this->assertSame('peter@example.nl', $lead->email);
        $this->assertSame('0623456789', $lead->phone);
        $this->assertSame('Haarlem', $lead->city);
        $this->assertSame(210.0, $lead->spaceSize);
        $this->assertSame('m3', $lead->spaceUnit);
        $this->assertSame(3, $lead->extra['rooms_count']);
    }

    #[Test]
    public function zonder_labels_valt_het_terug_op_de_afzender_en_losse_patronen(): void
    {
        $body = 'Hoi, ik wil graag een airco. Je kunt me bereiken op 06 98765432. Groet!';

        $lead = $this->parser->parse('Vraagje', $body, 'marieke@example.nl', 'Marieke Visser');

        $this->assertSame('Marieke Visser', $lead->name);
        $this->assertSame('marieke@example.nl', $lead->email);
        $this->assertSame('06 98765432', $lead->phone);
        $this->assertTrue($lead->isUsable());
    }

    #[Test]
    public function een_bericht_zonder_contactgegevens_is_niet_bruikbaar(): void
    {
        $lead = $this->parser->parse('Nieuwsbrief', 'Bekijk onze aanbiedingen van deze week.');

        $this->assertFalse($lead->isUsable());
    }

    #[Test]
    public function alleen_ingevulde_velden_belanden_in_de_lead(): void
    {
        $lead = $this->parser->parse('Aanvraag', "Naam: Tom\nTelefoon: 0612345678");

        $attributes = $lead->toAttributes();

        $this->assertSame(['name' => 'Tom', 'phone' => '0612345678'], $attributes);
        $this->assertArrayNotHasKey('city', $attributes);
    }
}
