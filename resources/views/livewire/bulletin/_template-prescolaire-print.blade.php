{{-- ============================================================
     Modèle bulletin PRÉSCOLAIRE — format carnet d'évaluation
     Données fictives, même mise en page que l'impression réelle
     ============================================================ --}}
@php
$yearLabel     = date('Y') . ' / ' . (date('Y') + 1);
$romanNumerals = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X'];

$subjects = [
    [
        'name'       => 'Langage et Communication',
        'max_score'  => 30,
        'scale_type' => 'competence',
        'competences' => [
            ['code' => 'L1', 'description' => 'Écoute et comprend des consignes simples',      'max_score' => null],
            ['code' => 'L2', 'description' => 'S\'exprime clairement à l\'oral',                'max_score' => null],
            ['code' => 'L3', 'description' => 'Reconnaît et nomme les lettres de son prénom',  'max_score' => null],
            ['code' => 'L4', 'description' => 'Récite une comptine ou un poème',               'max_score' => null],
        ],
    ],
    [
        'name'       => 'Mathématiques et Logique',
        'max_score'  => 30,
        'scale_type' => 'competence',
        'competences' => [
            ['code' => 'M1', 'description' => 'Compte jusqu\'à 10',                        'max_score' => null],
            ['code' => 'M2', 'description' => 'Reconnaît les formes géométriques simples', 'max_score' => null],
            ['code' => 'M3', 'description' => 'Compare des quantités (plus/moins)',         'max_score' => null],
        ],
    ],
    [
        'name'       => 'Motricité et Arts',
        'max_score'  => 20,
        'scale_type' => 'competence',
        'competences' => [
            ['code' => 'P1', 'description' => 'Tient correctement un crayon',              'max_score' => null],
            ['code' => 'P2', 'description' => 'Découpe avec des ciseaux adaptés',           'max_score' => null],
            ['code' => 'P3', 'description' => 'Colorie sans dépasser les contours',         'max_score' => null],
        ],
    ],
    [
        'name'       => 'Vie Sociale et Émotionnelle',
        'max_score'  => 20,
        'scale_type' => 'competence',
        'competences' => [
            ['code' => 'V1', 'description' => 'Respecte les règles de vie en classe',       'max_score' => null],
            ['code' => 'V2', 'description' => 'Partage et joue avec les autres',            'max_score' => null],
            ['code' => 'V3', 'description' => 'Exprime ses émotions de manière appropriée', 'max_score' => null],
        ],
    ],
];

// Sample status grid [subject_idx][comp_idx][period]
$sampleStatus = [
    0 => [['A','A','A'], ['EVA','A','A'], ['EVA','EVA','A'], ['A','A','A']],
    1 => [['A','A','A'], ['EVA','A','A'], ['NA','EVA','A']],
    2 => [['A','A','A'], ['EVA','EVA','A'], ['EVA','A','A']],
    3 => [['A','A','A'], ['A','A','A'], ['EVA','A','A']],
];

// Period totals (sample)
$periodTotals = [
    'T1' => ['total' => 72.0, 'moyenne' => 7.20, 'class_moyenne' => 6.80, 'teacher_comment' => 'Bonne participation en classe. Continue tes efforts !', 'direction_comment' => 'Encourageant pour ce premier trimestre.', 'published' => true],
    'T2' => ['total' => 81.5, 'moyenne' => 8.15, 'class_moyenne' => 7.40, 'teacher_comment' => 'Très bonne progression depuis le T1, bravo !', 'direction_comment' => 'Félicitations pour ces progrès.', 'published' => true],
    'T3' => ['total' => 88.0, 'moyenne' => 8.80, 'class_moyenne' => 7.95, 'teacher_comment' => 'Excellente fin d\'année. Très belle évolution !', 'direction_comment' => 'Admis(e) en classe supérieure.', 'published' => true],
];

$maxTotal = array_sum(array_column($subjects, 'max_score'));
@endphp

{{-- ===== PAGE 1 ===== --}}

{{-- Header — two logos + BILAN DES ACQUISITIONS --}}
<div class="flex items-start justify-between mb-4">
    <div class="logo-container">
        <img src="{{ asset('images/in tech.jpg') }}" alt="INTEC École Logo">
    </div>
    <div class="flex-1 text-center px-4">
        <div class="text-[11pt] font-bold uppercase tracking-wide">INTEC ÉCOLE</div>
        <div class="text-[9pt] mt-0.5">
            Année scolaire : {{ $yearLabel }}
            &nbsp;&bull;&nbsp;
            Classe : Petite Section A
        </div>
        <div class="text-[12pt] font-extrabold uppercase mt-2 border-b-2 border-black pb-1">
            BILAN DES ACQUISITIONS
        </div>
        <div class="text-[10pt] font-bold mt-1">KONÉ AMINATA</div>
        <div class="text-[9pt] mt-0.5 text-gray-600">
            Matricule : INT-2025-0042
            &nbsp;&bull;&nbsp;
            Section : A
        </div>
    </div>
    <div class="w-24 h-24 border-2 border-black flex items-center justify-center bg-gray-50 shrink-0">
        <div class="text-center text-[7px] text-gray-400 leading-tight">Cachet<br>Officiel</div>
    </div>
</div>

{{-- Info row --}}
<table class="w-full border-collapse border border-black mb-3 text-[9px]">
    <tr>
        <td class="border border-black p-1.5 font-semibold w-[33%]">
            <span class="text-gray-500">Enseignant(e) :</span> Mme. Diallo Fatou
        </td>
        <td class="border border-black p-1.5 font-semibold w-[33%]">
            <span class="text-gray-500">Date de naissance :</span> 15/03/2020
        </td>
        <td class="border border-black p-1.5 font-semibold w-[34%]">
            <span class="text-gray-500">Niveau :</span> Préscolaire
        </td>
    </tr>
</table>

{{-- Subject tables --}}
@foreach($subjects as $idx => $subject)
<div class="font-bold text-[11pt] mb-2">
    {{ $romanNumerals[$idx] ?? ($idx + 1) }} –
    COMPÉTENCE DE BASE : {{ strtoupper($subject['name']) }}
    : /{{ $subject['max_score'] }}
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
        @foreach($subject['competences'] as $ci => $competence)
        <tr>
            <td class="border border-black bg-gray-50 p-1.5 text-left">
                <strong>{{ $competence['code'] }}</strong>
                @if($competence['description']) : {{ $competence['description'] }} @endif
            </td>
            @foreach([0,1,2] as $pi)
            @php $st = $sampleStatus[$idx][$ci][$pi] ?? null; @endphp
            <td class="border border-black p-1 text-center font-bold text-green-700 w-[6.89%]">{{ $st === 'A'   ? 'A' : '' }}</td>
            <td class="border border-black p-1 text-center font-bold text-amber-600 w-[6.89%]">{{ $st === 'EVA' ? 'EVA' : '' }}</td>
            <td class="border border-black p-1 text-center font-bold text-red-600   w-[6.89%]">{{ $st === 'NA'  ? 'NA' : '' }}</td>
            @endforeach
        </tr>
        @endforeach
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
                {{ number_format($periodTotals[$_p]['total'], 1) }}
            </th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="border border-black p-1.5 text-left font-bold">Moyenne sur 10</td>
            @foreach(['T1','T2','T3'] as $_p)
            <td class="border border-black p-1 text-center font-bold">
                {{ number_format($periodTotals[$_p]['moyenne'], 2) }}
            </td>
            @endforeach
        </tr>
        <tr>
            <td class="border border-black p-1.5 text-left font-bold">Moyenne de la classe sur 10</td>
            @foreach(['T1','T2','T3'] as $_p)
            <td class="border border-black p-1 text-center">
                {{ number_format($periodTotals[$_p]['class_moyenne'], 2) }}
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
<div class="text-center text-sm font-bold mb-1">CARNET D'ÉVALUATION PRÉSCOLAIRE</div>
<div class="font-bold text-[11pt] text-center mb-3">ANNEE SCOLAIRE : {{ $yearLabel }}</div>
<div class="text-center text-[10px] mb-3 text-gray-600">
    Élève : <strong>KONÉ Aminata</strong>
    &bull; Classe : <strong>PS-A</strong>
    &bull; Matricule : <strong>INT-2025-0042</strong>
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
                <div class="mb-4">{{ $periodTotals[$p]['teacher_comment'] }}</div>
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
                {{ $moy >= 7 ? 'text-green-700' : ($moy >= 5 ? 'text-amber-700' : 'text-red-600') }}">
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
