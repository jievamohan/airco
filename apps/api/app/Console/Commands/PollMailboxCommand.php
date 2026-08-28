<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\LeadIntake;
use App\Services\Mailbox\LeadEmailParser;
use App\Services\Mailbox\MailboxReader;
use App\Services\SettingsRepository;
use Illuminate\Console\Command;

class PollMailboxCommand extends Command
{
    protected $signature = 'leads:poll-mailbox';

    protected $description = 'Leest de leadmailbox uit en maakt van elk bruikbaar bericht een lead.';

    public function handle(
        SettingsRepository $settings,
        MailboxReader $reader,
        LeadEmailParser $parser,
        LeadIntake $intake,
    ): int {
        if (! $settings->bool('agent.mailbox.enabled', false)) {
            $this->comment('Mailbox-intake staat uit.');

            return self::SUCCESS;
        }

        $messages = $reader->fetchUnread();
        $created = 0;
        $skipped = 0;

        foreach ($messages as $message) {
            $parsed = $parser->parse($message->subject, $message->body, $message->fromEmail, $message->fromName);

            if (! $parsed->isUsable()) {
                $skipped++;
                $this->warn(sprintf('Overgeslagen: "%s" bevat geen bruikbare contactgegevens.', $message->subject));

                continue;
            }

            $result = $intake->capture(
                $parsed->toAttributes() + $parsed->extra,
                'mailbox',
                $message->messageId !== '' ? $message->messageId : null,
            );

            if ($result['created']) {
                $created++;
            }
        }

        $this->info(sprintf('%d berichten gelezen, %d nieuwe leads, %d overgeslagen.', count($messages), $created, $skipped));

        return self::SUCCESS;
    }
}
