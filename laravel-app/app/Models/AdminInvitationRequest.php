<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminInvitationRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'requested_by_admin_id',
        'proposed_admin_id',
        'name',
        'email',
        'normalized_email',
        'reserved_email',
        'status',
        'approved_permissions',
        'token_selector',
        'token_hash',
        'token_expires_at',
        'delivery_status',
        'last_sent_at',
        'accepted_at',
        'revoked_at',
        'decision_note',
        'reviewed_by_admin_id',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'approved_permissions' => 'array',
            'token_expires_at' => 'datetime',
            'last_sent_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function requester()
    {
        return $this->belongsTo(Admin::class, 'requested_by_admin_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(Admin::class, 'reviewed_by_admin_id');
    }
}
