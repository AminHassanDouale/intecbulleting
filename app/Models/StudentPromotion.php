<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentPromotion extends Model
{
    protected $fillable = [
        'student_id', 'academic_year_id', 'decision',
        'next_classroom_id', 'decided_by', 'decided_at', 'notes',
    ];

    protected $casts = [
        'decided_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function nextClassroom()
    {
        return $this->belongsTo(Classroom::class, 'next_classroom_id');
    }

    public function decidedBy()
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    // ── Class-code progression helper ─────────────────────────────────────────

    /**
     * Returns the next classroom code in the school progression.
     * PS → MS → GS → CP → CE1 → CE2 → CM1 → CM2 → null (fin de cursus)
     */
    public static function nextClassCode(string $code): ?string
    {
        return match (strtoupper(trim($code))) {
            'PS'  => 'MS',
            'MS'  => 'GS',
            'GS'  => 'CP',
            'CP'  => 'CE1',
            'CE1' => 'CE2',
            'CE2' => 'CM1',
            'CM1' => 'CM2',
            'CM2' => null,
            default => null,
        };
    }
}
