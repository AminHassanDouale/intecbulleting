<?php

namespace Database\Seeders;

use App\Enums\AcademicLevelEnum;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Niveau;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

/**
 * FullSchoolSeeder
 *
 * Seeds complete school with EXACTLY 3 teachers per class.
 * Each teacher is assigned specific subjects with their competences for bulletin grading.
 *
 * PRÉSCOLAIRE Subject Codes (from SchoolStructureSeeder):
 *   LANG, PRELEC, GRAPH, MATHS, VIE, EI, ART, MONDE, PENSEE, DEC, MONDE_PS
 *
 * PRIMAIRE Subject Codes (from SchoolStructureSeeder):
 *   FR, AR, AR_ANG, AR_EI, DICT, OUTILS, MATHS, SCI, TECH, ANG, HIST, GEO, EPS
 */
class FullSchoolSeeder extends Seeder
{
    private array $names = [
        ['first_name' => 'Hodan',      'last_name' => 'IBRAHIM',    'gender' => 'F'],
        ['first_name' => 'Omar',       'last_name' => 'HASSAN',     'gender' => 'M'],
        ['first_name' => 'Fadumo',     'last_name' => 'ALI',        'gender' => 'F'],
        ['first_name' => 'Abdi',       'last_name' => 'WARSAME',    'gender' => 'M'],
        ['first_name' => 'Asad',       'last_name' => 'OMAR',       'gender' => 'M'],
        ['first_name' => 'Faadumo',    'last_name' => 'AHMED',      'gender' => 'F'],
        ['first_name' => 'Deeqa',      'last_name' => 'ISMAIL',     'gender' => 'F'],
        ['first_name' => 'Mahad',      'last_name' => 'ABDI',       'gender' => 'M'],
        ['first_name' => 'Nasteho',    'last_name' => 'YUSUF',      'gender' => 'F'],
        ['first_name' => 'Keynan',     'last_name' => 'MOHAMED',    'gender' => 'M'],
        ['first_name' => 'Saynab',     'last_name' => 'HUSSEIN',    'gender' => 'F'],
        ['first_name' => 'Feisal',     'last_name' => 'ABDIRAHMAN', 'gender' => 'M'],
        ['first_name' => 'Ladan',      'last_name' => 'JAMA',       'gender' => 'F'],
        ['first_name' => 'Mukhtar',    'last_name' => 'SALAH',      'gender' => 'M'],
        ['first_name' => 'Asha',       'last_name' => 'DAHIR',      'gender' => 'F'],
        ['first_name' => 'Ilyas',      'last_name' => 'OSMAN',      'gender' => 'M'],
        ['first_name' => 'Cawo',       'last_name' => 'FARAH',      'gender' => 'F'],
        ['first_name' => 'Abdullahi',  'last_name' => 'NUR',        'gender' => 'M'],
        ['first_name' => 'Hibo',       'last_name' => 'ELMI',       'gender' => 'F'],
        ['first_name' => 'Rashid',     'last_name' => 'GULED',      'gender' => 'M'],
    ];

    // ── PRÉSCOLAIRE: Each class has EXACTLY 3 teachers ───────────────────────
    private array $prescoClassrooms = [
        // PS A - Petite Section A
        [
            'code'               => 'PS',
            'section'            => 'A',
            'label'              => 'PS A',
            'subject_class_code' => null,
            'homeroom_name'      => 'Habibo HASSAN',
            'homeroom_email'     => 'prof.ps.a@intec.edu',
            'teachers'           => [
                [
                    'name'          => 'Ikran SALAH',
                    'email'         => 'ikran.salah@intec.edu',
                    'role'          => 'Langage',
                    'subject_codes' => ['LANG', 'PRELEC', 'GRAPH'],
                ],
                [
                    'name'          => 'Maryan ABDILLAHI',
                    'email'         => 'maryan.abdillahi@intec.edu',
                    'role'          => 'Sciences',
                    'subject_codes' => ['MATHS', 'DEC', 'PENSEE'],
                ],
                [
                    'name'          => 'Daoud ISMAIL',
                    'email'         => 'daoud.ismail@intec.edu',
                    'role'          => 'Éveil',
                    'subject_codes' => ['VIE', 'ART', 'MONDE_PS'],
                ],
            ],
        ],

        // MS A - Moyenne Section A
        [
            'code'               => 'MS',
            'section'            => 'A',
            'label'              => 'MS A',
            'subject_class_code' => null,
            'homeroom_name'      => 'Nasrin OMAR',
            'homeroom_email'     => 'nasrin.omar@intec.edu',
            'teachers'           => [
                [
                    'name'          => 'Hodan JAMA',
                    'email'         => 'hodan.jama@intec.edu',
                    'role'          => 'Langage',
                    'subject_codes' => ['LANG', 'PRELEC', 'GRAPH'],
                ],
                [
                    'name'          => 'Safia WAIS',
                    'email'         => 'safia.wais@intec.edu',
                    'role'          => 'Sciences',
                    'subject_codes' => ['MATHS', 'DEC', 'MONDE'],
                ],
                [
                    'name'          => 'Abdi HASHI',
                    'email'         => 'abdi.hashi@intec.edu',
                    'role'          => 'Éveil',
                    'subject_codes' => ['VIE', 'EI', 'ART'],
                ],
            ],
        ],

        // GS A - Grande Section A
        [
            'code'               => 'GS',
            'section'            => 'A',
            'label'              => 'GS A',
            'subject_class_code' => null,
            'homeroom_name'      => 'Farhiya AHMED',
            'homeroom_email'     => 'farhiya.ahmed@intec.edu',
            'teachers'           => [
                [
                    'name'          => 'Shamso ELMI',
                    'email'         => 'shamso.elmi@intec.edu',
                    'role'          => 'Langage',
                    'subject_codes' => ['LANG', 'PRELEC', 'GRAPH'],
                ],
                [
                    'name'          => 'Yusuf HERSI',
                    'email'         => 'yusuf.hersi@intec.edu',
                    'role'          => 'Sciences',
                    'subject_codes' => ['MATHS', 'DEC', 'MONDE'],
                ],
                [
                    'name'          => 'Khadra OSMAN',
                    'email'         => 'khadra.osman@intec.edu',
                    'role'          => 'Éveil',
                    'subject_codes' => ['VIE', 'EI', 'ART'],
                ],
            ],
        ],
    ];

    // ── PRIMAIRE: Each class has EXACTLY 3 teachers ──────────────────────────
    private array $primaireClassrooms = [
        // CP A
        [
            'code'               => 'CP',
            'section'            => 'A',
            'label'              => 'CP A',
            'subject_class_code' => 'CP',
            'homeroom_name'      => 'Hawa IBRAHIM',
            'homeroom_email'     => 'hawa.ibrahim@intec.edu',
            'teachers'           => [
                [
                    'name'          => 'Amina HASSAN',
                    'email'         => 'amina.hassan.cp.a@intec.edu',
                    'role'          => 'Langue',
                    'subject_codes' => ['FR', 'AR_ANG', 'DICT'],
                ],
                [
                    'name'          => 'Yonis OMAR',
                    'email'         => 'yonis.omar.cp.a@intec.edu',
                    'role'          => 'Sciences',
                    'subject_codes' => ['MATHS', 'SCI'],
                ],
                [
                    'name'          => 'Hodan ALI',
                    'email'         => 'hodan.ali.cp.a@intec.edu',
                    'role'          => 'Activités',
                    'subject_codes' => ['EPS', 'TECH'],
                ],
            ],
        ],

        // CP B
        [
            'code'               => 'CP',
            'section'            => 'B',
            'label'              => 'CP B',
            'subject_class_code' => 'CP',
            'homeroom_name'      => 'Safia WARSAME',
            'homeroom_email'     => 'safia.warsame@intec.edu',
            'teachers'           => [
                [
                    'name'          => 'Fadumo AHMED',
                    'email'         => 'fadumo.ahmed.cp.b@intec.edu',
                    'role'          => 'Langue',
                    'subject_codes' => ['FR', 'AR_ANG', 'DICT'],
                ],
                [
                    'name'          => 'Abdi JAMA',
                    'email'         => 'abdi.jama.cp.b@intec.edu',
                    'role'          => 'Sciences',
                    'subject_codes' => ['MATHS', 'SCI'],
                ],
                [
                    'name'          => 'Maryan ELMI',
                    'email'         => 'maryan.elmi.cp.b@intec.edu',
                    'role'          => 'Activités',
                    'subject_codes' => ['EPS', 'TECH'],
                ],
            ],
        ],

        // CE1 A
        [
            'code'               => 'CE1',
            'section'            => 'A',
            'label'              => 'CE1 A',
            'subject_class_code' => 'CE1',
            'homeroom_name'      => 'Aminata SOUMAH',
            'homeroom_email'     => 'aminata.soumah@intec.edu',
            'teachers'           => [
                [
                    'name'          => 'Seydou KOUYATÉ',
                    'email'         => 'seydou.kouyate@intec.edu',
                    'role'          => 'Langue',
                    'subject_codes' => ['FR', 'AR', 'OUTILS'],
                ],
                [
                    'name'          => 'Aminata TRAORÉ',
                    'email'         => 'aminata.traore@intec.edu',
                    'role'          => 'Sciences',
                    'subject_codes' => ['MATHS', 'SCI', 'TECH'],
                ],
                [
                    'name'          => 'Boubacar BARRY',
                    'email'         => 'boubacar.barry@intec.edu',
                    'role'          => 'Humanités',
                    'subject_codes' => ['ANG', 'HIST', 'GEO', 'EPS'],
                ],
            ],
        ],

        // CE1 B
        [
            'code'               => 'CE1',
            'section'            => 'B',
            'label'              => 'CE1 B',
            'subject_class_code' => 'CE1',
            'homeroom_name'      => 'Nasteho YUSUF',
            'homeroom_email'     => 'nasteho.yusuf@intec.edu',
            'teachers'           => [
                [
                    'name'          => 'Ladan HUSSEIN',
                    'email'         => 'ladan.hussein@intec.edu',
                    'role'          => 'Langue',
                    'subject_codes' => ['FR', 'AR', 'OUTILS'],
                ],
                [
                    'name'          => 'Feisal ABDI',
                    'email'         => 'feisal.abdi@intec.edu',
                    'role'          => 'Sciences',
                    'subject_codes' => ['MATHS', 'SCI', 'TECH'],
                ],
                [
                    'name'          => 'Cawo DAHIR',
                    'email'         => 'cawo.dahir@intec.edu',
                    'role'          => 'Humanités',
                    'subject_codes' => ['ANG', 'HIST', 'GEO', 'EPS'],
                ],
            ],
        ],

        // CE2 A
        [
            'code'               => 'CE2',
            'section'            => 'A',
            'label'              => 'CE2 A',
            'subject_class_code' => 'CE2',
            'homeroom_name'      => 'Fatou BAMBA',
            'homeroom_email'     => 'fatou.bamba@intec.edu',
            'teachers'           => [
                [
                    'name'          => 'Khadija OSMAN',
                    'email'         => 'khadija.osman@intec.edu',
                    'role'          => 'Langue',
                    'subject_codes' => ['FR', 'AR', 'OUTILS'],
                ],
                [
                    'name'          => 'Mukhtar SALAH',
                    'email'         => 'mukhtar.salah@intec.edu',
                    'role'          => 'Sciences',
                    'subject_codes' => ['MATHS', 'SCI', 'TECH'],
                ],
                [
                    'name'          => 'Ilyas FARAH',
                    'email'         => 'ilyas.farah@intec.edu',
                    'role'          => 'Humanités',
                    'subject_codes' => ['ANG', 'HIST', 'GEO', 'EPS'],
                ],
            ],
        ],

        // CE2 B
        [
            'code'               => 'CE2',
            'section'            => 'B',
            'label'              => 'CE2 B',
            'subject_class_code' => 'CE2',
            'homeroom_name'      => 'Rashid GULED',
            'homeroom_email'     => 'rashid.guled@intec.edu',
            'teachers'           => [
                [
                    'name'          => 'Saynab NUR',
                    'email'         => 'saynab.nur@intec.edu',
                    'role'          => 'Langue',
                    'subject_codes' => ['FR', 'AR', 'OUTILS'],
                ],
                [
                    'name'          => 'Abdullahi JAMA',
                    'email'         => 'abdullahi.jama@intec.edu',
                    'role'          => 'Sciences',
                    'subject_codes' => ['MATHS', 'SCI', 'TECH'],
                ],
                [
                    'name'          => 'Hibo WARSAME',
                    'email'         => 'hibo.warsame@intec.edu',
                    'role'          => 'Humanités',
                    'subject_codes' => ['ANG', 'HIST', 'GEO', 'EPS'],
                ],
            ],
        ],

        // CM1 A
        [
            'code'               => 'CM1',
            'section'            => 'A',
            'label'              => 'CM1 A',
            'subject_class_code' => 'CM1',
            'homeroom_name'      => 'Amadou DIALLO',
            'homeroom_email'     => 'amadou.diallo@intec.edu',
            'teachers'           => [
                [
                    'name'          => 'Maryam ISMAIL',
                    'email'         => 'maryam.ismail@intec.edu',
                    'role'          => 'Langue',
                    'subject_codes' => ['FR', 'AR_EI', 'OUTILS'],
                ],
                [
                    'name'          => 'Keynan AHMED',
                    'email'         => 'keynan.ahmed@intec.edu',
                    'role'          => 'Sciences',
                    'subject_codes' => ['MATHS', 'SCI', 'TECH'],
                ],
                [
                    'name'          => 'Asad HASSAN',
                    'email'         => 'asad.hassan@intec.edu',
                    'role'          => 'Humanités',
                    'subject_codes' => ['ANG', 'HIST', 'GEO', 'EPS'],
                ],
            ],
        ],

        // CM2 A
        [
            'code'               => 'CM2',
            'section'            => 'A',
            'label'              => 'CM2 A',
            'subject_class_code' => 'CM2',
            'homeroom_name'      => 'Deeqa FARAH',
            'homeroom_email'     => 'deeqa.farah@intec.edu',
            'teachers'           => [
                [
                    'name'          => 'Faadumo ELMI',
                    'email'         => 'faadumo.elmi@intec.edu',
                    'role'          => 'Langue',
                    'subject_codes' => ['FR', 'AR_EI', 'OUTILS'],
                ],
                [
                    'name'          => 'Omar HUSSEIN',
                    'email'         => 'omar.hussein@intec.edu',
                    'role'          => 'Sciences',
                    'subject_codes' => ['MATHS', 'SCI', 'TECH'],
                ],
                [
                    'name'          => 'Asha GULED',
                    'email'         => 'asha.guled@intec.edu',
                    'role'          => 'Humanités',
                    'subject_codes' => ['ANG', 'HIST', 'GEO', 'EPS'],
                ],
            ],
        ],
    ];

    // ─────────────────────────────────────────────────────────────────────────

    public function run(): void
    {
        $this->command->info('');
        $this->command->info('╔═══════════════════════════════════════════════════════════╗');
        $this->command->info('║       FULL SCHOOL SEEDER - 3 Teachers Per Class          ║');
        $this->command->info('╚═══════════════════════════════════════════════════════════╝');

        $year = AcademicYear::where('is_current', true)->first();

        if (!$year) {
            $this->command->error('✗ No current academic year found. Run AcademicYearSeeder first.');
            return;
        }

        $this->command->info("✓ Using academic year: {$year->label}");

        $teacherLog = [];
        $stats = ['classrooms' => 0, 'teachers' => 0, 'students' => 0, 'subjects_assigned' => 0];

        // Seed PRÉSCOLAIRE
        $prescoNiveau = Niveau::where('code', AcademicLevelEnum::PRESCOLAIRE->value)->first();
        if ($prescoNiveau) {
            $this->command->newLine();
            $this->command->info('═══════════════════════════════════════════════════════════');
            $this->command->info('  PRÉSCOLAIRE CLASSROOMS');
            $this->command->info('═══════════════════════════════════════════════════════════');

            foreach ($this->prescoClassrooms as $config) {
                $result = $this->seedClassroom($config, $prescoNiveau, $year, $teacherLog);
                $stats['classrooms']++;
                $stats['teachers'] += $result['teachers'];
                $stats['students'] += $result['students'];
                $stats['subjects_assigned'] += $result['subjects'];
            }
        }

        // Seed PRIMAIRE
        $primaireNiveau = Niveau::where('code', AcademicLevelEnum::PRIMAIRE->value)->first();
        if ($primaireNiveau) {
            $this->command->newLine();
            $this->command->info('═══════════════════════════════════════════════════════════');
            $this->command->info('  PRIMAIRE CLASSROOMS');
            $this->command->info('═══════════════════════════════════════════════════════════');

            foreach ($this->primaireClassrooms as $config) {
                $result = $this->seedClassroom($config, $primaireNiveau, $year, $teacherLog);
                $stats['classrooms']++;
                $stats['teachers'] += $result['teachers'];
                $stats['students'] += $result['students'];
                $stats['subjects_assigned'] += $result['subjects'];
            }
        }

        // Final Summary
        $this->printSummary($teacherLog, $stats);
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function seedClassroom(array $config, Niveau $niveau, AcademicYear $year, array &$teacherLog): array
    {
        $label = $config['label'];
        $stats = ['teachers' => 0, 'students' => 0, 'subjects' => 0];

        $this->command->newLine();
        $this->command->info("  ┌─ {$label} " . str_repeat('─', 50 - strlen($label)));
        $this->command->info("  │  Homeroom: {$config['homeroom_name']}");

        // Create homeroom teacher
        $homeroom = $this->createTeacher($config['homeroom_email'], $config['homeroom_name']);

        // Create/update classroom
        $classroom = Classroom::updateOrCreate(
            [
                'code'             => $config['code'],
                'section'          => $config['section'],
                'academic_year_id' => $year->id,
            ],
            [
                'label'      => $label,
                'teacher_id' => $homeroom->id,
                'niveau_id'  => $niveau->id,
            ]
        );

        // Seed students
        $studentsBefore = Student::where('classroom_id', $classroom->id)->count();
        $this->seedStudents($classroom, $year, $config['code'], $config['section']);
        $studentsAfter = Student::where('classroom_id', $classroom->id)->count();
        $studentsAdded = $studentsAfter - $studentsBefore;
        $stats['students'] = $studentsAfter;

        $this->command->info("  │  Students: {$studentsAfter}" . ($studentsAdded > 0 ? " <fg=green>(+{$studentsAdded})</>" : ''));
        $this->command->info("  │");
        $this->command->info("  │  Teachers (3):");

        // Validate: must have exactly 3 teachers
        if (count($config['teachers']) !== 3) {
            $this->command->error("  │  ✗ ERROR: Class must have exactly 3 teachers! Found: " . count($config['teachers']));
            $this->command->info("  └" . str_repeat('─', 58));
            return $stats;
        }

        $subjectClassCode = $config['subject_class_code'];

        // Attach each of the 3 teachers
        foreach ($config['teachers'] as $idx => $teacherConfig) {
            $teacher = $this->createTeacher($teacherConfig['email'], $teacherConfig['name']);
            $stats['teachers']++;

            // Get subjects for this teacher
            $subjectQuery = Subject::where('niveau_id', $niveau->id)
                ->whereIn('code', $teacherConfig['subject_codes'])
                ->with('competences');

            if ($subjectClassCode === null) {
                $subjectQuery->whereNull('classroom_code');
            } else {
                $subjectQuery->where('classroom_code', $subjectClassCode);
            }

            $subjects = $subjectQuery->get();

            if ($subjects->isEmpty()) {
                $this->command->error(sprintf(
                    '  │  %d. <fg=red>✗</> %-35s [%s] <fg=red>NO SUBJECTS FOUND: %s</>',
                    $idx + 1,
                    $teacher->name,
                    $teacherConfig['role'],
                    implode(', ', $teacherConfig['subject_codes'])
                ));
                continue;
            }

            $subjectNames = [];
            $competenceCount = 0;

            // Attach teacher to subjects and classroom
            foreach ($subjects as $subject) {
                // Attach to subject
                if (!$subject->teachers()->where('users.id', $teacher->id)->exists()) {
                    $subject->teachers()->attach($teacher->id);
                }

                // Attach to classroom
                if (!$classroom->teachers()->where('users.id', $teacher->id)->exists()) {
                    $classroom->teachers()->attach($teacher->id);
                }

                $subjectNames[] = $subject->name;
                $competenceCount += $subject->competences->count();
                $stats['subjects']++;
            }

            $this->command->info(sprintf(
                '  │  %d. <fg=green>✓</> %-35s [%s]',
                $idx + 1,
                $teacher->name,
                $teacherConfig['role']
            ));

            $this->command->info(sprintf(
                '  │     → %d subjects, %d competences: %s',
                $subjects->count(),
                $competenceCount,
                implode(', ', $subjectNames)
            ));

            // Log for summary
            if (!isset($teacherLog[$teacher->name])) {
                $teacherLog[$teacher->name] = [];
            }
            $teacherLog[$teacher->name][$label] = [
                'role' => $teacherConfig['role'],
                'subjects' => $subjectNames,
                'competences' => $competenceCount,
            ];
        }

        $this->command->info("  └" . str_repeat('─', 58));

        return $stats;
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function seedStudents(Classroom $classroom, AcademicYear $year, string $code, string $section): void
    {
        $existing = Student::where('classroom_id', $classroom->id)->count();
        $needed = max(0, 10 - $existing);

        if ($needed === 0) return;

        for ($i = 0; $i < $needed; $i++) {
            $index = $existing + $i + 1;
            $nameData = $this->names[($index - 1) % count($this->names)];
            $matricule = sprintf('INTEC-%s%s-%03d', $code, $section, $index);

            Student::firstOrCreate(
                ['matricule' => $matricule],
                [
                    'first_name'       => $nameData['first_name'],
                    'last_name'        => $nameData['last_name'],
                    'gender'           => $nameData['gender'],
                    'birth_date'       => $this->birthDate($code),
                    'classroom_id'     => $classroom->id,
                    'academic_year_id' => $year->id,
                ]
            );
        }
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function createTeacher(string $email, string $name): User
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name'     => $name,
                'password' => Hash::make('password'),
            ]
        );

        if (!$user->hasRole('teacher')) {
            $user->assignRole('teacher');
        }

        return $user;
    }

    private function birthDate(string $classCode): string
    {
        $baseYear = match ($classCode) {
            'PS'  => 2022, 'MS'  => 2021, 'GS'  => 2020,
            'CP'  => 2019, 'CE1' => 2018, 'CE2' => 2017,
            'CM1' => 2016, 'CM2' => 2015,
            default => 2017,
        };

        return sprintf(
            '%d-%02d-%02d',
            $baseYear + mt_rand(-1, 1),
            mt_rand(1, 12),
            mt_rand(1, 28)
        );
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function printSummary(array $teacherLog, array $stats): void
    {
        $this->command->newLine(2);
        $this->command->info('╔═══════════════════════════════════════════════════════════╗');
        $this->command->info('║                    SEEDING SUMMARY                        ║');
        $this->command->info('╠═══════════════════════════════════════════════════════════╣');
        $this->command->info(sprintf('║  Classrooms:  %-44d║', $stats['classrooms']));
        $this->command->info(sprintf('║  Teachers:    %-44d║', $stats['teachers']));
        $this->command->info(sprintf('║  Students:    %-44d║', $stats['students']));
        $this->command->info(sprintf('║  Subjects:    %-44d║', $stats['subjects_assigned']));
        $this->command->info('╚═══════════════════════════════════════════════════════════╝');

        $this->command->newLine();
        $this->command->info('┌─ TEACHER ASSIGNMENTS (for bulletin grading) ─────────────');

        foreach ($teacherLog as $teacherName => $assignments) {
            foreach ($assignments as $classLabel => $data) {
                $this->command->line(sprintf(
                    '  <comment>%-38s</comment> → <info>%-8s</info> [%s] (%d subjects, %d competences)',
                    $teacherName,
                    $classLabel,
                    $data['role'],
                    count($data['subjects']),
                    $data['competences']
                ));

                $this->command->line(sprintf(
                    '  %s└─ %s',
                    str_repeat(' ', 48),
                    '<fg=gray>' . implode(', ', $data['subjects']) . '</>'
                ));
            }
        }

        $this->command->info('└' . str_repeat('─', 58));
        $this->command->newLine();
        $this->command->info('<fg=green;options=bold>✓ Seeding completed successfully!</>');
        $this->command->info('<fg=yellow>  Each class now has 3 teachers with assigned subjects & competences for bulletin grading.</>');
        $this->command->newLine();
    }
}
