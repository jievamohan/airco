<?php

declare(strict_types=1);

namespace App\Services\Mailbox;

class IncomingMessage
{
    public function __construct(
        public readonly string $messageId,
        public readonly string $subject,
        public readonly string $body,
        public readonly ?string $fromEmail = null,
        public readonly ?string $fromName = null,
        public readonly ?string $receivedAt = null,
    ) {}
}
