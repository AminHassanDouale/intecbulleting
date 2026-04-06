{{-- ============================================================
     Modèle bulletin PRIMAIRE — aperçu statique (données fictives)
     ============================================================ --}}
<div style="font-family: Arial, sans-serif; font-size: 9.5pt; color: #1a1a1a; max-width: 820px; margin: 18px auto;">

    {{-- ── Header ─────────────────────────────────────────────────────────────── --}}
    <div style="text-align:center;border-bottom:3px solid #1e40af;padding-bottom:10px;margin-bottom:14px;">
        <div style="font-size:15pt;font-weight:bold;color:#1e40af;">CARNET D'ÉVALUATION CE1-A — INTEC ÉCOLE</div>
        <div style="font-size:10pt;color:#3b82f6;">Année Scolaire : 2025 / 2026 &bull; 1er Trimestre</div>
    </div>

    {{-- ── Student info ─────────────────────────────────────────────────────────── --}}
    <div style="background:#eff6ff;padding:10px;border-radius:4px;margin-bottom:14px;border:1px solid #bfdbfe;">
        <table style="width:100%;border-collapse:collapse;">
            <tr>
                <td style="padding:3px 8px;"><strong>Élève :</strong> TRAORÉ Mamadou</td>
                <td style="padding:3px 8px;"><strong>Matricule :</strong> INT-2025-0118</td>
                <td style="padding:3px 8px;"><strong>Classe :</strong> CE1 — Section A</td>
            </tr>
            <tr>
                <td style="padding:3px 8px;"><strong>Date de naissance :</strong> 07/09/2017</td>
                <td style="padding:3px 8px;"><strong>Période :</strong> 1er Trimestre</td>
                <td style="padding:3px 8px;"><strong>Enseignant(e) :</strong> M. Coulibaly Ibrahim</td>
            </tr>
        </table>
    </div>

    @php
    $subjects = [
        [
            'name' => 'Français',
            'max'  => 20,
            'competences' => [
                ['code' => 'FR1', 'desc' => 'Lecture et compréhension de texte',   'max' => 5, 'score' => 4.5],
                ['code' => 'FR2', 'desc' => 'Expression écrite',                    'max' => 5, 'score' => 3.5],
                ['code' => 'FR3', 'desc' => 'Grammaire et conjugaison',             'max' => 5, 'score' => 4.0],
                ['code' => 'FR4', 'desc' => 'Orthographe et dictée',                'max' => 5, 'score' => 3.0],
            ],
        ],
        [
            'name' => 'Mathématiques',
            'max'  => 20,
            'competences' => [
                ['code' => 'MA1', 'desc' => 'Calcul mental et opérations',          'max' => 7, 'score' => 6.5],
                ['code' => 'MA2', 'desc' => 'Résolution de problèmes',              'max' => 7, 'score' => 5.0],
                ['code' => 'MA3', 'desc' => 'Géométrie et mesures',                 'max' => 6, 'score' => 5.5],
            ],
        ],
        [
            'name' => 'Sciences & Découverte du monde',
            'max'  => 10,
            'competences' => [
                ['code' => 'SC1', 'desc' => 'Observation du milieu naturel',        'max' => 5, 'score' => 4.5],
                ['code' => 'SC2', 'desc' => 'Vie des êtres vivants',                'max' => 5, 'score' => 4.0],
            ],
        ],
        [
            'name' => 'Éducation Civique & Morale',
            'max'  => 10,
            'competences' => [
                ['code' => 'EC1', 'desc' => 'Règles de vie en société',             'max' => 5, 'score' => 5.0],
                ['code' => 'EC2', 'desc' => 'Droits et devoirs de l\'élève',        'max' => 5, 'score' => 4.5],
            ],
        ],
        [
            'name' => 'Activités Physiques & Sportives',
            'max'  => 10,
            'competences' => [
                ['code' => 'SP1', 'desc' => 'Motricité et coordination',            'max' => 5, 'score' => 5.0],
                ['code' => 'SP2', 'desc' => 'Esprit d\'équipe et fair-play',        'max' => 5, 'score' => 4.5],
            ],
        ],
    ];
    $totalMax   = array_sum(array_column($subjects, 'max'));
    $totalScore = 0;
    foreach ($subjects as $s) {
        $totalScore += array_sum(array_column($s['competences'], 'score'));
    }
    $moyenne = $totalMax > 0 ? round(($totalScore / $totalMax) * 20, 2) : 0;
    $appreciation = $moyenne >= 16 ? 'Très Bien' : ($moyenne >= 14 ? 'Bien' : ($moyenne >= 12 ? 'Assez Bien' : ($moyenne >= 10 ? 'Passable' : 'Insuffisant')));
    @endphp

    {{-- ── Subjects ──────────────────────────────────────────────────────────── --}}
    @foreach($subjects as $subject)
    <div style="background:#1e40af;color:white;padding:5px 10px;font-weight:bold;font-size:10pt;margin-top:10px;">
        {{ $subject['name'] }} &nbsp;/{{ $subject['max'] }}
    </div>
    <table style="width:100%;border-collapse:collapse;margin-bottom:6px;">
        <thead>
            <tr>
                <th style="background:#dbeafe;padding:4px 6px;border:1px solid #93c5fd;text-align:left;font-size:8pt;width:58%;">Compétence</th>
                <th style="background:#dbeafe;padding:4px 6px;border:1px solid #93c5fd;text-align:center;font-size:8pt;width:14%;">Note obtenue</th>
                <th style="background:#dbeafe;padding:4px 6px;border:1px solid #93c5fd;text-align:center;font-size:8pt;width:14%;">Note max</th>
                <th style="background:#dbeafe;padding:4px 6px;border:1px solid #93c5fd;text-align:center;font-size:8pt;width:14%;">Appré.</th>
            </tr>
        </thead>
        <tbody>
            @foreach($subject['competences'] as $i => $comp)
            @php
                $pct  = $comp['max'] > 0 ? ($comp['score'] / $comp['max'] * 100) : 0;
                $appr = $pct >= 80 ? 'TB' : ($pct >= 60 ? 'B' : ($pct >= 40 ? 'AB' : 'I'));
                $apprColor = $pct >= 80 ? '#15803d' : ($pct >= 60 ? '#1d4ed8' : ($pct >= 40 ? '#b45309' : '#b91c1c'));
            @endphp
            <tr style="{{ $i % 2 !== 0 ? 'background:#f8fafc;' : '' }}">
                <td style="padding:4px 6px;border:1px solid #e5e7eb;vertical-align:top;">
                    <strong>{{ $comp['code'] }}</strong> — {{ $comp['desc'] }}
                </td>
                <td style="padding:4px 6px;border:1px solid #e5e7eb;text-align:center;font-weight:bold;">{{ $comp['score'] }}</td>
                <td style="padding:4px 6px;border:1px solid #e5e7eb;text-align:center;color:#6b7280;">{{ $comp['max'] }}</td>
                <td style="padding:4px 6px;border:1px solid #e5e7eb;text-align:center;font-weight:bold;color:{{ $apprColor }};">{{ $appr }}</td>
            </tr>
            @endforeach
            <tr style="background:#dbeafe;font-weight:bold;">
                <td style="padding:4px 6px;border:1px solid #93c5fd;">Sous-total {{ $subject['name'] }}</td>
                <td style="padding:4px 6px;border:1px solid #93c5fd;text-align:center;">{{ number_format(array_sum(array_column($subject['competences'], 'score')), 2) }}</td>
                <td style="padding:4px 6px;border:1px solid #93c5fd;text-align:center;">{{ $subject['max'] }}</td>
                <td style="padding:4px 6px;border:1px solid #93c5fd;"></td>
            </tr>
        </tbody>
    </table>
    @endforeach

    {{-- ── Summary ───────────────────────────────────────────────────────────── --}}
    <table style="width:100%;border-collapse:collapse;margin-top:10px;">
        <tr style="background:#1e40af;color:white;font-weight:bold;">
            <td style="width:60%;padding:5px 8px;"><strong>TOTAL SUR {{ $totalMax }}</strong></td>
            <td colspan="3" style="text-align:center;font-size:11pt;"><strong>{{ number_format($totalScore, 1) }}</strong></td>
        </tr>
        <tr style="background:#dbeafe;font-weight:bold;">
            <td style="padding:5px 8px;"><strong>MOYENNE SUR 20</strong></td>
            <td colspan="3" style="text-align:center;font-size:13pt;font-weight:bold;color:#1e40af;">
                {{ number_format($moyenne, 2) }}/20
            </td>
        </tr>
        <tr>
            <td style="padding:4px 8px;border:1px solid #e5e7eb;">Moyenne de la classe</td>
            <td colspan="3" style="text-align:center;border:1px solid #e5e7eb;">13.85/20</td>
        </tr>
        <tr>
            <td style="padding:4px 8px;border:1px solid #e5e7eb;">Appréciation générale</td>
            <td colspan="3" style="text-align:center;font-weight:bold;border:1px solid #e5e7eb;color:#1e40af;">{{ $appreciation }}</td>
        </tr>
    </table>

    {{-- ── Comments ─────────────────────────────────────────────────────────── --}}
    <div style="margin-top:8px;padding:8px;border:1px solid #e5e7eb;border-radius:4px;min-height:35px;">
        <strong>Commentaire de l'enseignant(e) :</strong><br>
        Mamadou est un élève sérieux et appliqué. Il doit toutefois renforcer son orthographe. Bons résultats en mathématiques.
    </div>

    <div style="margin-top:6px;padding:8px;border:1px solid #1e40af;border-radius:4px;min-height:35px;">
        <strong>Mot de la Direction :</strong><br>
        Des résultats encourageants. Nous comptons sur votre assiduité pour le prochain trimestre.
    </div>

    {{-- ── Signatures ───────────────────────────────────────────────────────── --}}
    <div style="margin-top:18px;border-top:2px solid #e5e7eb;padding-top:10px;padding-bottom:10px;">
        <table style="width:100%;">
            <tr>
                <td style="width:33%;padding-right:8px;">
                    <div style="border:1px solid #d1d5db;min-height:55px;padding:6px 8px;border-radius:3px;font-size:8.5pt;">
                        <strong>Signature de l'enseignant(e)</strong>
                    </div>
                </td>
                <td style="width:33%;padding:0 4px;">
                    <div style="border:1px solid #d1d5db;min-height:55px;padding:6px 8px;border-radius:3px;font-size:8.5pt;">
                        <strong>Cachet et signature de la Direction</strong>
                    </div>
                </td>
                <td style="width:33%;padding-left:8px;">
                    <div style="border:1px solid #d1d5db;min-height:55px;padding:6px 8px;border-radius:3px;font-size:8.5pt;">
                        <strong>Signature des parents</strong>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    {{-- ── Legend bar (Primaire) ────────────────────────────────────────────── --}}
    <div style="margin-top:10px;background:#f8fafc;border:1px solid #e5e7eb;border-radius:4px;padding:8px 12px;font-size:8pt;color:#374151;">
        <strong>Légende des appréciations :</strong>
        &nbsp;&nbsp;
        <span style="color:#15803d;font-weight:bold;">TB</span> = Très Bien (≥ 80%)
        &nbsp;&bull;&nbsp;
        <span style="color:#1d4ed8;font-weight:bold;">B</span> = Bien (≥ 60%)
        &nbsp;&bull;&nbsp;
        <span style="color:#b45309;font-weight:bold;">AB</span> = Assez Bien (≥ 40%)
        &nbsp;&bull;&nbsp;
        <span style="color:#b91c1c;font-weight:bold;">I</span> = Insuffisant (&lt; 40%)
    </div>

</div>
