<?php

namespace App\Imports;

use App\Models\Competence;
use App\Models\Niveau;
use App\Models\Subject;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ProgrammeImport implements WithMultipleSheets
{
    public array $stats = [
        'subjects_created'    => 0,
        'subjects_updated'    => 0,
        'competences_created' => 0,
        'competences_updated' => 0,
        'errors'              => [],
    ];

    public function sheets(): array
    {
        return [
            0 => new ProgrammeMatiereImportSheet($this->stats),
            1 => new ProgrammeCompetenceImportSheet($this->stats),
        ];
    }

    public function getStats(): array { return $this->stats; }
}

// ── Sheet 1 : Matières ───────────────────────────────────────────────────────
class ProgrammeMatiereImportSheet implements
    \Maatwebsite\Excel\Concerns\ToModel,
    \Maatwebsite\Excel\Concerns\WithHeadingRow,
    \Maatwebsite\Excel\Concerns\WithValidation
{
    private array $niveauxCache = [];

    public function __construct(private array &$stats) {}

    public function rules(): array
    {
        return [
            '*.code_niveau'   => 'required',
            '*.nom_matiere'   => 'required',
            '*.code_matiere'  => 'required',
            '*.bareme'        => 'required|in:numeric,competence',
            '*.note_max'      => 'required|numeric|min:1',
        ];
    }

    public function model(array $row): ?Subject
    {
        $niveauCode = trim((string) ($row['code_niveau'] ?? ''));
        $name       = trim((string) ($row['nom_matiere'] ?? ''));
        $code       = strtoupper(trim((string) ($row['code_matiere'] ?? '')));
        $scaleType  = strtolower(trim((string) ($row['bareme'] ?? 'numeric')));
        $maxScore   = (float) ($row['note_max'] ?? 20);
        $classCode  = trim((string) ($row['classe_optionnel'] ?? '')) ?: null;
        $order      = (int) ($row['ordre'] ?? 0);

        if (! $niveauCode || ! $name || ! $code) return null;

        // Resolve niveau
        if (! isset($this->niveauxCache[$niveauCode])) {
            $this->niveauxCache[$niveauCode] = Niveau::where('code', $niveauCode)->first();
        }
        $niveau = $this->niveauxCache[$niveauCode];

        if (! $niveau) {
            $this->stats['errors'][] = "Niveau introuvable: {$niveauCode} (matière {$code})";
            return null;
        }

        $existing = Subject::where('code', $code)->where('niveau_id', $niveau->id)->first();

        if ($existing) {
            $existing->update([
                'name'           => $name,
                'classroom_code' => $classCode,
                'max_score'      => $maxScore,
                'scale_type'     => $scaleType,
                'order'          => $order,
            ]);
            $this->stats['subjects_updated']++;
            return null;
        }

        $this->stats['subjects_created']++;
        return new Subject([
            'niveau_id'      => $niveau->id,
            'name'           => $name,
            'code'           => $code,
            'classroom_code' => $classCode,
            'max_score'      => $maxScore,
            'scale_type'     => $scaleType,
            'order'          => $order,
        ]);
    }
}

// ── Sheet 2 : Compétences ────────────────────────────────────────────────────
class ProgrammeCompetenceImportSheet implements
    \Maatwebsite\Excel\Concerns\ToModel,
    \Maatwebsite\Excel\Concerns\WithHeadingRow
{
    private array $subjectsCache = [];

    public function __construct(private array &$stats) {}

    public function model(array $row): ?Competence
    {
        $subjectCode = strtoupper(trim((string) ($row['code_matiere'] ?? '')));
        $compCode    = strtoupper(trim((string) ($row['code_competence'] ?? '')));
        $desc        = trim((string) ($row['description'] ?? ''));
        $maxScore    = isset($row['note_max_videaevana']) && $row['note_max_videaevana'] !== ''
            ? (float) $row['note_max_videaevana'] : null;
        $period      = strtoupper(trim((string) ($row['periode'] ?? ''))) ?: null;
        if ($period && ! in_array($period, ['T1', 'T2', 'T3'])) $period = null;
        $order = (int) ($row['ordre'] ?? 0);

        if (! $subjectCode || ! $compCode || ! $desc) return null;

        if (! isset($this->subjectsCache[$subjectCode])) {
            $this->subjectsCache[$subjectCode] = Subject::where('code', $subjectCode)->first();
        }
        $subject = $this->subjectsCache[$subjectCode];

        if (! $subject) {
            $this->stats['errors'][] = "Matière introuvable: {$subjectCode} (compétence {$compCode})";
            return null;
        }

        $existing = Competence::where('subject_id', $subject->id)->where('code', $compCode)->first();

        if ($existing) {
            $existing->update([
                'description' => $desc,
                'max_score'   => $maxScore,
                'period'      => $period,
                'order'       => $order,
            ]);
            $this->stats['competences_updated']++;
            return null;
        }

        $this->stats['competences_created']++;
        return new Competence([
            'subject_id'  => $subject->id,
            'code'        => $compCode,
            'description' => $desc,
            'max_score'   => $maxScore,
            'period'      => $period,
            'order'       => $order,
        ]);
    }
}
