{{-- ============================================================
     Modèle bulletin PRÉSCOLAIRE — aperçu navigateur (données fictives)
     Format A4 paysage recto-verso — une seule feuille
     RECTO : Couverture + Compétences
     VERSO  : Introduction + Commentaires & Signatures
     ============================================================ --}}

<style>
.pv-wrap {display:flex;flex-direction:column;align-items:center;gap:6mm;padding:6mm;background:#b8a070;border-radius:6px;}
.pv-lbl  {font-family:Arial,sans-serif;font-size:8pt;color:#fff;text-align:center;margin-bottom:1mm;font-weight:600;}
.pv-page {width:594px;height:420px;display:flex;flex-direction:row;background:#fff;
          overflow:hidden;font-size:3.75pt;color:#000;font-family:'Calibri',Arial,sans-serif;
          box-shadow:0 3px 12px rgba(0,0,0,.4);}
.pv-panel{width:297px;height:420px;padding:2.5mm 3mm;overflow:hidden;position:relative;
          border:2px solid #6B5000;}
.pv-left {border-right:1px solid #6B5000;}
.pv-right{border-left:1px solid #6B5000;}

.pv-logo  {background:#1a5c00;display:inline-block;}
.pv-logo-t{display:flex;align-items:stretch;}
.pv-li    {background:#ED7D31;color:#fff;font-size:5.5pt;font-weight:900;font-style:italic;padding:0.7mm 1mm;display:flex;align-items:center;}
.pv-lt    {color:#fff;font-size:5.5pt;font-weight:900;padding:0.7mm 1.5mm;display:flex;align-items:center;letter-spacing:1px;}
.pv-lb    {color:#fff;font-size:3.5pt;font-weight:700;letter-spacing:2px;padding:0.5mm 1mm 0.7mm;text-align:center;}

.pv-cover  {display:flex;flex-direction:column;align-items:center;height:100%;}
.pv-sch-row{display:flex;align-items:center;gap:1.5mm;width:100%;margin-bottom:1.5mm;}
.pv-sch-blk{flex:1;border-left:1.5px solid #6B5000;padding-left:1.5mm;}
.pv-sch-nm {font-size:6pt;font-weight:700;font-style:italic;line-height:1.1;}
.pv-sch-tag{font-size:3.5pt;font-style:italic;color:#444;}
.pv-sch-ct {font-size:3pt;color:#444;margin-top:0.3mm;}
.pv-btitle {font-size:4.5pt;font-weight:900;text-transform:uppercase;text-decoration:underline;margin:0.7mm 0 0.3mm;}
.pv-bperiod{font-size:4pt;font-weight:700;margin-bottom:1mm;}
.pv-gs-ttl {font-size:8pt;font-weight:900;font-family:'Palatino Linotype',Palatino,serif;
            text-transform:uppercase;letter-spacing:1px;text-align:center;margin:1mm 0;}
.pv-cbox   {border:1px solid #6B5000;border-radius:4px;width:100%;height:21mm;
            display:flex;align-items:center;justify-content:center;
            background:linear-gradient(135deg,#e8f4f0,#d4e8f5,#f5e8d4,#e8f0d4);margin-bottom:1.5mm;}
.pv-info   {border:1px solid #6B5000;border-radius:4px;padding:1.2mm 1.7mm;width:100%;font-size:4pt;line-height:1.7;background:#fff;}
.pv-ir     {display:flex;}
.pv-il     {min-width:18mm;}
.pv-iv     {font-weight:700;}

.pv-intro  {font-size:3.5pt;line-height:1.5;text-align:justify;margin-bottom:0.7mm;}
.pv-ibold  {font-weight:700;text-decoration:underline;}
.pv-leg-t  {border-collapse:collapse;margin:1mm 0;font-size:3.5pt;width:90%;}
.pv-leg-t td{border:1px solid #000;padding:0.5mm 1.5mm;}
.pv-leg-t td:first-child{font-weight:900;text-align:center;width:6mm;}
.pv-cm-lbl {font-size:4pt;font-weight:700;margin-bottom:0.7mm;}
.pv-cm-box {border:1px solid #6B5000;border-radius:3px;padding:1mm 1.5mm;min-height:9mm;font-size:3.7pt;font-style:italic;background:#fffdf8;margin-bottom:1.5mm;}
.pv-sig-t  {width:100%;border-collapse:collapse;font-size:3.7pt;}
.pv-sig-t td,.pv-sig-t th{border:1px solid #000;padding:0.6mm 1mm;vertical-align:middle;}
.pv-sig-dr td{background:#FFC000;font-weight:700;text-decoration:underline;font-size:4pt;padding:0.6mm 1mm;}
.pv-sig-hr th{background:#FCE9D9;font-weight:700;text-align:center;width:33.3%;font-size:3.7pt;}
.pv-sig-br td{background:#FCE9D9;height:11mm;width:33.3%;}

.pv-comp   {width:100%;border-collapse:collapse;font-size:3.2pt;}
.pv-comp th,.pv-comp td{border:1px solid #000;vertical-align:middle;padding:0.3mm 0.6mm;}
.pv-hd     {background:#C2D59B;font-weight:700;text-align:center;font-size:3.5pt;}
.pv-sub-h  {background:#C2D59B;font-weight:700;text-align:center;font-size:3pt;padding:0.3mm;}
.pv-sep    {background:#FFC000;height:1.5px;padding:0;border:1px solid #6B5000;}
.pv-dom-c  {font-weight:900;text-align:center;vertical-align:middle;font-size:3.2pt;text-transform:uppercase;word-break:break-word;}
.pv-comp-c {font-size:3.2pt;}
.pv-score  {text-align:center;font-weight:700;font-size:3.5pt;}
</style>

<div class="pv-wrap">

  {{-- RECTO --}}
  <div class="pv-lbl">RECTO — Couverture (gauche) + Tableau des compétences (droite)</div>
  <div class="pv-page">

    {{-- LEFT: COUVERTURE --}}
    <div class="pv-panel pv-left">
      <div class="pv-cover">
        <div class="pv-sch-row">
          <div class="pv-logo">
            <div class="pv-logo-t"><div class="pv-li">in</div><div class="pv-lt">TEC</div></div>
            <div class="pv-lb">É C O L E</div>
          </div>
          <div class="pv-sch-blk">
            <div class="pv-sch-nm">École internationale</div>
            <div class="pv-sch-tag"><em>pour</em> les langues et les technologies</div>
            <div class="pv-sch-ct">☎ 77 08 79 79 | 77 05 78 78<br>✉ intec.ecole.djibouti@gmail.com</div>
          </div>
        </div>

        <div class="pv-btitle">BILAN DES ACQUISITIONS</div>
        <div class="pv-bperiod">DÉCEMBRE 2025 – FÉVRIER 2026</div>
        <div class="pv-gs-ttl">Grande Section</div>

        <div class="pv-cbox">
          <div style="text-align:center;">
            <div style="font-size:22pt;line-height:1;">🏫</div>
            <div style="font-size:3pt;color:#666;margin-top:0.5mm;">Salle de classe préscolaire inTEC</div>
          </div>
        </div>

        <div class="pv-info">
          <div class="pv-ir"><span class="pv-il">Nom :</span><span class="pv-iv">ABDOURAHMAN IBRAHIM HASSAN</span></div>
          <div class="pv-ir"><span class="pv-il">Date de naissance :</span><span class="pv-iv">22/12/2019</span></div>
          <div class="pv-ir"><span class="pv-il">Classe :</span><span class="pv-iv">GSA</span></div>
          <div class="pv-ir"><span class="pv-il">Enseignant(e) :</span><span class="pv-iv" style="font-weight:400;">Mlle AWADA GAMIL MOHAMED</span></div>
        </div>
      </div>
    </div>

    {{-- RIGHT: COMPÉTENCES --}}
    <div class="pv-panel pv-right">
      <div style="display:flex;justify-content:space-between;align-items:baseline;border-bottom:1px solid #6B5000;padding-bottom:0.5mm;margin-bottom:1mm;">
        <span style="font-size:3.7pt;font-weight:700;text-transform:uppercase;">Tableau des compétences</span>
        <span style="font-size:3.3pt;color:#555;">2ème Trimestre</span>
      </div>
      <table class="pv-comp">
        <thead>
          <tr>
            <th class="pv-hd" style="width:15%;" rowspan="2">Domaines</th>
            <th class="pv-hd" style="width:59%;" rowspan="2">Compétences</th>
            <th class="pv-hd" style="width:26%;" colspan="3">Degré d'acquisition</th>
          </tr>
          <tr>
            <th class="pv-sub-h" style="width:9%;">A</th>
            <th class="pv-sub-h" style="width:9%;">EVA</th>
            <th class="pv-sub-h" style="width:8%;">NA</th>
          </tr>
        </thead>
        <tbody>
          <tr><td class="pv-dom-c" rowspan="7"><strong>LANGAGE<br>ORAL</strong></td><td class="pv-comp-c">● Saluer et prendre congé.</td><td class="pv-score">A</td><td></td><td></td></tr>
          <tr><td class="pv-comp-c">● Identifier le personnel de la ferme.</td><td class="pv-score">A</td><td></td><td></td></tr>
          <tr><td class="pv-comp-c">● Nommer et décrire les animaux familiers.</td><td class="pv-score">A</td><td></td><td></td></tr>
          <tr><td class="pv-comp-c">● Reconnaître les bâtiments de la ferme.</td><td class="pv-score">A</td><td></td><td></td></tr>
          <tr><td class="pv-comp-c">● Identifier et nommer les animaux sauvages.</td><td class="pv-score">A</td><td></td><td></td></tr>
          <tr><td class="pv-comp-c">● Décrire et comparer quelques animaux sauvages.</td><td class="pv-score">A</td><td></td><td></td></tr>
          <tr><td class="pv-comp-c">● Situer dans l'espace.</td><td></td><td></td><td class="pv-score">NA</td></tr>

          <tr><td colspan="5" class="pv-sep"></td></tr>
          <tr><td></td><td></td><th class="pv-sub-h">A</th><th class="pv-sub-h">EVA</th><th class="pv-sub-h">NA</th></tr>

          <tr><td class="pv-dom-c" rowspan="5"><strong>PRÉLECTURE</strong></td><td class="pv-comp-c">Lire des images et des mots.</td><td class="pv-score">A</td><td></td><td></td></tr>
          <tr><td class="pv-comp-c">Reconnaître un mot à partir d'un référent.</td><td class="pv-score">A</td><td></td><td></td></tr>
          <tr><td class="pv-comp-c">Associer des mots à des images.</td><td class="pv-score">A</td><td></td><td></td></tr>
          <tr><td class="pv-comp-c">Découvrir et repérer le phonème à l'étude.</td><td class="pv-score">A</td><td></td><td></td></tr>
          <tr><td class="pv-comp-c">Lecture avec fluidité, sons, syllabes et phrases.</td><td></td><td class="pv-score">EVA</td><td></td></tr>

          <tr><td colspan="5" class="pv-sep"></td></tr>
          <tr><td></td><td></td><th class="pv-sub-h">A</th><th class="pv-sub-h">EVA</th><th class="pv-sub-h">NA</th></tr>

          <tr><td class="pv-dom-c" rowspan="6"><strong>GRAPHISME /<br>ÉCRITURE</strong></td><td class="pv-comp-c">Tracer des lignes horizontales.</td><td class="pv-score">A</td><td></td><td></td></tr>
          <tr><td class="pv-comp-c">Tracer des lignes verticales.</td><td class="pv-score">A</td><td></td><td></td></tr>
          <tr><td class="pv-comp-c">Tracer des lignes obliques.</td><td class="pv-score">A</td><td></td><td></td></tr>
          <tr><td class="pv-comp-c">Tracer des boucles.</td><td class="pv-score">A</td><td></td><td></td></tr>
          <tr><td class="pv-comp-c">Tracer des ponts.</td><td class="pv-score">A</td><td></td><td></td></tr>
          <tr><td class="pv-comp-c">Calligraphier la lettre (g, b, j et s).</td><td class="pv-score">A</td><td></td><td></td></tr>

          <tr><td colspan="5" class="pv-sep"></td></tr>
          <tr><td></td><td></td><th class="pv-sub-h">A</th><th class="pv-sub-h">EVA</th><th class="pv-sub-h">NA</th></tr>

          <tr><td class="pv-dom-c" rowspan="8"><strong>LOGICO<br>MATHS</strong></td><td class="pv-comp-c">Les nombres de 1 à 7.</td><td class="pv-score">A</td><td></td><td></td></tr>
          <tr><td class="pv-comp-c">Classement des aliments.</td><td class="pv-score">A</td><td></td><td></td></tr>
          <tr><td class="pv-comp-c">Comparer les quantités.</td><td></td><td class="pv-score">EVA</td><td></td></tr>
          <tr><td class="pv-comp-c">Situations additive.</td><td class="pv-score">A</td><td></td><td></td></tr>
          <tr><td class="pv-comp-c">Les animaux de la ferme.</td><td class="pv-score">A</td><td></td><td></td></tr>
          <tr><td class="pv-comp-c">Les petits animaux.</td><td class="pv-score">A</td><td></td><td></td></tr>
          <tr><td class="pv-comp-c">Le goût.</td><td></td><td class="pv-score">EVA</td><td></td></tr>
          <tr><td class="pv-comp-c">Près de, loin de…</td><td class="pv-score">A</td><td></td><td></td></tr>

          <tr><td colspan="5" class="pv-sep"></td></tr>
          <tr><td></td><td></td><th class="pv-sub-h">A</th><th class="pv-sub-h">EVA</th><th class="pv-sub-h">NA</th></tr>

          <tr><td class="pv-dom-c" rowspan="2"><strong>VIVRE<br>ENSEMBLE</strong></td><td class="pv-comp-c">● Respect de la nature.</td><td class="pv-score">A</td><td></td><td></td></tr>
          <tr><td class="pv-comp-c">● Respect du règlement.</td><td class="pv-score">A</td><td></td><td></td></tr>

          <tr><td colspan="5" class="pv-sep"></td></tr>
          <tr><td class="pv-dom-c"><strong>ÉD.<br>ISLAMIQUE</strong></td><td class="pv-comp-c">&nbsp;</td><td></td><td class="pv-score">EVA</td><td></td></tr>

          <tr><td colspan="5" class="pv-sep"></td></tr>
          <tr><td class="pv-dom-c" rowspan="2"><strong>ÉVEIL<br>ARTISTIQUE</strong></td><td class="pv-comp-c">Discriminer et produire des sons.</td><td class="pv-score">A</td><td></td><td></td></tr>
          <tr><td class="pv-comp-c">Dessiner selon un modèle.</td><td class="pv-score">A</td><td></td><td></td></tr>
        </tbody>
      </table>
    </div>

  </div>

  {{-- VERSO --}}
  <div class="pv-lbl">VERSO — Introduction pédagogique (gauche) + Observations &amp; Signatures (droite)</div>
  <div class="pv-page">

    {{-- LEFT: INTRO --}}
    <div class="pv-panel pv-left" style="display:flex;flex-direction:column;">
      <div style="text-align:center;margin-bottom:1mm;">
        <div class="pv-logo" style="display:inline-block;">
          <div class="pv-logo-t"><div class="pv-li">in</div><div class="pv-lt">TEC</div></div>
          <div class="pv-lb">É C O L E</div>
        </div>
      </div>
      <div style="text-align:center;font-size:4pt;font-weight:700;text-transform:uppercase;border-bottom:1px solid #6B5000;padding-bottom:0.7mm;margin-bottom:1mm;">
        Bilan des Acquisitions — Grande Section — 2025/2026
      </div>
      <p class="pv-intro">Outil de régulation pour les activités d'enseignement-apprentissages, l'évaluation se doit de reposer sur une observation attentive de ce que chaque enfant dit ou fait. Ce qui importe alors à l'éducateur va bien au-delà du résultat obtenu, il se fixe plutôt sur le cheminement de l'enfant et les progrès qu'il réalise par rapport à lui-même et non par rapport à ses camarades ou à une quelconque norme.</p>
      <p class="pv-intro">Une évaluation positive c'est donc celle qui permet à chacun enfant d'identifier ses propres réussites, d'en garder des traces, de percevoir son évolution personnelle.</p>
      <p class="pv-intro">De ce fait, au préscolaire, le suivi des apprentissages des enfants se fait à travers deux outils :</p>
      <p class="pv-intro">— <span class="pv-ibold">Le carnet de suivi des apprentissages</span> : recueil d'observations complété tout au long des apprentissages à travers des évaluations écrites, des productions réussies, des grilles d'observation, des photos, des commentaires écrits…</p>
      <p class="pv-intro">— <span class="pv-ibold">Le bilan des compétences</span> : établi à la fin de chaque période, communiqué aux parents quatre (4) fois dans l'année. Les parents le signent pour attester réception.</p>
      <p class="pv-intro">Le positionnement par rapport aux acquis des enfants se fait sur une échelle à trois (3) niveaux : A, EVA et NA — réussit souvent, est en voie de réussite, ne réussit pas encore.</p>

      <table class="pv-leg-t" style="margin:1mm 0;">
        <tr><td><strong>A</strong></td><td>Acquis, l'enfant réussit souvent</td></tr>
        <tr><td><strong>EVA</strong></td><td>En voie d'acquisition, réussit parfois ou avec aide</td></tr>
        <tr><td><strong>NA</strong></td><td>Non acquis encore</td></tr>
      </table>

      <p class="pv-intro">L'éducation préscolaire est une éducation bienveillante — tous les enfants sont capables d'apprendre et de progresser. Apprendre quelque chose de nouveau, c'est aussi se tromper et ne pas réussir dès le premier essai ; c'est essayer encore et encore jusqu'à réussir, ce qui lui permettra de progresser et d'avoir confiance en lui.</p>
    </div>

    {{-- RIGHT: COMMENTAIRES & SIGNATURES --}}
    <div class="pv-panel pv-right" style="display:flex;flex-direction:column;">
      <div style="display:flex;justify-content:space-between;align-items:baseline;border-bottom:1px solid #6B5000;padding-bottom:0.7mm;margin-bottom:1.5mm;">
        <span style="font-size:3.7pt;font-weight:700;text-transform:uppercase;">Observations &amp; Signatures</span>
        <span style="font-size:3.5pt;font-weight:700;">Grande Section | 2025/2026</span>
      </div>

      <p class="pv-cm-lbl">Commentaires de l'enseignant(e) :</p>
      <div class="pv-cm-box">Bon élève, il doit continuer à travailler la lecture syllabique.</div>

      <p class="pv-cm-lbl">Observation de la Direction :</p>
      <div class="pv-cm-box"></div>

      <div style="flex:1;"></div>

      <table class="pv-sig-t">
        <tr class="pv-sig-dr"><td colspan="3"><span style="text-decoration:underline;">Date :</span> 28 / 02 / 2026</td></tr>
        <tr class="pv-sig-hr">
          <th>Signature<br>enseignant(e)</th>
          <th>Cachet et signature<br>Direction</th>
          <th>Signature<br>parents</th>
        </tr>
        <tr class="pv-sig-br"><td></td><td></td><td></td></tr>
      </table>
    </div>

  </div>

</div>
