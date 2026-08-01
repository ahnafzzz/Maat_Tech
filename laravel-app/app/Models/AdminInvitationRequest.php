<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminInvitationRequest extends Model
{
    protected $fillable = [
        'requested_by_admin_id',
        'proposed_admin_id',
        'name',
        'email',
        'status',
        'decision_note',
        'reviewed_by_admin_id',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
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
