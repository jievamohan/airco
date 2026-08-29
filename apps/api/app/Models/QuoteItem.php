<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $quote_id
 * @property int|null $catalog_item_id
 * @property string $kind
 * @property string|null $sku
 * @property string $description
 * @property float $quantity
 * @property string $unit
 * @property int $unit_cost_cents
 * @property float $margin_pct
 * @property int $unit_price_cents
 * @property int $line_total_cents
 * @property int $labour_minutes
 * @property int $sort
 * @property Quote $quote
 */
class QuoteItem extends Model
{
    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'margin_pct' => 'float',
        ];
    }

    /** @return BelongsTo<Quote, $this> */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }
}
