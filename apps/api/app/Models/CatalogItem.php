<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PriceSource;
use App\Enums\Tier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $sku
 * @property string $kind
 * @property string $name
 * @property string|null $description
 * @property string|null $brand
 * @property string|null $series
 * @property Tier|null $tier
 * @property float|null $capacity_kw
 * @property float|null $capacity_class_kw
 * @property int|null $ports
 * @property string $unit
 * @property int $cost_cents
 * @property int|null $list_price_cents
 * @property float|null $purchase_discount_pct
 * @property float $margin_pct
 * @property int $labour_minutes
 * @property bool $active
 * @property string|null $source_note
 * @property PriceSource $price_source
 * @property string|null $price_list_ref
 * @property Carbon|null $priced_at
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
            'capacity_class_kw' => 'float',
            'purchase_discount_pct' => 'float',
            'margin_pct' => 'float',
            'active' => 'bool',
            'price_source' => PriceSource::class,
            'priced_at' => 'date',
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
     * Regels waarvan de prijs op een echt cijfer rust: een prijslijst van de
     * leverancier of eigen invoer, niet op marktonderzoek.
     *
     * @param  Builder<CatalogItem>  $query
     * @return Builder<CatalogItem>
     */
    public function scopeRealPriced(Builder $query): Builder
    {
        return $query->whereIn('price_source', [PriceSource::PriceList->value, PriceSource::Dashboard->value]);
    }

    /**
     * Verkoopprijs per eenheid, excl. btw, in centen.
     */
    public function sellPriceCents(): int
    {
        return (int) round($this->cost_cents * (1 + ($this->margin_pct / 100)));
    }
}
