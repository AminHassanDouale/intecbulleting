<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
  * { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 9pt; margin: 0; padding: 0; box-sizing: border-box; }
  body { margin: 0; color: #1a1a1a; background: #fff; }

  /* ── Header ── */
  .header-band { background: #16363a; color: white; padding: 10px 14px; border-bottom: 4px solid #c8913a; overflow: hidden; }
  .h-logo-left  { float: left;  width: 66px; }
  .h-logo-right { float: right; width: 66px; text-align: right; }
  .h-center     { margin: 0 74px; text-align: center; }
  .logo-img     { width: 60px; height: 60px; background: white; padding: 3px; }
  .school-name  { font-size: 13pt; font-weight: bold; color: #e8c57a; letter-spacing: 1px; }
  .school-sub   { font-size: 7.5pt; color: #ffffffaa; margin-top: 1px; }
  .bulletin-title { font-size: 11.5pt; font-weight: bold; color: white; margin-top: 5px; text-transform: uppercase; letter-spacing: 1px; }
  .annee-label  { font-size: 8pt; color: #e8c57a; margin-top: 3px; }
  .period-badge { background: #c8913a; color: white; font-weight: bold; font-size: 7.5pt; padding: 1px 7px; }

  /* ── Student info ── */
  .info-band { background: #1e4a4e; padding: 7px 14px; border-bottom: 3px solid #c8913a; }
  .info-table { width: 100%; border-collapse: collapse; }
  .info-table td { padding: 2px 6px; color: white; font-size: 8pt; }
  .info-label { color: #e8c57a; font-weight: bold; }

  /* ── Legend ── */
  .legend-box { margin: 8px 12px 5px; border: 1px solid #b2d8da; background: #f0f9f9; padding: 5px 8px; }
  .legend-title { font-weight: bold; font-size: 7.5pt; color: #16363a; margin-bottom: 3px; }
  .legend-table { border-collapse: collapse; width: 100%; }
  .legend-table th { padding: 2px 6px; font-size: 7pt; background: #16363a; color: white; border: 1px solid #16363a; }
  .legend-table td { padding: 2px 6px; font-size: 7pt; border: 1px solid #b2d8da; text-align: center; }
  .badge-a   { background: #16a34a; color: white; padding: 0 5px; font-size: 7.5pt; font-weight: bold; }
  .badge-eva { background: #c8913a; color: white; padding: 0 5px; font-size: 7.5pt; font-weight: bold; }
  .badge-na  { background: #dc2626; color: white; padding: 0 5px; font-size: 7.5pt; font-weight: bold; }
  .badge-dash { color: #9ca3af; }

  /* ── Subject sections ── */
  .section-wrap { margin: 0 12px 8px; }
  .section-head { background: #16363a; color: white; padding: 4px 10px; font-weight: bold; font-size: 9pt; border-left: 4px solid #c8913a; }
  table.comp { width: 100%; border-collapse: collapse; }
  table.comp th { background: #d9eeef; color: #16363a; padding: 3px 6px; border: 1px solid #b2d8da; font-size: 7.5pt; text-align: center; }
  table.comp th.desc { text-align: left; }
  table.comp td { padding: 3px 6px; border: 1px solid #e5e7eb; font-size: 8pt; }
  table.comp td.center { text-align: center; }
  table.comp tbody tr:nth-child(even) { background: #f5fbfb; }
  .comp-code { color: #1e4a4e; font-weight: bold; }

  /* ── Comments ── */
  .comment-area { margin: 0 12px 6px; }
  .comment-label { font-weight: bold; font-size: 8pt; color: #16363a; margin-bottom: 2px; }
  .comment-box { border: 1px solid #b2d8da; min-height: 30px; padding: 5px 7px; background: #f0f9f9; font-size: 8pt; }
  .comment-dir { border-color: #c8913a; background: #fef9f0; }

  /* ── Signatures ── */
  .sig-section { margin: 8px 12px 0; border-top: 2px solid #16363a; padding-top: 6px; }
  .sig-table { width: 100%; border-collapse: collapse; }
  .sig-table td { padding: 3px 5px; vertical-align: top; }
  .sig-box { border: 1px solid #b2d8da; min-height: 50px; padding: 4px 7px; background: #f8fafc; }
  .sig-title { font-weight: bold; font-size: 7.5pt; color: #16363a; margin-bottom: 3px; }
</style>
</head>
<body>

@php
  use App\Enums\PeriodEnum;

  // Embed logo as base64 for reliable DomPDF rendering
  $logoPath = public_path('images/in tech.jpg');
  $logoSrc  = file_exists($logoPath)
      ? 'data:image/jpeg;base64,' . base64_encode(file_get_contents($logoPath))
      : '';

  // Build grade lookup per period: [period][competence_id] => grade
  $gradeLookup = [];
  foreach (['T1' => $t1 ?? null, 'T2' => $t2 ?? null, 'T3' => $t3 ?? null] as $p => $b) {
    if ($b) {
      foreach ($b->grades as $g) {
        $gradeLookup[$p][$g->competence_id] = $g;
      }
    }
  }

  $refBulletin   = $t1 ?? $t2 ?? $t3 ?? $bulletin;
  $subjectGroups = $refBulletin->grades->groupBy(fn($g) => $g->competence->subject_id);

  if (! function_exists('presStatusBadge')) {
    function presStatusBadge(?object $grade): string {
      if (! $grade || $grade->competence_status === null) {
        return '<span class="badge-dash">&#8212;</span>';
      }
      $v   = is_string($grade->competence_status) ? $grade->competence_status : $grade->competence_status->value;
      $cls = match(strtoupper($v)) {
        'A'   => 'badge-a',
        'EVA' => 'badge-eva',
        'NA'  => 'badge-na',
        default => 'badge-dash',
      };
      return '<span class="' . $cls . '">' . htmlspecialchars($v) . '</span>';
    }
  }
@endphp

{{-- ── HEADER ── --}}
<div class="header-band">
  <div class="h-logo-left">
    @if($logoSrc)
      <img src="{{ $logoSrc }}" class="logo-img" alt="INTEC" />
    @else
      <div style="width:60px;height:60px;background:white;text-align:center;padding-top:20px;font-size:8pt;color:#16363a;font-weight:bold;">INTEC</div>
    @endif
  </div>
  <div class="h-logo-right">
    <div style="width:60px;height:60px;background:white;text-align:center;padding-top:20px;">
      <span style="font-size:7.5pt;color:#16363a;font-weight:bold;">MEN</span>
    </div>
  </div>
  <div class="h-center">
    <div class="school-name">INTEC ÉCOLE</div>
    <div class="school-sub">Système de gestion des bulletins scolaires</div>
    <div class="bulletin-title">Bilan des Acquisitions</div>
    <div class="annee-label">
      Année scolaire : {{ $bulletin->academicYear->label }}
      &nbsp;<span class="period-badge">{{ PeriodEnum::from($bulletin->period)->label() }}</span>
    </div>
  </div>
</div>

{{-- ── STUDENT INFO ── --}}
<div class="info-band">
  <table class="info-table">
    <tr>
      <td><span class="info-label">Élève :</span> {{ $bulletin->student->full_name }}</td>
      <td><span class="info-label">Matricule :</span> {{ $bulletin->student->matricule }}</td>
      <td><span class="info-label">Date de naissance :</span> {{ $bulletin->student->birth_date?->format('d/m/Y') ?? '&#8212;' }}</td>
    </tr>
    <tr>
      <td><span class="info-label">Classe :</span> {{ $bulletin->classroom->label }}</td>
      <td><span class="info-label">Section :</span> {{ $bulletin->classroom->section }}</td>
      <td><span class="info-label">Enseignant(e) :</span> {{ $bulletin->classroom->teacher?->name ?? '&#8212;' }}</td>
    </tr>
  </table>
</div>

{{-- ── LEGEND ── --}}
<div class="legend-box">
  <div class="legend-title">Grille d'évaluation</div>
  <table class="legend-table">
    <thead>
      <tr>
        <th>Sigle</th>
        <th>Signification</th>
        <th style="text-align:left">Description</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td><span class="badge-a">A</span></td>
        <td>Acquis</td>
        <td style="text-align:left">L'enfant maîtrise et réussit la compétence de façon autonome.</td>
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

{{-- ── COMPETENCES BY SUBJECT ── --}}
@foreach($subjectGroups as $subjectId => $gradeGroup)
@php $subject = $gradeGroup->first()->competence->subject; @endphp
<div class="section-wrap">
  <div class="section-head">{{ $subject->name }}</div>
  <table class="comp">
    <thead>
      <tr>
        <th class="desc" style="width:55%">Compétences / Objectifs</th>
        <th style="width:15%">1er Trimestre</th>
        <th style="width:15%">2ème Trimestre</th>
        <th style="width:15%">3ème Trimestre</th>
      </tr>
    </thead>
    <tbody>
      @foreach($subject->competences()->orderBy('order')->get() as $competence)
      <tr>
        <td>
          <span class="comp-code">{{ $competence->code }}</span>
          &#8212; {{ $competence->description }}
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

{{-- ── OBSERVATIONS ── --}}
<div class="comment-area">
  <div class="comment-label">Observation de l'enseignant(e) :</div>
  <div class="comment-box">{{ $bulletin->teacher_comment ?? '' }}</div>
</div>
<div class="comment-area">
  <div class="comment-label" style="color:#a07020;">Mot de la Direction :</div>
  <div class="comment-box comment-dir">{{ $bulletin->direction_comment ?? '' }}</div>
</div>

{{-- ── SIGNATURES ── --}}
<div class="sig-section">
  <table class="sig-table">
    <tr>
      <td style="width:33%;padding-right:5px;">
        <div class="sig-box"><div class="sig-title">Signature de l'enseignant(e)</div></div>
      </td>
      <td style="width:33%;padding:0 3px;">
        <div class="sig-box"><div class="sig-title">Cachet et signature de la Direction</div></div>
      </td>
      <td style="width:34%;padding-left:5px;">
        <div class="sig-box"><div class="sig-title">Signature des parents / tuteurs</div></div>
      </td>
    </tr>
  </table>
</div>

</body>
</html>
