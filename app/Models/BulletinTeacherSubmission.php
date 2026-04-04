<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BulletinTeacherSubmission extends Model
{
    protected $fillable = [
        'bulletin_id', 'teacher_id', 'status', 'submitted_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
    ];

    public function bulletin()
    {
        return $this->belongsTo(Bulletin::class);
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function isSubmitted(): bool
    {
        return $this->status === 'submitted';
    }
}
