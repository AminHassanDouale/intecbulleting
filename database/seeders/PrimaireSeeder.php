<?php

namespace Database\Seeders;

use App\Enums\AcademicLevelEnum;
use App\Enums\ScaleTypeEnum;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Competence;
use App\Models\Niveau;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * PrimaireSeeder
 *
 * ─── AUTHORITATIVE SOURCE ───────────────────────────────────────────────────
 *   modele_import_enseignants.xlsx  (sheet: Import Enseignants)
 *
 * ─── TEACHERS (verbatim from Excel) ─────────────────────────────────────────
 *   MARIAM YOUSSOUF BARREH    mariam.youssouf@intec.org    Intec@2026   CPA
 *   SAMIRA OMAR YOUSSOUF      samira.omar@intec.org        Intec@2026   CPB
 *   ABDOULAZIZ MAHDI OSMAN    abdoulaziz.mahdi@intec.org   Intec@2026   CE1A
 *   FATHIA MOHAMED ILMY       fathia.mohamed@intec.org     Intec@2026   CE1B
 *   Marwa Abdi                marwa.abdi@intec.org         Intec@2026   CE2A
 *   KADRA ISMAN WAIS          kadra.isman@intec.org        Intec@2026   CE2B
 *   AMINA OMAR SAMIREH        amina.omar@intec.org         Intec@2026   CM1A
 *   RAHMA HOUSSEIN            rahma.houssein@intec.org     Intec@2026   CM2A
 *
 * ─── CLASSROOM MAPPING ──────────────────────────────────────────────────────
 *   class_code → classroom_code (Subject FK) + section
 *   CPA  → code=CP,  section=A
 *   CPB  → code=CP,  section=B
 *   CE1A → code=CE1, section=A
 *   CE1B → code=CE1, section=B
 *   CE2A → code=CE2, section=A
 *   CE2B → code=CE2, section=B
 *   CM1A → code=CM1, section=A
 *   CM2A → code=CM2, section=A
 */
class PrimaireSeeder extends Seeder
{
    // ── Teacher data verbatim from Excel ──────────────────────────────────────
    private array $teachers = [
        [
            'name'       => 'MARIAM YOUSSOUF BARREH',
            'email'      => 'mariam.youssouf@intec.org',
            'password'   => 'Intec@2026',
            'class_code' => 'CPA',
            'code'       => 'CP',
            'section'    => 'A',
            'label'      => 'CP A',
        ],
        [
            'name'       => 'SAMIRA OMAR YOUSSOUF',
            'email'      => 'samira.omar@intec.org',
            'password'   => 'Intec@2026',
            'class_code' => 'CPB',
            'code'       => 'CP',
            'section'    => 'B',
            'label'      => 'CP B',
        ],
        [
            'name'       => 'ABDOULAZIZ MAHDI OSMAN',
            'email'      => 'abdoulaziz.mahdi@intec.org',
            'password'   => 'Intec@2026',
            'class_code' => 'CE1A',
            'code'       => 'CE1',
            'section'    => 'A',
            'label'      => 'CE1 A',
        ],
        [
            'name'       => 'FATHIA MOHAMED ILMY',
            'email'      => 'fathia.mohamed@intec.org',
            'password'   => 'Intec@2026',
            'class_code' => 'CE1B',
            'code'       => 'CE1',
            'section'    => 'B',
            'label'      => 'CE1 B',
        ],
        [
            'name'       => 'Marwa Abdi',
            'email'      => 'marwa.abdi@intec.org',
            'password'   => 'Intec@2026',
            'class_code' => 'CE2A',
            'code'       => 'CE2',
            'section'    => 'A',
            'label'      => 'CE2 A',
        ],
        [
            'name'       => 'KADRA ISMAN WAIS',
            'email'      => 'kadra.isman@intec.org',
            'password'   => 'Intec@2026',
            'class_code' => 'CE2B',
            'code'       => 'CE2',
            'section'    => 'B',
            'label'      => 'CE2 B',
        ],
        [
            'name'       => 'AMINA OMAR SAMIREH',
            'email'      => 'amina.omar@intec.org',
            'password'   => 'Intec@2026',
            'class_code' => 'CM1A',
            'code'       => 'CM1',
            'section'    => 'A',
            'label'      => 'CM1 A',
        ],
        [
            'name'       => 'RAHMA HOUSSEIN',
            'email'      => 'rahma.houssein@intec.org',
            'password'   => 'Intec@2026',
            'class_code' => 'CM2A',
            'code'       => 'CM2',
            'section'    => 'A',
            'label'      => 'CM2 A',
        ],
    ];

    public function run(): void
    {
        $year   = AcademicYear::where('is_current', true)->firstOrFail();
        $niveau = Niveau::where('code', AcademicLevelEnum::PRIMAIRE->value)->firstOrFail();

        // ── Create users + classrooms ─────────────────────────────────────────
        foreach ($this->teachers as $t) {
            $user = User::firstOrCreate(
                ['email' => $t['email']],
                [
                    'name'     => $t['name'],
                    'password' => Hash::make($t['password']),
                ]
            );
            $user->assignRole('teacher');

            Classroom::firstOrCreate(
                [
                    'code'             => $t['code'],
                    'section'          => $t['section'],
                    'academic_year_id' => $year->id,
                    'niveau_id'        => $niveau->id,
                ],
                [
                    'label'      => $t['label'],
                    'teacher_id' => $user->id,
                ]
            );
        }
    }
}
