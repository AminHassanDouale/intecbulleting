<?php

namespace App\Actions\Bulletin;

use App\Enums\BulletinStatusEnum;
use App\Models\Bulletin;
use App\Models\BulletinTeacherSubmission;
use App\Models\User;

class SubmitTeacherSubjectsAction
{
    /**
     * Mark a teacher as having submitted their subjects for a bulletin.
     *
     * @return array{
     *   success: bool,
     *   fully_submitted: bool,
     *   message: string,
     *   progress: array
     * }
     */
    public function execute(Bulletin $bulletin, User $teacher): array
    {
        // Guard: teacher must have subjects assigned
        if (! $teacher->subjects()->exists()) {
            return [
                'success'          => false,
                'fully_submitted'  => false,
                'message'          => 'Aucune matière ne vous est assignée.',
                'progress'         => [],
            ];
        }

        // Guard: bulletin must still be editable by this teacher
        if (! $bulletin->canTeacherEdit($teacher->id)) {
            return [
                'success'          => false,
                'fully_submitted'  => false,
                'message'          => 'Ce bulletin ne peut plus être modifié.',
                'progress'         => [],
            ];
        }

        // Mark this teacher as submitted
        BulletinTeacherSubmission::updateOrCreate(
            ['bulletin_id' => $bulletin->id, 'teacher_id' => $teacher->id],
            ['status' => 'submitted', 'submitted_at' => now()]
        );

        $bulletin->load('teacherSubmissions', 'classroom.niveau');
        $progress = $bulletin->teacherSubmissionProgress();

        // If ALL teachers have submitted → advance bulletin to SUBMITTED
        if ($bulletin->allTeachersSubmitted()) {
            $bulletin->update([
                'status'       => BulletinStatusEnum::SUBMITTED,
                'submitted_by' => $teacher->id,
                'submitted_at' => now(),
            ]);

            // Notify pédagogie
            User::role('pedagogie')->each(fn($u) =>
                $u->notify(new \App\Notifications\GradesSubmittedNotification($bulletin))
            );

            return [
                'success'         => true,
                'fully_submitted' => true,
                'message'         => 'Tous les enseignants ont soumis — bulletin transmis à la pédagogie.',
                'progress'        => $progress,
            ];
        }

        $remaining = $progress['total'] - $progress['submitted'];

        return [
            'success'         => true,
            'fully_submitted' => false,
            'message'         => "Vos notes sont soumises. En attente de {$remaining} autre(s) enseignant(s).",
            'progress'        => $progress,
        ];
    }
}
