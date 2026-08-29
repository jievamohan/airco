<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $lead_id
 * @property int|null $quote_id
 * @property string $provider
 * @property string|null $provider_event_id
 * @property string|null $calendar_ref
 * @property string $ics_uid
 * @property string $kind
 * @property string $title
 * @property string|null $location
 * @property string|null $notes
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property string $timezone
 * @property string $status
 * @property string|null $sync_error
 * @property Lead $lead
 * @property Quote|null $quote
 */
class Appointment extends Model
{
    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /** @return BelongsTo<Quote, $this> */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }
}
