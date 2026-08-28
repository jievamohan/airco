<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $key
 * @property string $group
 * @property string|null $value
 * @property string $type
 * @property bool $is_secret
 * @property string $label
 * @property string|null $description
 */
class Setting extends Model
{
    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_secret' => 'bool'];
    }

    /**
     * Waarde omgezet naar het gedeclareerde type.
     */
    public function typedValue(): mixed
    {
        $raw = $this->value;

        if ($raw === null) {
            return null;
        }

        return match ($this->type) {
            'int' => (int) $raw,
            'float' => (float) $raw,
            'bool' => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($raw, true),
            default => $raw,
        };
    }
}
