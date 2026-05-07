<?php

namespace Database\Seeders;

use App\Enums\AcademicLevelEnum;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Niveau;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * FullSchoolSeeder
 *
 * Seeds:
 *   1) Préscolaire classrooms (5)
 *   2) Primaire classrooms + teachers (8) — sourced verbatim from
 *      modele_import_enseignants.xlsx → sheet "Import Enseignants"
 *
 * Classroom.code is the full section code:
 *   PSA, MSA, MSB, GSA, GSB, CPA, CPB, CE1A, CE1B, CE2A, CE2B, CM1A, CM2A
 *
 * Subject precedence (matches GradeSheetExport / GradeSheetImport):
 *   1) section_code = classroom.code          e.g. "CPA"
 *   2) classroom_code = level (CPA → "CP")    when section_code IS NULL
 *   3) global subject (no section, no level)  within the niveau
 *
 * Idempotent: existing teachers matched by email;
 *             existing classrooms matched by (code, section, academic_year_id).
 */
class FullSchoolSeeder extends Seeder
{
    private const DEFAULT_PASSWORD = 'Intec@2026';

    /**
     * Préscolaire classrooms.
     * No Excel source — teacher credentials are placeholders.
     */
    private array $prescoClassrooms = [
        ['code' => 'PSA', 'section' => 'A', 'label' => 'PS A',
         'teacher_name' => 'Habibo HASSAN',  'teacher_email' => 'prof.ps.a@intec.org'],

        ['code' => 'MSA', 'section' => 'A', 'label' => 'MS A',
         'teacher_name' => 'Nasrin OMAR',    'teacher_email' => 'prof.ms.a@intec.org'],

        ['code' => 'MSB', 'section' => 'B', 'label' => 'MS B',
         'teacher_name' => 'Safia WAIS',     'teacher_email' => 'prof.ms.b@intec.org'],

        ['code' => 'GSA', 'section' => 'A', 'label' => 'GS A',
         'teacher_name' => 'Farhiya AHMED',  'teacher_email' => 'prof.gs.a@intec.org'],

        ['code' => 'GSB', 'section' => 'B', 'label' => 'GS B',
         'teacher_name' => 'Khadra OSMAN',   'teacher_email' => 'prof.gs.b@intec.org'],
    ];

    /**
     * Primaire teachers — copied verbatim from:
     *   modele_import_enseignants.xlsx → sheet "Import Enseignants"
     *   Columns: Nom | Email | Mot de Passe | Code Classe
     *
     * One teacher per section (8 rows, 8 classrooms).
     */
    private array $primaireTeachers = [
        // Nom                       | Email                       | Mot de Passe | Code Classe
        ['name' => 'MARIAM YOUSSOUF BARREH',  'email' => 'mariam.youssouf@intec.org',  'password' => 'Intec@2026', 'classroom_code' => 'CPA'],
        ['name' => 'SAMIRA OMAR YOUSSOUF',    'email' => 'samira.omar@intec.org',      'password' => 'Intec@2026', 'classroom_code' => 'CPB'],
        ['name' => 'ABDOULAZIZ MAHDI OSMAN',  'email' => 'abdoulaziz.mahdi@intec.org', 'password' => 'Intec@2026', 'classroom_code' => 'CE1A'],
        ['name' => 'FATHIA MOHAMED ILMY',     'email' => 'fathia.mohamed@intec.org',   'password' => 'Intec@2026', 'classroom_code' => 'CE1B'],
        ['name' => 'MARWA ABDI',              'email' => 'marwa.abdi@intec.org',       'password' => 'Intec@2026', 'classroom_code' => 'CE2A'],
        ['name' => 'KADRA ISMAN WAIS',        'email' => 'kadra.isman@intec.org',      'password' => 'Intec@2026', 'classroom_code' => 'CE2B'],
        ['name' => 'AMINA OMAR SAMIREH',      'email' => 'amina.omar@intec.org',       'password' => 'Intec@2026', 'classroom_code' => 'CM1A'],
        ['name' => 'RAHMA HOUSSEIN',          'email' => 'rahma.houssein@intec.org',   'password' => 'Intec@2026', 'classroom_code' => 'CM2A'],
    ];

    public function run(): void
    {
        $year = AcademicYear::where('is_current', true)->firstOrFail();

        $prescoCount   = $this->seedPrescolaire($year);
        $primaireCount = $this->seedPrimaire($year);

        $this->command?->info("✓ {$prescoCount} préscolaire + {$primaireCount} primaire classrooms seeded.");
    }

    // ── Préscolaire ──────────────────────────────────────────────────────────

    private function seedPrescolaire(AcademicYear $year): int
    {
        $niveau = Niveau::where('code', AcademicLevelEnum::PRESCOLAIRE->value)->first();
        if (! $niveau) {
            $this->command?->warn('Niveau PRESCOLAIRE introuvable — skip préscolaire.');
            return 0;
        }

        $count = 0;
        foreach ($this->prescoClassrooms as $cfg) {
            $teacher = $this->upsertTeacher(
                $cfg['teacher_name'],
                $cfg['teacher_email'],
                self::DEFAULT_PASSWORD,
            );

            $classroom = Classroom::updateOrCreate(
                [
                    'code'             => $cfg['code'],
                    'section'          => $cfg['section'],
                    'academic_year_id' => $year->id,
                ],
                [
                    'label'      => $cfg['label'],
                    'teacher_id' => $teacher->id,
                    'niveau_id'  => $niveau->id,
                ]
            );

            $this->attachTeacherToClassSubjects($teacher, $classroom, $niveau);
            $count++;
        }

        return $count;
    }

    // ── Primaire ─────────────────────────────────────────────────────────────

    private function seedPrimaire(AcademicYear $year): int
    {
        $niveau = Niveau::where('code', AcademicLevelEnum::PRIMAIRE->value)->first();
        if (! $niveau) {
            $this->command?->warn('Niveau PRIMAIRE introuvable — skip primaire.');
            return 0;
        }

        $count = 0;
        foreach ($this->primaireTeachers as $row) {
            $sectionCode = strtoupper(trim($row['classroom_code']));
            $section     = $this->extractSectionLetter($sectionCode); // 'A', 'B', or null
            $level       = $this->extractLevelCode($sectionCode);     // e.g. 'CP', 'CE1', 'CM2'
            $label       = $section !== null ? "{$level} {$section}" : $level;

            $teacher = $this->upsertTeacher(
                trim($row['name']),
                strtolower(trim($row['email'])),
                $row['password'] ?: self::DEFAULT_PASSWORD,
            );

            $classroom = Classroom::updateOrCreate(
                [
                    'code'             => $sectionCode,
                    'section'          => $section,   // null when no A/B suffix
                    'academic_year_id' => $year->id,
                ],
                [
                    'label'      => $label,
                    'teacher_id' => $teacher->id,
                    'niveau_id'  => $niveau->id,
                ]
            );

            $this->attachTeacherToClassSubjects($teacher, $classroom, $niveau);
            $count++;
        }

        return $count;
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function upsertTeacher(string $name, string $email, string $password): User
    {
        $teacher = User::firstOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Hash::make($password)]
        );

        // Sync name if it changed (e.g. capitalisation fix).
        if ($name !== '' && $teacher->name !== $name) {
            $teacher->update(['name' => $name]);
        }

        if (! $teacher->hasRole('teacher')) {
            $teacher->assignRole('teacher');
        }

        return $teacher;
    }

    /**
     * Attach the teacher to every subject relevant to this classroom,
     * then register the teacher on the classroom pivot.
     *
     * Subject precedence (mirrors GradeSheetExport / GradeSheetImport):
     *   1) section_code = classroom.code              e.g. "CPA"
     *   2) classroom_code = level (CPA → "CP")        when section_code IS NULL
     *   3) global subject (no section, no level)      within the niveau
     */
    private function attachTeacherToClassSubjects(User $teacher, Classroom $classroom, Niveau $niveau): void
    {
        $sectionCode = (string) $classroom->code;
        $levelCode   = $this->extractLevelCode($sectionCode);

        $subjects = Subject::where('niveau_id', $niveau->id)
            ->where(function ($q) use ($sectionCode, $levelCode) {
                // 1) exact section match
                $q->where('section_code', $sectionCode)
                  // 2) level match (no section_code on the subject)
                  ->orWhere(function ($q2) use ($levelCode) {
                      $q2->whereNull('section_code')
                         ->where('classroom_code', $levelCode);
                  })
                  // 3) global subject (neither section nor level)
                  ->orWhere(function ($q3) {
                      $q3->whereNull('section_code')
                         ->whereNull('classroom_code');
                  });
            })
            ->get();

        foreach ($subjects as $subject) {
            if (! $subject->teachers()->where('users.id', $teacher->id)->exists()) {
                $subject->teachers()->attach($teacher->id);
            }
        }

        // Also register on the classroom ↔ teacher pivot.
        if (! $classroom->teachers()->where('users.id', $teacher->id)->exists()) {
            $classroom->teachers()->attach($teacher->id);
        }
    }

    /**
     * Extract the trailing section letter ('A' or 'B') from a classroom code.
     * Returns null (not empty string) when no A/B suffix is present.
     *
     * Examples:
     *   'CPA'  → 'A'
     *   'CE1B' → 'B'
     *   'PSA'  → 'A'
     *   'CM2'  → null
     */
    private function extractSectionLetter(string $sectionCode): ?string
    {
        return preg_match('/([AB])$/', $sectionCode, $m) ? $m[1] : null;
    }

    /**
     * Strip the trailing section letter to get the level code.
     *
     * Examples:
     *   'CPA'  → 'CP'
     *   'CE1A' → 'CE1'
     *   'CM2A' → 'CM2'
     *   'PSA'  → 'PS'
     */
    private function extractLevelCode(string $sectionCode): string
    {
        return preg_replace('/[AB]$/', '', $sectionCode) ?: $sectionCode;
    }
}
