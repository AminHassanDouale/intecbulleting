<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
  * { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 9pt; margin: 0; padding: 0; box-sizing: border-box; }
  body { margin: 0; color: #1a1a1a; background: #fff; }
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
  .info-band { background: #1e4a4e; padding: 7px 14px; border-bottom: 3px solid #c8913a; }
  .info-table { width: 100%; border-collapse: collapse; }
  .info-table td { padding: 2px 6px; color: white; font-size: 8pt; }
  .info-label { color: #e8c57a; font-weight: bold; }
  .section-wrap { margin: 0 12px 8px; }
  .section-head { background: #16363a; color: white; padding: 4px 10px; font-weight: bold; font-size: 9pt; border-left: 4px solid #c8913a; }
  .section-sub  { font-size: 7.5pt; font-weight: normal; color: #d9eeef; }
  table.grades { width: 100%; border-collapse: collapse; }
  table.grades th { background: #d9eeef; color: #16363a; padding: 3px 6px; border: 1px solid #b2d8da; font-size: 7.5pt; text-align: center; }
  table.grades th.left { text-align: left; }
  table.grades td { padding: 3px 6px; border: 1px solid #e5e7eb; font-size: 8pt; vertical-align: middle; }
  table.grades tbody tr:nth-child(even) { background: #f5fbfb; }
  .comp-code { color: #1e4a4e; font-weight: bold; }
  .subtotal-row td { background: #d9eeef; font-weight: bold; color: #16363a; }
  .summary-wrap { margin: 4px 12px 8px; border: 2px solid #16363a; }
  .summary-head { background: #16363a; color: white; padding: 4px 10px; font-weight: bold; font-size: 9pt; text-align: center; }
  table.summary { width: 100%; border-collapse: collapse; }
  table.summary td { padding: 4px 10px; border-bottom: 1px solid #b2d8da; font-size: 8.5pt; }
  .total-row td { background: #1e4a4e; color: white; font-weight: bold; font-size: 9.5pt; }
  .moyenne-row td { background: #f0f9f9; }
  .moyenne-val { font-size: 13pt; font-weight: bold; color: #16363a; }
  .appre-tb  { background: #16a34a; color: white; font-weight: bold; font-size: 8pt; padding: 1px 8px; }
  .appre-b   { background: #1e4a4e; color: white; font-weight: bold; font-size: 8pt; padding: 1px 8px; }
  .appre-ab  { background: #c8913a; color: white; font-weight: bold; font-size: 8pt; padding: 1px 8px; }
  .appre-i   { background: #dc2626; color: white; font-weight: bold; font-size: 8pt; padding: 1px 8px; }
  .comment-area { margin: 0 12px 6px; }
  .comment-label { font-weight: bold; font-size: 8pt; color: #16363a; margin-bottom: 2px; }
  .comment-box { border: 1px solid #b2d8da; min-height: 30px; padding: 5px 7px; background: #f0f9f9; font-size: 8pt; }
  .comment-dir { border-color: #c8913a; background: #fef9f0; }
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

  $logoPath = public_path('images/in tech.jpg');
  $logoSrc  = file_exists($logoPath)
      ? 'data:image/jpeg;base64,' . base64_encode(file_get_contents($logoPath))
      : '';

  $subjectGroups = $bulletin->grades->groupBy('competence.subject_id');
  $totalMax      = 0;

  if (! function_exists('primaireAppre')) {
    function primaireAppre(float $pct): string {
      if ($pct >= 80) return '<span class="appre-tb">TB</span>';
      if ($pct >= 60) return '<span class="appre-b">B</span>';
      if ($pct >= 40) return '<span class="appre-ab">AB</span>';
      return '<span class="appre-i">I</span>';
    }
  }

  if (! function_exists('primaireMoyenneLabel')) {
    function primaireMoyenneLabel(float $m): array {
      if ($m >= 16) return ['Très Bien',   'appre-tb'];
      if ($m >= 14) return ['Bien',        'appre-b'];
      if ($m >= 12) return ['Assez Bien',  'appre-ab'];
      if ($m >= 10) return ['Passable',    'appre-ab'];
      return ['Insuffisant', 'appre-i'];
    }
  }

  $periodLabel   = PeriodEnum::from($bulletin->period)->label();
  $birthDate     = $bulletin->student->birth_date ? $bulletin->student->birth_date->format('d/m/Y') : '&mdash;';
  $teacherName   = $bulletin->classroom->teacher?->name ?? '&mdash;';
  $classMoyenne  = $bulletin->class_moyenne !== null ? $bulletin->class_moyenne . '/20' : '&mdash;';
  $totalScore    = $bulletin->total_score ?? '&mdash;';
  $moyenneStr    = '';
  $moyenneClass  = '';
  $moyenneTitle  = '';
  if ($bulletin->moyenne !== null) {
    [$moyenneTitle, $moyenneClass] = primaireMoyenneLabel((float) $bulletin->moyenne);
    $moyenneStr = $bulletin->moyenne . '/20';
  }
@endphp

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
    <div class="bulletin-title">Carnet d'Évaluation &mdash; {{ $bulletin->classroom->code }}</div>
    <div class="annee-label">
      Année scolaire : {{ $bulletin->academicYear->label }}
      &nbsp;<span class="period-badge">{{ $periodLabel }}</span>
    </div>
  </div>
</div>

<div class="info-band">
  <table class="info-table">
    <tr>
      <td><span class="info-label">Élève :</span> {{ $bulletin->student->full_name }}</td>
      <td><span class="info-label">Matricule :</span> {{ $bulletin->student->matricule }}</td>
      <td><span class="info-label">Date de naissance :</span> {!! $birthDate !!}</td>
    </tr>
    <tr>
      <td><span class="info-label">Classe :</span> {{ $bulletin->classroom->code }} &mdash; Section {{ $bulletin->classroom->section }}</td>
      <td><span class="info-label">Enseignant(e) :</span> {!! $teacherName !!}</td>
      <td><span class="info-label">Appréciation :</span> {{ $bulletin->appreciation ?? '' }}</td>
    </tr>
  </table>
</div>

@foreach($subjectGroups as $subjectId => $gradeGroup)
@php
  $subject   = $gradeGroup->first()->competence->subject;
  $totalMax += $subject->max_score;
  $rows      = [];
  foreach ($subject->competences()->orderBy('order')->get() as $competence) {
    $grade = $gradeGroup->firstWhere('competence_id', $competence->id);
    $score = $grade?->score;
    $appreHtml = '&mdash;';
    if ($score !== null && $competence->max_score > 0) {
      $appreHtml = primaireAppre((float)$score / (float)$competence->max_score * 100);
    }
    $rows[] = [
      'code'      => $competence->code,
      'desc'      => $competence->description,
      'score'     => $score !== null ? $score : '&mdash;',
      'max_score' => $competence->max_score,
      'appre'     => $appreHtml,
    ];
  }
  $subtotal = number_format($gradeGroup->sum('score'), 2);
@endphp
<div class="section-wrap">
  <div class="section-head">
    {{ $subject->name }}
    <span class="section-sub">&nbsp;/ {{ $subject->max_score }} points</span>
  </div>
  <table class="grades">
    <thead>
      <tr>
        <th class="left" style="width:55%">Compétence</th>
        <th style="width:15%">Note obtenue</th>
        <th style="width:15%">Note max</th>
        <th style="width:15%">Appré.</th>
      </tr>
    </thead>
    <tbody>
      @foreach($rows as $row)
      <tr>
        <td><span class="comp-code">{{ $row['code'] }}</span> &mdash; {{ $row['desc'] }}</td>
        <td style="text-align:center;font-weight:bold">{!! $row['score'] !!}</td>
        <td style="text-align:center;color:#6b7280">{{ $row['max_score'] }}</td>
        <td style="text-align:center">{!! $row['appre'] !!}</td>
      </tr>
      @endforeach
      <tr class="subtotal-row">
        <td>Sous-total {{ $subject->name }}</td>
        <td style="text-align:center">{{ $subtotal }}</td>
        <td style="text-align:center">{{ $subject->max_score }}</td>
        <td></td>
      </tr>
    </tbody>
  </table>
</div>
@endforeach

<div class="summary-wrap">
  <div class="summary-head">RÉCAPITULATIF</div>
  <table class="summary">
    <tr class="total-row">
      <td style="width:55%">Total des points</td>
      <td colspan="2" style="text-align:center">{!! $totalScore !!} / {{ $totalMax }}</td>
    </tr>
    <tr class="moyenne-row">
      <td>Moyenne sur 20</td>
      <td style="text-align:center"><span class="moyenne-val">{{ $moyenneStr ?: '&mdash;' }}</span></td>
      <td style="text-align:center">
        @if($moyenneTitle)
          <span class="{{ $moyenneClass }}">{{ $moyenneTitle }}</span>
        @endif
      </td>
    </tr>
    <tr>
      <td>Moyenne de la classe</td>
      <td colspan="2" style="text-align:center">{!! $classMoyenne !!}</td>
    </tr>
    @if($bulletin->appreciation)
    <tr>
      <td>Appréciation générale</td>
      <td colspan="2" style="text-align:center;font-weight:bold">{{ $bulletin->appreciation }}</td>
    </tr>
    @endif
  </table>
</div>

<div class="comment-area">
  <div class="comment-label">Commentaire de l'enseignant(e) :</div>
  <div class="comment-box">{{ $bulletin->teacher_comment ?? '' }}</div>
</div>
<div class="comment-area">
  <div class="comment-label" style="color:#a07020;">Mot de la Direction :</div>
  <div class="comment-box comment-dir">{{ $bulletin->direction_comment ?? '' }}</div>
</div>

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
