<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Classroom extends Model
{
    protected $fillable = [
        'niveau_id', 'code', 'label', 'section',
        'academic_year_id', 'teacher_id',
    ];

    public function niveau()
    {
        return $this->belongsTo(Niveau::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function students()
    {
        return $this->hasMany(Student::class);
    }

    public function bulletins()
    {
        return $this->hasMany(Bulletin::class);
    }

    public function teachers()
    {
        return $this->belongsToMany(User::class, 'classroom_teacher', 'classroom_id', 'teacher_id');
    }

    public function getLabelWithSectionAttribute(): string
    {
        return $this->label . ' — Section ' . $this->section;
    }
}
