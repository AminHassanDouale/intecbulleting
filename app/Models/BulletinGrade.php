<?php

namespace App\Models;

use App\Enums\CompetenceStatusEnum;
use Illuminate\Database\Eloquent\Model;

class BulletinGrade extends Model
{
    protected $fillable = [
        'bulletin_id', 'competence_id', 'period',
        'score', 'competence_status',
    ];

    protected $casts = [
        'score'              => 'decimal:2',
        'competence_status'  => CompetenceStatusEnum::class,
    ];

    public function bulletin()
    {
        return $this->belongsTo(Bulletin::class);
    }

    public function competence()
    {
        return $this->belongsTo(Competence::class);
    }
}
