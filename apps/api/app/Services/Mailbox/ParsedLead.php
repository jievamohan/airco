<?php

declare(strict_types=1);

namespace App\Services\Mailbox;

/**
 * Wat een parser uit een binnengekomen e-mail heeft weten te halen.
 */
class ParsedLead
{
    /**
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $email = null,
        public readonly ?string $phone = null,
        public readonly ?string $address = null,
        public readonly ?string $postcode = null,
        public readonly ?string $city = null,
        public readonly ?float $spaceSize = null,
        public readonly ?string $spaceUnit = null,
        public readonly ?string $notes = null,
        public readonly array $extra = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return array_filter([
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'postcode' => $this->postcode,
            'city' => $this->city,
            'space_size' => $this->spaceSize,
            'space_unit' => $this->spaceUnit,
            'notes' => $this->notes,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    public function isUsable(): bool
    {
        return trim($this->name) !== '' && ($this->email !== null || $this->phone !== null);
    }
}
