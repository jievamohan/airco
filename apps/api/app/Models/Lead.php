<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LeadStatus;
use App\Enums\SystemType;
use App\Enums\Tier;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property LeadStatus $status
 * @property string $source
 * @property string|null $source_reference
 * @property string|null $dedupe_hash
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $postcode
 * @property string|null $city
 * @property string $country
 * @property float|null $space_size
 * @property string|null $space_unit
 * @property int $rooms_count
 * @property string|null $insulation
 * @property int|null $building_year
 * @property int|null $floor_level
 * @property string|null $wall_type
 * @property string|null $outdoor_unit_placement
 * @property int|null $pipe_length_m
 * @property bool $needs_condensate_pump
 * @property bool $needs_extra_group
 * @property Carbon|null $desired_start
 * @property string|null $notes
 * @property float|null $estimated_kw
 * @property SystemType|null $recommended_system
 * @property Tier|null $tier
 * @property bool $do_not_contact
 * @property int $call_attempts
 * @property int $email_attempts
 * @property Carbon|null $last_contact_at
 * @property Carbon|null $last_request_at
 * @property int $requests_count
 * @property Carbon|null $next_action_at
 * @property Carbon|null $owner_notified_at
 * @property Carbon|null $won_at
 * @property Carbon|null $lost_at
 * @property string|null $lost_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Collection<int, LeadEvent> $events
 * @property Collection<int, Quote> $quotes
 * @property Collection<int, Call> $calls
 * @property Collection<int, Appointment> $appointments
 * @property Collection<int, EmailMessage> $emails
 * @property Quote|null $latestQuote
 */
class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => LeadStatus::class,
            'recommended_system' => SystemType::class,
            'tier' => Tier::class,
            'space_size' => 'float',
            'estimated_kw' => 'float',
            'do_not_contact' => 'bool',
            'needs_condensate_pump' => 'bool',
            'needs_extra_group' => 'bool',
            'desired_start' => 'date',
            'last_contact_at' => 'datetime',
            'last_request_at' => 'datetime',
            'next_action_at' => 'datetime',
            'owner_notified_at' => 'datetime',
            'won_at' => 'datetime',
            'lost_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Lead $lead): void {
            $lead->uuid ??= (string) Str::uuid();
        });
    }

    /** @return HasMany<LeadEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(LeadEvent::class)->orderByDesc('occurred_at')->orderByDesc('id');
    }

    /** @return HasMany<Quote, $this> */
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class)->orderByDesc('version');
    }

    /** @return HasOne<Quote, $this> */
    public function latestQuote(): HasOne
    {
        return $this->hasOne(Quote::class)->latestOfMany('version');
    }

    /** @return HasMany<Call, $this> */
    public function calls(): HasMany
    {
        return $this->hasMany(Call::class)->orderByDesc('id');
    }

    /** @return HasMany<Appointment, $this> */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class)->orderBy('starts_at');
    }

    /** @return HasMany<EmailMessage, $this> */
    public function emails(): HasMany
    {
        return $this->hasMany(EmailMessage::class)->orderByDesc('id');
    }

    /** @return HasMany<LeadSequenceRun, $this> */
    public function sequenceRuns(): HasMany
    {
        return $this->hasMany(LeadSequenceRun::class);
    }

    public function isContactable(): bool
    {
        return ! $this->do_not_contact && ! $this->status->isTerminal();
    }

    public function displayLocation(): string
    {
        return trim(implode(', ', array_filter([
            trim((string) $this->address),
            trim(implode(' ', array_filter([(string) $this->postcode, (string) $this->city]))),
        ])));
    }
}
