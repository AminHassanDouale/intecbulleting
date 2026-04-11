{{-- ============================================================
     Modèle bulletin PRÉSCOLAIRE — aperçu statique (données fictives)
     Format A4 paysage recto-verso — une seule feuille
     RECTO : Couverture + Compétences
     VERSO  : Introduction + Commentaires & Signatures
     ============================================================ --}}

<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}

@page { size: A4 landscape; margin: 0; }
@media print { body{background:#fff;} .ps-wrap{padding:0;gap:0;} .snote{display:none!important;} }

.ps-wrap {display:flex;flex-direction:column;align-items:center;gap:8mm;padding:8mm;}
.ps-page {width:297mm;height:210mm;display:flex;flex-direction:row;background:#fff;
          page-break-after:always;overflow:hidden;font-size:7.5pt;color:#000;
          font-family:'Calibri',Arial,sans-serif;box-shadow:0 4px 18px rgba(0,0,0,.4);}
.ps-panel{width:148.5mm;height:210mm;padding:5mm 6mm;overflow:hidden;position:relative;
          border:4px solid #6B5000;}
.ps-left {border-right:2px solid #6B5000;}
.ps-right{border-left:2px solid #6B5000;}

.ps-logo  {background:#1a5c00;display:inline-block;}
.ps-logo-t{display:flex;align-items:stretch;}
.ps-li    {background:#ED7D31;color:#fff;font-size:11pt;font-weight:900;font-style:italic;padding:1.5mm 2mm;display:flex;align-items:center;}
.ps-lt    {color:#fff;font-size:11pt;font-weight:900;padding:1.5mm 3mm;display:flex;align-items:center;letter-spacing:2px;}
.ps-lb    {color:#fff;font-size:7.5pt;font-weight:700;letter-spacing:4px;padding:1mm 2.5mm 1.5mm;text-align:center;}

.ps-cover  {display:flex;flex-direction:column;align-items:center;height:100%;}
.ps-sch-row{display:flex;align-items:center;gap:3mm;width:100%;margin-bottom:3mm;}
.ps-sch-blk{flex:1;border-left:2.5px solid #6B5000;padding-left:3mm;}
.ps-sch-nm {font-size:12pt;font-weight:700;font-style:italic;line-height:1.1;}
.ps-sch-tag{font-size:7pt;font-style:italic;color:#444;}
.ps-sch-ct {font-size:6.5pt;color:#444;margin-top:0.5mm;}
.ps-btitle {font-size:9pt;font-weight:900;text-transform:uppercase;text-decoration:underline;margin:1.5mm 0 0.5mm;}
.ps-bperiod{font-size:8pt;font-weight:700;margin-bottom:2mm;}
.ps-gs-ttl {font-size:16pt;font-weight:900;font-family:'Palatino Linotype','Book Antiqua',Palatino,serif;
            text-transform:uppercase;letter-spacing:2px;text-align:center;margin:2mm 0;}
.ps-cbox   {border:2px solid #6B5000;border-radius:8px;width:100%;height:42mm;
            display:flex;align-items:center;justify-content:center;
            background:linear-gradient(135deg,#e8f4f0,#d4e8f5,#f5e8d4,#e8f0d4);margin-bottom:2.5mm;}
.ps-info   {border:2px solid #6B5000;border-radius:8px;padding:2.5mm 3.5mm;width:100%;font-size:8pt;line-height:1.75;background:#fff;}
.ps-ir     {display:flex;}
.ps-il     {min-width:38mm;}
.ps-iv     {font-weight:700;}

.ps-intro  {font-size:7pt;line-height:1.55;text-align:justify;margin-bottom:1.5mm;}
.ps-ibold  {font-weight:700;text-decoration:underline;}
.ps-leg-t  {border-collapse:collapse;margin:2mm 0;font-size:7.5pt;width:90%;}
.ps-leg-t td{border:1px solid #000;padding:1mm 3mm;}
.ps-leg-t td:first-child{font-weight:900;text-align:center;width:12mm;}
.ps-cm-lbl {font-size:8pt;font-weight:700;margin-bottom:1.5mm;}
.ps-cm-box {border:1px solid #6B5000;border-radius:4px;padding:2mm 3mm;min-height:18mm;font-size:7.5pt;font-style:italic;background:#fffdf8;margin-bottom:3mm;}
.ps-sig-t  {width:100%;border-collapse:collapse;font-size:7.5pt;}
.ps-sig-t td,.ps-sig-t th{border:1px solid #000;padding:1.2mm 2mm;vertical-align:middle;}
.ps-sig-dr td{background:#FFC000;font-weight:700;text-decoration:underline;font-size:8pt;padding:1.2mm 2mm;}
.ps-sig-hr th{background:#FCE9D9;font-weight:700;text-align:center;width:33.3%;font-size:7.5pt;}
.ps-sig-br td{background:#FCE9D9;height:22mm;width:33.3%;}

.ps-comp   {width:100%;border-collapse:collapse;font-size:6.5pt;}
.ps-comp th,.ps-comp td{border:1px solid #000;vertical-align:middle;padding:0.7mm 1.2mm;}
.ps-hd     {background:#C2D59B;font-weight:700;text-align:center;font-size:7pt;}
.ps-sub-h  {background:#C2D59B;font-weight:700;text-align:center;font-size:6.5pt;padding:0.5mm;}
.ps-sep    {background:#FFC000;height:3px;padding:0;border:1px solid #6B5000;}
.ps-dom-c  {font-weight:900;text-align:center;vertical-align:middle;font-size:6.5pt;text-transform:uppercase;word-break:break-word;}
.ps-comp-c {font-size:6.5pt;}
.ps-score  {text-align:center;font-weight:700;font-size:7pt;}

.plbl {font-family:Arial,sans-serif;font-size:9pt;color:#555;margin-bottom:2mm;text-align:center;}
.snote{max-width:297mm;background:#fffbe6;border:1px solid #d4a900;border-radius:4px;padding:4mm 7mm;font-size:9pt;color:#444;font-family:Arial,sans-serif;margin-bottom:5mm;}
</style>

<div class="ps-wrap">

  <div class="snote">
    <strong>Instructions d'impression :</strong>
    Ouvrir dans Chrome → Imprimer → <strong>Paysage A4</strong> → <strong>Recto-verso, bord long</strong>.
    Une seule feuille imprimée des deux côtés. Pas de pliage.
  </div>

  {{-- RECTO --}}
  <div>
    <div class="plbl">RECTO — Couverture (gauche) + Compétences (droite)</div>
    <div class="ps-page">

      {{-- LEFT: COUVERTURE --}}
      <div class="ps-panel ps-left">
        <div class="ps-cover">
          <div class="ps-sch-row">
            <div class="ps-logo">
              <div class="ps-logo-t"><div class="ps-li">in</div><div class="ps-lt">TEC</div></div>
              <div class="ps-lb">É C O L E</div>
            </div>
            <div class="ps-sch-blk">
              <div class="ps-sch-nm">École internationale</div>
              <div class="ps-sch-tag"><em>pour</em> les langues et les technologies</div>
              <div class="ps-sch-ct">☎ 77 08 79 79 | 77 05 78 78<br>✉ intec.ecole.djibouti@gmail.com</div>
            </div>
          </div>

          <div class="ps-btitle">BILAN DES ACQUISITIONS</div>
          <div class="ps-bperiod">DÉCEMBRE 2025 – FÉVRIER 2026</div>
          <div class="ps-gs-ttl">Grande Section</div>

          <div class="ps-cbox">
            <div style="text-align:center;">
              <div style="font-size:42pt;line-height:1;">🏫</div>
              <div style="font-size:7pt;color:#666;margin-top:1mm;">Salle de classe préscolaire inTEC</div>
            </div>
          </div>

          <div class="ps-info">
            <div class="ps-ir"><span class="ps-il">Nom :</span><span class="ps-iv">ABDOURAHMAN IBRAHIM HASSAN</span></div>
            <div class="ps-ir"><span class="ps-il">Date de naissance :</span><span class="ps-iv">22/12/2019</span></div>
            <div class="ps-ir"><span class="ps-il">Classe :</span><span class="ps-iv">GSA</span></div>
            <div class="ps-ir"><span class="ps-il">Enseignant(e) :</span><span class="ps-iv" style="font-weight:400;">Mlle AWADA GAMIL MOHAMED</span></div>
          </div>
        </div>
      </div>

      {{-- RIGHT: COMPÉTENCES --}}
      <div class="ps-panel ps-right">
        <div style="display:flex;justify-content:space-between;align-items:baseline;border-bottom:1.5px solid #6B5000;padding-bottom:1mm;margin-bottom:2mm;">
          <span style="font-size:7.5pt;font-weight:700;text-transform:uppercase;">Tableau des compétences</span>
          <span style="font-size:7pt;color:#555;">2ème Trimestre</span>
        </div>
        <table class="ps-comp">
          <thead>
            <tr>
              <th class="ps-hd" style="width:15%;" rowspan="2">Domaines</th>
              <th class="ps-hd" style="width:59%;" rowspan="2">Compétences</th>
              <th class="ps-hd" style="width:26%;" colspan="3">Degré d'acquisition</th>
            </tr>
            <tr>
              <th class="ps-sub-h" style="width:9%;">A</th>
              <th class="ps-sub-h" style="width:9%;">EVA</th>
              <th class="ps-sub-h" style="width:8%;">NA</th>
            </tr>
          </thead>
          <tbody>
            <tr><td class="ps-dom-c" rowspan="7"><strong>LANGAGE<br>ORAL</strong></td><td class="ps-comp-c">● Saluer et prendre congé.</td><td class="ps-score">A</td><td></td><td></td></tr>
            <tr><td class="ps-comp-c">● Identifier le personnel de la ferme.</td><td class="ps-score">A</td><td></td><td></td></tr>
            <tr><td class="ps-comp-c">● Nommer et décrire les animaux familiers.</td><td class="ps-score">A</td><td></td><td></td></tr>
            <tr><td class="ps-comp-c">● Reconnaître les bâtiments de la ferme.</td><td class="ps-score">A</td><td></td><td></td></tr>
            <tr><td class="ps-comp-c">● Identifier et nommer les animaux sauvages.</td><td class="ps-score">A</td><td></td><td></td></tr>
            <tr><td class="ps-comp-c">● Décrire et comparer quelques animaux sauvages.</td><td class="ps-score">A</td><td></td><td></td></tr>
            <tr><td class="ps-comp-c">● Situer dans l'espace.</td><td></td><td></td><td class="ps-score">NA</td></tr>

            <tr><td colspan="5" class="ps-sep"></td></tr>
            <tr><td></td><td></td><th class="ps-sub-h">A</th><th class="ps-sub-h">EVA</th><th class="ps-sub-h">NA</th></tr>

            <tr><td class="ps-dom-c" rowspan="5"><strong>PRÉLECTURE</strong></td><td class="ps-comp-c">Lire des images et des mots.</td><td class="ps-score">A</td><td></td><td></td></tr>
            <tr><td class="ps-comp-c">Reconnaître un mot à partir d'un référent.</td><td class="ps-score">A</td><td></td><td></td></tr>
            <tr><td class="ps-comp-c">Associer des mots à des images.</td><td class="ps-score">A</td><td></td><td></td></tr>
            <tr><td class="ps-comp-c">Découvrir et repérer le phonème à l'étude.</td><td class="ps-score">A</td><td></td><td></td></tr>
            <tr><td class="ps-comp-c">Lecture avec fluidité, sons, syllabes et phrases.</td><td></td><td class="ps-score">EVA</td><td></td></tr>

            <tr><td colspan="5" class="ps-sep"></td></tr>
            <tr><td></td><td></td><th class="ps-sub-h">A</th><th class="ps-sub-h">EVA</th><th class="ps-sub-h">NA</th></tr>

            <tr><td class="ps-dom-c" rowspan="6"><strong>GRAPHISME /<br>ÉCRITURE</strong></td><td class="ps-comp-c">Tracer des lignes horizontales.</td><td class="ps-score">A</td><td></td><td></td></tr>
            <tr><td class="ps-comp-c">Tracer des lignes verticales.</td><td class="ps-score">A</td><td></td><td></td></tr>
            <tr><td class="ps-comp-c">Tracer des lignes obliques.</td><td class="ps-score">A</td><td></td><td></td></tr>
            <tr><td class="ps-comp-c">Tracer des boucles.</td><td class="ps-score">A</td><td></td><td></td></tr>
            <tr><td class="ps-comp-c">Tracer des ponts.</td><td class="ps-score">A</td><td></td><td></td></tr>
            <tr><td class="ps-comp-c">Calligraphier correctement la lettre (g, b, j et s).</td><td class="ps-score">A</td><td></td><td></td></tr>

            <tr><td colspan="5" class="ps-sep"></td></tr>
            <tr><td></td><td></td><th class="ps-sub-h">A</th><th class="ps-sub-h">EVA</th><th class="ps-sub-h">NA</th></tr>

            <tr><td class="ps-dom-c" rowspan="8"><strong>LOGICO<br>MATHS</strong></td><td class="ps-comp-c">Les nombres de 1 à 7.</td><td class="ps-score">A</td><td></td><td></td></tr>
            <tr><td class="ps-comp-c">Classement des aliments.</td><td class="ps-score">A</td><td></td><td></td></tr>
            <tr><td class="ps-comp-c">Comparer les quantités.</td><td></td><td class="ps-score">EVA</td><td></td></tr>
            <tr><td class="ps-comp-c">Situations additive.</td><td class="ps-score">A</td><td></td><td></td></tr>
            <tr><td class="ps-comp-c">Les animaux de la ferme.</td><td class="ps-score">A</td><td></td><td></td></tr>
            <tr><td class="ps-comp-c">Les petits animaux.</td><td class="ps-score">A</td><td></td><td></td></tr>
            <tr><td class="ps-comp-c">Le goût.</td><td></td><td class="ps-score">EVA</td><td></td></tr>
            <tr><td class="ps-comp-c">Près de, loin de…</td><td class="ps-score">A</td><td></td><td></td></tr>

            <tr><td colspan="5" class="ps-sep"></td></tr>
            <tr><td></td><td></td><th class="ps-sub-h">A</th><th class="ps-sub-h">EVA</th><th class="ps-sub-h">NA</th></tr>

            <tr><td class="ps-dom-c" rowspan="2"><strong>VIVRE<br>ENSEMBLE</strong></td><td class="ps-comp-c">● Respect de la nature.</td><td class="ps-score">A</td><td></td><td></td></tr>
            <tr><td class="ps-comp-c">● Respect du règlement.</td><td class="ps-score">A</td><td></td><td></td></tr>

            <tr><td colspan="5" class="ps-sep"></td></tr>
            <tr><td class="ps-dom-c"><strong>ÉD.<br>ISLAMIQUE</strong></td><td class="ps-comp-c">&nbsp;</td><td></td><td class="ps-score">EVA</td><td></td></tr>

            <tr><td colspan="5" class="ps-sep"></td></tr>
            <tr><td class="ps-dom-c" rowspan="2"><strong>ÉVEIL<br>ARTISTIQUE</strong></td><td class="ps-comp-c">Discriminer et produire des sons d'intensité variables.</td><td class="ps-score">A</td><td></td><td></td></tr>
            <tr><td class="ps-comp-c">Dessiner selon un modèle.</td><td class="ps-score">A</td><td></td><td></td></tr>
          </tbody>
        </table>
      </div>

    </div>
  </div>

  {{-- VERSO --}}
  <div>
    <div class="plbl">VERSO — Introduction (gauche) + Commentaires &amp; Signatures (droite)</div>
    <div class="ps-page">

      {{-- LEFT: INTRO PÉDAGOGIQUE --}}
      <div class="ps-panel ps-left" style="display:flex;flex-direction:column;">

        <div style="text-align:center;margin-bottom:2mm;">
          <div class="ps-logo" style="display:inline-block;">
            <div class="ps-logo-t"><div class="ps-li">in</div><div class="ps-lt">TEC</div></div>
            <div class="ps-lb">É C O L E</div>
          </div>
        </div>

        <div style="text-align:center;font-size:8pt;font-weight:700;text-transform:uppercase;border-bottom:2px solid #6B5000;padding-bottom:1.5mm;margin-bottom:2mm;">
          Bilan des Acquisitions — Grande Section — 2025/2026
        </div>

        <p class="ps-intro">Outil de régulation pour les activités d'enseignement-apprentissages, l'évaluation se doit de reposer sur une observation attentive de ce que chaque enfant dit ou fait. Ce qui importe alors à l'éducateur va bien au-delà du résultat obtenu, il se fixe plutôt sur le cheminement de l'enfant et les progrès qu'il réalise par rapport à lui-même et non par rapport à ses camarades ou à une quelconque norme.</p>
        <p class="ps-intro">Une évaluation positive c'est donc celle qui permet à chacun enfant d'identifier ses propres réussites, d'en garder des traces, de percevoir son évolution personnelle.</p>
        <p class="ps-intro">De ce fait, au préscolaire, le suivi des apprentissages des enfants se fait à travers deux outils :</p>
        <p class="ps-intro">— <span class="ps-ibold">Le carnet de suivi des apprentissages</span> : recueil d'observations complété tout au long des apprentissages à travers des évaluations écrites, des productions réussies, des grilles d'observation, des photos, des commentaires écrits…</p>
        <p class="ps-intro">— <span class="ps-ibold">Le bilan des compétences</span> : établi à la fin de chaque période, communiqué aux parents quatre (4) fois dans l'année. Les parents le signent pour attester réception.</p>
        <p class="ps-intro">Le positionnement par rapport aux acquis des enfants se fait sur une échelle à trois (3) niveaux : A, EVA et NA — réussit souvent, est en voie de réussite, ne réussit pas encore.</p>

        <table class="ps-leg-t" style="margin:2mm 0;">
          <tr><td><strong>A</strong></td><td>Acquis, l'enfant réussit souvent</td></tr>
          <tr><td><strong>EVA</strong></td><td>En voie d'acquisition, réussit parfois ou avec aide</td></tr>
          <tr><td><strong>NA</strong></td><td>Non acquis encore</td></tr>
        </table>

        <p class="ps-intro">L'éducation préscolaire est une éducation bienveillante — tous les enfants sont capables d'apprendre et de progresser. Apprendre quelque chose de nouveau, c'est aussi se tromper et ne pas réussir dès le premier essai ; c'est essayer encore et encore jusqu'à réussir, ce qui lui permettra de progresser et d'avoir confiance en lui.</p>
      </div>

      {{-- RIGHT: COMMENTAIRES & SIGNATURES --}}
      <div class="ps-panel ps-right" style="display:flex;flex-direction:column;">

        <div style="display:flex;justify-content:space-between;align-items:baseline;border-bottom:1.5px solid #6B5000;padding-bottom:1.5mm;margin-bottom:3mm;">
          <span style="font-size:8pt;font-weight:700;text-transform:uppercase;">Observations &amp; Signatures</span>
          <span style="font-size:7.5pt;font-weight:700;">Grande Section &nbsp;|&nbsp; 2025/2026</span>
        </div>

        <p class="ps-cm-lbl" style="margin-top:0;border-top:none;padding-top:0;">Commentaires de l'enseignant(e) :</p>
        <div class="ps-cm-box">Bon élève, il doit continuer à travailler la lecture syllabique.</div>

        <p class="ps-cm-lbl" style="margin-top:0;border-top:none;padding-top:0;">Observation de la Direction :</p>
        <div class="ps-cm-box"></div>

        <div style="flex:1;"></div>

        <table class="ps-sig-t">
          <tr class="ps-sig-dr"><td colspan="3"><span style="text-decoration:underline;">Date :</span> 28 / 02 / 2026</td></tr>
          <tr class="ps-sig-hr">
            <th>Signature de<br>l'enseignant(e)</th>
            <th>Cachet et signature<br>de la Direction</th>
            <th>Signature<br>des parents</th>
          </tr>
          <tr class="ps-sig-br"><td></td><td></td><td></td></tr>
        </table>
      </div>

    </div>
  </div>

</div>
