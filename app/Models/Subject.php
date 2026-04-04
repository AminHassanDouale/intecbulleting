<?php

namespace App\Models;

use App\Enums\ScaleTypeEnum;
use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    protected $fillable = [
        'name', 'code', 'niveau_id', 'classroom_code',
        'max_score', 'scale_type', 'order',
    ];

    public function niveau()
    {
        return $this->belongsTo(Niveau::class);
    }

    public function competences()
    {
        return $this->hasMany(Competence::class)->orderBy('order');
    }

    public function teachers()
    {
        return $this->belongsToMany(User::class, 'subject_teacher');
    }

    public function isPrescolaire(): bool
    {
        return $this->scale_type === ScaleTypeEnum::COMPETENCE->value;
    }
}
