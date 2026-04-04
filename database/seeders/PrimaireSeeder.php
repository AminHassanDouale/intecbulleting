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

class PrimaireSeeder extends Seeder
{
    public function run(): void
    {
        $year  = AcademicYear::where('is_current', true)->firstOrFail();
        $niveau = Niveau::where('code', AcademicLevelEnum::PRIMAIRE->value)->firstOrFail();

        // ── 3 enseignants primaire ────────────────────────────────────────────
        $teachers = [
            [
                'email' => 'prof.diallo@intec.edu',
                'name'  => 'Amadou DIALLO',
                'class' => 'CM1',
                'label' => 'CM1 A',
            ],
            [
                'email' => 'prof.bamba@intec.edu',
                'name'  => 'Fatou BAMBA',
                'class' => 'CE2',
                'label' => 'CE2 B',
            ],
            [
                'email' => 'prof.kone@intec.edu',
                'name'  => 'Youssouf KONÉ',
                'class' => 'CP',
                'label' => 'CP A',
            ],
        ];

        $createdTeachers = [];
        foreach ($teachers as $t) {
            $user = User::firstOrCreate(
                ['email' => $t['email']],
                ['name' => $t['name'], 'password' => Hash::make('password')]
            );
            $user->assignRole('teacher');
            $createdTeachers[$t['email']] = $user;
        }

        // ── 3 classes primaire (une par enseignant) ───────────────────────────
        $classrooms = [];
        foreach ($teachers as $t) {
            $teacher = $createdTeachers[$t['email']];

            $classroom = Classroom::firstOrCreate(
                [
                    'code'             => $t['class'],
                    'section'          => substr($t['label'], -1), // 'A' or 'B'
                    'academic_year_id' => $year->id,
                    'niveau_id'        => $niveau->id,
                ],
                [
                    'label'      => $t['label'],
                    'teacher_id' => $teacher->id,
                ]
            );

            $classrooms[$t['email']] = $classroom;
        }

        // ── Matières supplémentaires pour chaque classe ───────────────────────
        // CM1 : ajouter HISTOIRE-GÉO et ANGLAIS
        $cm1Classroom = $classrooms['prof.diallo@intec.edu'];
        $this->ensureSubjectWithCompetences($niveau, [
            'name'           => 'HISTOIRE-GÉOGRAPHIE',
            'code'           => 'HG',
            'classroom_code' => 'CM1',
            'max_score'      => 20,
            'scale_type'     => ScaleTypeEnum::NUMERIC->value,
            'order'          => 4,
        ], [
            ['code' => 'CB1', 'description' => 'Situer les grandes périodes de l\'histoire nationale et africaine.', 'max_score' => 10, 'order' => 1],
            ['code' => 'CB2', 'description' => 'Lire et interpréter une carte géographique simple.', 'max_score' => 10, 'order' => 2],
        ]);

        // CE2 : ajouter EPS et SCIENCES
        $this->ensureSubjectWithCompetences($niveau, [
            'name'           => 'EPS',
            'code'           => 'EPS',
            'classroom_code' => 'CE2',
            'max_score'      => 20,
            'scale_type'     => ScaleTypeEnum::NUMERIC->value,
            'order'          => 4,
        ], [
            ['code' => 'CB1', 'description' => 'Réaliser des actions motrices variées (course, saut, lancer).', 'max_score' => 10, 'order' => 1],
            ['code' => 'CB2', 'description' => 'Respecter les règles de vie collective lors des jeux sportifs.', 'max_score' => 10, 'order' => 2],
        ]);

        // CP : ajouter ÉDUCATION MORALE et CIVIQUE
        $this->ensureSubjectWithCompetences($niveau, [
            'name'           => 'ÉDUCATION MORALE ET CIVIQUE',
            'code'           => 'EMC',
            'classroom_code' => 'CP',
            'max_score'      => 20,
            'scale_type'     => ScaleTypeEnum::NUMERIC->value,
            'order'          => 4,
        ], [
            ['code' => 'CB1', 'description' => 'Connaître et respecter les règles de vie en communauté.', 'max_score' => 10, 'order' => 1],
            ['code' => 'CB2', 'description' => 'Identifier les symboles et institutions civiques de base.', 'max_score' => 10, 'order' => 2],
        ]);

        // ── 10 élèves répartis sur les 3 classes ─────────────────────────────
        $students = [
            // CM1 A (4 élèves)
            ['first_name' => 'Kofi',     'last_name' => 'ASANTE',   'gender' => 'M', 'birth_date' => '2014-03-12', 'classroom' => 'prof.diallo@intec.edu'],
            ['first_name' => 'Ama',      'last_name' => 'MENSAH',   'gender' => 'F', 'birth_date' => '2014-07-25', 'classroom' => 'prof.diallo@intec.edu'],
            ['first_name' => 'Ibrahim',  'last_name' => 'COULIBALY', 'gender' => 'M', 'birth_date' => '2013-11-08', 'classroom' => 'prof.diallo@intec.edu'],
            ['first_name' => 'Aissatou', 'last_name' => 'BARRY',    'gender' => 'F', 'birth_date' => '2014-01-30', 'classroom' => 'prof.diallo@intec.edu'],
            // CE2 B (3 élèves)
            ['first_name' => 'Moussa',   'last_name' => 'TRAORÉ',   'gender' => 'M', 'birth_date' => '2015-05-19', 'classroom' => 'prof.bamba@intec.edu'],
            ['first_name' => 'Mariam',   'last_name' => 'DIOMANDE',  'gender' => 'F', 'birth_date' => '2015-09-03', 'classroom' => 'prof.bamba@intec.edu'],
            ['first_name' => 'Seydou',   'last_name' => 'OUATTARA', 'gender' => 'M', 'birth_date' => '2015-02-14', 'classroom' => 'prof.bamba@intec.edu'],
            // CP A (3 élèves)
            ['first_name' => 'Adja',     'last_name' => 'CAMARA',   'gender' => 'F', 'birth_date' => '2017-06-22', 'classroom' => 'prof.kone@intec.edu'],
            ['first_name' => 'Lamine',   'last_name' => 'SYLLA',    'gender' => 'M', 'birth_date' => '2017-08-10', 'classroom' => 'prof.kone@intec.edu'],
            ['first_name' => 'Nafi',     'last_name' => 'KOUYATÉ',  'gender' => 'F', 'birth_date' => '2017-04-05', 'classroom' => 'prof.kone@intec.edu'],
        ];

        $index = 1;
        foreach ($students as $s) {
            $classroom = $classrooms[$s['classroom']];
            $matricule = 'INTEC-PRIM-' . str_pad($index, 3, '0', STR_PAD_LEFT);

            Student::firstOrCreate(
                ['matricule' => $matricule],
                [
                    'first_name'       => $s['first_name'],
                    'last_name'        => $s['last_name'],
                    'gender'           => $s['gender'],
                    'birth_date'       => $s['birth_date'],
                    'classroom_id'     => $classroom->id,
                    'academic_year_id' => $year->id,
                ]
            );
            $index++;
        }
    }

    private function ensureSubjectWithCompetences(Niveau $niveau, array $subjectData, array $competencesData): void
    {
        $subjectData['niveau_id'] = $niveau->id;

        $subject = Subject::firstOrCreate(
            [
                'code'           => $subjectData['code'],
                'niveau_id'      => $niveau->id,
                'classroom_code' => $subjectData['classroom_code'] ?? null,
            ],
            $subjectData
        );

        foreach ($competencesData as $comp) {
            Competence::firstOrCreate(
                ['subject_id' => $subject->id, 'code' => $comp['code']],
                array_merge($comp, ['subject_id' => $subject->id])
            );
        }
    }
}
