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
                        $scoreDisplay[$p][$competence->id] = rtrim(rtrim(number_format($score, 2, '.', ''), '0'), '.') . '/' . (int) $maxScore;
                    } else {
                        $statusMap[$p][$competence->id]    = null;
                        $scoreDisplay[$p][$competence->id] = '';
                    }
                }
            }
        }

        // ── 7. Max total: sum of all subjects' max_score ──────────────────────
        $maxTotal = $subjects->sum(fn($s) => (int) ($s->max_score ?? 0));
        if ($maxTotal === 0) $maxTotal = '—';

        // ── 8. Per-period totals ───────────────────────────────────────────────
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
                        $compMax = (float) ($competence->max_score ?? $subjectMax / $compCount);
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

            $moyenne = ($maxTotalFloat > 0 && $allSubjectsHaveGrades)
                ? round(($total / $maxTotalFloat) * 10, 2)
                : null;

            $modelTotal   = $b->total_score ?? null;
            $modelMoyenne = $b->moyenne     ?? null;

            $finalTotal   = ($modelTotal   !== null && $modelTotal   > 0) ? $modelTotal   : ($allSubjectsHaveGrades ? round($total, 2) : null);
            $finalMoyenne = ($modelMoyenne !== null && $modelMoyenne > 0) ? $modelMoyenne : $moyenne;

            $classMoyenne = null;
            if ($isVisible) {
                $classBulletins = Bulletin::where('classroom_id', $this->student->classroom_id)
                    ->where('academic_year_id', $academicYearId)
                    ->where('period', $p)
                    ->whereNotIn('status', [BulletinStatusEnum::DRAFT->value])
                    ->get();

                if ($classBulletins->count() > 1) {
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

@php
$isPrescolaire = mb_stripos($student->classroom->niveau->cycle ?? '', 'scolaire') !== false;
$classCode     = $student->classroom->code ?? '—';
$classSection  = $student->classroom->section ?? '';
$niveauLabel   = $student->classroom->niveau->code ?? '';
@endphp

<div>
@if($isPrescolaire)
{{-- ══════════════════════════════════════════════════════════════
     PRÉSCOLAIRE — Format A4 paysage recto-verso (1 feuille)
     RECTO : Couverture (gauche) + Compétences (droite)
     VERSO  : Introduction (gauche) + Commentaires & Signatures (droite)
     Impression : Paysage A4, recto-verso bord long
     ══════════════════════════════════════════════════════════════ --}}
@php
$_psList = ['T3','T2','T1'];
$_dpPres = null;
foreach ($_psList as $_tryP) {
    $b = $bulletins->get($_tryP);
    if ($b && $b->status === \App\Enums\BulletinStatusEnum::PUBLISHED) { $_dpPres = $_tryP; break; }
}
if (!$_dpPres) foreach ($_psList as $_tryP) { if ($bulletins->has($_tryP)) { $_dpPres = $_tryP; break; } }
if (!$_dpPres) $_dpPres = 'T1';

$_dpLabel     = match($_dpPres) { 'T1'=>'1er Trimestre','T2'=>'2ème Trimestre','T3'=>'3ème Trimestre',default=>$_dpPres };
$_dpDateRange = match($_dpPres) { 'T1'=>'SEPTEMBRE – NOVEMBRE','T2'=>'DÉCEMBRE – FÉVRIER','T3'=>'MARS – JUIN',default=>'' };
$_dpTeacherComment   = $periodTotals[$_dpPres]['teacher_comment']   ?? '';
$_dpDirectionComment = $periodTotals[$_dpPres]['direction_comment'] ?? '';
$_dpBulletin  = $bulletins->get($_dpPres);
$_dpDate      = $_dpBulletin?->updated_at?->format('d / m / Y') ?? date('d / m / Y');
$_psNiveauLabel = $student->classroom->niveau->label ?? ($student->classroom->niveau->code ?? '');
$_psTeacherName = $student->classroom->teacher?->name ?? '—';
@endphp

<style>
@page { size: A4 landscape; margin: 0; }
@media print { body{background:#fff;} .ps-wrap{padding:0;gap:0;} .ps-no-print{display:none!important;} }

*,*::before,*::after{box-sizing:border-box;}
.ps-wrap  {display:flex;flex-direction:column;align-items:center;gap:8mm;padding:8mm;}
.ps-page  {width:297mm;height:210mm;display:flex;flex-direction:row;background:#fff;
           page-break-after:always;overflow:hidden;font-size:7.5pt;color:#000;
           font-family:'Calibri',Arial,sans-serif;box-shadow:0 4px 18px rgba(0,0,0,.4);}
.ps-panel {width:148.5mm;height:210mm;padding:5mm 6mm;overflow:hidden;position:relative;
           border:4px solid #6B5000;}
.ps-left  {border-right:2px solid #6B5000;}
.ps-right {border-left:2px solid #6B5000;}

.ps-logo  {background:#1a5c00;display:inline-block;}
.ps-logo-t{display:flex;align-items:stretch;}
.ps-li    {background:#ED7D31;color:#fff;font-size:11pt;font-weight:900;font-style:italic;padding:1.5mm 2mm;display:flex;align-items:center;}
.ps-lt    {color:#fff;font-size:11pt;font-weight:900;padding:1.5mm 3mm;display:flex;align-items:center;letter-spacing:2px;}
.ps-lb    {color:#fff;font-size:7.5pt;font-weight:700;letter-spacing:4px;padding:1mm 2.5mm 1.5mm;text-align:center;}

.ps-cover  {display:flex;flex-direction:column;align-items:center;height:100%;}
.ps-sch-row{display:flex;align-items:center;gap:3mm;width:100%;margin-bottom:3mm;}
.ps-sch-blk{flex:1;border-left:2.5px solid #6B5000;padding-left:3mm;}
.ps-sch-nm {font-size:12pt;font-weight:700;font-style:italic;line-height:1.1;}
.ps-sch-tag{font-size:7pt;font-style:italic;color:#444;}
.ps-sch-ct {font-size:6.5pt;color:#444;margin-top:0.5mm;}
.ps-btitle {font-size:9pt;font-weight:900;text-transform:uppercase;text-decoration:underline;margin:1.5mm 0 0.5mm;}
.ps-bperiod{font-size:8pt;font-weight:700;margin-bottom:2mm;}
.ps-gs-ttl {font-size:16pt;font-weight:900;font-family:'Palatino Linotype','Book Antiqua',Palatino,serif;
            text-transform:uppercase;letter-spacing:2px;text-align:center;margin:2mm 0;}
.ps-cbox   {border:2px solid #6B5000;border-radius:8px;width:100%;height:42mm;
            display:flex;align-items:center;justify-content:center;
            background:linear-gradient(135deg,#e8f4f0,#d4e8f5,#f5e8d4,#e8f0d4);margin-bottom:2.5mm;}
.ps-info   {border:2px solid #6B5000;border-radius:8px;padding:2.5mm 3.5mm;width:100%;font-size:8pt;line-height:1.75;background:#fff;}
.ps-ir     {display:flex;}
.ps-il     {min-width:38mm;}
.ps-iv     {font-weight:700;}

.ps-intro  {font-size:7pt;line-height:1.55;text-align:justify;margin-bottom:1.5mm;}
.ps-ibold  {font-weight:700;text-decoration:underline;}
.ps-leg-t  {border-collapse:collapse;margin:2mm 0;font-size:7.5pt;width:90%;}
.ps-leg-t td{border:1px solid #000;padding:1mm 3mm;}
.ps-leg-t td:first-child{font-weight:900;text-align:center;width:12mm;}
.ps-cm-lbl {font-size:8pt;font-weight:700;margin-top:3mm;margin-bottom:1.5mm;border-top:1.5px solid #6B5000;padding-top:2mm;}
.ps-cm-box {border:1px solid #6B5000;border-radius:4px;padding:2mm 3mm;min-height:18mm;font-size:7.5pt;font-style:italic;background:#fffdf8;margin-bottom:3mm;}
.ps-sig-t  {width:100%;border-collapse:collapse;font-size:7.5pt;}
.ps-sig-t td,.ps-sig-t th{border:1px solid #000;padding:1.2mm 2mm;vertical-align:middle;}
.ps-sig-dr td{background:#FFC000;font-weight:700;text-decoration:underline;font-size:8pt;padding:1.2mm 2mm;}
.ps-sig-hr th{background:#FCE9D9;font-weight:700;text-align:center;width:33.3%;font-size:7.5pt;}
.ps-sig-br td{background:#FCE9D9;height:22mm;width:33.3%;}

.ps-comp   {width:100%;border-collapse:collapse;font-size:6.5pt;}
.ps-comp th,.ps-comp td{border:1px solid #000;vertical-align:middle;padding:0.7mm 1.2mm;}
.ps-hd     {background:#C2D59B;font-weight:700;text-align:center;font-size:7pt;}
.ps-sub-h  {background:#C2D59B;font-weight:700;text-align:center;font-size:6.5pt;padding:0.5mm;}
.ps-sep    {background:#FFC000;height:3px;padding:0;border:1px solid #6B5000;}
.ps-dom-c  {font-weight:900;text-align:center;vertical-align:middle;font-size:6.5pt;text-transform:uppercase;word-break:break-word;}
.ps-comp-c {font-size:6.5pt;}
.ps-score  {text-align:center;font-weight:700;font-size:7pt;}
</style>

<div class="ps-wrap">

  <div class="ps-no-print" style="max-width:297mm;background:#fffbe6;border:1px solid #d4a900;border-radius:4px;padding:4mm 7mm;font-size:9pt;color:#444;font-family:Arial,sans-serif;margin-bottom:5mm;display:flex;justify-content:space-between;align-items:center;">
    <span><strong>Instructions :</strong> Chrome → Imprimer → <strong>Paysage A4</strong> → <strong>Recto-verso bord long</strong>. Une seule feuille imprimée des deux côtés.</span>
    <span style="display:flex;gap:6px;">
      <a href="{{ route('bulletins.index') }}" style="padding:3px 10px;border:1px solid #aaa;border-radius:4px;color:#333;text-decoration:none;">← Retour</a>
      <button onclick="window.print()" style="background:#1e40af;color:#fff;border:none;border-radius:4px;padding:3px 10px;cursor:pointer;">🖨 Imprimer</button>
    </span>
  </div>

  {{-- ═══ RECTO — Couverture (gauche) + Compétences (droite) ═══ --}}
  <div class="ps-page">

    {{-- LEFT: COUVERTURE --}}
    <div class="ps-panel ps-left">
      <div class="ps-cover">
        <div class="ps-sch-row">
          <img src="{{ asset('images/in tech.jpg') }}" alt="INTEC"
               style="width:22mm;height:22mm;object-fit:contain;flex-shrink:0;border-radius:3px;">
          <div class="ps-sch-blk">
            <div class="ps-sch-nm">École internationale</div>
            <div class="ps-sch-tag"><em>pour</em> les langues et les technologies</div>
            <div class="ps-sch-ct">☎ 77 08 79 79 | 77 05 78 78<br>✉ intec.ecole.djibouti@gmail.com</div>
          </div>
        </div>

        <div class="ps-btitle">BILAN DES ACQUISITIONS</div>
        <div class="ps-bperiod">{{ $_dpDateRange }} {{ $yearLabel }}</div>
        <div class="ps-gs-ttl">{{ strtoupper($_psNiveauLabel) }}</div>

        <div class="ps-cbox">
          <img src="{{ asset('images/in tech.jpg') }}" alt="INTEC"
               style="width:36mm;height:36mm;object-fit:contain;border-radius:5px;">
        </div>

        <div class="ps-info">
          <div class="ps-ir"><span class="ps-il">Nom :</span><span class="ps-iv">{{ strtoupper($student->full_name) }}</span></div>
          <div class="ps-ir"><span class="ps-il">Date de naissance :</span><span class="ps-iv">{{ $student->birth_date?->format('d/m/Y') ?? '—' }}</span></div>
          <div class="ps-ir"><span class="ps-il">Classe :</span><span class="ps-iv">{{ $classCode }}</span></div>
          <div class="ps-ir"><span class="ps-il">Enseignant(e) :</span><span class="ps-iv" style="font-weight:400;">{{ $_psTeacherName }}</span></div>
        </div>
      </div>
    </div>

    {{-- RIGHT: TABLEAU DES COMPÉTENCES --}}
    <div class="ps-panel ps-right">
      <div style="display:flex;justify-content:space-between;align-items:baseline;border-bottom:1.5px solid #6B5000;padding-bottom:1mm;margin-bottom:2mm;">
        <span style="font-size:7.5pt;font-weight:700;text-transform:uppercase;">Tableau des compétences</span>
        <span style="font-size:7pt;color:#555;">{{ $_dpLabel }}</span>
      </div>
      <table class="ps-comp">
        <thead>
          <tr>
            <th class="ps-hd" style="width:15%;" rowspan="2">Domaines</th>
            <th class="ps-hd" style="width:59%;" rowspan="2">Compétences</th>
            <th class="ps-hd" style="width:26%;" colspan="3">Degré d'acquisition</th>
          </tr>
          <tr>
            <th class="ps-sub-h" style="width:9%;">A</th>
            <th class="ps-sub-h" style="width:9%;">EVA</th>
            <th class="ps-sub-h" style="width:8%;">NA</th>
          </tr>
        </thead>
        <tbody>
          @foreach($subjects as $subIdx => $subject)
          @php $comps = $subject->competences; @endphp
          @if($subIdx > 0)
          <tr><td colspan="5" class="ps-sep"></td></tr>
          <tr><td></td><td></td><th class="ps-sub-h">A</th><th class="ps-sub-h">EVA</th><th class="ps-sub-h">NA</th></tr>
          @endif
          @forelse($comps as $cIdx => $competence)
          @php $st = $statusMap[$_dpPres][$competence->id] ?? null; @endphp
          <tr>
            @if($cIdx === 0)
            <td class="ps-dom-c" rowspan="{{ $comps->count() }}"><strong>{{ strtoupper($subject->name) }}</strong></td>
            @endif
            <td class="ps-comp-c">{{ $competence->description ?? $competence->code }}</td>
            <td class="ps-score">{{ $st === 'A'   ? 'A'   : '' }}</td>
            <td class="ps-score">{{ $st === 'EVA' ? 'EVA' : '' }}</td>
            <td class="ps-score">{{ $st === 'NA'  ? 'NA'  : '' }}</td>
          </tr>
          @empty
          <tr>
            <td class="ps-dom-c"><strong>{{ strtoupper($subject->name) }}</strong></td>
            <td colspan="4" style="font-style:italic;color:#999;text-align:center;font-size:6.5pt;">Aucune compétence.</td>
          </tr>
          @endforelse
          @endforeach
        </tbody>
      </table>
    </div>

  </div>{{-- /RECTO --}}

  {{-- ═══ VERSO — Introduction (gauche) + Commentaires & Signatures (droite) ═══ --}}
  <div class="ps-page">

    {{-- LEFT: INTRODUCTION PÉDAGOGIQUE --}}
    <div class="ps-panel ps-left" style="display:flex;flex-direction:column;">

      <div style="text-align:center;margin-bottom:2mm;">
        <img src="{{ asset('images/in tech.jpg') }}" alt="INTEC"
             style="width:18mm;height:18mm;object-fit:contain;border-radius:3px;">
      </div>

      <div style="text-align:center;font-size:8pt;font-weight:700;text-transform:uppercase;border-bottom:2px solid #6B5000;padding-bottom:1.5mm;margin-bottom:2mm;">
        Bilan des Acquisitions — {{ $_psNiveauLabel }} — {{ $yearLabel }}
      </div>

      <p class="ps-intro">Outil de régulation pour les activités d'enseignement-apprentissages, l'évaluation se doit de reposer sur une observation attentive de ce que chaque enfant dit ou fait. Ce qui importe alors à l'éducateur va bien au-delà du résultat obtenu, il se fixe plutôt sur le cheminement de l'enfant et les progrès qu'il réalise par rapport à lui-même et non par rapport à ses camarades ou à une quelconque norme.</p>
      <p class="ps-intro">Une évaluation positive c'est donc celle qui permet à chacun enfant d'identifier ses propres réussites, d'en garder des traces, de percevoir son évolution personnelle.</p>
      <p class="ps-intro">De ce fait, au préscolaire, le suivi des apprentissages des enfants se fait à travers deux outils :</p>
      <p class="ps-intro">— <span class="ps-ibold">Le carnet de suivi des apprentissages</span> : recueil d'observations complété tout au long des apprentissages à travers des évaluations écrites, des productions réussies, des grilles d'observation, des photos, des commentaires écrits…</p>
      <p class="ps-intro">— <span class="ps-ibold">Le bilan des compétences</span> : établi à la fin de chaque période, communiqué aux parents quatre (4) fois dans l'année. Les parents le signent pour attester réception.</p>
      <p class="ps-intro">Le positionnement par rapport aux acquis des enfants se fait sur une échelle à trois (3) niveaux : A, EVA et NA — réussit souvent, est en voie de réussite, ne réussit pas encore.</p>

      <table class="ps-leg-t" style="margin:2mm 0;">
        <tr><td><strong>A</strong></td><td>Acquis, l'enfant réussit souvent</td></tr>
        <tr><td><strong>EVA</strong></td><td>En voie d'acquisition, réussit parfois ou avec aide</td></tr>
        <tr><td><strong>NA</strong></td><td>Non acquis encore</td></tr>
      </table>

      <p class="ps-intro">L'éducation préscolaire est une éducation bienveillante — tous les enfants sont capables d'apprendre et de progresser. Apprendre quelque chose de nouveau, c'est aussi se tromper et ne pas réussir dès le premier essai ; c'est essayer encore et encore jusqu'à réussir, ce qui lui permettra de progresser et d'avoir confiance en lui.</p>
    </div>

    {{-- RIGHT: COMMENTAIRES & SIGNATURES --}}
    <div class="ps-panel ps-right" style="display:flex;flex-direction:column;">

      <div style="display:flex;justify-content:space-between;align-items:baseline;border-bottom:1.5px solid #6B5000;padding-bottom:1.5mm;margin-bottom:3mm;">
        <span style="font-size:8pt;font-weight:700;text-transform:uppercase;">Observations &amp; Signatures</span>
        <span style="font-size:7.5pt;font-weight:700;">{{ $_psNiveauLabel }} &nbsp;|&nbsp; {{ $yearLabel }}</span>
      </div>

      <p class="ps-cm-lbl" style="margin-top:0;border-top:none;padding-top:0;">Commentaires de l'enseignant(e) :</p>
      <div class="ps-cm-box">{{ $_dpTeacherComment ?: '' }}</div>

      @if($_dpDirectionComment)
      <p class="ps-cm-lbl">Observation de la Direction :</p>
      <div class="ps-cm-box">{{ $_dpDirectionComment }}</div>
      @else
      <p class="ps-cm-lbl">Observation de la Direction :</p>
      <div class="ps-cm-box"></div>
      @endif

      <div style="flex:1;"></div>

      <table class="ps-sig-t">
        <tr class="ps-sig-dr"><td colspan="3"><span style="text-decoration:underline;">Date :</span> {{ $_dpDate }}</td></tr>
        <tr class="ps-sig-hr">
          <th>Signature de<br>l'enseignant(e)</th>
          <th>Cachet et signature<br>de la Direction</th>
          <th>Signature<br>des parents</th>
        </tr>
        <tr class="ps-sig-br"><td></td><td></td><td></td></tr>
      </table>
    </div>

  </div>{{-- /VERSO --}}

</div>{{-- /ps-wrap --}}

@else
{{-- ══════════════════════════════════════════════════════════════
     PRIMAIRE — Format livret A4 paysage (2 pages × 2 panneaux)
     Impression : Paysage, recto-verso bord court, plier en 2
     ══════════════════════════════════════════════════════════════ --}}

<style>
/* Override @page pour le paysage */
@page { size: A4 landscape; margin: 0; }
@media print { body { background:#fff; } .bk-wrap { padding:0; gap:0; } .bk-no-print { display:none !important; } }

/* ── Livret ── */
*, *::before, *::after { box-sizing: border-box; }
.bk-wrap  { display:flex; flex-direction:column; align-items:center; gap:8mm; padding:8mm; }
.bk-page  { width:297mm; height:210mm; display:flex; flex-direction:row; background:#fff;
            page-break-after:always; overflow:hidden; font-size:8pt; color:#000;
            font-family:Arial,sans-serif; box-shadow:0 3px 14px rgba(0,0,0,.3); }
.bk-panel { width:148.5mm; height:210mm; padding:5mm 6mm; overflow:hidden; position:relative; }
.bk-left  { border-right:1.5px solid #000; }

/* ── En-têtes de page ── */
.bk-pbar { display:flex; justify-content:space-between; align-items:baseline;
           border-bottom:2px solid #000; padding-bottom:1.5mm; margin-bottom:1.5mm; }
.bk-pbar span { font-size:8.5pt; font-weight:700; text-transform:uppercase; }

/* ── Label section ── */
.bk-sl { font-size:7.5pt; font-weight:700; text-transform:uppercase; margin:2mm 0 0.8mm; }

/* ── En-tête des périodes ── */
.bk-ph { width:100%; border-collapse:collapse; table-layout:fixed; }
.bk-ph th { border:1px solid #000; font-size:6.5pt; font-weight:700; text-align:center; padding:0.5mm; }
.bk-ph .ph-blank { width:52%; background:#fff; border:none; border-right:1px solid #000; }
.bk-ph .ph-top   { background:#A6A6A6; }
.bk-ph .ph-sub   { background:#D9D9D9; font-size:6pt; }

/* ── Table de notes ── */
.bk-gt { width:100%; border-collapse:collapse; margin-bottom:0.5mm; table-layout:fixed; }
.bk-gt td { border:1px solid #000; vertical-align:middle; padding:0.5mm 1.2mm; font-size:7pt; background:#fff; }
.bk-gt tbody tr:nth-child(even) td { background:#BFBFBF; }
.bk-cb  { width:52%; }
.bk-p   { width:6.7%; text-align:center; font-weight:700; }
.bk-aen { width:5%;   text-align:center; }

/* ── Totaux ── */
.bk-tt { width:100%; border-collapse:collapse; margin-top:2mm; table-layout:fixed; }
.bk-tt td { border:1px solid #000; padding:1mm 2mm; font-size:8pt; background:#fff; text-align:center; }
.bk-tt .lbl { text-align:left; font-weight:700; background:#D9D9D9; width:40%; }
.bk-tt .big { font-size:10pt; font-weight:700; }

/* ── Légende ── */
.bk-leg { width:100%; border-collapse:collapse; margin-top:2mm; font-size:7pt; }
.bk-leg th { border:1px solid #000; padding:0.8mm 1mm; font-weight:700; text-align:center; background:#A6A6A6; }
.bk-leg th.lh { text-align:left; }
.bk-leg td { border:1px solid #000; padding:0.8mm 1mm; text-align:center; background:#fff; }
.bk-leg td.lbl { text-align:left; font-weight:700; background:#D9D9D9; }

/* ── Appréciations ── */
.bk-at { width:100%; border-collapse:collapse; font-size:7.5pt; }
.bk-at th { border:1px solid #000; background:#A6A6A6; font-weight:700; text-align:center; padding:1mm 1.5mm; }
.bk-at th.title { background:#D9D9D9; font-size:9pt; }
.bk-at td { border:1px solid #000; padding:1.5mm 2mm; vertical-align:top; background:#fff; }
.bk-at .per { text-align:center; font-weight:700; width:13%; vertical-align:middle; background:#D9D9D9; }
.bk-at .obs { width:43%; }
.bk-at .sig { width:22%; text-align:center; vertical-align:bottom; font-size:6.5pt; color:#444; }
.bk-sline   { display:block; border-bottom:1px solid #555; margin:8mm auto 1mm; width:75%; }

/* ── Test de fin d'année ── */
.bk-tft { width:62%; border-collapse:collapse; margin:1.5mm 0 1.5mm 4mm; font-size:7.5pt; }
.bk-tft td { border:1px solid #000; padding:1.2mm 2mm; background:#fff; }
.bk-tft td:first-child { font-weight:700; }
.bk-tft td:last-child  { width:36%; }

/* ── Décision ── */
.bk-dec { border:1.5px solid #000; margin-top:2mm; }
.bk-chk { width:3.5mm; height:3.5mm; border:1.5px solid #000; display:inline-block; flex-shrink:0; }

/* ── Couverture ── */
.bk-cover { display:flex; flex-direction:column; align-items:center; }
.bk-republic { font-size:8pt; font-weight:700; text-transform:uppercase; letter-spacing:1px; margin-bottom:2.5mm; text-align:center; }
.bk-logo { background:#1a5c00; display:inline-block; margin-bottom:3mm; }
.bk-logo-top { display:flex; align-items:stretch; }
.bk-logo-in  { background:#ED7D31; color:#fff; font-size:16pt; font-weight:900; font-style:italic;
               padding:2.5mm 3mm; display:flex; align-items:center; }
.bk-logo-tec { color:#fff; font-size:16pt; font-weight:900; padding:2.5mm 4.5mm;
               display:flex; align-items:center; letter-spacing:2px; }
.bk-logo-bot { color:#fff; font-size:11pt; font-weight:700; letter-spacing:6px;
               padding:1.5mm 4mm 2mm; text-align:center; }
.bk-cov-school  { font-size:11pt; font-weight:900; text-transform:uppercase; text-align:center; margin-bottom:1mm; }
.bk-cov-carnet  { font-size:13pt; font-weight:400; text-align:center; margin:1mm 0; }
.bk-cov-classe  { font-size:18pt; font-weight:900; text-align:center; margin-bottom:3mm; }
.bk-infobox { border:2.5px solid #000; border-radius:8px; padding:3mm 5mm; width:100%;
              font-size:8.5pt; background:#fff; line-height:2; }
.bk-infobox .ir { display:flex; }
.bk-infobox .il { flex-shrink:0; min-width:38mm; }
.bk-infobox .iv { font-weight:700; }
</style>

@php
$splitAt       = (int) ceil($subjects->count() / 2);
$subjectsLeft  = $subjects->take($splitAt);
$subjectsRight = $subjects->skip($splitAt)->values();
$directorName  = \App\Models\SchoolSetting::get('director_name', '—');
$schoolName    = \App\Models\SchoolSetting::get('school_name', 'ECOLE PRIVEE INTEC');
$nextClassCode = \App\Models\StudentPromotion::nextClassCode($classCode);
@endphp

{{-- No-print bar --}}
<div class="bk-no-print" style="padding:12px 16px;background:#eef2ff;border-bottom:1px solid #c7d2fe;display:flex;justify-content:space-between;align-items:center;">
    <div>
        <p style="font-weight:700;color:#3730a3;font-size:14px;">📋 Carnet d'évaluation — {{ $student->full_name }}</p>
        <p style="font-size:11px;color:#6366f1;margin-top:2px;">
            {{ $classCode }}{{ $classSection ? '/'.$classSection : '' }}
            &bull; {{ $yearLabel }}
            &bull; <em>Impression : Paysage A4 — Recto-verso bord court — Plier en deux</em>
        </p>
    </div>
    <div style="display:flex;gap:8px;">
        <a href="{{ route('bulletins.index') }}" style="padding:6px 14px;border:1px solid #a5b4fc;border-radius:8px;color:#4338ca;font-size:13px;text-decoration:none;font-family:Arial,sans-serif;">← Retour</a>
        <button onclick="window.print()" style="padding:6px 14px;background:#4f46e5;color:#fff;border:none;border-radius:8px;font-size:13px;cursor:pointer;font-family:Arial,sans-serif;">🖨 Imprimer</button>
    </div>
</div>

<div class="bk-wrap">

{{-- ═══════════════════════════════════════════
     PAGE 1 — EXTÉRIEUR
     Gauche = Dos / Appréciations   Droite = Couverture
     ═══════════════════════════════════════════ --}}
<div class="bk-page">

    {{-- ▌ PANNEAU GAUCHE : DOS ▌ --}}
    <div class="bk-panel bk-left">

        <div style="display:flex;justify-content:space-between;margin-bottom:1mm;">
            <strong style="font-size:8.5pt;text-transform:uppercase;">{{ strtoupper($schoolName) }}</strong>
            <strong style="font-size:8.5pt;text-transform:uppercase;">PRIMAIRE</strong>
        </div>
        <div style="margin-bottom:1.5mm;">
            <strong style="font-size:8.5pt;text-transform:uppercase;">CARNET D'EVALUATION {{ strtoupper($classCode) }}</strong>
            &nbsp;&nbsp;
            <strong style="font-size:8.5pt;">ANNEE SCOLAIRE : {{ $yearLabel }}</strong>
        </div>
        <hr style="border:none;border-top:1.5px solid #000;margin-bottom:2mm;">

        <table class="bk-at">
            <thead>
                <tr><th colspan="4" class="title">APPRECIATIONS GENERALES</th></tr>
                <tr>
                    <th style="width:13%;">Périodes</th>
                    <th style="width:43%;">Observations</th>
                    <th style="width:22%;">Signature de la Direction</th>
                    <th style="width:22%;">Signature des parents</th>
                </tr>
            </thead>
            <tbody>
                @foreach([['T1','1<sup>ère</sup>','Période'],['T2','2<sup>ème</sup>','Période'],['T3','3<sup>ème</sup>','Période']] as [$p,$num,$lbl])
                <tr>
                    <td class="per">{!! $num !!}<br><strong>{{ $lbl }}</strong></td>
                    <td class="obs">
                        @if($periodTotals[$p]['teacher_comment'])
                            {{ $periodTotals[$p]['teacher_comment'] }}<br><br>
                        @else
                            <br><br><br>
                        @endif
                        <em style="font-size:7pt;">L'Enseignant(e)</em>
                    </td>
                    <td class="sig">
                        @if($periodTotals[$p]['direction_comment'])
                            <span style="font-size:7pt;">{{ $periodTotals[$p]['direction_comment'] }}</span>
                        @endif
                        <span class="bk-sline"></span>
                    </td>
                    <td class="sig"><span class="bk-sline"></span></td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <p style="margin-top:2.5mm;font-weight:700;font-size:8pt;">TEST DE FIN D'ANNEE :</p>
        <table class="bk-tft">
            <tr><td>Français</td><td></td></tr>
            <tr><td>Mathématiques</td><td></td></tr>
            <tr><td>Arabe</td><td></td></tr>
        </table>

        <p style="margin-top:1.5mm;font-weight:700;font-size:8pt;text-decoration:underline;">Décision de fin d'année :</p>
        <div class="bk-dec">
            <table style="width:100%;border-collapse:collapse;font-size:7.5pt;">
                <tr>
                    <td style="border:1px solid #000;padding:1.5mm 2mm;width:55%;text-align:center;">
                        Décision de fin d'année en conseil des maîtres
                    </td>
                    <td style="border:1px solid #000;padding:2mm;vertical-align:top;" rowspan="2">
                        Cachet de l'école<br><br>Du ………………….
                    </td>
                </tr>
                <tr>
                    <td style="border:1px solid #000;padding:2mm 3mm;">
                        <div style="display:flex;flex-direction:column;gap:2mm;">
                            <div style="display:flex;align-items:center;gap:2mm;">
                                <div class="bk-chk"></div>
                                Admis(e) {{ $nextClassCode ? 'en ' . $nextClassCode : 'en classe supérieure' }}
                            </div>
                            <div style="display:flex;align-items:center;gap:2mm;">
                                <div class="bk-chk"></div>
                                Reprend le {{ $classCode }}
                            </div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

    </div>{{-- /DOS --}}

    {{-- ▌ PANNEAU DROIT : COUVERTURE ▌ --}}
    <div class="bk-panel">
        <div class="bk-cover">

            <p class="bk-republic">REPUBLIQUE DE DJIBOUTI</p>

            <img src="{{ asset('images/in tech.jpg') }}" alt="INTEC"
                 style="width:38mm;height:38mm;object-fit:contain;border-radius:6px;margin-bottom:3mm;">

            <p class="bk-cov-school">{{ strtoupper($schoolName) }}</p>
            <p class="bk-cov-carnet">CARNET D'EVALUATION</p>
            <p class="bk-cov-classe">{{ strtoupper($classCode) }}</p>

            <div class="bk-infobox">
                <div class="ir"><span class="il">École privée :</span><span class="iv" style="font-style:italic;font-weight:400;">{{ $schoolName }}</span></div>
                <div class="ir"><span class="il">Nom de l'élève :</span><span class="iv">{{ strtoupper($student->full_name) }}</span></div>
                <div class="ir"><span class="il">Né(e) le :</span><span class="iv">{{ $student->birth_date?->format('d/m/Y') ?? '—' }}</span></div>
                <div class="ir"><span class="il">Classe :</span><span class="iv">{{ strtoupper($classCode) }}{{ $classSection ? '/'.strtoupper($classSection) : '' }}</span></div>
                <div class="ir"><span class="il">Nom de l'enseignant(e) :</span><span class="iv" style="font-weight:400;">{{ $student->classroom->teacher?->name ?? '—' }}</span></div>
                <div class="ir"><span class="il">Nom du Directeur :</span><span class="iv" style="font-weight:400;">{{ $directorName }}</span></div>
            </div>

        </div>
    </div>{{-- /COUVERTURE --}}

</div>{{-- /PAGE 1 --}}

{{-- ═══════════════════════════════════════════
     PAGE 2 — INTÉRIEUR
     Gauche = premières matières   Droite = reste + totaux + légende
     ═══════════════════════════════════════════ --}}
<div class="bk-page">

    {{-- ▌ PANNEAU GAUCHE : MATIÈRES (première moitié) ▌ --}}
    <div class="bk-panel bk-left">

        <div class="bk-pbar">
            <span>CARNET D'EVALUATION {{ strtoupper($classCode) }}</span>
            <span>INTEC ECOLE</span>
        </div>

        {{-- En-tête des périodes --}}
        <table class="bk-ph">
            <tr>
                <th class="ph-blank"></th>
                <th colspan="3" class="ph-top">PERIODE 1</th>
                <th colspan="3" class="ph-top">PERIODE 2</th>
                <th colspan="3" class="ph-top">PERIODE 3</th>
            </tr>
            <tr>
                <th class="ph-blank"></th>
                <th class="ph-sub">A</th><th class="ph-sub">EVA</th><th class="ph-sub">NA</th>
                <th class="ph-sub">A</th><th class="ph-sub">EVA</th><th class="ph-sub">NA</th>
                <th class="ph-sub">A</th><th class="ph-sub">EVA</th><th class="ph-sub">NA</th>
            </tr>
        </table>

        @foreach($subjectsLeft as $idx => $subject)
        <p class="bk-sl">{{ $romanNumerals[$idx] ?? ($idx + 1) }} – COMPETENCE DE BASE : {{ strtoupper($subject->name) }} : /{{ $subject->max_score ?? '—' }}</p>
        <table class="bk-gt">
            <colgroup>
                <col class="bk-cb">
                <col class="bk-p"><col class="bk-aen"><col class="bk-aen">
                <col class="bk-p"><col class="bk-aen"><col class="bk-aen">
                <col class="bk-p"><col class="bk-aen"><col class="bk-aen">
            </colgroup>
            <tbody>
                @foreach($subject->competences as $competence)
                <tr>
                    <td class="bk-cb">
                        <strong>{{ $competence->code }}</strong>
                        @if($competence->description) / {{ $competence->description }} @endif
                        @if($competence->max_score) <strong>/{{ $competence->max_score }}</strong> @endif
                    </td>
                    @foreach(['T1','T2','T3'] as $_p)
                    @php
                        $grade    = $gradesMap[$_p][$competence->id] ?? null;
                        $numScore = $grade && $grade->score !== null ? rtrim(rtrim(number_format((float)$grade->score, 2, '.', ''), '0'), '.') : null;
                        $stChar   = $statusMap[$_p][$competence->id] ?? null;
                        $display  = $numScore ?? ($stChar ?? '');
                        $cellA    = $stChar === 'A'   ? $display : '';
                        $cellEVA  = $stChar === 'EVA' ? $display : '';
                        $cellNA   = $stChar === 'NA'  ? $display : '';
                    @endphp
                    <td class="bk-p">{{ $cellA }}</td>
                    <td class="bk-aen">{{ $cellEVA }}</td>
                    <td class="bk-aen">{{ $cellNA }}</td>
                    @endforeach
                </tr>
                @endforeach
            </tbody>
        </table>
        @endforeach

    </div>{{-- /GAUCHE --}}

    {{-- ▌ PANNEAU DROIT : MATIÈRES (seconde moitié) + Totaux + Légende ▌ --}}
    <div class="bk-panel">

        <div class="bk-pbar">
            <span>CARNET D'EVALUATION {{ strtoupper($classCode) }}</span>
            <span style="font-size:7.5pt;text-transform:none;">Année scolaire : {{ $yearLabel }}</span>
        </div>

        @foreach($subjectsRight as $ridx => $subject)
        @php $globalIdx = $splitAt + $ridx; @endphp
        <p class="bk-sl">{{ $romanNumerals[$globalIdx] ?? ($globalIdx + 1) }} – COMPETENCE DE BASE : {{ strtoupper($subject->name) }} : /{{ $subject->max_score ?? '—' }}</p>
        <table class="bk-gt">
            <colgroup>
                <col class="bk-cb">
                <col class="bk-p"><col class="bk-aen"><col class="bk-aen">
                <col class="bk-p"><col class="bk-aen"><col class="bk-aen">
                <col class="bk-p"><col class="bk-aen"><col class="bk-aen">
            </colgroup>
            <tbody>
                @foreach($subject->competences as $competence)
                <tr>
                    <td class="bk-cb">
                        <strong>{{ $competence->code }}</strong>
                        @if($competence->description) / {{ $competence->description }} @endif
                        @if($competence->max_score) <strong>/{{ $competence->max_score }}</strong> @endif
                    </td>
                    @foreach(['T1','T2','T3'] as $_p)
                    @php
                        $grade    = $gradesMap[$_p][$competence->id] ?? null;
                        $numScore = $grade && $grade->score !== null ? rtrim(rtrim(number_format((float)$grade->score, 2, '.', ''), '0'), '.') : null;
                        $stChar   = $statusMap[$_p][$competence->id] ?? null;
                        $display  = $numScore ?? ($stChar ?? '');
                        $cellA    = $stChar === 'A'   ? $display : '';
                        $cellEVA  = $stChar === 'EVA' ? $display : '';
                        $cellNA   = $stChar === 'NA'  ? $display : '';
                    @endphp
                    <td class="bk-p">{{ $cellA }}</td>
                    <td class="bk-aen">{{ $cellEVA }}</td>
                    <td class="bk-aen">{{ $cellNA }}</td>
                    @endforeach
                </tr>
                @endforeach
            </tbody>
        </table>
        @endforeach

        {{-- TOTAUX --}}
        <table class="bk-tt">
            <colgroup>
                <col style="width:40%"><col style="width:20%"><col style="width:20%"><col style="width:20%">
            </colgroup>
            <tbody>
                <tr>
                    <td class="lbl">Total sur {{ $maxTotal }}</td>
                    @foreach(['T1','T2','T3'] as $_p)
                    <td class="big">{{ $periodTotals[$_p]['total'] !== null ? number_format($periodTotals[$_p]['total'], 1) : '' }}</td>
                    @endforeach
                </tr>
                <tr>
                    <td class="lbl">Moyenne sur 10</td>
                    @foreach(['T1','T2','T3'] as $_p)
                    <td class="big">{{ $periodTotals[$_p]['moyenne'] !== null ? number_format($periodTotals[$_p]['moyenne'], 2) : '' }}</td>
                    @endforeach
                </tr>
                <tr>
                    <td class="lbl">Moyenne de la classe sur 10</td>
                    @foreach(['T1','T2','T3'] as $_p)
                    <td class="big">{{ $periodTotals[$_p]['class_moyenne'] !== null ? number_format($periodTotals[$_p]['class_moyenne'], 2) : '' }}</td>
                    @endforeach
                </tr>
            </tbody>
        </table>

        {{-- LÉGENDE --}}
        <table class="bk-leg">
            <thead>
                <tr>
                    <th class="lh" style="width:30%;">Degré de maîtrise de la compétence de base.</th>
                    <th>Acquis<br><strong>A</strong></th>
                    <th>En voie d'acquisition<br><strong>EVA</strong></th>
                    <th>Non acquis<br><strong>NA</strong></th>
                </tr>
            </thead>
            <tbody>
                <tr><td class="lbl">Appréciations</td><td>(Très) satisfaisant</td><td>Moyen</td><td>Insuffisant</td></tr>
                <tr><td class="lbl">Seuil de maîtrise.</td><td>Plus de 2/3</td><td>Moins de 2/3</td><td>Moins de 1/3</td></tr>
                <tr><td class="lbl">Notes correspondantes.</td><td>7 à 10</td><td>4 à 6</td><td>0 à 3</td></tr>
            </tbody>
        </table>

    </div>{{-- /DROIT --}}

</div>{{-- /PAGE 2 --}}

</div>{{-- /bk-wrap --}}

@endif
</div>
