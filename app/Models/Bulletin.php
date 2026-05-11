<?php

namespace App\Models;

use App\Enums\BulletinStatusEnum;
use App\Traits\CalculatesMoyenne;
use App\Traits\HasWorkflow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Bulletin extends Model implements HasMedia
{
    use SoftDeletes, InteractsWithMedia, CalculatesMoyenne;

    use HasWorkflow {
        rejectWorkflow as protected traitRejectWorkflow;
    }

    protected $fillable = [
        'student_id', 'classroom_id', 'academic_year_id', 'period',
        'status', 'total_score', 'moyenne', 'class_moyenne',
        'appreciation', 'teacher_comment', 'direction_comment',
        'total_manuel',
        'moyenne_10',
        'moyenne_classe',
        'discipline_status',
        'submitted_by', 'pedagogie_approved_by', 'finance_approved_by',
        'direction_approved_by', 'submitted_at', 'pedagogie_approved_at',
        'finance_approved_at', 'direction_approved_at', 'published_at',
    ];

    protected $casts = [
        'status'                => BulletinStatusEnum::class,
        'total_score'           => 'decimal:2',
        'moyenne'               => 'decimal:2',
        'class_moyenne'         => 'decimal:2',
        'total_manuel'          => 'decimal:2',
        'moyenne_10'            => 'decimal:2',
        'moyenne_classe'        => 'decimal:2',
        'submitted_at'          => 'datetime',
        'pedagogie_approved_at' => 'datetime',
        'finance_approved_at'   => 'datetime',
        'direction_approved_at' => 'datetime',
        'published_at'          => 'datetime',
    ];

    // ═══════════════════════════════════════════════════════════════════════
    // IMPORTANT — anti-recomputation guard
    //
    // The CalculatesMoyenne trait is used here for legacy reasons. If that
    // trait registers a saving/saved hook that recomputes `moyenne` or
    // `moyenne_10` from grades, it WILL clobber imported / manually-entered
    // values, and that's been the source of intermittent "the data I
    // imported isn't there" bugs — especially visible on CE1/CE2/CM1/CM2
    // where the /20 scale makes wrong recomputed values stand out.
    //
    // If you have access to the trait, ensure its recomputation methods:
    //   1. Are NEVER called from a saving/saved/creating/updated hook.
    //      They should only run when explicitly invoked (e.g. a "Recalculer"
    //      button), never automatically.
    //   2. Respect the manual fields: if `total_manuel`, `moyenne_10`, or
    //      `moyenne_classe` are non-null, the trait must NOT overwrite them.
    //
    // The two helpers below give explicit, traceable access for callers
    // that want to write summary fields without any chance of trait
    // interference.
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Persist the four manual summary fields atomically, bypassing any
     * trait-level recomputation. Pass null to clear a field.
     */
    public function setManualSummary(
        ?float  $totalManuel,
        ?float  $moyenne10,
        ?float  $moyenneClasse,
        ?string $disciplineStatus,
    ): bool {
        return $this->update([
            'total_manuel'      => $totalManuel,
            'moyenne_10'        => $moyenne10,
            'moyenne_classe'    => $moyenneClasse,
            'discipline_status' => $disciplineStatus,
        ]);
    }

    /**
     * True if ANY manual summary field has been set — used by carnet/saisir
     * to decide whether to render stored values vs computed fallback.
     */
    public function hasManualSummary(): bool
    {
        return $this->total_manuel    !== null
            || $this->moyenne_10      !== null
            || $this->moyenne_classe  !== null
            || $this->discipline_status !== null;
    }

    // ── Relationships ──────────────────────────────────────────────────────────

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function classroom()
    {
        return $this->belongsTo(Classroom::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function grades()
    {
        return $this->hasMany(BulletinGrade::class);
    }

    public function approvals()
    {
        return $this->hasMany(WorkflowApproval::class)->orderBy('created_at');
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function teacherSubmissions()
    {
        return $this->hasMany(BulletinTeacherSubmission::class);
    }

    // ── Teacher submission helpers ─────────────────────────────────────────────

    public function isTeacherSubmitted(int $userId): bool
    {
        return $this->teacherSubmissions()
            ->where('teacher_id', $userId)
            ->where('status', 'submitted')
            ->exists();
    }

    public function canTeacherEdit(int $userId): bool
    {
        $user = \App\Models\User::find($userId);

        if ($user?->hasAnyRole(['admin', 'direction'])) {
            return $this->status !== BulletinStatusEnum::PUBLISHED;
        }

        if ($this->status === BulletinStatusEnum::REJECTED) {
            return true;
        }

        return $this->status === BulletinStatusEnum::DRAFT
            && ! $this->isTeacherSubmitted($userId);
    }

    public function allTeachersSubmitted(): bool
    {
        $this->loadMissing('classroom');

        $teacherIds = $this->classroom->teachers()->pluck('users.id');

        if ($teacherIds->isEmpty()) {
            return false;
        }

        $submitted = $this->teacherSubmissions()
            ->where('status', 'submitted')
            ->pluck('teacher_id');

        return $teacherIds->diff($submitted)->isEmpty();
    }

    public function teacherSubmissionProgress(): array
    {
        $this->loadMissing('classroom', 'teacherSubmissions.teacher');

        $teachers     = $this->classroom->teachers;
        $submittedIds = $this->teacherSubmissions()
            ->where('status', 'submitted')
            ->pluck('teacher_id');

        return [
            'total'     => $teachers->count(),
            'submitted' => $submittedIds->count(),
            'teachers'  => $teachers->map(fn ($t) => [
                'name'      => $t->name,
                'submitted' => $submittedIds->contains($t->id),
            ])->toArray(),
        ];
    }

    // ── Effective-value helpers ────────────────────────────────────────────────

    /**
     * Manual override takes precedence over any computed total.
     */
    public function effectiveTotal(): ?float
    {
        return $this->total_manuel !== null
            ? (float) $this->total_manuel
            : ($this->total_score !== null ? (float) $this->total_score : null);
    }

    /**
     * Manual override takes precedence over any computed moyenne.
     */
    public function effectiveMoyenne(): ?float
    {
        return $this->moyenne_10 !== null
            ? (float) $this->moyenne_10
            : ($this->moyenne !== null ? (float) $this->moyenne : null);
    }

    /**
     * Effective class average — manual override wins over legacy field.
     */
    public function effectiveClassMoyenne(): ?float
    {
        return $this->moyenne_classe !== null
            ? (float) $this->moyenne_classe
            : ($this->class_moyenne !== null ? (float) $this->class_moyenne : null);
    }

    public function hasManuaOverride(): bool
    {
        return ! is_null($this->total_manuel);
    }

    // ── Workflow override ──────────────────────────────────────────────────────

    public function rejectWorkflow(int $userId, string $reason): bool
    {
        $this->teacherSubmissions()->update(['status' => 'draft', 'submitted_at' => null]);

        return $this->traitRejectWorkflow($userId, $reason);
    }

    // ── Media ──────────────────────────────────────────────────────────────────

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('bulletin_pdf')->singleFile();
        $this->addMediaCollection('pieces_jointes');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isEditable(): bool
    {
        return in_array($this->status, [
            BulletinStatusEnum::DRAFT,
            BulletinStatusEnum::REJECTED,
        ]);
    }

    public function canDirectionEdit(): bool
    {
        return in_array($this->status, [
            BulletinStatusEnum::FINANCE_APPROVED,
            BulletinStatusEnum::DIRECTION_REVIEW,
            BulletinStatusEnum::APPROVED,
        ]);
    }

    public function getPdfUrl(): ?string
    {
        return $this->getFirstMediaUrl('bulletin_pdf') ?: null;
    }
}
