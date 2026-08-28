<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SystemType;
use App\Models\Lead;

/**
 * Bepaalt het benodigde koelvermogen en het systeemtype op basis van de
 * vuistregel uit docs/research/pricing-baseline.md.
 */
class SizingCalculator
{
    /** Beschikbare standaard capaciteitsklassen in kW. */
    public const CAPACITY_CLASSES = [2.0, 2.5, 3.5, 5.0, 7.1];

    private const DEFAULT_CEILING_HEIGHT_M = 2.6;

    /** Watt per m3 per isolatiekwaliteit. */
    private const INSULATION_FACTORS = [
        'good' => 30,
        'average' => 40,
        'poor' => 50,
    ];

    /**
     * @return array{kw: float, system: SystemType, volume_m3: float|null, factor: int, indoor_units: int}
     */
    public function forLead(Lead $lead): array
    {
        $factor = $this->insulationFactor($lead);
        $volume = $this->volumeM3($lead);
        $rooms = max(1, (int) $lead->rooms_count);

        if ($volume === null) {
            // Zonder ruimtemaat vallen we terug op de meest verkochte klasse,
            // en markeren dat als aanname op de offerte.
            $perRoomKw = 3.5;
        } else {
            $perRoomKw = $this->roundUpToClass(($volume * $factor) / 1000 / $rooms);
        }

        $system = ($rooms > 1 || $perRoomKw > max(self::CAPACITY_CLASSES))
            ? SystemType::MultiSplit
            : SystemType::SingleSplit;

        return [
            'kw' => $this->roundUpToClass($perRoomKw),
            'system' => $system,
            'volume_m3' => $volume,
            'factor' => $factor,
            'indoor_units' => $rooms,
        ];
    }

    public function volumeM3(Lead $lead): ?float
    {
        $size = $lead->space_size;

        if ($size === null || $size <= 0) {
            return null;
        }

        return $lead->space_unit === 'm3'
            ? round($size, 2)
            : round($size * self::DEFAULT_CEILING_HEIGHT_M, 2);
    }

    public function insulationFactor(Lead $lead): int
    {
        if ($lead->insulation !== null && isset(self::INSULATION_FACTORS[$lead->insulation])) {
            return self::INSULATION_FACTORS[$lead->insulation];
        }

        // Zonder opgave leiden we af uit het bouwjaar.
        $year = $lead->building_year;

        if ($year === null) {
            return self::INSULATION_FACTORS['average'];
        }

        return match (true) {
            $year >= 2010 => self::INSULATION_FACTORS['good'],
            $year < 1990 => self::INSULATION_FACTORS['poor'],
            default => self::INSULATION_FACTORS['average'],
        };
    }

    public function roundUpToClass(float $kw): float
    {
        foreach (self::CAPACITY_CLASSES as $class) {
            if ($kw <= $class + 0.001) {
                return $class;
            }
        }

        return (float) max(self::CAPACITY_CLASSES);
    }
}
