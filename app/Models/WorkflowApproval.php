<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowApproval extends Model
{
    protected $fillable = [
        'bulletin_id', 'step', 'action', 'user_id', 'comment',
    ];

    public function bulletin()
    {
        return $this->belongsTo(Bulletin::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isApproved(): bool
    {
        return $this->action === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->action === 'rejected';
    }
}
