<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
  * { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 9pt; }
  body { margin: 18px; color: #1a1a1a; }
  .header { text-align: center; border-bottom: 3px solid #065f46; padding-bottom: 10px; margin-bottom: 14px; }
  .title { font-size: 14pt; font-weight: bold; color: #065f46; }
  .student-info { background: #ecfdf5; padding: 10px; border-radius: 4px; margin-bottom: 14px; border: 1px solid #a7f3d0; }
  .student-info table { width: 100%; border-collapse: collapse; }
  .student-info td { padding: 3px 8px; }
  table.grades { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
  table.grades thead tr { background: #065f46; color: white; }
  table.grades th { padding: 5px 8px; border: 1px solid #047857; text-align: center; font-size: 8pt; }
  table.grades td { padding: 4px 8px; border: 1px solid #d1fae5; vertical-align: middle; }
  table.grades tbody tr:nth-child(even) { background: #f0fdf4; }
  table.grades tbody tr:hover { background: #d1fae5; }
  .subject-row { background: #d1fae5 !important; font-weight: bold; color: #065f46; }
  .total-section { margin-top: 10px; border: 2px solid #065f46; border-radius: 4px; overflow: hidden; }
  .total-section .row { display: flex; justify-content: space-between; padding: 5px 12px; border-bottom: 1px solid #a7f3d0; }
  .total-section .row:last-child { border-bottom: none; }
  .total-section .row-header { background: #065f46; color: white; font-weight: bold; }
  .total-section table { width: 100%; border-collapse: collapse; }
  .total-section td { padding: 5px 10px; border-bottom: 1px solid #a7f3d0; }
  .moyenne-val { font-size: 14pt; font-weight: bold; color: #065f46; text-align: center; }
  .comment-box { margin-top: 8px; padding: 8px; border: 1px solid #a7f3d0; border-radius: 4px; min-height: 35px; }
  .signatures { margin-top: 16px; border-top: 2px solid #e5e7eb; padding-top: 10px; }
  .sig-box { border: 1px solid #d1d5db; min-height: 55px; padding: 6px 8px; border-radius: 3px; font-size: 8pt; }
  .rang { display: inline-block; background: #fbbf24; color: #78350f; padding: 2px 8px; border-radius: 10px; font-size: 8pt; font-weight: bold; }
</style>
</head>
<body>

<div class="header">
  <div class="title">BULLETIN DE NOTES — {{ strtoupper($bulletin->classroom->code) }}</div>
  <div style="font-size:9pt;color:#047857">INTEC ÉCOLE — Année Scolaire {{ $bulletin->academicYear->label }}</div>
</div>

<div class="student-info">
  <table>
    <tr>
      <td><strong>Élève :</strong> {{ $bulletin->student->full_name }}</td>
      <td><strong>Matricule :</strong> {{ $bulletin->student->matricule }}</td>
      <td><strong>Classe :</strong> {{ $bulletin->classroom->code }} — Section {{ $bulletin->classroom->section }}</td>
    </tr>
    <tr>
      <td><strong>Né(e) le :</strong> {{ $bulletin->student->birth_date->format('d/m/Y') }}</td>
      <td><strong>Période :</strong> {{ \App\Enums\PeriodEnum::from($bulletin->period)->label() }}</td>
      <td><strong>Titulaire :</strong> {{ $bulletin->classroom->teacher?->name ?? '—' }}</td>
    </tr>
  </table>
</div>

@php
  $subjectGroups = $bulletin->grades->groupBy('competence.subject_id');
  $totalMax = 0;
@endphp

<table class="grades">
  <thead>
    <tr>
      <th style="width:40%;text-align:left">Matière / Compétence</th>
      <th style="width:12%">Note</th>
      <th style="width:10%">Max</th>
      <th style="width:12%">Coeff.</th>
      <th style="width:12%">Moy. classe</th>
      <th style="width:14%">Appré.</th>
    </tr>
  </thead>
  <tbody>
    @foreach($subjectGroups as $subjectId => $gradeGroup)
      @php
        $subject   = $gradeGroup->first()->competence->subject;
        $subTotal  = $gradeGroup->sum('score');
        $totalMax += $subject->max_score;
      @endphp
      <tr class="subject-row">
        <td colspan="6">{{ $subject->name }}</td>
      </tr>
      @foreach($subject->competences()->orderBy('order')->get() as $competence)
        @php $grade = $gradeGroup->firstWhere('competence_id', $competence->id); @endphp
        <tr>
          <td style="padding-left:16px">{{ $competence->code }} — {{ Str::limit($competence->description, 55) }}</td>
          <td style="text-align:center;font-weight:{{ $grade?->score !== null ? 'bold' : 'normal' }}">
            {{ $grade?->score ?? '—' }}
          </td>
          <td style="text-align:center;color:#6b7280">{{ $competence->max_score ?? '—' }}</td>
          <td style="text-align:center">1</td>
          <td style="text-align:center">—</td>
          <td style="text-align:center;font-size:8pt">
            @if($grade?->score !== null && $competence->max_score)
              @php $pct = $grade->score / $competence->max_score * 100; @endphp
              {{ $pct >= 85 ? 'Très bien' : ($pct >= 70 ? 'Bien' : ($pct >= 55 ? 'Assez bien' : ($pct >= 40 ? 'Passable' : 'Insuffisant'))) }}
            @else —
            @endif
          </td>
        </tr>
      @endforeach
    @endforeach
  </tbody>
</table>

{{-- Récapitulatif --}}
<div class="total-section">
  <table>
    <tr style="background:#065f46;color:white;font-weight:bold">
      <td><strong>TOTAL SUR {{ $totalMax }}</strong></td>
      <td style="text-align:center"><strong>{{ $bulletin->total_score ?? '—' }}</strong></td>
      <td style="text-align:right;width:40%"></td>
    </tr>
    <tr>
      <td><strong>MOYENNE GÉNÉRALE SUR 20</strong></td>
      <td class="moyenne-val">{{ $bulletin->moyenne ?? '—' }}/20</td>
      <td style="text-align:right">
        Moyenne de la classe :
        <strong>{{ $bulletin->class_moyenne ?? '—' }}/20</strong>
      </td>
    </tr>
    @if($bulletin->appreciation)
    <tr style="background:#ecfdf5">
      <td colspan="3" style="text-align:center;font-weight:bold;color:#065f46">
        Appréciation : {{ $bulletin->appreciation }}
      </td>
    </tr>
    @endif
  </table>
</div>

@if($bulletin->teacher_comment)
<div class="comment-box" style="margin-top:10px">
  <strong>Appréciation du professeur principal :</strong><br>{{ $bulletin->teacher_comment }}
</div>
@endif

@if($bulletin->direction_comment)
<div class="comment-box" style="border-color:#065f46;margin-top:6px">
  <strong>Mot de la Direction :</strong><br>{{ $bulletin->direction_comment }}
</div>
@endif

<div class="signatures">
  <table style="width:100%">
    <tr>
      <td style="width:33%;padding-right:8px"><div class="sig-box"><strong>Signature du prof. principal</strong></div></td>
      <td style="width:33%;padding:0 4px"><div class="sig-box"><strong>Cachet et signature de la Direction</strong></div></td>
      <td style="width:33%;padding-left:8px"><div class="sig-box"><strong>Signature des parents / tuteur</strong></div></td>
    </tr>
  </table>
</div>

</body>
</html>
