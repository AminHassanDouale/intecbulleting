<?php

namespace App\Models;

use App\Enums\ScaleTypeEnum;
use Illuminate\Database\Eloquent\Model;

class Competence extends Model
{
    protected $fillable = [
        'subject_id', 'code', 'description',
        'max_score', 'period', 'order',
    ];

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function grades()
    {
        return $this->hasMany(BulletinGrade::class);
    }

    public function isPrescolaire(): bool
    {
        return $this->subject->scale_type === ScaleTypeEnum::COMPETENCE->value;
    }
}
