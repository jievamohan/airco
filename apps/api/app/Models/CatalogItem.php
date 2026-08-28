<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Tier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $sku
 * @property string $kind
 * @property string $name
 * @property string|null $description
 * @property string|null $brand
 * @property Tier|null $tier
 * @property float|null $capacity_kw
 * @property int|null $ports
 * @property string $unit
 * @property int $cost_cents
 * @property float $margin_pct
 * @property int $labour_minutes
 * @property bool $active
 * @property string|null $source_note
 */
class CatalogItem extends Model
{
    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tier' => Tier::class,
            'capacity_kw' => 'float',
            'margin_pct' => 'float',
            'active' => 'bool',
        ];
    }

    /**
     * @param  Builder<CatalogItem>  $query
     * @return Builder<CatalogItem>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /**
     * Verkoopprijs per eenheid, excl. btw, in centen.
     */
    public function sellPriceCents(): int
    {
        return (int) round($this->cost_cents * (1 + ($this->margin_pct / 100)));
    }
}
