<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
  * { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 9.5pt; }
  body { margin: 18px; color: #1a1a1a; }
  .header { text-align: center; border-bottom: 3px solid #1e40af; padding-bottom: 10px; margin-bottom: 14px; }
  .title { font-size: 15pt; font-weight: bold; color: #1e40af; }
  .student-info { background: #eff6ff; padding: 10px; border-radius: 4px; margin-bottom: 14px; border: 1px solid #bfdbfe; }
  .student-info table { width: 100%; border-collapse: collapse; }
  .student-info td { padding: 3px 8px; }
  .subject-header { background: #1e40af; color: white; padding: 5px 10px; font-weight: bold; font-size: 10pt; margin-top: 10px; }
  table.grades { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
  table.grades th { background: #dbeafe; padding: 4px 6px; border: 1px solid #93c5fd; text-align: center; font-size: 8pt; }
  table.grades td { padding: 4px 6px; border: 1px solid #e5e7eb; vertical-align: top; }
  table.grades tr:nth-child(even) { background: #f8fafc; }
  .total-row { background: #1e40af; color: white; font-weight: bold; }
  .moyenne-row { background: #dbeafe; font-weight: bold; }
  .appr-box { margin-top: 8px; padding: 8px; border: 1px solid #bfdbfe; border-radius: 4px; min-height: 35px; }
  .comment-box { margin-top: 6px; padding: 8px; border: 1px solid #e5e7eb; border-radius: 4px; min-height: 35px; }
  .signatures { margin-top: 15px; border-top: 2px solid #e5e7eb; padding-top: 10px; }
  .sig-box { border: 1px solid #d1d5db; min-height: 55px; padding: 6px 8px; border-radius: 3px; font-size: 8.5pt; }
</style>
</head>
<body>

<div class="header">
  <div class="title">CARNET D'ÉVALUATION {{ strtoupper($bulletin->classroom->code) }} — INTEC ÉCOLE</div>
  <div style="font-size:10pt;color:#3b82f6">Année Scolaire : {{ $bulletin->academicYear->label }}</div>
</div>

<div class="student-info">
  <table>
    <tr>
      <td><strong>Élève :</strong> {{ $bulletin->student->full_name }}</td>
      <td><strong>Matricule :</strong> {{ $bulletin->student->matricule }}</td>
      <td><strong>Classe :</strong> {{ $bulletin->classroom->code }} — Section {{ $bulletin->classroom->section }}</td>
    </tr>
    <tr>
      <td><strong>Date de naissance :</strong> {{ $bulletin->student->birth_date->format('d/m/Y') }}</td>
      <td><strong>Période :</strong> {{ \App\Enums\PeriodEnum::from($bulletin->period)->label() }}</td>
      <td><strong>Enseignant(e) :</strong> {{ $bulletin->classroom->teacher?->name ?? '—' }}</td>
    </tr>
  </table>
</div>

@php
  $subjectGroups = $bulletin->grades->groupBy('competence.subject_id');
  $totalMax = 0;
@endphp

@foreach($subjectGroups as $subjectId => $gradeGroup)
  @php $subject = $gradeGroup->first()->competence->subject; $totalMax += $subject->max_score; @endphp
  <div class="subject-header">{{ $subject->name }} &nbsp;/{{ $subject->max_score }}</div>
  <table class="grades">
    <thead>
      <tr>
        <th style="width:60%;text-align:left">Compétence</th>
        <th style="width:13%">Note obtenue</th>
        <th style="width:13%">Note max</th>
        <th style="width:14%">Appré.</th>
      </tr>
    </thead>
    <tbody>
      @foreach($subject->competences()->orderBy('order')->get() as $competence)
        @php $grade = $gradeGroup->firstWhere('competence_id', $competence->id); @endphp
        <tr>
          <td><strong>{{ $competence->code }}</strong> — {{ $competence->description }}</td>
          <td style="text-align:center;font-weight:bold">{{ $grade?->score ?? '—' }}</td>
          <td style="text-align:center;color:#6b7280">{{ $competence->max_score }}</td>
          <td style="text-align:center">
            @if($grade?->score !== null)
              @php $pct = $grade->score / $competence->max_score * 100; @endphp
              {{ $pct >= 80 ? 'TB' : ($pct >= 60 ? 'B' : ($pct >= 40 ? 'AB' : 'I')) }}
            @else —
            @endif
          </td>
        </tr>
      @endforeach
      <tr style="background:#dbeafe;font-weight:bold">
        <td>Sous-total {{ $subject->name }}</td>
        <td style="text-align:center">{{ number_format($gradeGroup->sum('score'), 2) }}</td>
        <td style="text-align:center">{{ $subject->max_score }}</td>
        <td></td>
      </tr>
    </tbody>
  </table>
@endforeach

{{-- Récapitulatif --}}
<table class="grades" style="margin-top:10px">
  <tr class="total-row">
    <td style="width:60%;padding:5px 8px"><strong>TOTAL SUR {{ $totalMax }}</strong></td>
    <td colspan="3" style="text-align:center;font-size:11pt"><strong>{{ $bulletin->total_score ?? '—' }}</strong></td>
  </tr>
  <tr class="moyenne-row">
    <td style="padding:5px 8px"><strong>MOYENNE SUR 20</strong></td>
    <td colspan="3" style="text-align:center;font-size:13pt;font-weight:bold;color:#1e40af">
      {{ $bulletin->moyenne ?? '—' }}/20
    </td>
  </tr>
  <tr>
    <td style="padding:4px 8px">Moyenne de la classe</td>
    <td colspan="3" style="text-align:center">{{ $bulletin->class_moyenne ?? '—' }}/20</td>
  </tr>
  @if($bulletin->appreciation)
  <tr>
    <td style="padding:4px 8px">Appréciation générale</td>
    <td colspan="3" style="text-align:center;font-weight:bold">{{ $bulletin->appreciation }}</td>
  </tr>
  @endif
</table>

@if($bulletin->teacher_comment)
<div class="comment-box">
  <strong>Commentaire de l'enseignant(e) :</strong><br>{{ $bulletin->teacher_comment }}
</div>
@endif

@if($bulletin->direction_comment)
<div class="comment-box" style="border-color:#1e40af">
  <strong>Mot de la Direction :</strong><br>{{ $bulletin->direction_comment }}
</div>
@endif

<div class="signatures" style="margin-top:18px">
  <table style="width:100%">
    <tr>
      <td style="width:33%;padding-right:8px"><div class="sig-box"><strong>Signature de l'enseignant(e)</strong></div></td>
      <td style="width:33%;padding:0 4px"><div class="sig-box"><strong>Cachet et signature de la Direction</strong></div></td>
      <td style="width:33%;padding-left:8px"><div class="sig-box"><strong>Signature des parents</strong></div></td>
    </tr>
  </table>
</div>

</body>
</html>
