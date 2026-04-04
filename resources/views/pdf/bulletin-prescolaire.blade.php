<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
  * { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 9pt; margin: 0; padding: 0; box-sizing: border-box; }
  body { margin: 0; color: #1a1a1a; background: #fff; }

  /* ── Header ── */
  .header-band {
    background: #1e3a8a;
    color: white;
    padding: 12px 16px;
    border-bottom: 4px solid #f59e0b;
  }
  .header-table { width: 100%; border-collapse: collapse; }
  .header-table td { vertical-align: middle; }
  .header-logo-cell { width: 70px; text-align: center; }
  .header-logo { width: 60px; height: 60px; border: 2px solid rgba(255,255,255,.3); border-radius: 6px; object-fit: contain; background: white; padding: 4px; }
  .header-center { text-align: center; }
  .school-name { font-size: 14pt; font-weight: bold; color: #fcd34d; letter-spacing: 1px; }
  .school-sub   { font-size: 8pt; color: rgba(255,255,255,.8); margin-top: 2px; }
  .bulletin-title { font-size: 13pt; font-weight: bold; color: white; margin-top: 6px; text-transform: uppercase; letter-spacing: 2px; }
  .annee-label { font-size: 8.5pt; color: #fcd34d; margin-top: 3px; }

  /* ── Student info ── */
  .info-band {
    background: #1e40af;
    color: white;
    padding: 8px 16px;
    border-bottom: 3px solid #f59e0b;
  }
  .info-table { width: 100%; border-collapse: collapse; }
  .info-table td { padding: 2px 8px; color: white; font-size: 8.5pt; }
  .info-label { color: #fcd34d; font-weight: bold; }

  /* ── Legend ── */
  .legend-box {
    margin: 10px 14px 6px;
    border: 1px solid #bfdbfe;
    border-radius: 4px;
    background: #eff6ff;
    padding: 6px 10px;
  }
  .legend-title { font-weight: bold; font-size: 8pt; color: #1e3a8a; margin-bottom: 4px; }
  .legend-table { border-collapse: collapse; width: 100%; }
  .legend-table td { padding: 2px 8px; font-size: 7.5pt; border: 1px solid #dbeafe; text-align: center; }
  .legend-table th { padding: 3px 8px; font-size: 7.5pt; background: #1e3a8a; color: white; border: 1px solid #1e3a8a; }
  .badge-a   { background: #16a34a; color: white; padding: 2px 8px; border-radius: 10px; font-size: 8pt; font-weight: bold; display: inline-block; }
  .badge-eva { background: #d97706; color: white; padding: 2px 8px; border-radius: 10px; font-size: 8pt; font-weight: bold; display: inline-block; }
  .badge-na  { background: #dc2626; color: white; padding: 2px 8px; border-radius: 10px; font-size: 8pt; font-weight: bold; display: inline-block; }
  .badge-dash { color: #9ca3af; font-size: 9pt; }

  /* ── Subject sections ── */
  .section-wrap { margin: 0 14px 10px; }
  .section-head {
    background: #1e3a8a;
    color: white;
    padding: 5px 10px;
    font-weight: bold;
    font-size: 9.5pt;
    border-radius: 3px 3px 0 0;
    border-left: 4px solid #f59e0b;
  }
  table.comp { width: 100%; border-collapse: collapse; }
  table.comp th {
    background: #dbeafe;
    color: #1e3a8a;
    padding: 4px 8px;
    border: 1px solid #bfdbfe;
    font-size: 8pt;
    text-align: center;
  }
  table.comp th.desc { text-align: left; }
  table.comp td { padding: 4px 8px; border: 1px solid #e5e7eb; font-size: 8.5pt; }
  table.comp tr:nth-child(even) td { background: #f0f7ff; }
  table.comp td.center { text-align: center; width: 60px; }
  .comp-code { color: #1e40af; font-weight: bold; }

  /* ── Comments ── */
  .comment-area { margin: 0 14px 8px; }
  .comment-label { font-weight: bold; font-size: 8.5pt; color: #1e3a8a; margin-bottom: 3px; }
  .comment-box {
    border: 1px solid #bfdbfe;
    border-radius: 3px;
    min-height: 36px;
    padding: 6px 8px;
    background: #f0f7ff;
    font-size: 8.5pt;
    color: #1a1a1a;
  }
  .comment-dir { border-color: #f59e0b; background: #fffbeb; }

  /* ── Signatures ── */
  .sig-section { margin: 10px 14px 0; border-top: 2px solid #1e3a8a; padding-top: 8px; }
  .sig-table { width: 100%; border-collapse: collapse; }
  .sig-table td { padding: 4px 6px; vertical-align: top; }
  .sig-box {
    border: 1px solid #bfdbfe;
    min-height: 55px;
    padding: 5px 8px;
    border-radius: 3px;
    background: #f8fafc;
  }
  .sig-title { font-weight: bold; font-size: 8pt; color: #1e3a8a; margin-bottom: 4px; }

  /* ── Period indicator ── */
  .period-tag {
    display: inline-block;
    background: #f59e0b;
    color: #1a1a1a;
    font-weight: bold;
    font-size: 8pt;
    padding: 2px 8px;
    border-radius: 10px;
    margin-left: 6px;
  }
</style>
</head>
<body>

@php
  use App\Enums\PeriodEnum;

  // Build grade lookup per period: [period][competence_id] => grade
  $gradeLookup = [];
  foreach (['T1' => $t1 ?? null, 'T2' => $t2 ?? null, 'T3' => $t3 ?? null] as $p => $b) {
    if ($b) {
      foreach ($b->grades as $g) {
        $gradeLookup[$p][$g->competence_id] = $g;
      }
    }
  }

  // Reference bulletin for listing subjects/competences
  $refBulletin   = $t1 ?? $t2 ?? $t3 ?? $bulletin;
  $subjectGroups = $refBulletin->grades->groupBy(fn($g) => $g->competence->subject_id);

  // Helper: render badge or dash from grade
  function presStatusBadge(?object $grade): string {
    if (! $grade || $grade->competence_status === null) {
      return '<span class="badge-dash">—</span>';
    }
    $v = is_string($grade->competence_status) ? $grade->competence_status : $grade->competence_status->value;
    $cls = match(strtoupper($v)) {
      'A'   => 'badge-a',
      'EVA' => 'badge-eva',
      'NA'  => 'badge-na',
      default => 'badge-dash',
    };
    return '<span class="' . $cls . '">' . htmlspecialchars($v) . '</span>';
  }
@endphp

{{-- ── HEADER ───────────────────────────────────────────────── --}}
<div class="header-band">
  <table class="header-table">
    <tr>
      <td class="header-logo-cell">
        {{-- Logo placeholder --}}
        <div style="width:58px;height:58px;border:2px solid rgba(255,255,255,.3);border-radius:6px;background:white;display:table-cell;vertical-align:middle;text-align:center;">
          <span style="font-size:22pt;">🏫</span>
        </div>
      </td>
      <td class="header-center">
        <div class="school-name">INTEC ÉCOLE</div>
        <div class="school-sub">Système de gestion des bulletins scolaires</div>
        <div class="bulletin-title">Bilan des Acquisitions</div>
        <div class="annee-label">
          Année scolaire : {{ $bulletin->academicYear->label }}
          <span class="period-tag">{{ PeriodEnum::from($bulletin->period)->label() }}</span>
        </div>
      </td>
      <td class="header-logo-cell">
        {{-- Ministry logo placeholder --}}
        <div style="width:58px;height:58px;border:2px solid rgba(255,255,255,.3);border-radius:6px;background:white;display:table-cell;vertical-align:middle;text-align:center;">
          <span style="font-size:16pt;">📋</span>
        </div>
      </td>
    </tr>
  </table>
</div>

{{-- ── STUDENT INFO ─────────────────────────────────────────── --}}
<div class="info-band">
  <table class="info-table">
    <tr>
      <td><span class="info-label">Élève :</span> {{ $bulletin->student->full_name }}</td>
      <td><span class="info-label">Matricule :</span> {{ $bulletin->student->matricule }}</td>
      <td><span class="info-label">Date de naissance :</span> {{ $bulletin->student->birth_date->format('d/m/Y') }}</td>
    </tr>
    <tr>
      <td><span class="info-label">Classe :</span> {{ $bulletin->classroom->label }}</td>
      <td><span class="info-label">Section :</span> {{ $bulletin->classroom->section }}</td>
      <td><span class="info-label">Enseignant(e) :</span> {{ $bulletin->classroom->teacher?->name ?? '—' }}</td>
    </tr>
  </table>
</div>

{{-- ── LEGEND ───────────────────────────────────────────────── --}}
<div class="legend-box">
  <div class="legend-title">Grille d'évaluation</div>
  <table class="legend-table">
    <thead>
      <tr>
        <th>Sigle</th>
        <th>Signification</th>
        <th>Description</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td><span class="badge-a">A</span></td>
        <td>Acquis</td>
        <td style="text-align:left">L'enfant maîtrise et réussit souvent la compétence de façon autonome.</td>
      </tr>
      <tr>
        <td><span class="badge-eva">EVA</span></td>
        <td>En voie d'acquisition</td>
        <td style="text-align:left">L'enfant est en cours d'apprentissage, des progrès sont visibles.</td>
      </tr>
      <tr>
        <td><span class="badge-na">NA</span></td>
        <td>Non acquis</td>
        <td style="text-align:left">La compétence n'est pas encore maîtrisée, un accompagnement est nécessaire.</td>
      </tr>
    </tbody>
  </table>
</div>

{{-- ── COMPETENCES BY SUBJECT ───────────────────────────────── --}}
@foreach($subjectGroups as $subjectId => $gradeGroup)
@php $subject = $gradeGroup->first()->competence->subject; @endphp
<div class="section-wrap">
  <div class="section-head">{{ $subject->name }}</div>
  <table class="comp">
    <thead>
      <tr>
        <th class="desc" style="width:58%">Compétences / Objectifs</th>
        <th style="width:14%">1er Trimestre</th>
        <th style="width:14%">2ème Trimestre</th>
        <th style="width:14%">3ème Trimestre</th>
      </tr>
    </thead>
    <tbody>
      @foreach($subject->competences()->orderBy('order')->get() as $competence)
      <tr>
        <td>
          <span class="comp-code">{{ $competence->code }}</span>
          — {{ $competence->description }}
        </td>
        <td class="center">{!! presStatusBadge($gradeLookup['T1'][$competence->id] ?? null) !!}</td>
        <td class="center">{!! presStatusBadge($gradeLookup['T2'][$competence->id] ?? null) !!}</td>
        <td class="center">{!! presStatusBadge($gradeLookup['T3'][$competence->id] ?? null) !!}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endforeach

{{-- ── OBSERVATIONS ─────────────────────────────────────────── --}}
<div class="comment-area">
  <div class="comment-label">Observation de l'enseignant(e) :</div>
  <div class="comment-box">{{ $bulletin->teacher_comment ?? '' }}</div>
</div>

<div class="comment-area">
  <div class="comment-label" style="color:#b45309;">Mot de la Direction :</div>
  <div class="comment-box comment-dir">{{ $bulletin->direction_comment ?? '' }}</div>
</div>

{{-- ── SIGNATURES ───────────────────────────────────────────── --}}
<div class="sig-section">
  <table class="sig-table">
    <tr>
      <td style="width:33%;padding-right:6px;">
        <div class="sig-box">
          <div class="sig-title">Signature de l'enseignant(e)</div>
        </div>
      </td>
      <td style="width:33%;padding:0 3px;">
        <div class="sig-box">
          <div class="sig-title">Cachet et signature de la Direction</div>
        </div>
      </td>
      <td style="width:33%;padding-left:6px;">
        <div class="sig-box">
          <div class="sig-title">Signature des parents / tuteurs</div>
        </div>
      </td>
    </tr>
  </table>
</div>

</body>
</html>
