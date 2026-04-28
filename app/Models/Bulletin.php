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

    // Override rejectWorkflow from HasWorkflow so we can reset teacher submissions
    use HasWorkflow {
        rejectWorkflow as protected traitRejectWorkflow;
    }

    protected $fillable = [
        'student_id', 'classroom_id', 'academic_year_id', 'period',
        'status', 'total_score', 'moyenne', 'class_moyenne',
        'appreciation', 'teacher_comment', 'direction_comment',
        'submitted_by', 'pedagogie_approved_by', 'finance_approved_by',
        'direction_approved_by', 'submitted_at', 'pedagogie_approved_at',
        'finance_approved_at', 'direction_approved_at', 'published_at',
    ];

    protected $casts = [
        'status'                => BulletinStatusEnum::class,
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
     * - Direction/admin: yes unless bulletin is approved or published
     * - Teacher: yes if DRAFT and not yet submitted, or REJECTED
     */
    public function canTeacherEdit(int $userId): bool
    {
        $user = \App\Models\User::find($userId);
        if ($user?->hasAnyRole(['admin', 'direction'])) {
            return ! in_array($this->status->value, ['approved', 'published']);
        }

        if ($this->status === BulletinStatusEnum::REJECTED) {
            return true;
        }

        return $this->status === BulletinStatusEnum::DRAFT
            && ! $this->isTeacherSubmitted($userId);
    }

    /**
     * Returns true when every teacher assigned to THIS classroom has submitted.
     * Uses the classroom_teacher pivot — not the classroom_code on subjects —
     * so CP A only waits for its own 3 teachers, not CP B's.
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
            'teachers'  => $teachers->map(fn($t) => [
                'name'      => $t->name,
                'submitted' => $submittedIds->contains($t->id),
            ])->toArray(),
        ];
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
