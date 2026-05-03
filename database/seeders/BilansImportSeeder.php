<?php

namespace Database\Seeders;

use App\Enums\BulletinStatusEnum;
use App\Enums\ScaleTypeEnum;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Imports Préscolaire + Primaire data from the two temp databases
 * (intec_tmp_prescolaire / intec_tmp_primaire) into intec_v2.
 *
 * Anomalies fixed:
 *  - Junk domaines '(Sans domaine)', 'Domaines' are excluded
 *  - 'EXPLORER LE ×MONDE' normalised → 'EXPLORER LE MONDE' (merged into one subject for MS-A)
 *  - max_score stored as DECIMAL (subjects.max_score migration already applied)
 *  - Primaire subjects get scale_type='numeric', Préscolaire get 'competence'
 *  - All bulletins published immediately
 *  - gender left NULL (SQL data has no gender field)
 *
 * Run: php artisan db:seed --class=BilansImportSeeder
 */
class BilansImportSeeder extends Seeder
{
    private const PRS = 'intec_tmp_prescolaire';  // short alias
    private const PRM = 'intec_tmp_primaire';

    // Domaines to skip (Excel header/footer artefacts)
    private const SKIP_DOMAINS = ['(Sans domaine)', 'Domaines'];

    // Normalise typos / encoding artefacts in domain names
    private const DOMAIN_NORMALISE = [
        'EXPLORER LE ×MONDE' => 'EXPLORER LE MONDE',
        'Pré lecture'        => 'PRELECTURE',
        'Maths'              => 'LOGICO MATHS',
        'MATHS'              => 'LOGICO MATHS',
    ];

    // ─── State maps ───────────────────────────────────────────────────────────
    private int   $yearId;
    private array $niveauId   = [];  // code → id
    private array $classId    = [];  // code → id   (e.g. 'GS-A' → 7)
    private array $teacherId  = [];  // email → id
    private array $subjectId  = [];  // "niveauCode:domainName" → id
    private array $compId     = [];  // "niveauCode:domainName:label" → id
    private array $studentId  = [];  // "db:tmpStudentId" → id

    // ─── Entry point ──────────────────────────────────────────────────────────

    public function run(): void
    {
        $this->info('Checking temp databases …');
        $dbs = DB::select("SHOW DATABASES LIKE 'intec_tmp_%'");
        if (count($dbs) < 2) {
            $this->error('Temp databases not found. Import the SQL files first.');
            return;
        }

        $this->info('Clearing existing data …');
        $this->clearData();

        $this->info('Creating academic year …');
        $this->createAcademicYear();

        $this->info('Creating niveaux …');
        $this->createNiveaux();

        $this->info('Creating classrooms …');
        $this->createClassrooms();

        $this->info('Creating teachers …');
        $this->createTeachers();

        $this->info('Creating subjects + competences …');
        $this->createPrescolaireSubjects();
        $this->createPrimaireSubjects();

        $this->info('Creating students …');
        $this->createStudents();

        $this->info('Creating bulletins + grades …');
        $this->createPrescolaireBulletins();
        $this->createPrimaireBulletins();

        $this->printSummary();
    }

    // ─── Clear ────────────────────────────────────────────────────────────────

    private function clearData(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach ([
            'bulletin_grades', 'workflow_approvals', 'bulletin_teacher_submissions',
            'bulletins', 'students', 'subject_teacher', 'classroom_teacher',
            'competences', 'subjects', 'classrooms', 'niveaux',
        ] as $t) {
            DB::table($t)->truncate();
        }
        // Remove only teacher users (keep admin/direction/finance/pedagogie)
        $teacherIds = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', 'teacher')
            ->pluck('model_has_roles.model_id');
        DB::table('users')->whereIn('id', $teacherIds)->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    // ─── Academic Year ────────────────────────────────────────────────────────

    private function createAcademicYear(): void
    {
        // Get or update the 2025-2026 academic year, mark as current
        DB::table('academic_years')->updateOrInsert(
            ['label' => '2025-2026'],
            ['start_date' => '2025-09-01', 'end_date' => '2026-06-30', 'is_current' => true,
             'created_at' => now(), 'updated_at' => now()]
        );
        // Mark all others as not current
        DB::table('academic_years')->where('label', '!=', '2025-2026')->update(['is_current' => false]);
        $this->yearId = DB::table('academic_years')->where('label', '2025-2026')->value('id');
    }

    // ─── Niveaux ──────────────────────────────────────────────────────────────

    private function createNiveaux(): void
    {
        $data = [
            ['code' => 'PS',  'label' => 'Petite Section',  'cycle' => 'Préscolaire'],
            ['code' => 'MS',  'label' => 'Moyenne Section', 'cycle' => 'Préscolaire'],
            ['code' => 'GS',  'label' => 'Grande Section',  'cycle' => 'Préscolaire'],
            ['code' => 'CP',  'label' => 'CP',              'cycle' => 'Primaire'],
            ['code' => 'CE1', 'label' => 'CE1',             'cycle' => 'Primaire'],
            ['code' => 'CM1', 'label' => 'CM1',             'cycle' => 'Primaire'],
            ['code' => 'CM2', 'label' => 'CM2',             'cycle' => 'Primaire'],
        ];
        foreach ($data as $d) {
            $id = DB::table('niveaux')->insertGetId(array_merge($d, [
                'created_at' => now(), 'updated_at' => now(),
            ]));
            $this->niveauId[$d['code']] = $id;
        }
    }

    // ─── Classrooms ───────────────────────────────────────────────────────────

    private function createClassrooms(): void
    {
        $rooms = [
            // Préscolaire
            ['code' => 'PS-A',  'label' => 'Petite Section A',  'section' => 'A', 'niveau' => 'PS'],
            ['code' => 'MS-A',  'label' => 'Moyenne Section A', 'section' => 'A', 'niveau' => 'MS'],
            ['code' => 'MS-B',  'label' => 'Moyenne Section B', 'section' => 'B', 'niveau' => 'MS'],
            ['code' => 'GS-A',  'label' => 'Grande Section A',  'section' => 'A', 'niveau' => 'GS'],
            ['code' => 'GS-B',  'label' => 'Grande Section B',  'section' => 'B', 'niveau' => 'GS'],
            // Primaire
            ['code' => 'CP-A',  'label' => 'CP A',  'section' => 'A', 'niveau' => 'CP'],
            ['code' => 'CP-B',  'label' => 'CP B',  'section' => 'B', 'niveau' => 'CP'],
            ['code' => 'CE1-A', 'label' => 'CE1 A', 'section' => 'A', 'niveau' => 'CE1'],
            ['code' => 'CE1-B', 'label' => 'CE1 B', 'section' => 'B', 'niveau' => 'CE1'],
            ['code' => 'CM1-A', 'label' => 'CM1 A', 'section' => 'A', 'niveau' => 'CM1'],
            ['code' => 'CM2-A', 'label' => 'CM2 A', 'section' => 'A', 'niveau' => 'CM2'],
        ];
        foreach ($rooms as $r) {
            $id = DB::table('classrooms')->insertGetId([
                'code'             => $r['code'],
                'label'            => $r['label'],
                'section'          => $r['section'],
                'niveau_id'        => $this->niveauId[$r['niveau']],
                'academic_year_id' => $this->yearId,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
            $this->classId[$r['code']] = $id;
        }
    }

    // ─── Teachers (Préscolaire only — Primaire SQL has no teachers) ───────────

    private function createTeachers(): void
    {
        $teachers = DB::select('SELECT id, nom, email FROM ' . self::PRS . '.teachers');
        $role     = Role::where('name', 'teacher')->first();

        // class_teacher links from prescolaire
        $ctLinks = DB::select('SELECT class_id, teacher_id FROM ' . self::PRS . '.class_teacher');

        // Build a map: tmpTeacherId → prescolaire class section key
        $classLinks = [];
        foreach ($ctLinks as $ct) {
            $classLinks[$ct->teacher_id][] = $ct->class_id;
        }

        // Prescolaire tmp class_id → classroom code
        $tmpClasses = DB::select(
            'SELECT c.id, n.niveau, n.section FROM ' . self::PRS . '.classes c
             JOIN ' . self::PRS . '.niveaux n ON c.niveau_id=n.id'
        );
        $tmpClassCode = [];
        foreach ($tmpClasses as $tc) {
            $nCode = $this->prescolaireNiveauCode($tc->niveau);
            $tmpClassCode[$tc->id] = $nCode . '-' . $tc->section;
        }

        foreach ($teachers as $t) {
            $email = $this->normaliseEmail($t->email);
            $user  = User::updateOrCreate(
                ['email' => $email],
                ['name' => $t->nom, 'password' => Hash::make('password'),
                 'email_verified_at' => now()]
            );
            $user->syncRoles([$role]);
            $this->teacherId[$t->id] = $user->id;

            // Link teacher to classroom(s)
            foreach ($classLinks[$t->id] ?? [] as $tmpCid) {
                $classCode = $tmpClassCode[$tmpCid] ?? null;
                if ($classCode && isset($this->classId[$classCode])) {
                    DB::table('classroom_teacher')->insertOrIgnore([
                        'classroom_id' => $this->classId[$classCode],
                        'teacher_id'   => $user->id,
                    ]);
                }
            }
        }
    }

    // ─── Préscolaire subjects (domaines) ──────────────────────────────────────

    private function createPrescolaireSubjects(): void
    {
        // Load all domaines
        $domaines = DB::select('SELECT id, nom FROM ' . self::PRS . '.domaines');
        $domainMap = [];
        foreach ($domaines as $d) {
            $normalised = $this->normaliseDomain($d->nom);
            if ($normalised === null) continue;  // skip junk
            $domainMap[$d->id] = $normalised;
        }

        // Load competences grouped by (niveau, section, domaine)
        $rows = DB::select(
            'SELECT c.id, c.domaine_id, c.niveau_id, c.label,
                    n.niveau, n.section
             FROM ' . self::PRS . '.competences c
             JOIN ' . self::PRS . '.niveaux n ON c.niveau_id=n.id
             ORDER BY c.domaine_id, n.id, c.id'
        );

        // Group by (classroom_code, normalised_domain_name)
        $groups = [];
        foreach ($rows as $r) {
            if (!isset($domainMap[$r->domaine_id])) continue;
            $nCode   = $this->prescolaireNiveauCode($r->niveau);
            $clCode  = $nCode . '-' . $r->section;
            $domain  = $domainMap[$r->domaine_id];
            $label   = trim($r->label);

            // Skip labels that are the same as the domain name (header rows)
            if (strtolower($label) === strtolower($domain)) continue;
            // Skip date/signature artefacts
            if (preg_match('/^(Date:|Signature|EVA$)/i', $label)) continue;
            // Skip empty
            if (strlen($label) < 3) continue;

            $groupKey = $clCode . '::' . $domain;
            $groups[$groupKey][] = ['tmpId' => $r->id, 'label' => $label, 'clCode' => $clCode, 'domain' => $domain, 'nCode' => $nCode];
        }

        $subjectOrder = 1;
        foreach ($groups as $groupKey => $comps) {
            $first  = $comps[0];
            $clCode = $first['clCode'];
            $domain = $first['domain'];
            $nCode  = $first['nCode'];
            $niveauId = $this->niveauId[$nCode] ?? null;
            if (!$niveauId) continue;

            $subjKey = $nCode . ':' . $clCode . ':' . $domain;
            if (!isset($this->subjectId[$subjKey])) {
                $sid = DB::table('subjects')->insertGetId([
                    'name'           => $domain,
                    'code'           => 'DOM' . str_pad($subjectOrder, 2, '0', STR_PAD_LEFT),
                    'niveau_id'      => $niveauId,
                    'classroom_code' => $clCode,
                    'max_score'      => 0,
                    'scale_type'     => ScaleTypeEnum::COMPETENCE->value,
                    'order'          => $subjectOrder,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
                $this->subjectId[$subjKey] = $sid;
                $subjectOrder++;
            }
            $sid = $this->subjectId[$subjKey];

            // Deduplicate competences by label within this group
            $seenLabels = [];
            $compOrder  = 1;
            foreach ($comps as $comp) {
                $labelKey = strtolower($comp['label']);
                if (isset($seenLabels[$labelKey])) {
                    // Map duplicate to existing competence
                    $this->compId['PRS:' . $comp['tmpId']] = $seenLabels[$labelKey];
                    continue;
                }
                $cid = DB::table('competences')->insertGetId([
                    'subject_id'  => $sid,
                    'code'        => 'C' . $compOrder,
                    'description' => $comp['label'],
                    'max_score'   => null,
                    'period'      => null,
                    'order'       => $compOrder,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
                $seenLabels[$labelKey]            = $cid;
                $this->compId['PRS:' . $comp['tmpId']] = $cid;
                $compOrder++;
            }
        }
    }

    // ─── Primaire subjects (matieres) ─────────────────────────────────────────

    private function createPrimaireSubjects(): void
    {
        $matieres = DB::select(
            'SELECT m.id, m.nom, m.note_max, n.niveau, n.section
             FROM ' . self::PRM . '.matieres m
             JOIN ' . self::PRM . '.niveaux n ON m.niveau_id=n.id
             ORDER BY n.id, m.id'
        );

        $competences = DB::select(
            'SELECT co.id, co.matiere_id, co.label
             FROM ' . self::PRM . '.competences co
             ORDER BY co.matiere_id, co.id'
        );

        $compsByMatiere = [];
        foreach ($competences as $c) {
            $compsByMatiere[$c->matiere_id][] = $c;
        }

        // Determine classroom_code: use NULL for single-section niveaux (CM1, CM2),
        // explicit code for multi-section niveaux (CP, CE1)
        $multiSection = ['CP', 'CE1'];
        $subjectOrder = 100;  // start after Préscolaire

        foreach ($matieres as $m) {
            $nCode   = strtoupper($m->niveau);
            $clCode  = in_array($nCode, $multiSection) ? ($nCode . '-' . $m->section) : null;
            $niveauId = $this->niveauId[$nCode] ?? null;
            if (!$niveauId) continue;

            $subjKey = $nCode . ':' . ($clCode ?? 'ALL') . ':' . $m->nom;
            if (!isset($this->subjectId[$subjKey])) {
                $sid = DB::table('subjects')->insertGetId([
                    'name'           => $m->nom,
                    'code'           => 'MAT' . str_pad($subjectOrder, 2, '0', STR_PAD_LEFT),
                    'niveau_id'      => $niveauId,
                    'classroom_code' => $clCode,
                    'max_score'      => (float)$m->note_max,
                    'scale_type'     => ScaleTypeEnum::NUMERIC->value,
                    'order'          => $subjectOrder,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
                $this->subjectId[$subjKey] = $sid;
                $subjectOrder++;
            }
            $sid = $this->subjectId[$subjKey];

            $compOrder = 1;
            foreach ($compsByMatiere[$m->id] ?? [] as $c) {
                $label = trim($c->label);
                if (strlen($label) < 3) continue;
                $cid = DB::table('competences')->insertGetId([
                    'subject_id'  => $sid,
                    'code'        => 'CB' . $compOrder,
                    'description' => $label,
                    'max_score'   => (int)$m->note_max,
                    'period'      => null,
                    'order'       => $compOrder,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
                $this->compId['PRM:' . $c->id] = $cid;
                $compOrder++;
            }
        }
    }

    // ─── Students ─────────────────────────────────────────────────────────────

    private function createStudents(): void
    {
        $this->createStudentsFromDb(self::PRS, 'PRS', [
            'GS-A' => 'Grande Section:A',
            'GS-B' => 'Grande Section:B',
            'MS-A' => 'Moyenne Section:A',
            'MS-B' => 'Moyenne Section:B',
            'PS-A' => 'Petite Section:A',
        ]);

        $this->createStudentsFromDb(self::PRM, 'PRM', [
            'CE1-A' => 'CE1:A',
            'CE1-B' => 'CE1:B',
            'CM1-A' => 'CM1:A',
            'CM2-A' => 'CM2:A',
            'CP-A'  => 'CP:A',
            'CP-B'  => 'CP:B',
        ]);
    }

    private function createStudentsFromDb(string $db, string $prefix, array $classCodeMap): void
    {
        // Build tmp class_id → classroom_code map
        $tmpClasses = DB::select(
            "SELECT c.id, n.niveau, n.section
             FROM {$db}.classes c JOIN {$db}.niveaux n ON c.niveau_id=n.id"
        );
        $tmpToCode = [];
        foreach ($tmpClasses as $tc) {
            $nCode = strtoupper($tc->niveau);
            $code  = $nCode . '-' . $tc->section;
            // Préscolaire: code like 'Grande Section:A' → mapped key
            foreach ($classCodeMap as $classCode => $niveauSection) {
                [$niveau, $section] = explode(':', $niveauSection);
                if (strtoupper($tc->niveau) === strtoupper($niveau) && $tc->section === $section) {
                    $tmpToCode[$tc->id] = $classCode;
                }
            }
        }

        $students = DB::select("SELECT id, nom, date_naissance, class_id FROM {$db}.students");
        $counters = [];

        foreach ($students as $s) {
            $classCode = $tmpToCode[$s->class_id] ?? null;
            if (!$classCode || !isset($this->classId[$classCode])) continue;

            $counters[$classCode] = ($counters[$classCode] ?? 0) + 1;
            $matricule = strtoupper(str_replace('-', '', $classCode))
                       . '26'
                       . str_pad($counters[$classCode], 3, '0', STR_PAD_LEFT);

            $id = DB::table('students')->insertGetId([
                'full_name'        => trim($s->nom),
                'matricule'        => $matricule,
                'birth_date'       => ($s->date_naissance && $s->date_naissance !== '0000-00-00') ? $s->date_naissance : null,
                'gender'           => null,
                'classroom_id'     => $this->classId[$classCode],
                'academic_year_id' => $this->yearId,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
            $this->studentId[$prefix . ':' . $s->id] = $id;
        }
    }

    // ─── Préscolaire Bulletins ────────────────────────────────────────────────

    private function createPrescolaireBulletins(): void
    {
        // Load all evaluations with trimester number
        $evals = DB::select(
            'SELECT e.id, e.student_id, tr.number as trim_num, e.commentaire
             FROM ' . self::PRS . '.evaluations e
             JOIN ' . self::PRS . '.trimesters tr ON e.trimester_id=tr.id'
        );

        $evalToBulletin = [];  // tmp eval_id → bulletin_id + period

        foreach ($evals as $ev) {
            $period    = 'T' . $ev->trim_num;
            $studentId = $this->studentId['PRS:' . $ev->student_id] ?? null;
            if (!$studentId) continue;

            $classroomId = DB::table('students')->where('id', $studentId)->value('classroom_id');
            $bulletinId  = DB::table('bulletins')->insertGetId([
                'student_id'       => $studentId,
                'classroom_id'     => $classroomId,
                'academic_year_id' => $this->yearId,
                'period'           => $period,
                'status'           => BulletinStatusEnum::PUBLISHED->value,
                'teacher_comment'  => $ev->commentaire,
                'published_at'     => now(),
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
            $evalToBulletin[$ev->id] = ['id' => $bulletinId, 'period' => $period];
        }

        // Load eval_competences in chunks
        $grades = DB::select(
            'SELECT ec.evaluation_id, ec.competence_id, ec.degre
             FROM ' . self::PRS . '.eval_competences ec
             WHERE ec.degre IS NOT NULL'
        );

        $batch = [];
        foreach ($grades as $g) {
            $meta  = $evalToBulletin[$g->evaluation_id] ?? null;
            $compId = $this->compId['PRS:' . $g->competence_id] ?? null;
            if (!$meta || !$compId) continue;

            $batch[] = [
                'bulletin_id'       => $meta['id'],
                'competence_id'     => $compId,
                'period'            => $meta['period'],
                'score'             => null,
                'competence_status' => $g->degre,
                'created_at'        => now(),
                'updated_at'        => now(),
            ];
            if (count($batch) >= 500) {
                DB::table('bulletin_grades')->insertOrIgnore($batch);
                $batch = [];
            }
        }
        if ($batch) DB::table('bulletin_grades')->insertOrIgnore($batch);
    }

    // ─── Primaire Bulletins ───────────────────────────────────────────────────

    private function createPrimaireBulletins(): void
    {
        $evals = DB::select(
            'SELECT e.id, e.student_id, tr.number as trim_num,
                    e.moy_eleve, e.moy_sur, e.moy_classe, e.moy_classe_sur,
                    e.obs_periode1, e.obs_periode2, e.obs_periode3
             FROM ' . self::PRM . '.evaluations e
             JOIN ' . self::PRM . '.trimesters tr ON e.trimester_id=tr.id'
        );

        $evalToBulletin = [];

        foreach ($evals as $ev) {
            $period    = 'T' . $ev->trim_num;
            $studentId = $this->studentId['PRM:' . $ev->student_id] ?? null;
            if (!$studentId) continue;

            // Pick the observation matching this trimester
            $comment = match($ev->trim_num) {
                1 => $ev->obs_periode1,
                2 => $ev->obs_periode2,
                3 => $ev->obs_periode3,
                default => null,
            };

            $classroomId = DB::table('students')->where('id', $studentId)->value('classroom_id');
            $moyenne     = $ev->moy_eleve !== null && $ev->moy_sur
                           ? round((float)$ev->moy_eleve / (float)$ev->moy_sur * 20, 2)
                           : null;
            $classMoyenne = $ev->moy_classe !== null && $ev->moy_classe_sur
                            ? round((float)$ev->moy_classe / (float)$ev->moy_classe_sur * 20, 2)
                            : null;

            $bulletinId = DB::table('bulletins')->insertGetId([
                'student_id'       => $studentId,
                'classroom_id'     => $classroomId,
                'academic_year_id' => $this->yearId,
                'period'           => $period,
                'status'           => BulletinStatusEnum::PUBLISHED->value,
                'total_score'      => $ev->moy_eleve,
                'moyenne'          => $moyenne,
                'class_moyenne'    => $classMoyenne,
                'teacher_comment'  => $comment,
                'published_at'     => now(),
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
            $evalToBulletin[$ev->id] = ['id' => $bulletinId, 'period' => $period, 'trim_num' => $ev->trim_num];
        }

        // cb_entries joined with matching cb_eval_result for this trimester
        $entries = DB::select(
            'SELECT cb.evaluation_id, cb.competence_id, cb.note, cb.note_max,
                    cer.degre, cer.eval_number
             FROM ' . self::PRM . '.cb_entries cb
             LEFT JOIN ' . self::PRM . '.cb_eval_results cer
                ON cer.cb_entry_id=cb.id
             WHERE cer.degre IS NOT NULL OR cb.note IS NOT NULL'
        );

        // Group by (evaluation_id, competence_id) → pick the cer where eval_number matches trimester
        $gradeMap = [];  // "evalId:compId" → grade data
        foreach ($entries as $e) {
            $meta = $evalToBulletin[$e->evaluation_id] ?? null;
            if (!$meta) continue;
            $key = $e->evaluation_id . ':' . $e->competence_id;

            // Only use the cb_eval_result that matches this trimester's number
            if ($e->eval_number !== null && (int)$e->eval_number !== $meta['trim_num']) continue;

            if (!isset($gradeMap[$key])) {
                $gradeMap[$key] = ['evaluation_id' => $e->evaluation_id, 'competence_id' => $e->competence_id,
                                   'note' => $e->note, 'degre' => $e->degre];
            } elseif ($e->eval_number == $meta['trim_num']) {
                // Prefer the entry whose eval_number matches exactly
                $gradeMap[$key]['degre'] = $e->degre;
            }
        }

        // For entries with cb.note but no matching cer, include the score anyway
        $allEntries = DB::select(
            'SELECT cb.evaluation_id, cb.competence_id, cb.note
             FROM ' . self::PRM . '.cb_entries cb
             WHERE cb.note IS NOT NULL'
        );
        foreach ($allEntries as $e) {
            $key = $e->evaluation_id . ':' . $e->competence_id;
            if (!isset($gradeMap[$key])) {
                $gradeMap[$key] = ['evaluation_id' => $e->evaluation_id, 'competence_id' => $e->competence_id,
                                   'note' => $e->note, 'degre' => null];
            } else {
                $gradeMap[$key]['note'] = $e->note;
            }
        }

        $batch = [];
        foreach ($gradeMap as $g) {
            $meta   = $evalToBulletin[$g['evaluation_id']] ?? null;
            $compId = $this->compId['PRM:' . $g['competence_id']] ?? null;
            if (!$meta || !$compId) continue;

            $batch[] = [
                'bulletin_id'       => $meta['id'],
                'competence_id'     => $compId,
                'period'            => $meta['period'],
                'score'             => $g['note'] !== null ? (float)$g['note'] : null,
                'competence_status' => $g['degre'],
                'created_at'        => now(),
                'updated_at'        => now(),
            ];
            if (count($batch) >= 500) {
                DB::table('bulletin_grades')->insertOrIgnore($batch);
                $batch = [];
            }
        }
        if ($batch) DB::table('bulletin_grades')->insertOrIgnore($batch);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function normaliseDomain(string $name): ?string
    {
        if (in_array($name, self::SKIP_DOMAINS)) return null;
        return self::DOMAIN_NORMALISE[$name] ?? $name;
    }

    private function prescolaireNiveauCode(string $niveau): string
    {
        return match (trim($niveau)) {
            'Grande Section'  => 'GS',
            'Moyenne Section' => 'MS',
            'Petite Section'  => 'PS',
            default           => strtoupper(substr($niveau, 0, 2)),
        };
    }

    private function normaliseEmail(string $email): string
    {
        // Replace .local / @school.local with @intec.local
        return preg_replace('/@school\.local$/', '@intec.local', $email);
    }

    private function printSummary(): void
    {
        $counts = [
            'niveaux'     => DB::table('niveaux')->count(),
            'classrooms'  => DB::table('classrooms')->count(),
            'subjects'    => DB::table('subjects')->count(),
            'competences' => DB::table('competences')->count(),
            'students'    => DB::table('students')->count(),
            'bulletins'   => DB::table('bulletins')->count(),
            'grades'      => DB::table('bulletin_grades')->count(),
            'teachers'    => count($this->teacherId),
        ];

        $this->info('');
        $this->info('✅  Import complete');
        foreach ($counts as $label => $count) {
            $this->info("    {$label}: {$count}");
        }
    }

    private function info(string $msg): void
    {
        $this->command?->info($msg);
    }

    private function error(string $msg): void
    {
        $this->command?->error($msg);
    }
}
