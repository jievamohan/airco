<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Sequence;
use Illuminate\Database\Seeder;

/**
 * De chase-cadans die start zodra een lead niet opneemt.
 * Volledig aanpasbaar via het dashboard.
 */
class SequenceSeeder extends Seeder
{
    /** @var list<array{position: int, channel: string, action: string, delay_minutes: int, label: string}> */
    private const CHASE_STEPS = [
        ['position' => 1, 'channel' => 'call', 'action' => 'chase', 'delay_minutes' => 20, 'label' => 'Tweede belpoging'],
        ['position' => 2, 'channel' => 'email', 'action' => 'missed_call', 'delay_minutes' => 5, 'label' => 'Mail: we hebben u geprobeerd te bellen'],
        ['position' => 3, 'channel' => 'call', 'action' => 'chase', 'delay_minutes' => 180, 'label' => 'Derde belpoging'],
        ['position' => 4, 'channel' => 'email', 'action' => 'quote_without_call', 'delay_minutes' => 1440, 'label' => 'Mail: prijsindicatie alsnog toesturen'],
        ['position' => 5, 'channel' => 'call', 'action' => 'final', 'delay_minutes' => 1440, 'label' => 'Laatste belpoging'],
        ['position' => 6, 'channel' => 'email', 'action' => 'last_chance', 'delay_minutes' => 2880, 'label' => 'Mail: laatste bericht'],
    ];

    public function run(): void
    {
        $sequence = Sequence::firstOrCreate(
            ['key' => Sequence::CHASE],
            [
                'name' => 'Opvolging bij geen gehoor',
                'description' => 'Wordt gestart zodra een belpoging niet wordt beantwoord en stopt zodra de lead reageert.',
                'active' => true,
            ],
        );

        foreach (self::CHASE_STEPS as $step) {
            $sequence->steps()->firstOrCreate(
                ['position' => $step['position']],
                [
                    'channel' => $step['channel'],
                    'action' => $step['action'],
                    'delay_minutes' => $step['delay_minutes'],
                    'label' => $step['label'],
                    'active' => true,
                ],
            );
        }
    }
}
