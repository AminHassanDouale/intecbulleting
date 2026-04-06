<?php

use App\Models\Student;
use App\Models\Subject;
use App\Models\Bulletin;
use App\Models\BulletinGrade;
use App\Enums\BulletinStatusEnum;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.print')] class extends Component {

    public Student $student;

    public function mount(Student $student): void
    {
        $this->student = $student;
        abort_unless(auth()->check(), 403);
    }

    public function with(): array
    {
        $this->student->load(['classroom.niveau', 'academicYear']);

        $periods       = ['T1', 'T2', 'T3'];
        $classroomCode = $this->student->classroom->code ?? null;

        // ── 1. Academic year ──────────────────────────────────────────────────
        $academicYearId = $this->student->academic_year_id
            ?? \App\Models\AcademicYear::where('is_current', true)->value('id');

        // ── 2. Bulletins (Collection — use ->get($key), never [$key]) ─────────
        $bulletins = Bulletin::where('student_id', $this->student->id)
            ->where('academic_year_id', $academicYearId)
            ->get()
            ->keyBy('period');

        // ── 3. All grades for this student's bulletins ────────────────────────
        $bulletinIds = $bulletins->pluck('id')->filter()->values()->toArray();

        $allGrades = $bulletinIds
            ? BulletinGrade::whereIn('bulletin_id', $bulletinIds)->get()
            : collect();

        $gradesByBulletin = $allGrades->groupBy('bulletin_id');

        // ── 4. Subjects for this niveau + classroom ───────────────────────────
        $subjects = Subject::with(['competences' => fn($q) => $q->orderBy('order')])
            ->where('niveau_id', $this->student->classroom->niveau_id)
            ->where(fn($q) => $q
                ->whereNull('classroom_code')
                ->orWhere('classroom_code', $classroomCode)
            )
            ->orderBy('order')
            ->get();

        // ── 5. Grades map [period][competence_id] => BulletinGrade ───────────
        $gradesMap = [];
        foreach ($periods as $p) {
            $gradesMap[$p] = [];
            $b = $bulletins->get($p);
            if (!$b || $b->status === BulletinStatusEnum::DRAFT) continue;
            $grades = $gradesByBulletin->get($b->id, collect());
            foreach ($grades as $grade) {
                $gradesMap[$p][$grade->competence_id] = $grade;
            }
        }

        // ── 6. Status map + score display ─────────────────────────────────────
        $statusMap    = [];
        $scoreDisplay = [];

        foreach ($periods as $p) {
            $statusMap[$p]    = [];
            $scoreDisplay[$p] = [];

            foreach ($subjects as $subject) {
                foreach ($subject->competences as $competence) {
                    $grade = $gradesMap[$p][$competence->id] ?? null;

                    if (!$grade) {
                        $statusMap[$p][$competence->id]    = null;
                        $scoreDisplay[$p][$competence->id] = '';
                        continue;
                    }

                    if ($grade->competence_status !== null) {
                        $status = is_string($grade->competence_status)
                            ? $grade->competence_status
                            : $grade->competence_status->value;
                        $statusMap[$p][$competence->id]    = $status;
                        $scoreDisplay[$p][$competence->id] = $status;

                    } elseif ($grade->score !== null) {
                        $maxScore = (float) ($competence->max_score ?? $subject->max_score ?? 20);
                        $score    = (float) $grade->score;
                        $ratio    = $maxScore > 0 ? $score / $maxScore : 0;
                        $status   = $ratio >= 0.667 ? 'A' : ($ratio >= 0.333 ? 'EVA' : 'NA');

                        $statusMap[$p][$competence->id]    = $status;
                        $scoreDisplay[$p][$competence->id] = number_format($score, 1) . '/' . (int) $maxScore;
                    } else {
                        $statusMap[$p][$competence->id]    = null;
                        $scoreDisplay[$p][$competence->id] = '';
                    }
                }
            }
        }

        // ── 7. Max total: sum of all subjects' max_score ──────────────────────
        // Each subject contributes its max_score once (not per competence).
        $maxTotal = $subjects->sum(fn($s) => (int) ($s->max_score ?? 0));
        if ($maxTotal === 0) $maxTotal = '—';

        // ── 8. Per-period totals — computed directly from grades ───────────────
        //
        // STRATEGY:
        //   • Each subject has a max_score (e.g. 20 or 10).
        //   • A subject's score = average of its competences' scores, scaled to max_score.
        //     For préscolaire: A=max, EVA=max*0.5, NA=0
        //   • Total = sum of all subject scores.
        //   • Moyenne/10 = (Total / maxTotal) * 10
        //   • class_moyenne: average of all students' moyennes in the same classroom/period.

        $periodTotals = [];

        foreach ($periods as $p) {
            $b         = $bulletins->get($p);
            $isVisible = $b && $b->status !== BulletinStatusEnum::DRAFT;
            $isPublished = $b && $b->status === BulletinStatusEnum::PUBLISHED;

            if (!$isVisible || !$b) {
                $periodTotals[$p] = [
                    'total'             => null,
                    'moyenne'           => null,
                    'class_moyenne'     => null,
                    'teacher_comment'   => null,
                    'direction_comment' => null,
                    'published'         => false,
                ];
                continue;
            }

            // Compute total from grades
            $total          = 0.0;
            $maxTotalFloat  = 0.0;
            $allSubjectsHaveGrades = true;

            foreach ($subjects as $subject) {
                $subjectMax    = (float) ($subject->max_score ?? 0);
                $competences   = $subject->competences;
                $compCount     = $competences->count();

                if ($compCount === 0 || $subjectMax === 0) continue;

                $maxTotalFloat += $subjectMax;
                $isPrescolaire  = $subject->scale_type === 'competence';

                $subjectScore   = 0.0;
                $gradedCount    = 0;

                foreach ($competences as $competence) {
                    $grade = $gradesMap[$p][$competence->id] ?? null;
                    if (!$grade) continue;

                    if ($isPrescolaire && $grade->competence_status !== null) {
                        $status = is_string($grade->competence_status)
                            ? $grade->competence_status
                            : $grade->competence_status->value;
                        $compMax = (float) ($competence->max_score ?? $subjectMax / $compCount);
                        $subjectScore += match ($status) {
                            'A'   => $compMax,
                            'EVA' => $compMax * 0.5,
                            'NA'  => 0,
                            default => 0,
                        };
                        $gradedCount++;
                    } elseif ($grade->score !== null) {
                        // Numeric: scale competence score to subject's contribution
                        $compMax = (float) ($competence->max_score ?? $subjectMax / $compCount);
                        // Ratio of this competence then scale to subject's share of total
                        $ratio = $compMax > 0 ? (float) $grade->score / $compMax : 0;
                        $subjectScore += $ratio * ($subjectMax / $compCount);
                        $gradedCount++;
                    }
                }

                if ($gradedCount === 0) {
                    $allSubjectsHaveGrades = false;
                }

                $total += $subjectScore;
            }

            // Moyenne/10
            $moyenne = ($maxTotalFloat > 0 && $allSubjectsHaveGrades)
                ? round(($total / $maxTotalFloat) * 10, 2)
                : null;

            // Use model total_score if it's set and non-zero (trust the model when available)
            $modelTotal   = $b->total_score ?? null;
            $modelMoyenne = $b->moyenne     ?? null;

            $finalTotal   = ($modelTotal   !== null && $modelTotal   > 0) ? $modelTotal   : ($allSubjectsHaveGrades ? round($total, 2) : null);
            $finalMoyenne = ($modelMoyenne !== null && $modelMoyenne > 0) ? $modelMoyenne : $moyenne;

            // Class moyenne — average of all published/visible bulletins in this classroom
            $classMoyenne = null;
            if ($isVisible) {
                $classBulletins = Bulletin::where('classroom_id', $this->student->classroom_id)
                    ->where('academic_year_id', $academicYearId)
                    ->where('period', $p)
                    ->whereNotIn('status', [BulletinStatusEnum::DRAFT->value])
                    ->get();

                if ($classBulletins->count() > 1) {
                    // Collect moyennes for all students in class for this period
                    $allBulletinIds = $classBulletins->pluck('id')->toArray();
                    $allClassGrades = BulletinGrade::whereIn('bulletin_id', $allBulletinIds)->get()->groupBy('bulletin_id');

                    $classMoyennes = [];
                    foreach ($classBulletins as $cb) {
                        $cbGrades   = $allClassGrades->get($cb->id, collect());
                        $cbTotal    = 0.0;
                        $cbMaxTotal = 0.0;
                        $cbAllGraded = true;

                        foreach ($subjects as $subject) {
                            $subjectMax  = (float) ($subject->max_score ?? 0);
                            $compCount   = $subject->competences->count();
                            if ($compCount === 0 || $subjectMax === 0) continue;

                            $cbMaxTotal    += $subjectMax;
                            $isPrescolaire  = $subject->scale_type === 'competence';
                            $cbSubScore     = 0.0;
                            $cbGraded       = 0;

                            foreach ($subject->competences as $competence) {
                                $g = $cbGrades->firstWhere('competence_id', $competence->id);
                                if (!$g) continue;

                                if ($isPrescolaire && $g->competence_status !== null) {
                                    $st = is_string($g->competence_status) ? $g->competence_status : $g->competence_status->value;
                                    $compMax = (float) ($competence->max_score ?? $subjectMax / $compCount);
                                    $cbSubScore += match($st) { 'A' => $compMax, 'EVA' => $compMax * 0.5, 'NA' => 0, default => 0 };
                                    $cbGraded++;
                                } elseif ($g->score !== null) {
                                    $compMax = (float) ($competence->max_score ?? $subjectMax / $compCount);
                                    $ratio = $compMax > 0 ? (float) $g->score / $compMax : 0;
                                    $cbSubScore += $ratio * ($subjectMax / $compCount);
                                    $cbGraded++;
                                }
                            }

                            if ($cbGraded === 0) $cbAllGraded = false;
                            $cbTotal += $cbSubScore;
                        }

                        if ($cbAllGraded && $cbMaxTotal > 0) {
                            $classMoyennes[] = ($cbTotal / $cbMaxTotal) * 10;
                        }
                    }

                    if (count($classMoyennes) > 0) {
                        $classMoyenne = round(array_sum($classMoyennes) / count($classMoyennes), 2);
                    }
                }

                // Fall back to model class_moyenne if available
                if ($classMoyenne === null && ($b->class_moyenne ?? 0) > 0) {
                    $classMoyenne = $b->class_moyenne;
                }
            }

            $periodTotals[$p] = [
                'total'             => $finalTotal,
                'moyenne'           => $finalMoyenne,
                'class_moyenne'     => $classMoyenne,
                'teacher_comment'   => $b->teacher_comment   ?? null,
                'direction_comment' => $b->direction_comment ?? $b->appreciation ?? null,
                'published'         => $isPublished,
            ];
        }

        $yearLabel = $this->student->academicYear?->label
            ?? (\App\Models\AcademicYear::find($academicYearId)?->label)
            ?? (date('Y') . ' / ' . (date('Y') + 1));

        $romanNumerals = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X'];

        return compact(
            'subjects', 'periods', 'statusMap', 'scoreDisplay', 'gradesMap',
            'periodTotals', 'maxTotal', 'yearLabel', 'romanNumerals', 'bulletins'
        );
    }
}; ?>

@php $isPrescolaire = str_contains(strtoupper($student->classroom->niveau->code ?? ''), 'PRES'); @endphp

<div class="max-w-[210mm] mx-auto bg-white @if(!$isPrescolaire) p-6 @else p-8 @endif">

    {{-- ── Print action bar (hidden when printing) ────────────────────────── --}}
    <div class="no-print flex items-center justify-between mb-5 p-3 bg-indigo-50 rounded-xl border border-indigo-100">
        <div>
            <p class="font-bold text-indigo-800">📋 Carnet d'évaluation — {{ $student->full_name }}</p>
            <p class="text-xs text-indigo-500 mt-0.5">{{ $student->classroom->code ?? '—' }} &bull; {{ $yearLabel }}</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('bulletins.index') }}" class="btn btn-sm btn-ghost">← Retour</a>
            <button onclick="window.print()" class="btn btn-sm btn-primary">🖨 Imprimer</button>
        </div>
    </div>

    {{-- ===== PAGE 1 ===== --}}

    @if($isPrescolaire)
    {{-- ── PRÉSCOLAIRE HEADER — deux logos + titre centré ─────────────────── --}}
    <div class="flex items-start justify-between mb-4">
        {{-- Logo gauche (école) --}}
        <div class="logo-container">
            <img src="{{ asset('images/in tech.jpg') }}" alt="INTEC École Logo">
        </div>

        {{-- Centre --}}
        <div class="flex-1 text-center px-4">
            <div class="text-[11pt] font-bold uppercase tracking-wide">INTEC ÉCOLE</div>
            <div class="text-[9pt] mt-0.5">
                Année scolaire : {{ $yearLabel }}
                &nbsp;&bull;&nbsp;
                Classe : {{ $student->classroom->label ?? $student->classroom->code ?? '—' }}
            </div>
            <div class="text-[12pt] font-extrabold uppercase mt-2 border-b-2 border-black pb-1">
                BILAN DES ACQUISITIONS
            </div>
            <div class="text-[10pt] font-bold mt-1">
                {{ strtoupper($student->full_name) }}
            </div>
            <div class="text-[9pt] mt-0.5 text-gray-600">
                Matricule : {{ $student->matricule }}
                &nbsp;&bull;&nbsp;
                Section : {{ $student->classroom->section ?? '—' }}
            </div>
        </div>

        {{-- Logo droit (ministère / cachet officiel) --}}
        <div class="w-24 h-24 border-2 border-black flex items-center justify-center bg-gray-50 flex-shrink-0">
            <div class="text-center text-[7px] text-gray-400 leading-tight">Cachet<br>Officiel</div>
        </div>
    </div>

    {{-- Info élève --}}
    <table class="w-full border-collapse border border-black mb-3 text-[9px]">
        <tr>
            <td class="border border-black p-1.5 font-semibold w-[33%]">
                <span class="text-gray-500">Enseignant(e) :</span>
                {{ $student->classroom->teacher?->name ?? '—' }}
            </td>
            <td class="border border-black p-1.5 font-semibold w-[33%]">
                <span class="text-gray-500">Date de naissance :</span>
                {{ $student->birth_date?->format('d/m/Y') ?? '—' }}
            </td>
            <td class="border border-black p-1.5 font-semibold w-[34%]">
                <span class="text-gray-500">Niveau :</span>
                {{ $student->classroom->niveau->label ?? $student->classroom->niveau->code ?? '—' }}
            </td>
        </tr>
    </table>

    @else
    {{-- ── PRIMAIRE / AUTRES HEADER — logo gauche, info centre, photo droite ─ --}}
    <div class="flex items-center justify-between mb-4 pb-3 border-b-2 border-black">
        <div class="logo-container">
            <img src="{{ asset('images/in tech.jpg') }}" alt="INTEC École Logo">
        </div>
        <div class="flex-1 text-center px-4">
            <div class="text-base font-bold">CARNET D'ÉVALUATION {{ strtoupper($student->classroom->niveau->code ?? '') }}</div>
            <div class="text-sm font-bold mt-1">INTEC ÉCOLE</div>
            <div class="text-[10px] mt-1">Année scolaire : {{ $yearLabel }}</div>
            <div class="text-[10px] mt-1 font-semibold">
                Élève : {{ $student->full_name }}
                &bull; Classe : {{ $student->classroom->label ?? $student->classroom->code ?? '—' }}
            </div>
            <div class="text-[10px] mt-0.5 text-gray-500">Matricule : {{ $student->matricule }}</div>
        </div>
        <div class="w-24 h-24 border-2 border-black flex items-center justify-center bg-gray-50 flex-shrink-0">
            <div class="text-center text-[8px] text-gray-400">Photo<br>Élève</div>
        </div>
    </div>
    @endif

    {{-- Subject tables --}}
    @foreach($subjects as $idx => $subject)
    <div class="font-bold text-[11pt] mb-2">
        {{ $romanNumerals[$idx] ?? ($idx + 1) }} –
        COMPÉTENCE DE BASE : {{ strtoupper($subject->name) }}
        @if($subject->max_score) : /{{ $subject->max_score }} @endif
    </div>

    <table class="w-full border-collapse border border-black mb-3 text-[10px]">
        <thead>
            <tr>
                <th rowspan="2" class="border border-black bg-gray-200 p-1.5 text-center font-bold w-[38%]">Compétence</th>
                <th colspan="3" class="border border-black bg-gray-200 p-1.5 text-center font-bold">PERIODE 1</th>
                <th colspan="3" class="border border-black bg-gray-200 p-1.5 text-center font-bold">PERIODE 2</th>
                <th colspan="3" class="border border-black bg-gray-200 p-1.5 text-center font-bold">PERIODE 3</th>
            </tr>
            <tr>
                @foreach(['T1','T2','T3'] as $_p)
                <th class="border border-black bg-gray-200 p-1 text-center font-bold">A</th>
                <th class="border border-black bg-gray-200 p-1 text-center font-bold">EVA</th>
                <th class="border border-black bg-gray-200 p-1 text-center font-bold">NA</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($subject->competences as $competence)
            <tr>
                <td class="border border-black bg-gray-50 p-1.5 text-left">
                    <strong>{{ $competence->code }}</strong>
                    @if($competence->description) : {{ $competence->description }} @endif
                    @if($competence->max_score && $subject->scale_type !== 'competence')
                        <strong>/{{ $competence->max_score }}</strong>
                    @endif
                </td>
                @foreach(['T1','T2','T3'] as $_p)
                @php
                    $st      = $statusMap[$_p][$competence->id] ?? null;
                    $display = $scoreDisplay[$_p][$competence->id] ?? '';
                @endphp
                <td class="border border-black p-1 text-center font-bold text-green-700 w-[6.89%]">{{ $st === 'A'   ? $display : '' }}</td>
                <td class="border border-black p-1 text-center font-bold text-amber-600 w-[6.89%]">{{ $st === 'EVA' ? $display : '' }}</td>
                <td class="border border-black p-1 text-center font-bold text-red-600   w-[6.89%]">{{ $st === 'NA'  ? $display : '' }}</td>
                @endforeach
            </tr>
            @empty
            <tr>
                <td colspan="10" class="border border-black p-2 text-center text-gray-400 italic">Aucune compétence définie.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
    @endforeach

    {{-- Totals --}}
    <table class="w-full border-collapse border border-black mb-3 text-[10px]">
        <thead>
            <tr class="bg-gray-200">
                <th class="border border-black p-1.5 text-left font-bold w-[38%]">Total sur {{ $maxTotal }}</th>
                @foreach(['T1','T2','T3'] as $_p)
                <th class="border border-black p-1 text-center w-[20.67%]">
                    {{ $periodTotals[$_p]['total'] !== null ? number_format($periodTotals[$_p]['total'], 1) : '' }}
                </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="border border-black p-1.5 text-left font-bold">Moyenne sur 10</td>
                @foreach(['T1','T2','T3'] as $_p)
                <td class="border border-black p-1 text-center font-bold">
                    {{ $periodTotals[$_p]['moyenne'] !== null ? number_format($periodTotals[$_p]['moyenne'], 2) : '' }}
                </td>
                @endforeach
            </tr>
            <tr>
                <td class="border border-black p-1.5 text-left font-bold">Moyenne de la classe sur 10</td>
                @foreach(['T1','T2','T3'] as $_p)
                <td class="border border-black p-1 text-center">
                    {{ $periodTotals[$_p]['class_moyenne'] !== null ? number_format($periodTotals[$_p]['class_moyenne'], 2) : '' }}
                </td>
                @endforeach
            </tr>
        </tbody>
    </table>

    {{-- Legend --}}
    <table class="w-full border-collapse border border-black mb-4 text-[9px]">
        <thead>
            <tr class="bg-gray-200">
                <th class="border border-black p-1.5 text-left w-[31%] font-bold">Degré de maîtrise de la compétence de base.</th>
                <th class="border border-black p-1.5 text-center w-[23%] font-bold">Acquis<br>A</th>
                <th class="border border-black p-1.5 text-center w-[23%] font-bold">En voie d'acquisition<br>EVA</th>
                <th class="border border-black p-1.5 text-center w-[23%] font-bold">Non acquis<br>NA</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="border border-black p-1.5 font-bold">Appréciations</td>
                <td class="border border-black p-1.5 text-center font-bold">(Très) satisfaisant</td>
                <td class="border border-black p-1.5 text-center font-bold">Moyen</td>
                <td class="border border-black p-1.5 text-center font-bold">Insuffisant</td>
            </tr>
            <tr>
                <td class="border border-black p-1.5 font-bold">Seuil de maîtrise.</td>
                <td class="border border-black p-1.5 text-center font-bold">Plus de 2/3</td>
                <td class="border border-black p-1.5 text-center font-bold">Entre 1/3 et 2/3</td>
                <td class="border border-black p-1.5 text-center font-bold">Moins de 1/3</td>
            </tr>
            <tr>
                <td class="border border-black p-1.5 font-bold">Notes correspondantes.</td>
                <td class="border border-black p-1.5 text-center font-bold">7 à 10</td>
                <td class="border border-black p-1.5 text-center font-bold">4 à 6</td>
                <td class="border border-black p-1.5 text-center font-bold">0 à 3</td>
            </tr>
        </tbody>
    </table>

    {{-- ===== PAGE 2 ===== --}}
    <div class="page-break"></div>

    <div class="text-center text-base font-bold mb-1">ÉCOLE PRIVÉE INTEC</div>
    <div class="text-center text-sm font-bold mb-1">CARNET D'ÉVALUATION {{ strtoupper($student->classroom->niveau->code ?? '') }}</div>
    <div class="font-bold text-[11pt] text-center mb-3">ANNEE SCOLAIRE : {{ $yearLabel }}</div>
    <div class="text-center text-[10px] mb-3 text-gray-600">
        Élève : <strong>{{ $student->full_name }}</strong>
        &bull; Classe : <strong>{{ $student->classroom->code ?? '—' }}</strong>
        &bull; Matricule : <strong>{{ $student->matricule }}</strong>
    </div>

    {{-- Appréciations générales --}}
    <div class="font-bold text-[11pt] mb-2">APPRÉCIATIONS GÉNÉRALES</div>
    <table class="w-full border-collapse border border-black mb-3 text-[10px]">
        <thead>
            <tr>
                <th class="border border-black p-2 text-center w-[16%] font-bold">Périodes</th>
                <th class="border border-black p-2 text-center w-[44%] font-bold">Observations</th>
                <th class="border border-black p-2 text-center w-[22%] font-bold">Signature de la Direction</th>
                <th class="border border-black p-2 text-center w-[18%] font-bold">Signature des parents</th>
            </tr>
        </thead>
        <tbody>
            @foreach([['T1','1<sup>ère</sup>','Période'],['T2','2<sup>ème</sup>','Période'],['T3','3<sup>ème</sup>','Période']] as [$p,$num,$lbl])
            <tr>
                <td class="border border-black p-2 text-center align-top"><strong>{!! $num !!}</strong><br><strong>{{ $lbl }}</strong></td>
                <td class="border border-black p-2 text-left align-top" style="min-height:80px">
                    @if($periodTotals[$p]['teacher_comment'])
                        <div class="mb-4">{{ $periodTotals[$p]['teacher_comment'] }}</div>
                    @else
                        <div class="mb-8"></div>
                    @endif
                    <div class="text-gray-500">L'Enseignant(e)</div>
                </td>
                <td class="border border-black p-2 text-left align-top text-[9px] text-gray-600">
                    {{ $periodTotals[$p]['direction_comment'] ?? '' }}
                </td>
                <td class="border border-black p-2"></td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Classement --}}
    <div class="font-bold text-[11pt] mb-2">CLASSEMENT</div>
    <table class="w-full border-collapse border border-black mb-3 text-[10px]">
        <thead>
            <tr class="bg-gray-200">
                <th class="border border-black p-1.5 w-[38%] font-bold text-left">Période</th>
                <th class="border border-black p-1.5 text-center font-bold">Période 1</th>
                <th class="border border-black p-1.5 text-center font-bold">Période 2</th>
                <th class="border border-black p-1.5 text-center font-bold">Période 3</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="border border-black p-1.5 font-bold">Mention</td>
                @foreach(['T1','T2','T3'] as $_p)
                @php
                    $moy     = $periodTotals[$_p]['moyenne'];
                    $mention = '';
                    if ($moy !== null) {
                        if ($moy >= 9)     $mention = 'Très Bien';
                        elseif ($moy >= 7) $mention = 'Bien';
                        elseif ($moy >= 5) $mention = 'Assez Bien';
                        elseif ($moy >= 3) $mention = 'Passable';
                        else               $mention = 'Insuffisant';
                    }
                @endphp
                <td class="border border-black p-1.5 text-center font-bold
                    {{ $moy !== null ? ($moy >= 7 ? 'text-green-700' : ($moy >= 5 ? 'text-amber-700' : 'text-red-600')) : '' }}">
                    {{ $mention }}
                </td>
                @endforeach
            </tr>
        </tbody>
    </table>

    {{-- Décision fin d'année --}}
    <div class="font-bold text-[11pt] mb-2"><u>Décision de fin d'année :</u></div>
    <table class="w-full border-collapse border border-black mb-4 text-[10px]">
        <thead>
            <tr>
                <th class="border border-black p-2 text-left w-[60%] font-bold">Décision de fin d'année en conseil des maîtres</th>
                <th class="border border-black p-2 text-center w-[40%]"></th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="border border-black p-4 text-left align-top leading-8">
                    Admis(e) en classe supérieure<br>Reprend la classe
                </td>
                <td class="border border-black p-4 text-center align-middle text-gray-500">
                    Cachet de l'école<br><br>Du ….………………
                </td>
            </tr>
        </tbody>
    </table>

</div>
