<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'matricule', 'first_name', 'last_name', 'birth_date',
        'gender', 'classroom_id', 'academic_year_id',
    ];

    protected $casts = [
        'birth_date' => 'date',
    ];

    public function classroom()
    {
        return $this->belongsTo(Classroom::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function bulletins()
    {
        return $this->hasMany(Bulletin::class);
    }

    public function getFullNameAttribute(): string
    {
        return strtoupper($this->last_name) . ' ' . ucfirst(strtolower($this->first_name));
    }
}
