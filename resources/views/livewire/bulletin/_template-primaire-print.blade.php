{{-- ============================================================
     Modèle bulletin PRIMAIRE — format livret paysage A4
     Données fictives — même mise en page que l'impression réelle
     Impression : Paysage, recto-verso bord court, plier en deux
     ============================================================ --}}

<style>
@page { size: A4 landscape; margin: 0; }
@media print { body { background:#fff; } .bk-wrap { padding:0; gap:0; } }

*, *::before, *::after { box-sizing: border-box; }
.bk-wrap  { display:flex; flex-direction:column; align-items:center; gap:8mm; padding:8mm; }
.bk-page  { width:297mm; height:210mm; display:flex; flex-direction:row; background:#fff;
            page-break-after:always; overflow:hidden; font-size:8pt; color:#000;
            font-family:Arial,sans-serif; box-shadow:0 3px 14px rgba(0,0,0,.3); }
.bk-panel { width:148.5mm; height:210mm; padding:5mm 6mm; overflow:hidden; position:relative; }
.bk-left  { border-right:1.5px solid #000; }

.bk-pbar { display:flex; justify-content:space-between; align-items:baseline;
           border-bottom:2px solid #000; padding-bottom:1.5mm; margin-bottom:1.5mm; }
.bk-pbar span { font-size:8.5pt; font-weight:700; text-transform:uppercase; }
.bk-sl { font-size:7.5pt; font-weight:700; text-transform:uppercase; margin:2mm 0 0.8mm; }

.bk-ph { width:100%; border-collapse:collapse; table-layout:fixed; }
.bk-ph th { border:1px solid #000; font-size:6.5pt; font-weight:700; text-align:center; padding:0.5mm; }
.bk-ph .ph-blank { width:52%; background:#fff; border:none; border-right:1px solid #000; }
.bk-ph .ph-top   { background:#A6A6A6; }
.bk-ph .ph-sub   { background:#D9D9D9; font-size:6pt; }

.bk-gt { width:100%; border-collapse:collapse; margin-bottom:0.5mm; table-layout:fixed; }
.bk-gt td { border:1px solid #000; vertical-align:middle; padding:0.5mm 1.2mm; font-size:7pt; background:#fff; }
.bk-gt tbody tr:nth-child(even) td { background:#BFBFBF; }
.bk-cb  { width:52%; }
.bk-p   { width:6.7%; text-align:center; font-weight:700; }
.bk-aen { width:5%;   text-align:center; }

.bk-tt { width:100%; border-collapse:collapse; margin-top:2mm; table-layout:fixed; }
.bk-tt td { border:1px solid #000; padding:1mm 2mm; font-size:8pt; background:#fff; text-align:center; }
.bk-tt .lbl { text-align:left; font-weight:700; background:#D9D9D9; width:40%; }
.bk-tt .big { font-size:10pt; font-weight:700; }

.bk-leg { width:100%; border-collapse:collapse; margin-top:2mm; font-size:7pt; }
.bk-leg th { border:1px solid #000; padding:0.8mm 1mm; font-weight:700; text-align:center; background:#A6A6A6; }
.bk-leg th.lh { text-align:left; }
.bk-leg td { border:1px solid #000; padding:0.8mm 1mm; text-align:center; background:#fff; }
.bk-leg td.lbl { text-align:left; font-weight:700; background:#D9D9D9; }

.bk-at { width:100%; border-collapse:collapse; font-size:7.5pt; }
.bk-at th { border:1px solid #000; background:#A6A6A6; font-weight:700; text-align:center; padding:1mm 1.5mm; }
.bk-at th.title { background:#D9D9D9; font-size:9pt; }
.bk-at td { border:1px solid #000; padding:1.5mm 2mm; vertical-align:top; background:#fff; }
.bk-at .per { text-align:center; font-weight:700; width:13%; vertical-align:middle; background:#D9D9D9; }
.bk-at .obs { width:43%; }
.bk-at .sig { width:22%; text-align:center; vertical-align:bottom; font-size:6.5pt; color:#444; }
.bk-sline   { display:block; border-bottom:1px solid #555; margin:8mm auto 1mm; width:75%; }

.bk-tft { width:62%; border-collapse:collapse; margin:1.5mm 0 1.5mm 4mm; font-size:7.5pt; }
.bk-tft td { border:1px solid #000; padding:1.2mm 2mm; background:#fff; }
.bk-tft td:first-child { font-weight:700; }
.bk-tft td:last-child  { width:36%; }

.bk-dec { border:1.5px solid #000; margin-top:2mm; }
.bk-chk { width:3.5mm; height:3.5mm; border:1.5px solid #000; display:inline-block; }

.bk-cover { display:flex; flex-direction:column; align-items:center; }
.bk-republic { font-size:8pt; font-weight:700; text-transform:uppercase; letter-spacing:1px; margin-bottom:2.5mm; text-align:center; }
.bk-logo { background:#1a5c00; display:inline-block; margin-bottom:3mm; }
.bk-logo-top { display:flex; align-items:stretch; }
.bk-logo-in  { background:#ED7D31; color:#fff; font-size:16pt; font-weight:900; font-style:italic; padding:2.5mm 3mm; display:flex; align-items:center; }
.bk-logo-tec { color:#fff; font-size:16pt; font-weight:900; padding:2.5mm 4.5mm; display:flex; align-items:center; letter-spacing:2px; }
.bk-logo-bot { color:#fff; font-size:11pt; font-weight:700; letter-spacing:6px; padding:1.5mm 4mm 2mm; text-align:center; }
.bk-cov-school { font-size:11pt; font-weight:900; text-transform:uppercase; text-align:center; margin-bottom:1mm; }
.bk-cov-carnet { font-size:13pt; font-weight:400; text-align:center; margin:1mm 0; }
.bk-cov-classe { font-size:18pt; font-weight:900; text-align:center; margin-bottom:3mm; }
.bk-infobox { border:2.5px solid #000; border-radius:8px; padding:3mm 5mm; width:100%; font-size:8.5pt; background:#fff; line-height:2; }
.bk-infobox .ir { display:flex; }
.bk-infobox .il { flex-shrink:0; min-width:38mm; }
.bk-infobox .iv { font-weight:700; }
</style>

@php
$yearLabel = date('Y') . '/' . (date('Y') + 1);
$subjects  = [
    ['label'=>'FRANÇAIS','max'=>40,'competences'=>[
        ['code'=>'CB 1','desc'=>'Lecture : Appréhender le sens général d\'un écrit et oraliser un court texte /20','scores'=>[18.5,19.5,null]],
        ['code'=>'CB 2','desc'=>'Production écrite : Créer un court message signifiant d\'au moins trois phrases /10','scores'=>[5,10,null]],
        ['code'=>'CB 3','desc'=>'Langage : Produire un court énoncé en utilisant les formulations adaptées /5','scores'=>[4,5,null]],
        ['code'=>'ECRITURE','desc'=>'Écrire en cursive des lettres, syllabes, mots et phrases /5','scores'=>[5,5,null]],
    ]],
    ['label'=>'MATHÉMATIQUES','max'=>30,'competences'=>[
        ['code'=>'CB 1','desc'=>'Numération : Résoudre une situation problème faisant intervenir l\'écriture des nombres','scores'=>[10,10,null]],
        ['code'=>'CB 2','desc'=>'Géométrie : Se situer ou situer des objets dans l\'espace','scores'=>[10,9,null]],
        ['code'=>'CB 3','desc'=>'Mesure : Trier, comparer et ranger des objets selon un critère donné','scores'=>[10,10,null]],
    ]],
    ['label'=>'ARABE / ANGLAIS','max'=>30,'competences'=>[
        ['code'=>'CB 1','desc'=>'ARABE /20','scores'=>[19,19,null]],
        ['code'=>'CB 2','desc'=>'ANGLAIS /10','scores'=>[5,10,null]],
    ]],
    ['label'=>'SCIENCES','max'=>5,'competences'=>[
        ['code'=>'CB 1','desc'=>'Identifier et reconnaître les différentes parties de son corps','scores'=>[3.5,null,null]],
        ['code'=>'CB 2','desc'=>'Identifier les animaux et les végétaux de son milieu','scores'=>[null,4,null]],
        ['code'=>'CB 3','desc'=>'Nommer et sélectionner des objets d\'une collection type','scores'=>[null,null,null]],
    ]],
    ['label'=>'EDUCATION PHYSIQUE ET SPORTIVE','max'=>5,'competences'=>[
        ['code'=>'CB 1','desc'=>'Produire des actions adaptées au milieu en appliquant les règles du jeu','scores'=>[5,null,null]],
        ['code'=>'CB 2','desc'=>'Lancer et rattraper des objets variés','scores'=>[null,5,null]],
    ]],
    ['label'=>'TECHNOLOGIE','max'=>20,'competences'=>[
        ['code'=>'CB 1','desc'=>'Informatique : Allumer/éteindre l\'outil informatique, utiliser souris et clavier','scores'=>[10,10,null]],
        ['code'=>'CB 2','desc'=>'Robotique : Types de robots, utilité des écrans','scores'=>[10,10,null]],
    ]],
    ['label'=>'DICTÉE','max'=>10,'competences'=>[
        ['code'=>'','desc'=>'Maîtriser l\'orthographe de la langue française. Respecter les règles grammaticales.','scores'=>[10,10,null]],
    ]],
    ['label'=>'DIMENSION PERSONNELLE','max'=>null,'competences'=>[
        ['code'=>'','desc'=>'Discipline, assiduité, ponctualité, respect des règles, participation…','scores'=>['ACQUIS','ACQUIS',null]],
    ]],
];
$splitAt       = (int) ceil(count($subjects) / 2);
$subjectsLeft  = array_slice($subjects, 0, $splitAt);
$subjectsRight = array_slice($subjects, $splitAt);
$romanNumerals = ['I','II','III','IV','V','VI','VII','VIII','IX','X'];
@endphp

<div class="bk-wrap">

{{-- ═══════════ PAGE 1 — EXTÉRIEUR ═══════════ --}}
<div class="bk-page">

    {{-- ▌ DOS / APPRÉCIATIONS ▌ --}}
    <div class="bk-panel bk-left">

        <div style="display:flex;justify-content:space-between;margin-bottom:1mm;">
            <strong style="font-size:8.5pt;text-transform:uppercase;">ECOLE PRIVEE INTEC</strong>
            <strong style="font-size:8.5pt;text-transform:uppercase;">PRIMAIRE</strong>
        </div>
        <div style="margin-bottom:1.5mm;">
            <strong style="font-size:8.5pt;text-transform:uppercase;">CARNET D'EVALUATION CP</strong>
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
                <tr>
                    <td class="per">1<sup>ère</sup><br><strong>Période</strong></td>
                    <td class="obs">Très bon trimestre ! Toutes mes félicitations !<br><br><em style="font-size:7pt;">L'Enseignant(e)</em></td>
                    <td class="sig"><span class="bk-sline"></span></td>
                    <td class="sig"><span class="bk-sline"></span></td>
                </tr>
                <tr>
                    <td class="per">2<sup>ème</sup><br><strong>Période</strong></td>
                    <td class="obs">Excellent travail ! Élève brillant.<br><br><em style="font-size:7pt;">L'Enseignant(e)</em></td>
                    <td class="sig"><span class="bk-sline"></span></td>
                    <td class="sig"><span class="bk-sline"></span></td>
                </tr>
                <tr>
                    <td class="per">3<sup>ème</sup><br><strong>Période</strong></td>
                    <td class="obs" style="height:14mm;"><br><br><em style="font-size:7pt;">L'Enseignant(e)</em></td>
                    <td class="sig"><span class="bk-sline"></span></td>
                    <td class="sig"><span class="bk-sline"></span></td>
                </tr>
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
                            <div style="display:flex;align-items:center;gap:2mm;"><div class="bk-chk"></div>Admis(e) en CE1</div>
                            <div style="display:flex;align-items:center;gap:2mm;"><div class="bk-chk"></div>Reprend le CP</div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

    </div>{{-- /DOS --}}

    {{-- ▌ COUVERTURE ▌ --}}
    <div class="bk-panel">
        <div class="bk-cover">

            <p class="bk-republic">REPUBLIQUE DE DJIBOUTI</p>

            <div class="bk-logo">
                <div class="bk-logo-top">
                    <div class="bk-logo-in">in</div>
                    <div class="bk-logo-tec">TEC</div>
                </div>
                <div class="bk-logo-bot">É C O L E</div>
            </div>

            <p class="bk-cov-school">ECOLE PRIVEE INTEC</p>
            <p class="bk-cov-carnet">CARNET D'EVALUATION</p>
            <p class="bk-cov-classe">CP</p>

            <div class="bk-infobox">
                <div class="ir"><span class="il">École privée :</span><span class="iv" style="font-style:italic;font-weight:400;">Ecole Privée inTEC</span></div>
                <div class="ir"><span class="il">Nom de l'élève :</span><span class="iv">TRAORÉ MAMADOU</span></div>
                <div class="ir"><span class="il">Né(e) le :</span><span class="iv">07/09/2017</span></div>
                <div class="ir"><span class="il">Classe :</span><span class="iv">CP/B</span></div>
                <div class="ir"><span class="il">Nom de l'enseignant(e) :</span><span class="iv" style="font-weight:400;">M. Coulibaly Ibrahim</span></div>
                <div class="ir"><span class="il">Nom du Directeur :</span><span class="iv" style="font-weight:400;">Mr. Mahamoud Ali Hared</span></div>
            </div>

        </div>
    </div>{{-- /COUVERTURE --}}

</div>{{-- /PAGE 1 --}}

{{-- ═══════════ PAGE 2 — INTÉRIEUR ═══════════ --}}
<div class="bk-page">

    {{-- ▌ GAUCHE ▌ --}}
    <div class="bk-panel bk-left">

        <div class="bk-pbar">
            <span>CARNET D'EVALUATION CP</span>
            <span>INTEC ECOLE</span>
        </div>

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
        <p class="bk-sl">{{ $romanNumerals[$idx] ?? ($idx+1) }} – COMPETENCE DE BASE : {{ $subject['label'] }} : /{{ $subject['max'] ?? '—' }}</p>
        <table class="bk-gt">
            <colgroup>
                <col class="bk-cb">
                <col class="bk-p"><col class="bk-aen"><col class="bk-aen">
                <col class="bk-p"><col class="bk-aen"><col class="bk-aen">
                <col class="bk-p"><col class="bk-aen"><col class="bk-aen">
            </colgroup>
            <tbody>
                @foreach($subject['competences'] as $comp)
                <tr>
                    <td class="bk-cb">@if($comp['code'])<strong>{{ $comp['code'] }}</strong> / @endif{{ $comp['desc'] }}</td>
                    @foreach([0,1,2] as $pi)
                    @php $v = $comp['scores'][$pi] ?? null; @endphp
                    <td class="bk-p">{{ $v !== null ? $v : '' }}</td>
                    <td class="bk-aen"></td><td class="bk-aen"></td>
                    @endforeach
                </tr>
                @endforeach
            </tbody>
        </table>
        @endforeach

    </div>{{-- /GAUCHE --}}

    {{-- ▌ DROITE ▌ --}}
    <div class="bk-panel">

        <div class="bk-pbar">
            <span>CARNET D'EVALUATION CP</span>
            <span style="font-size:7.5pt;text-transform:none;">Année scolaire : {{ $yearLabel }}</span>
        </div>

        @foreach($subjectsRight as $ridx => $subject)
        @php $gi = $splitAt + $ridx; @endphp
        <p class="bk-sl">{{ $romanNumerals[$gi] ?? ($gi+1) }} – COMPETENCE DE BASE : {{ $subject['label'] }} : /{{ $subject['max'] ?? '—' }}</p>
        <table class="bk-gt">
            <colgroup>
                <col class="bk-cb">
                <col class="bk-p"><col class="bk-aen"><col class="bk-aen">
                <col class="bk-p"><col class="bk-aen"><col class="bk-aen">
                <col class="bk-p"><col class="bk-aen"><col class="bk-aen">
            </colgroup>
            <tbody>
                @foreach($subject['competences'] as $comp)
                <tr>
                    <td class="bk-cb">@if($comp['code'])<strong>{{ $comp['code'] }}</strong> / @endif{{ $comp['desc'] }}</td>
                    @foreach([0,1,2] as $pi)
                    @php $v = $comp['scores'][$pi] ?? null; @endphp
                    <td class="bk-p">{{ $v !== null ? $v : '' }}</td>
                    <td class="bk-aen"></td><td class="bk-aen"></td>
                    @endforeach
                </tr>
                @endforeach
            </tbody>
        </table>
        @endforeach

        {{-- TOTAUX --}}
        <table class="bk-tt">
            <colgroup><col style="width:40%"><col style="width:20%"><col style="width:20%"><col style="width:20%"></colgroup>
            <tbody>
                <tr><td class="lbl">Total sur 140</td><td class="big">125</td><td class="big">136.5</td><td class="big"></td></tr>
                <tr><td class="lbl">Moyenne sur 10</td><td class="big">8.9</td><td class="big">9.7</td><td class="big"></td></tr>
                <tr><td class="lbl">Moyenne de la classe sur 10</td><td class="big">8.3</td><td class="big">8.7</td><td class="big"></td></tr>
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

    </div>{{-- /DROITE --}}

</div>{{-- /PAGE 2 --}}

</div>{{-- /bk-wrap --}}
