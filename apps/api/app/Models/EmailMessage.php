<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $lead_id
 * @property string $direction
 * @property string|null $template
 * @property string $to_address
 * @property string $subject
 * @property string $status
 * @property string|null $body_preview
 * @property string|null $error_message
 * @property Carbon|null $sent_at
 * @property Lead|null $lead
 */
class EmailMessage extends Model
{
    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
