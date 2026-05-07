<?php

namespace App\Models;

use App\Enums\ScaleTypeEnum;
use Illuminate\Database\Eloquent\Model;

class Competence extends Model
{
    protected $fillable = [
        'subject_id', 'section_code', 'code', 'description',
        'max_score', 'period', 'order',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function grades()
    {
        return $this->hasMany(BulletinGrade::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    /**
     * Competences visible for a given section:
     * those pinned to that section OR shared ones (section_code IS NULL).
     */
    public function scopeForSection($query, ?string $sectionCode)
    {
        if (! $sectionCode) {
            return $query;
        }

        return $query->where(function ($q) use ($sectionCode) {
            $q->where('section_code', $sectionCode)
              ->orWhereNull('section_code');
        });
    }

    /**
     * Competences that apply to a specific period or all periods (period IS NULL).
     */
    public function scopeForPeriod($query, ?string $period)
    {
        if (! $period) {
            return $query;
        }

        return $query->where(function ($q) use ($period) {
            $q->where('period', $period)
              ->orWhereNull('period');
        });
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    public function isPrescolaire(): bool
    {
        return $this->subject->scale_type === ScaleTypeEnum::COMPETENCE->value;
    }

    /**
     * Whether this competence uses a shared pool (no individual max_score).
     */
    public function isPooled(): bool
    {
        return is_null($this->max_score);
    }

    /**
     * Whether this competence is pinned to a specific section.
     */
    public function isSectionSpecific(): bool
    {
        return ! is_null($this->section_code);
    }
}
