<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PhoneNumberTest extends TestCase
{
    /** @return list<array{0: string|null, 1: string|null}> */
    public static function nummers(): array
    {
        return [
            ['06 12345678', '+31612345678'],
            ['0612345678', '+31612345678'],
            ['+31 6 1234 5678', '+31612345678'],
            ['0031612345678', '+31612345678'],
            ['020-1234567', '+31201234567'],
            ['(020) 123 45 67', '+31201234567'],
            ['31612345678', '+31612345678'],
            ['12345', null],
            ['geen nummer', null],
            [null, null],
        ];
    }

    #[Test]
    #[DataProvider('nummers')]
    public function het_normaliseert_nederlandse_nummers_naar_e164(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, (new PhoneNumber)->normalise($input));
    }

    #[Test]
    public function het_herkent_mobiele_nummers(): void
    {
        $phone = new PhoneNumber;

        $this->assertTrue($phone->isMobile($phone->normalise('0612345678')));
        $this->assertFalse($phone->isMobile($phone->normalise('0201234567')));
    }
}
