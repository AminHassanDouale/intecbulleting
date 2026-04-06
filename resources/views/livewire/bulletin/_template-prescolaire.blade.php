{{-- ============================================================
     Modèle bulletin PRÉSCOLAIRE — aperçu statique (données fictives)
     ============================================================ --}}
<div style="font-family: Arial, sans-serif; font-size: 9pt; color: #1a1a1a; max-width: 820px; margin: 0 auto;">

    {{-- ── Header band ─────────────────────────────────────────────────────── --}}
    <div style="background:#1e3a8a;color:white;padding:12px 16px;border-bottom:4px solid #f59e0b;">
        <table style="width:100%;border-collapse:collapse;">
            <tr>
                <td style="width:70px;text-align:center;vertical-align:middle;">
                    <div style="width:58px;height:58px;border:2px solid rgba(255,255,255,.3);border-radius:6px;background:white;display:inline-flex;align-items:center;justify-content:center;font-size:22pt;">🏫</div>
                </td>
                <td style="text-align:center;vertical-align:middle;">
                    <div style="font-size:14pt;font-weight:bold;color:#fcd34d;letter-spacing:1px;">INTEC ÉCOLE</div>
                    <div style="font-size:8pt;color:rgba(255,255,255,.8);margin-top:2px;">Système de gestion des bulletins scolaires</div>
                    <div style="font-size:13pt;font-weight:bold;color:white;margin-top:6px;text-transform:uppercase;letter-spacing:2px;">Bilan des Acquisitions</div>
                    <div style="font-size:8.5pt;color:#fcd34d;margin-top:3px;">
                        Année scolaire : 2025 / 2026
                        <span style="display:inline-block;background:#f59e0b;color:#1a1a1a;font-weight:bold;font-size:8pt;padding:2px 8px;border-radius:10px;margin-left:6px;">1er Trimestre</span>
                    </div>
                </td>
                <td style="width:70px;text-align:center;vertical-align:middle;">
                    <div style="width:58px;height:58px;border:2px solid rgba(255,255,255,.3);border-radius:6px;background:white;display:inline-flex;align-items:center;justify-content:center;font-size:16pt;">📋</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- ── Student info band ────────────────────────────────────────────────── --}}
    <div style="background:#1e40af;color:white;padding:8px 16px;border-bottom:3px solid #f59e0b;">
        <table style="width:100%;border-collapse:collapse;">
            <tr>
                <td style="padding:2px 8px;color:white;font-size:8.5pt;"><span style="color:#fcd34d;font-weight:bold;">Élève :</span> KONÉ Aminata</td>
                <td style="padding:2px 8px;color:white;font-size:8.5pt;"><span style="color:#fcd34d;font-weight:bold;">Matricule :</span> INT-2025-0042</td>
                <td style="padding:2px 8px;color:white;font-size:8.5pt;"><span style="color:#fcd34d;font-weight:bold;">Date de naissance :</span> 15/03/2020</td>
            </tr>
            <tr>
                <td style="padding:2px 8px;color:white;font-size:8.5pt;"><span style="color:#fcd34d;font-weight:bold;">Classe :</span> Petite Section</td>
                <td style="padding:2px 8px;color:white;font-size:8.5pt;"><span style="color:#fcd34d;font-weight:bold;">Section :</span> A</td>
                <td style="padding:2px 8px;color:white;font-size:8.5pt;"><span style="color:#fcd34d;font-weight:bold;">Enseignant(e) :</span> Mme. Diallo Fatou</td>
            </tr>
        </table>
    </div>

    {{-- ── Legend ────────────────────────────────────────────────────────────── --}}
    <div style="margin:10px 14px 6px;border:1px solid #bfdbfe;border-radius:4px;background:#eff6ff;padding:6px 10px;">
        <div style="font-weight:bold;font-size:8pt;color:#1e3a8a;margin-bottom:4px;">Grille d'évaluation</div>
        <table style="border-collapse:collapse;width:100%;">
            <thead>
                <tr>
                    <th style="padding:3px 8px;font-size:7.5pt;background:#1e3a8a;color:white;border:1px solid #1e3a8a;text-align:center;">Sigle</th>
                    <th style="padding:3px 8px;font-size:7.5pt;background:#1e3a8a;color:white;border:1px solid #1e3a8a;text-align:center;">Signification</th>
                    <th style="padding:3px 8px;font-size:7.5pt;background:#1e3a8a;color:white;border:1px solid #1e3a8a;text-align:center;">Description</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="padding:2px 8px;font-size:7.5pt;border:1px solid #dbeafe;text-align:center;"><span style="background:#16a34a;color:white;padding:2px 8px;border-radius:10px;font-size:8pt;font-weight:bold;">A</span></td>
                    <td style="padding:2px 8px;font-size:7.5pt;border:1px solid #dbeafe;text-align:center;">Acquis</td>
                    <td style="padding:2px 8px;font-size:7.5pt;border:1px solid #dbeafe;">L'enfant maîtrise et réussit souvent la compétence de façon autonome.</td>
                </tr>
                <tr>
                    <td style="padding:2px 8px;font-size:7.5pt;border:1px solid #dbeafe;text-align:center;"><span style="background:#d97706;color:white;padding:2px 8px;border-radius:10px;font-size:8pt;font-weight:bold;">EVA</span></td>
                    <td style="padding:2px 8px;font-size:7.5pt;border:1px solid #dbeafe;text-align:center;">En voie d'acquisition</td>
                    <td style="padding:2px 8px;font-size:7.5pt;border:1px solid #dbeafe;">L'enfant est en cours d'apprentissage, des progrès sont visibles.</td>
                </tr>
                <tr>
                    <td style="padding:2px 8px;font-size:7.5pt;border:1px solid #dbeafe;text-align:center;"><span style="background:#dc2626;color:white;padding:2px 8px;border-radius:10px;font-size:8pt;font-weight:bold;">NA</span></td>
                    <td style="padding:2px 8px;font-size:7.5pt;border:1px solid #dbeafe;text-align:center;">Non acquis</td>
                    <td style="padding:2px 8px;font-size:7.5pt;border:1px solid #dbeafe;">La compétence n'est pas encore maîtrisée, un accompagnement est nécessaire.</td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- ── Subject 1: Langage ───────────────────────────────────────────────── --}}
    @php
    $subjects = [
        [
            'name' => 'LANGAGE ET COMMUNICATION',
            'competences' => [
                ['code' => 'L1', 'desc' => 'Écoute et comprend des consignes simples', 'T1' => 'A',   'T2' => 'A',   'T3' => 'A'],
                ['code' => 'L2', 'desc' => 'S\'exprime clairement à l\'oral',           'T1' => 'EVA', 'T2' => 'A',   'T3' => 'A'],
                ['code' => 'L3', 'desc' => 'Reconnaît et nomme les lettres de son prénom','T1' => 'EVA','T2' => 'EVA','T3' => 'A'],
                ['code' => 'L4', 'desc' => 'Récite une comptine ou un poème',           'T1' => 'A',   'T2' => 'A',   'T3' => 'A'],
            ],
        ],
        [
            'name' => 'MATHÉMATIQUES ET LOGIQUE',
            'competences' => [
                ['code' => 'M1', 'desc' => 'Compte jusqu\'à 10',                        'T1' => 'A',   'T2' => 'A',   'T3' => 'A'],
                ['code' => 'M2', 'desc' => 'Reconnaît les formes géométriques simples', 'T1' => 'EVA', 'T2' => 'A',   'T3' => 'A'],
                ['code' => 'M3', 'desc' => 'Compare des quantités (plus/moins)',         'T1' => 'NA',  'T2' => 'EVA', 'T3' => 'A'],
            ],
        ],
        [
            'name' => 'MOTRICITÉ ET ARTS',
            'competences' => [
                ['code' => 'P1', 'desc' => 'Tient correctement un crayon',               'T1' => 'A',   'T2' => 'A',   'T3' => 'A'],
                ['code' => 'P2', 'desc' => 'Découpe avec des ciseaux adaptés',            'T1' => 'EVA', 'T2' => 'EVA', 'T3' => 'A'],
                ['code' => 'P3', 'desc' => 'Colorie sans dépasser les contours',          'T1' => 'EVA', 'T2' => 'A',   'T3' => 'A'],
            ],
        ],
        [
            'name' => 'VIE SOCIALE ET ÉMOTIONNELLE',
            'competences' => [
                ['code' => 'V1', 'desc' => 'Respecte les règles de vie en classe',        'T1' => 'A',   'T2' => 'A',   'T3' => 'A'],
                ['code' => 'V2', 'desc' => 'Partage et joue avec les autres',             'T1' => 'A',   'T2' => 'A',   'T3' => 'A'],
                ['code' => 'V3', 'desc' => 'Exprime ses émotions de manière appropriée',  'T1' => 'EVA', 'T2' => 'A',   'T3' => 'A'],
            ],
        ],
    ];

    $badgeStyle = fn($v) => match($v) {
        'A'   => 'background:#16a34a;color:white;padding:2px 7px;border-radius:10px;font-size:8pt;font-weight:bold;',
        'EVA' => 'background:#d97706;color:white;padding:2px 5px;border-radius:10px;font-size:8pt;font-weight:bold;',
        'NA'  => 'background:#dc2626;color:white;padding:2px 7px;border-radius:10px;font-size:8pt;font-weight:bold;',
        default => 'color:#9ca3af;',
    };
    @endphp

    @foreach($subjects as $subject)
    <div style="margin:0 14px 10px;">
        <div style="background:#1e3a8a;color:white;padding:5px 10px;font-weight:bold;font-size:9.5pt;border-radius:3px 3px 0 0;border-left:4px solid #f59e0b;">
            {{ $subject['name'] }}
        </div>
        <table style="width:100%;border-collapse:collapse;">
            <thead>
                <tr>
                    <th style="background:#dbeafe;color:#1e3a8a;padding:4px 8px;border:1px solid #bfdbfe;font-size:8pt;text-align:left;width:52%;">Compétences / Objectifs</th>
                    <th style="background:#dbeafe;color:#1e3a8a;padding:4px 8px;border:1px solid #bfdbfe;font-size:8pt;text-align:center;width:16%;">1er Trimestre</th>
                    <th style="background:#dbeafe;color:#1e3a8a;padding:4px 8px;border:1px solid #bfdbfe;font-size:8pt;text-align:center;width:16%;">2ème Trimestre</th>
                    <th style="background:#dbeafe;color:#1e3a8a;padding:4px 8px;border:1px solid #bfdbfe;font-size:8pt;text-align:center;width:16%;">3ème Trimestre</th>
                </tr>
            </thead>
            <tbody>
                @foreach($subject['competences'] as $i => $comp)
                <tr style="{{ $i % 2 === 1 ? 'background:#f0f7ff;' : '' }}">
                    <td style="padding:4px 8px;border:1px solid #e5e7eb;font-size:8.5pt;">
                        <span style="color:#1e40af;font-weight:bold;">{{ $comp['code'] }}</span>
                        — {{ $comp['desc'] }}
                    </td>
                    <td style="padding:4px 8px;border:1px solid #e5e7eb;text-align:center;">
                        <span style="{{ $badgeStyle($comp['T1']) }}">{{ $comp['T1'] }}</span>
                    </td>
                    <td style="padding:4px 8px;border:1px solid #e5e7eb;text-align:center;">
                        <span style="{{ $badgeStyle($comp['T2']) }}">{{ $comp['T2'] }}</span>
                    </td>
                    <td style="padding:4px 8px;border:1px solid #e5e7eb;text-align:center;">
                        <span style="{{ $badgeStyle($comp['T3']) }}">{{ $comp['T3'] }}</span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endforeach

    {{-- ── Observation de l'enseignant ──────────────────────────────────────── --}}
    <div style="margin:0 14px 8px;">
        <div style="font-weight:bold;font-size:8.5pt;color:#1e3a8a;margin-bottom:3px;">Observation de l'enseignant(e) :</div>
        <div style="border:1px solid #bfdbfe;border-radius:3px;min-height:36px;padding:6px 8px;background:#f0f7ff;font-size:8.5pt;color:#1a1a1a;">
            Aminata fait de réels progrès au fil des trimestres. Sa participation en classe et son engagement sont appréciables.
        </div>
    </div>

    <div style="margin:0 14px 8px;">
        <div style="font-weight:bold;font-size:8.5pt;color:#b45309;margin-bottom:3px;">Mot de la Direction :</div>
        <div style="border:1px solid #f59e0b;border-radius:3px;min-height:36px;padding:6px 8px;background:#fffbeb;font-size:8.5pt;color:#1a1a1a;">
            Bonne progression dans l'ensemble. Continuez les efforts !
        </div>
    </div>

    {{-- ── Signatures ────────────────────────────────────────────────────────── --}}
    <div style="margin:10px 14px 14px;border-top:2px solid #1e3a8a;padding-top:8px;">
        <table style="width:100%;border-collapse:collapse;">
            <tr>
                <td style="width:33%;padding-right:6px;vertical-align:top;">
                    <div style="border:1px solid #bfdbfe;min-height:55px;padding:5px 8px;border-radius:3px;background:#f8fafc;">
                        <div style="font-weight:bold;font-size:8pt;color:#1e3a8a;margin-bottom:4px;">Signature de l'enseignant(e)</div>
                    </div>
                </td>
                <td style="width:33%;padding:0 3px;vertical-align:top;">
                    <div style="border:1px solid #bfdbfe;min-height:55px;padding:5px 8px;border-radius:3px;background:#f8fafc;">
                        <div style="font-weight:bold;font-size:8pt;color:#1e3a8a;margin-bottom:4px;">Cachet et signature de la Direction</div>
                    </div>
                </td>
                <td style="width:33%;padding-left:6px;vertical-align:top;">
                    <div style="border:1px solid #bfdbfe;min-height:55px;padding:5px 8px;border-radius:3px;background:#f8fafc;">
                        <div style="font-weight:bold;font-size:8pt;color:#1e3a8a;margin-bottom:4px;">Signature des parents / tuteurs</div>
                    </div>
                </td>
            </tr>
        </table>
    </div>

</div>
