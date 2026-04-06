<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'matricule', 'full_name', 'birth_date',
        'gender', 'classroom_id', 'academic_year_id',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Student $student) {
            if (empty($student->matricule)) {
                $student->matricule = static::generateMatricule();
            }
        });
    }

    public static function generateMatricule(): string
    {
        $year = date('Y');
        do {
            $n    = str_pad((static::withTrashed()->max('id') ?? 0) + 1 + rand(0, 9), 4, '0', STR_PAD_LEFT);
            $code = "INTEC-{$year}-{$n}";
        } while (static::withTrashed()->where('matricule', $code)->exists());

        return $code;
    }

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

}
