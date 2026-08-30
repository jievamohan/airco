<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CallOutcome;
use App\Enums\CallPurpose;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $lead_id
 * @property string $provider
 * @property string|null $provider_call_id
 * @property string|null $conversation_id
 * @property CallPurpose $purpose
 * @property int $attempt_no
 * @property string $status
 * @property CallOutcome|null $outcome
 * @property string|null $to_number
 * @property Carbon|null $scheduled_for
 * @property bool $ignores_calling_window
 * @property Carbon|null $started_at
 * @property Carbon|null $ended_at
 * @property int|null $duration_seconds
 * @property string|null $transcript
 * @property string|null $summary
 * @property array<string, mixed>|null $collected
 * @property string|null $recording_url
 * @property string|null $error_message
 * @property Carbon|null $created_at
 * @property Lead $lead
 */
class Call extends Model
{
    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'ignores_calling_window' => 'bool',
            'purpose' => CallPurpose::class,
            'outcome' => CallOutcome::class,
            'collected' => 'array',
            'scheduled_for' => 'datetime',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
