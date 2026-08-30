<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QuoteKind;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $lead_id
 * @property string $number
 * @property int $version
 * @property QuoteKind $kind
 * @property string $status
 * @property string $public_token
 * @property string|null $system_type
 * @property string|null $tier
 * @property float|null $total_kw
 * @property int $subtotal_cents
 * @property float $vat_rate
 * @property int $vat_cents
 * @property int $total_cents
 * @property int $discount_cents
 * @property int $cost_cents
 * @property float $margin_pct
 * @property bool $margin_warning
 * @property string $currency
 * @property int $labour_minutes
 * @property int $onsite_minutes
 * @property list<string>|null $assumptions
 * @property Carbon|null $valid_until
 * @property Carbon|null $sent_at
 * @property Carbon|null $viewed_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $declined_at
 * @property Carbon|null $created_at
 * @property Lead $lead
 * @property Collection<int, QuoteItem> $items
 */
class Quote extends Model
{
    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'kind' => QuoteKind::class,
            'assumptions' => 'array',
            'valid_until' => 'date',
            'sent_at' => 'datetime',
            'viewed_at' => 'datetime',
            'accepted_at' => 'datetime',
            'declined_at' => 'datetime',
            'vat_rate' => 'float',
            'total_kw' => 'float',
            'margin_pct' => 'float',
            'margin_warning' => 'bool',
        ];
    }

    /**
     * Alleen een offerte is een aanbod waaraan de klant rechten ontleent; een
     * prijsindicatie is een richtbedrag onder voorbehoud van de opname.
     */
    public function isBinding(): bool
    {
        return $this->kind->isBinding();
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /** @return HasMany<QuoteItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class)->orderBy('sort')->orderBy('id');
    }
}
