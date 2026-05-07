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

    // Override rejectWorkflow from HasWorkflow so we can reset teacher submissions.
    use HasWorkflow {
        rejectWorkflow as protected traitRejectWorkflow;
    }

    protected $fillable = [
        'student_id', 'classroom_id', 'academic_year_id', 'period',
        'status', 'total_score', 'moyenne', 'class_moyenne',
        'appreciation', 'teacher_comment', 'direction_comment',
        // ── New columns ───────────────────────────────────────────────────
        'total_manuel',       // manual override for total score
        'moyenne_10',         // moyenne scaled to /10
        'moyenne_classe',     // class average (replaces legacy class_moyenne)
        'discipline_status',  // e.g. 'bien', 'passable', 'insuffisant'
        // ─────────────────────────────────────────────────────────────────
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

    /** Has this specific teacher already submitted their subjects for this bulletin? */
    public function isTeacherSubmitted(int $userId): bool
    {
        return $this->teacherSubmissions()
            ->where('teacher_id', $userId)
            ->where('status', 'submitted')
            ->exists();
    }

    /**
     * Can this user still edit grades?
     * - Direction/admin: yes unless bulletin is published.
     * - Teacher: yes if DRAFT and not yet submitted, or REJECTED.
     */
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

    /**
     * Returns true when every teacher assigned to THIS classroom has submitted.
     * Uses the classroom_teacher pivot — not classroom_code on subjects —
     * so CP A only waits for its own teachers, not CP B's.
     */
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

    /**
     * Returns progress data: how many teachers submitted vs total for THIS classroom.
     */
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

    // ── Moyenne helpers ────────────────────────────────────────────────────────

    /**
     * The effective total to use: manual override takes precedence over computed.
     */
    public function effectiveTotal(): ?float
    {
        return $this->total_manuel ?? $this->total_score;
    }

    /**
     * The effective class average: new moyenne_classe takes precedence over legacy class_moyenne.
     */
    public function effectiveClassMoyenne(): ?float
    {
        return $this->moyenne_classe ?? $this->class_moyenne;
    }

    /**
     * Whether the total has been manually overridden by direction/admin.
     */
    public function hasManuaOverride(): bool
    {
        return ! is_null($this->total_manuel);
    }

    // ── Workflow override ──────────────────────────────────────────────────────

    /**
     * On rejection, reset all teacher submissions to draft so teachers can edit again.
     */
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

    /**
     * Direction/admin can edit grades inline when the bulletin is pending their review
     * or already approved (before publication).
     */
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
