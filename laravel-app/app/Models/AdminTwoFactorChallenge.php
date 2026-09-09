<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminTwoFactorChallenge extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CONSUMED = 'consumed';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_EXHAUSTED = 'exhausted';

    public const STATUS_SUPERSEDED = 'superseded';

    public const STATUS_INVALIDATED = 'invalidated';

    public const STATUS_DELIVERY_FAILED = 'delivery_failed';

    protected $guarded = [];

    protected $hidden = ['code_hash', 'session_binding_hash', 'credential_version'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'delivered_at' => 'datetime',
            'consumed_at' => 'datetime',
            'invalidated_at' => 'datetime',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}
