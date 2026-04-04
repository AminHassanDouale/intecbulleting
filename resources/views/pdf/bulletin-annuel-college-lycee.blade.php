<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
  * { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 8.5pt; }
  body { margin: 16px; color: #1a1a1a; }
  .header { text-align: center; border-bottom: 3px solid #065f46; padding-bottom: 8px; margin-bottom: 12px; }
  .title { font-size: 13pt; font-weight: bold; color: #065f46; }
  .subtitle { font-size: 9pt; color: #047857; margin-top: 2px; }
  .student-info { background: #ecfdf5; padding: 8px 10px; border-radius: 4px; margin-bottom: 12px; border: 1px solid #a7f3d0; }
  .student-info table { width: 100%; border-collapse: collapse; }
  .student-info td { padding: 2px 6px; }
  table.grades { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
  table.grades thead tr { background: #065f46; color: white; }
  table.grades th { padding: 4px 5px; border: 1px solid #047857; text-align: center; font-size: 7.5pt; }
  table.grades td { padding: 3px 5px; border: 1px solid #d1fae5; vertical-align: middle; }
  table.grades tbody tr:nth-child(even) { background: #f0fdf4; }
  .subject-block { margin-top: 8px; }
  .subject-header { background: #065f46; color: white; font-weight: bold; padding: 4px 8px; font-size: 9pt; }
  .subtotal-row { background: #d1fae5; font-weight: bold; }
  .summary-table { width: 100%; border-collapse: collapse; margin-top: 10px; border: 2px solid #065f46; border-radius: 4px; overflow: hidden; }
  .summary-table td { padding: 5px 10px; border-bottom: 1px solid #a7f3d0; }
  .summary-header { background: #065f46; color: white; font-weight: bold; font-size: 9.5pt; }
  .annual-avg { font-size: 14pt; font-weight: bold; color: #065f46; text-align: center; }
  .comment-box { margin-top: 8px; padding: 7px; border: 1px solid #bfdbfe; border-radius: 4px; min-height: 30px; }
  .signatures { margin-top: 14px; border-top: 2px solid #e5e7eb; padding-top: 8px; }
  .sig-box { border: 1px solid #d1d5db; min-height: 50px; padding: 5px 7px; border-radius: 3px; font-size: 7.5pt; }
  .badge-pass { color: #166534; font-weight: bold; }
  .badge-fail { color: #991b1b; font-weight: bold; }
  .na { color: #9ca3af; }
</style>
</head>
<body>

<div class="header">
  <div class="title">BILAN ANNUEL — {{ strtoupper($classroom->code) }} — INTEC ÉCOLE</div>
  <div class="subtitle">Année Scolaire : {{ $academicYear->label }}</div>
</div>

<div class="student-info">
  <table>
    <tr>
      <td><strong>Élève :</strong> {{ $student->full_name }}</td>
      <td><strong>Matricule :</strong> {{ $student->matricule }}</td>
      <td><strong>Classe :</strong> {{ $classroom->code }} — Section {{ $classroom->section }}</td>
    </tr>
    <tr>
      <td><strong>Date de naissance :</strong> {{ $student->birth_date->format('d/m/Y') }}</td>
      <td><strong>Enseignant(e) :</strong> {{ $classroom->teacher?->name ?? '—' }}</td>
      <td><strong>Période :</strong> Bilan des 3 Trimestres</td>
    </tr>
  </table>
</div>

@php
  // Collect all subjects from whichever trimester has grades
  $sourceBulletin = $t1 ?? $t2 ?? $t3;
  $subjectIds = collect();
  foreach ([$t1, $t2, $t3] as $b) {
      if ($b) {
          $b->grades->each(fn($g) => $subjectIds->push($g->competence->subject_id));
      }
  }
  $subjectIds = $subjectIds->unique()->values();

  // Build a subject map: subject_id => subject model
  $subjects = collect();
  foreach ([$t1, $t2, $t3] as $b) {
      if ($b) {
          $b->grades->each(function($g) use (&$subjects) {
              $subjects[$g->competence->subject_id] = $g->competence->subject;
          });
      }
  }

  // Grade lookup per trimester: competence_id => score
  $g1 = $t1 ? $t1->grades->keyBy('competence_id') : collect();
  $g2 = $t2 ? $t2->grades->keyBy('competence_id') : collect();
  $g3 = $t3 ? $t3->grades->keyBy('competence_id') : collect();

  $grandT1 = 0; $grandT2 = 0; $grandT3 = 0; $grandMax = 0;
@endphp

@foreach($subjects as $subjectId => $subject)
@php
  $competences = $subject->competences()->orderBy('order')->get();
  $subT1 = 0; $subT2 = 0; $subT3 = 0;
  foreach ($competences as $c) {
      $subT1 += (float) ($g1[$c->id]?->score ?? 0);
      $subT2 += (float) ($g2[$c->id]?->score ?? 0);
      $subT3 += (float) ($g3[$c->id]?->score ?? 0);
  }
  $grandT1 += $subT1; $grandT2 += $subT2; $grandT3 += $subT3;
  $grandMax += $subject->max_score;
@endphp

<div class="subject-block">
  <div class="subject-header">
    {{ $subject->name }}
    &nbsp;<span style="font-size:7.5pt;font-weight:normal">/ {{ $subject->max_score }} pts par trimestre</span>
  </div>
  <table class="grades">
    <thead>
      <tr>
        <th style="width:46%;text-align:left">Compétence</th>
        <th style="width:9%">T1<br><span style="font-weight:normal;font-size:7pt">Max</span></th>
        <th style="width:9%">T2<br><span style="font-weight:normal;font-size:7pt">Max</span></th>
        <th style="width:9%">T3<br><span style="font-weight:normal;font-size:7pt">Max</span></th>
        <th style="width:9%">Total<br><span style="font-weight:normal;font-size:7pt">Annuel</span></th>
        <th style="width:9%">Max<br><span style="font-weight:normal;font-size:7pt">Annuel</span></th>
        <th style="width:9%">Appré.</th>
      </tr>
    </thead>
    <tbody>
      @foreach($competences as $competence)
      @php
        $gr1 = $g1[$competence->id] ?? null;
        $gr2 = $g2[$competence->id] ?? null;
        $gr3 = $g3[$competence->id] ?? null;
        $s1 = $gr1?->score;
        $s2 = $gr2?->score;
        $s3 = $gr3?->score;
        $cMax = $competence->max_score;
        $annualMax = $cMax * collect([$t1, $t2, $t3])->filter()->count();
        $annualScore = (float)($s1 ?? 0) + (float)($s2 ?? 0) + (float)($s3 ?? 0);
        $pct = $annualMax > 0 ? ($annualScore / $annualMax * 100) : null;
        $appre = $pct !== null
            ? ($pct >= 80 ? 'TB' : ($pct >= 60 ? 'B' : ($pct >= 40 ? 'AB' : 'I')))
            : '—';
        $appreClass = ($pct !== null && $pct >= 40) ? 'badge-pass' : ($pct !== null ? 'badge-fail' : 'na');
      @endphp
      <tr>
        <td><strong>{{ $competence->code }}</strong> — {{ Str::limit($competence->description, 50) }}</td>
        <td style="text-align:center">
          @if($s1 !== null)<strong>{{ $s1 }}</strong><br><span class="na">/{{ $cMax }}</span>@else<span class="na">—</span>@endif
        </td>
        <td style="text-align:center">
          @if($s2 !== null)<strong>{{ $s2 }}</strong><br><span class="na">/{{ $cMax }}</span>@else<span class="na">—</span>@endif
        </td>
        <td style="text-align:center">
          @if($s3 !== null)<strong>{{ $s3 }}</strong><br><span class="na">/{{ $cMax }}</span>@else<span class="na">—</span>@endif
        </td>
        <td style="text-align:center;font-weight:bold">{{ $annualScore > 0 ? $annualScore : '—' }}</td>
        <td style="text-align:center;color:#6b7280">{{ $annualMax }}</td>
        <td style="text-align:center" class="{{ $appreClass }}">{{ $appre }}</td>
      </tr>
      @endforeach
      {{-- Subject subtotal --}}
      <tr class="subtotal-row">
        <td>Sous-total {{ $subject->name }}</td>
        <td style="text-align:center">{{ $subT1 > 0 ? number_format($subT1, 1) : '—' }}<br><span style="font-weight:normal;color:#6b7280;font-size:7.5pt">/{{ $subject->max_score }}</span></td>
        <td style="text-align:center">{{ $subT2 > 0 ? number_format($subT2, 1) : '—' }}<br><span style="font-weight:normal;color:#6b7280;font-size:7.5pt">/{{ $subject->max_score }}</span></td>
        <td style="text-align:center">{{ $subT3 > 0 ? number_format($subT3, 1) : '—' }}<br><span style="font-weight:normal;color:#6b7280;font-size:7.5pt">/{{ $subject->max_score }}</span></td>
        <td style="text-align:center;font-weight:bold">{{ number_format($subT1 + $subT2 + $subT3, 1) }}</td>
        <td style="text-align:center;color:#6b7280">{{ $subject->max_score * collect([$t1, $t2, $t3])->filter()->count() }}</td>
        <td></td>
      </tr>
    </tbody>
  </table>
</div>
@endforeach

{{-- Annual Summary --}}
<table class="summary-table" style="margin-top:12px">
  <tr>
    <td class="summary-header" colspan="4" style="text-align:center">RÉCAPITULATIF ANNUEL</td>
  </tr>
  <tr>
    <td style="width:30%;font-weight:bold">Total des points</td>
    <td style="text-align:center">T1 : <strong>{{ $grandT1 > 0 ? number_format($grandT1, 1) : '—' }}</strong>/{{ $grandMax }}</td>
    <td style="text-align:center">T2 : <strong>{{ $grandT2 > 0 ? number_format($grandT2, 1) : '—' }}</strong>/{{ $grandMax }}</td>
    <td style="text-align:center">T3 : <strong>{{ $grandT3 > 0 ? number_format($grandT3, 1) : '—' }}</strong>/{{ $grandMax }}</td>
  </tr>
  <tr>
    <td style="font-weight:bold">Moyenne / 20</td>
    <td style="text-align:center">{{ $t1?->moyenne ? $t1->moyenne.'/20' : '—' }}</td>
    <td style="text-align:center">{{ $t2?->moyenne ? $t2->moyenne.'/20' : '—' }}</td>
    <td style="text-align:center">{{ $t3?->moyenne ? $t3->moyenne.'/20' : '—' }}</td>
  </tr>
  <tr style="background:#dbeafe">
    <td style="font-weight:bold;font-size:10pt">MOYENNE ANNUELLE</td>
    <td colspan="3" class="annual-avg">
      {{ $annualMoyenne ? $annualMoyenne.'/20' : '—' }}
      @if($annualMoyenne)
        &nbsp;<span style="font-size:9pt;color:{{ $annualMoyenne >= 10 ? '#166534' : '#991b1b' }}">
          {{ $annualMoyenne >= 16 ? 'Très Bien' : ($annualMoyenne >= 14 ? 'Bien' : ($annualMoyenne >= 12 ? 'Assez Bien' : ($annualMoyenne >= 10 ? 'Passable' : 'Insuffisant'))) }}
        </span>
      @endif
    </td>
  </tr>
  @if($annualMoyenne)
  <tr>
    <td colspan="4" style="text-align:center;padding:5px">
      <strong style="color: {{ $annualMoyenne >= 10 ? '#166534' : '#991b1b' }}">
        {{ $annualMoyenne >= 10 ? '✓ ADMIS(E) EN CLASSE SUPÉRIEURE' : '✗ RÉSULTATS INSUFFISANTS' }}
      </strong>
    </td>
  </tr>
  @endif
</table>

@if($t3?->teacher_comment)
<div class="comment-box" style="margin-top:8px">
  <strong>Appréciation de l'enseignant(e) :</strong><br>{{ $t3->teacher_comment }}
</div>
@endif

@if($t3?->direction_comment)
<div class="comment-box" style="border-color:#1e40af;margin-top:5px">
  <strong>Mot de la Direction :</strong><br>{{ $t3->direction_comment }}
</div>
@endif

<div class="signatures" style="margin-top:14px">
  <table style="width:100%">
    <tr>
      <td style="width:33%;padding-right:6px"><div class="sig-box"><strong>Signature de l'enseignant(e)</strong></div></td>
      <td style="width:33%;padding:0 3px"><div class="sig-box"><strong>Cachet et signature de la Direction</strong></div></td>
      <td style="width:33%;padding-left:6px"><div class="sig-box"><strong>Signature des parents / tuteur</strong></div></td>
    </tr>
  </table>
</div>

</body>
</html>
