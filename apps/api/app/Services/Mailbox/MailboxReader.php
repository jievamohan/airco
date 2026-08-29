<?php

declare(strict_types=1);

namespace App\Services\Mailbox;

use App\Services\SettingsRepository;
use Illuminate\Support\Facades\Log;
use Throwable;
use Webklex\PHPIMAP\ClientManager;

/**
 * Leest ongelezen berichten uit de leadmailbox.
 *
 * webklex/php-imap praat zelf IMAP over een socket, dus de php-imap-extensie
 * hoeft niet in de image te zitten.
 */
class MailboxReader
{
    public function __construct(private readonly SettingsRepository $settings) {}

    /**
     * @return list<IncomingMessage>
     */
    public function fetchUnread(): array
    {
        $host = $this->settings->string('agent.mailbox.host');
        $username = $this->settings->string('agent.mailbox.username');
        $password = $this->settings->string('agent.mailbox.password');

        if ($host === '' || $username === '' || $password === '') {
            return [];
        }

        $manager = new ClientManager;

        try {
            $client = $manager->make([
                'host' => $host,
                'port' => $this->settings->int('agent.mailbox.port', 993),
                'encryption' => $this->settings->string('agent.mailbox.encryption', 'ssl'),
                'validate_cert' => (bool) config('agent.mailbox.validate_cert', true),
                'username' => $username,
                'password' => $password,
                'protocol' => 'imap',
            ]);

            $client->connect();

            $folder = $client->getFolder($this->settings->string('agent.mailbox.folder', 'INBOX'));

            if ($folder === null) {
                return [];
            }

            $limit = $this->settings->int('agent.mailbox.max_per_run', 25);
            $messages = $folder->query()->unseen()->limit($limit)->get();
        } catch (Throwable $e) {
            Log::error('Uitlezen van de mailbox mislukt', ['exception' => $e->getMessage()]);

            return [];
        }

        $result = [];

        foreach ($messages as $message) {
            try {
                $from = $message->getFrom()[0] ?? null;

                $result[] = new IncomingMessage(
                    messageId: (string) $message->getMessageId(),
                    subject: (string) $message->getSubject(),
                    body: $message->hasHTMLBody() ? (string) $message->getHTMLBody() : (string) $message->getTextBody(),
                    fromEmail: $from?->mail,
                    fromName: $from?->personal,
                    receivedAt: $message->getDate()?->first()?->toDateTimeString(),
                );

                $message->setFlag('Seen');
            } catch (Throwable $e) {
                Log::warning('Bericht in de mailbox kon niet gelezen worden', ['exception' => $e->getMessage()]);
            }
        }

        return $result;
    }
}
