<?php

namespace App\Models;

use App\Enums\ScaleTypeEnum;
use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    protected $fillable = [
        'name', 'code', 'niveau_id', 'classroom_code', 'section_code',
        'max_score', 'scale_type', 'order',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

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

    // ── Scopes ────────────────────────────────────────────────────────────

    /**
     * Subjects visible for a given section:
     * those that belong to that exact section OR shared ones (section_code IS NULL).
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
     * Subjects that are shared across all sections of a niveau (no specific section).
     */
    public function scopeShared($query)
    {
        return $query->whereNull('section_code');
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    public function isPrescolaire(): bool
    {
        return $this->scale_type === ScaleTypeEnum::COMPETENCE->value;
    }

    /**
     * Whether this subject is pinned to a specific section (not shared).
     */
    public function isSectionSpecific(): bool
    {
        return ! is_null($this->section_code);
    }
}
