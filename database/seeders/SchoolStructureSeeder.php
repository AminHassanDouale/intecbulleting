<?php

namespace Database\Seeders;

use App\Enums\AcademicLevelEnum;
use App\Enums\ScaleTypeEnum;
use App\Models\Competence;
use App\Models\Niveau;
use App\Models\Subject;
use Illuminate\Database\Seeder;

/**
 * SchoolStructureSeeder
 *
 * Seeds the full academic structure: niveaux, subjects and competences.
 * Data sourced directly from the INTEC ECOLE carnets d'évaluation 2025/2026:
 *
 *   Préscolaire:
 *     - aashika_-PS.docx          → Petite Section (PS)
 *     - ACHRAF_-_MSA.docx         → Moyenne Section (MS) A
 *     - ABDOUKARIM_...-MSB.docx   → Moyenne Section (MS) B
 *     - ABDOURAHMAN-GSA.docx      → Grande Section (GS) A
 *     - abdillahi-_GSB.docx       → Grande Section (GS) B
 *
 *   Primaire:
 *     - A__Aden-cpb.docx          → CP B
 *     - ABASS-cpa.docx            → CP A
 *     - ANAS_ABDO___ce1_a.docx    → CE1 A
 *     - ABDOURAHMAN_ce1_b.docx    → CE1 B
 *     - ABDIRAZAK_AHMED-ce2b.doc  → CE2 B  (template file — structure from CE1/CM1 pattern)
 *     - AMAREH-ce2a.doc           → CE2 A  (template file — structure from CE1/CM1 pattern)
 *     - Carnet_CM1__AFNAN-cm1.docx → CM1
 *     - RAYSSO_ISMAEL-cm2.docx    → CM2
 *
 * Scale rules:
 *   Préscolaire : A / EVA / NA  → scale_type = COMPETENCE, max_score = 0
 *   Primaire    : numeric       → scale_type = NUMERIC,    max_score = as per carnet
 *
 * NOTE: The two CE2 .doc files contained only theme XML (no document body).
 *       Their structure is derived from the carnet headers and matches the
 *       CE1 / CM1 pattern confirmed in the other primaire carnets.
 */
class SchoolStructureSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedPrescolaire();
        $this->seedPrimaire();
        $this->seedCollegeLycee();
    }

    // =========================================================================
    // PRÉSCOLAIRE
    // =========================================================================
    // All subjects share classroom_code = null (PS / MS / GS all use the same
    // subject pool; the classroom seeder assigns the right ones per class).
    // Scale: A / EVA / NA  →  COMPETENCE, max_score = 0
    //
    // Domains per level (from the actual carnets):
    //
    //   PS  : LANGAGE ORAL, GRAPHISME/ÉCRITURE, EXPLORER LE MONDE (body/space),
    //         STRUCTURER SA PENSÉE, ÉDUCATION ARTISTIQUE, ACTIVITÉS MANUELLES
    //
    //   MS  : LANGAGE ORAL, PRÉ-LECTURE, GRAPHISME/ÉCRITURE,
    //         DÉCOUVRIR LE MONDE (numération), EXPLORER LE MONDE,
    //         JE VIS AVEC LES AUTRES
    //
    //   GS  : LANGAGE ORAL, PRÉLECTURE, GRAPHISME/ÉCRITURE,
    //         LOGICO-MATHS, VIVRE ENSEMBLE, ÉDUCATION ISLAMIQUE,
    //         ÉVEIL ARTISTIQUE
    //
    // Subject codes used by teacher_groups in FullSchoolSeeder:
    //   LANG, PRELEC, GRAPH    → Langage/Lecture/Écriture
    //   MATHS                  → Logico-Maths (GS) / Structurer sa Pensée (PS)
    //   VIE                    → Vivre ensemble / Je vis avec les autres
    //   EI                     → Éducation islamique
    //   ART                    → Éveil artistique / Activités manuelles
    //   MONDE                  → Explorer le monde (MS/GS)
    //   PENSEE                 → Structurer sa pensée (PS)
    //   DEC                    → Découvrir le monde — numération (MS/PS)
    //   MONDE_PS               → Explorer le monde corps & espace (PS)
    // =========================================================================

    private function seedPrescolaire(): void
    {
        $niveau = Niveau::firstOrCreate(
            ['code' => AcademicLevelEnum::PRESCOLAIRE->value],
            ['label' => AcademicLevelEnum::PRESCOLAIRE->label()]
        );

        $C = ScaleTypeEnum::COMPETENCE->value;

        // ── 1 · LANGAGE ORAL ─────────────────────────────────────────────────
        // GS carnet (GSA): 7 compétences
        // MS carnet (MSA): 9 compétences (demander info, service, préférence…)
        // PS carnet: S'habiller, corps, santé, fruits, légumes, graine à plante
        // All carnets use the LANGAGE ORAL domain → merged into one subject.
        $this->createSubject($niveau, [
            'name'       => 'LANGAGE ORAL',
            'code'       => 'LANG',
            'max_score'  => 0,
            'scale_type' => $C,
            'order'      => 1,
        ], [
            // GS set
            ['code' => 'CB1',  'description' => "Saluer et prendre congé.",                                                                                     'max_score' => null, 'order' => 1],
            ['code' => 'CB2',  'description' => "Identifier le personnel de la ferme / de l'école.",                                                            'max_score' => null, 'order' => 2],
            ['code' => 'CB3',  'description' => "Nommer et décrire les animaux familiers.",                                                                      'max_score' => null, 'order' => 3],
            ['code' => 'CB4',  'description' => "Reconnaître les bâtiments de la ferme.",                                                                        'max_score' => null, 'order' => 4],
            ['code' => 'CB5',  'description' => "Identifier et nommer les animaux sauvages.",                                                                    'max_score' => null, 'order' => 5],
            ['code' => 'CB6',  'description' => "Décrire et comparer quelques animaux sauvages.",                                                                'max_score' => null, 'order' => 6],
            ['code' => 'CB7',  'description' => "Situer dans l'espace (dessus, dessous, à côté...).",                                                            'max_score' => null, 'order' => 7],
            // MS additional set
            ['code' => 'CB8',  'description' => "Demander une information. Nommer des légumes et des fruits.",                                                   'max_score' => null, 'order' => 8],
            ['code' => 'CB9',  'description' => "Demander un service. Exprimer sa préférence. Donner des conseils. Exprimer l'admiration.",                      'max_score' => null, 'order' => 9],
            ['code' => 'CB10', 'description' => "Décrire un animal. Demander la permission. Demander des explications.",                                         'max_score' => null, 'order' => 10],
            // PS additional set (Projet 3 & 4 — Apprendre ensemble et vivre ensemble)
            ['code' => 'CB11', 'description' => "S'habiller. La propreté du corps. La santé. Le corps. Les fruits. Les légumes. De la graine à la plante.",      'max_score' => null, 'order' => 11],
        ]);

        // ── 2 · PRÉ-LECTURE ──────────────────────────────────────────────────
        // GS carnet: 5 compétences listed with scores
        // MS carnet: 4 compétences (identifier images, perception visuelle, mots, prénoms)
        $this->createSubject($niveau, [
            'name'       => 'PRÉ-LECTURE',
            'code'       => 'PRELEC',
            'max_score'  => 0,
            'scale_type' => $C,
            'order'      => 2,
        ], [
            ['code' => 'CB1', 'description' => "Lire des images et des mots.",                                                                        'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Reconnaître un mot à partir d'un référent.",                                                          'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Associer des mots à des images.",                                                                     'max_score' => null, 'order' => 3],
            ['code' => 'CB4', 'description' => "Découvrir et repérer le phonème à l'étude dans des mots.",                                            'max_score' => null, 'order' => 4],
            ['code' => 'CB5', 'description' => "Lire avec fluidité des sons, syllabes et les phrases étudiés.",                                       'max_score' => null, 'order' => 5],
            ['code' => 'CB6', 'description' => "Identifier des images. Affiner sa perception visuelle, comparer des images et des mots.",             'max_score' => null, 'order' => 6],
            ['code' => 'CB7', 'description' => "Lire globalement quelques mots et des prénoms.",                                                      'max_score' => null, 'order' => 7],
        ]);

        // ── 3 · GRAPHISME / ÉCRITURE ─────────────────────────────────────────
        // GS carnet: 6 compétences (lignes H, V, obliques, boucles, ponts, calligraphie g,b,j,s)
        // MS carnet: 6 compétences (demi-ronds C, ronds O, canne J, lettres EFH, ponts envers U, ondulée)
        // PS carnet: Les lignes ondulées, H, colorier, chiffre 1 + ABCD, lignes V, repasser, chiffre 2
        $this->createSubject($niveau, [
            'name'       => 'GRAPHISME / ÉCRITURE',
            'code'       => 'GRAPH',
            'max_score'  => 0,
            'scale_type' => $C,
            'order'      => 3,
        ], [
            // GS set
            ['code' => 'CB1',  'description' => "Tracer des lignes horizontales.",                                                                                 'max_score' => null, 'order' => 1],
            ['code' => 'CB2',  'description' => "Tracer des lignes verticales.",                                                                                   'max_score' => null, 'order' => 2],
            ['code' => 'CB3',  'description' => "Tracer des lignes obliques.",                                                                                     'max_score' => null, 'order' => 3],
            ['code' => 'CB4',  'description' => "Tracer des boucles.",                                                                                             'max_score' => null, 'order' => 4],
            ['code' => 'CB5',  'description' => "Tracer des ponts.",                                                                                               'max_score' => null, 'order' => 5],
            ['code' => 'CB6',  'description' => "Calligraphier correctement la lettre (g, b, j, s / a, i, u, e, o, p, m, t, r, c, l).",                           'max_score' => null, 'order' => 6],
            // MS set
            ['code' => 'CB7',  'description' => "Affiner son geste graphique : tracer des demi-ronds (C), des ronds (O), une canne (J), les lettres E, F, H, U.", 'max_score' => null, 'order' => 7],
            ['code' => 'CB8',  'description' => "Tracer une ligne ondulée.",                                                                                       'max_score' => null, 'order' => 8],
            // PS set
            ['code' => 'CB9',  'description' => "Les lignes horizontales. Colorier. Chiffre 1 et lettres A, B, C, D. Les lignes verticales. Chiffre 2.",          'max_score' => null, 'order' => 9],
            ['code' => 'CB10', 'description' => "Repasser sur une ligne.",                                                                                         'max_score' => null, 'order' => 10],
        ]);

        // ── 4 · LOGICO-MATHS (GS) ────────────────────────────────────────────
        // From GS carnet: Les nombres 1–7, classement aliments, comparer quantités,
        // situations additives, animaux ferme, petits animaux, goût, près/loin
        $this->createSubject($niveau, [
            'name'       => 'LOGICO-MATHS',
            'code'       => 'MATHS',
            'max_score'  => 0,
            'scale_type' => $C,
            'order'      => 4,
        ], [
            ['code' => 'CB1',  'description' => "Les nombres de 1 à 7.",                                      'max_score' => null, 'order' => 1],
            ['code' => 'CB2',  'description' => "Classement des aliments.",                                    'max_score' => null, 'order' => 2],
            ['code' => 'CB3',  'description' => "Comparer les quantités.",                                     'max_score' => null, 'order' => 3],
            ['code' => 'CB4',  'description' => "Situations additives.",                                       'max_score' => null, 'order' => 4],
            ['code' => 'CB5',  'description' => "Les animaux de la ferme.",                                    'max_score' => null, 'order' => 5],
            ['code' => 'CB6',  'description' => "Les petits des animaux.",                                     'max_score' => null, 'order' => 6],
            ['code' => 'CB7',  'description' => "Le goût.",                                                    'max_score' => null, 'order' => 7],
            ['code' => 'CB8',  'description' => "Près de, loin de...",                                         'max_score' => null, 'order' => 8],
            ['code' => 'CB9',  'description' => "Comparer deux collections sans comptage : « plus que » / « moins que ».",  'max_score' => null, 'order' => 9],
            ['code' => 'CB10', 'description' => "Comparer des collections selon le critère « autant que ».",  'max_score' => null, 'order' => 10],
            ['code' => 'CB11', 'description' => "Découvrir et dénombrer les nombres de 1 à 5.",               'max_score' => null, 'order' => 11],
            ['code' => 'CB12', 'description' => "Continuer une suite logique complexe.",                       'max_score' => null, 'order' => 12],
            ['code' => 'CB13', 'description' => "Respecter un codage.",                                        'max_score' => null, 'order' => 13],
        ]);

        // ── 5 · VIVRE ENSEMBLE / JE VIS AVEC LES AUTRES ─────────────────────
        // GS carnet: sensibiliser nature + règlement (2 items, 3rd empty)
        // MS carnet: Apprendre à vivre avec ses camarades à l'école
        $this->createSubject($niveau, [
            'name'       => 'VIVRE ENSEMBLE',
            'code'       => 'VIE',
            'max_score'  => 0,
            'scale_type' => $C,
            'order'      => 5,
        ], [
            ['code' => 'CB1', 'description' => "Se sensibiliser au respect de la nature.",                    'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Se sensibiliser au respect du règlement.",                    'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Apprendre à vivre avec ses camarades à l'école.",             'max_score' => null, 'order' => 3],
        ]);

        // ── 6 · ÉDUCATION ISLAMIQUE ──────────────────────────────────────────
        // GS carnet: 1 item (unmarked domain name + X)
        $this->createSubject($niveau, [
            'name'       => 'ÉDUCATION ISLAMIQUE',
            'code'       => 'EI',
            'max_score'  => 0,
            'scale_type' => $C,
            'order'      => 6,
        ], [
            ['code' => 'CB1', 'description' => "Connaître et pratiquer les fondements de l'éducation islamique adaptés à l'âge préscolaire.", 'max_score' => null, 'order' => 1],
        ]);

        // ── 7 · ÉVEIL ARTISTIQUE / ACTIVITÉS MANUELLES ──────────────────────
        // GS carnet: Discriminer sons fort/faible + Dessiner selon modèle
        // PS carnet: Discriminer sons fort/faible + Gribouiller librement + Dessiner selon modèle
        $this->createSubject($niveau, [
            'name'       => 'ÉVEIL ARTISTIQUE',
            'code'       => 'ART',
            'max_score'  => 0,
            'scale_type' => $C,
            'order'      => 7,
        ], [
            ['code' => 'CB1', 'description' => "Discriminer et produire des sons d'intensité variables : fort, faible.",  'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Dessiner selon un modèle.",                                               'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Gribouiller librement.",                                                  'max_score' => null, 'order' => 3],
        ]);

        // ── 8 · EXPLORER LE MONDE — MS / GS ─────────────────────────────────
        // MS carnet: 8 compétences (orienter plan, jour/nuit, 5 sens, devant/derrière,
        //            parties corps, matin/midi/soir, croissance animal, animaux/petits)
        $this->createSubject($niveau, [
            'name'       => 'EXPLORER LE MONDE',
            'code'       => 'MONDE',
            'max_score'  => 0,
            'scale_type' => $C,
            'order'      => 8,
        ], [
            ['code' => 'CB1', 'description' => "Se repérer dans l'espace : s'orienter sur un plan, suivre un chemin sur un labyrinthe.", 'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Distinguer le jour de la nuit.",                                                          'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Connaître la fonction de chacun des cinq sens.",                                          'max_score' => null, 'order' => 3],
            ['code' => 'CB4', 'description' => "Se situer dans l'espace : utiliser correctement devant / derrière pour localiser un objet ou une personne.", 'max_score' => null, 'order' => 4],
            ['code' => 'CB5', 'description' => "Nommer et situer les différentes parties de son corps.",                                  'max_score' => null, 'order' => 5],
            ['code' => 'CB6', 'description' => "Se repérer dans le temps : matin, midi, soir.",                                           'max_score' => null, 'order' => 6],
            ['code' => 'CB7', 'description' => "Reconnaître les étapes de la croissance d'un animal domestique.",                         'max_score' => null, 'order' => 7],
            ['code' => 'CB8', 'description' => "Associer les animaux domestiques à leurs petits.",                                        'max_score' => null, 'order' => 8],
        ]);

        // ── 9 · STRUCTURER SA PENSÉE — PS only ───────────────────────────────
        // PS carnet (Projet 3 & 4): petit/moyen/grand, carré, nb 1&2, plus/moins,
        //   triangle, autant, nombres jusqu'à 3
        $this->createSubject($niveau, [
            'name'       => 'STRUCTURER SA PENSÉE',
            'code'       => 'PENSEE',
            'max_score'  => 0,
            'scale_type' => $C,
            'order'      => 9,
        ], [
            ['code' => 'CB1', 'description' => "Petit, moyen, grand.",             'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Le carré.",                        'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Le nombre 1 et 2.",                'max_score' => null, 'order' => 3],
            ['code' => 'CB4', 'description' => "Plus de, moins de.",               'max_score' => null, 'order' => 4],
            ['code' => 'CB5', 'description' => "Le triangle.",                     'max_score' => null, 'order' => 5],
            ['code' => 'CB6', 'description' => "Autant de.",                       'max_score' => null, 'order' => 6],
            ['code' => 'CB7', 'description' => "Les nombres jusqu'à 3.",           'max_score' => null, 'order' => 7],
        ]);

        // ── 10 · DÉCOUVRIR LE MONDE — numération (MS / PS) ───────────────────
        // MS carnet (Découvrir le monde): 8 compétences identical to the PS DEC set
        $this->createSubject($niveau, [
            'name'       => 'DÉCOUVRIR LE MONDE',
            'code'       => 'DEC',
            'max_score'  => 0,
            'scale_type' => $C,
            'order'      => 10,
        ], [
            ['code' => 'CB1', 'description' => "Comparer deux collections sans comptage. Critères « plus que » ou « moins que ».",                            'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Comparer des collections. Réaliser une collection selon le critère « autant de... que de... ».",              'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Découvrir le nombre 3. Savoir dénombrer des collections de 1 à 3 éléments.",                                 'max_score' => null, 'order' => 3],
            ['code' => 'CB4', 'description' => "Respecter un codage.",                                                                                        'max_score' => null, 'order' => 4],
            ['code' => 'CB5', 'description' => "Découvrir le nombre 4. Savoir dénombrer et constituer une collection de 4 éléments.",                        'max_score' => null, 'order' => 5],
            ['code' => 'CB6', 'description' => "Découvrir le nombre 5. Savoir dénombrer et constituer une collection de 5 éléments.",                        'max_score' => null, 'order' => 6],
            ['code' => 'CB7', 'description' => "Continuer une suite logique complexe.",                                                                       'max_score' => null, 'order' => 7],
            ['code' => 'CB8', 'description' => "Réaliser des collections de 2 à 5 éléments.",                                                                'max_score' => null, 'order' => 8],
        ]);

        // ── 11 · EXPLORER LE MONDE — corps & espace (PS only) ────────────────
        // PS carnet (Explorer le monde): se repérer journée, à côté/entre, parties corps,
        //   voir/entendre/sentir, avant/après, en haut/bas, ombre/lumière, goût
        $this->createSubject($niveau, [
            'name'       => 'EXPLORER LE MONDE (Corps & Espace)',
            'code'       => 'MONDE_PS',
            'max_score'  => 0,
            'scale_type' => $C,
            'order'      => 11,
        ], [
            ['code' => 'CB1', 'description' => "Se repérer dans la journée.",                                                                              'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "À côté, entre.",                                                                                           'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Les parties du corps et du visage.",                                                                       'max_score' => null, 'order' => 3],
            ['code' => 'CB4', 'description' => "Voir, entendre, sentir.",                                                                                  'max_score' => null, 'order' => 4],
            ['code' => 'CB5', 'description' => "Avant, après. En haut, en bas.",                                                                           'max_score' => null, 'order' => 5],
            ['code' => 'CB6', 'description' => "L'ombre et la lumière.",                                                                                   'max_score' => null, 'order' => 6],
            ['code' => 'CB7', 'description' => "Le goût.",                                                                                                 'max_score' => null, 'order' => 7],
            ['code' => 'CB8', 'description' => "S'habiller. La propreté du corps. La santé. Les fruits. Les légumes. De la graine à la plante.",          'max_score' => null, 'order' => 8],
        ]);
    }

    // =========================================================================
    // PRIMAIRE
    // =========================================================================

    private function seedPrimaire(): void
    {
        $niveau = Niveau::firstOrCreate(
            ['code' => AcademicLevelEnum::PRIMAIRE->value],
            ['label' => AcademicLevelEnum::PRIMAIRE->label()]
        );

        $this->seedCP($niveau);
        $this->seedCE1($niveau);
        $this->seedCE2($niveau);
        $this->seedCM1($niveau);
        $this->seedCM2($niveau);
    }

    // ── CP ────────────────────────────────────────────────────────────────────
    // Source: ABASS-cpa.docx (CP A) + A__Aden-cpb.docx (CP B)
    // Total: 140 pts  (FR/40 + MATHS/30 + AR_ANG/30 + SCI/5 + EPS/5 + TECH/20 + DICT/10)
    private function seedCP(Niveau $niveau): void
    {
        $N = ScaleTypeEnum::NUMERIC->value;

        // I — FRANÇAIS : /40
        $this->createSubject($niveau, [
            'name' => 'FRANÇAIS', 'code' => 'FR', 'classroom_code' => 'CP',
            'max_score' => 40, 'scale_type' => $N, 'order' => 1,
        ], [
            ['code' => 'CB1',      'description' => "Lecture : Appréhender le sens général d'un écrit et oraliser un court texte en maîtrisant le système de correspondance graphie/phonie.",  'max_score' => 20, 'order' => 1],
            ['code' => 'CB2',      'description' => "Production écrite : Créer un court message signifiant d'au moins trois phrases.",                                                           'max_score' => 10, 'order' => 2],
            ['code' => 'CB3',      'description' => "Langage : Produire un court énoncé en utilisant les formulations adaptées.",                                                                'max_score' => 5,  'order' => 3],
            ['code' => 'ECRITURE', 'description' => "Écriture : Écrire en cursive des lettres, syllabes, mots et phrases.",                                                                     'max_score' => 5,  'order' => 4],
        ]);

        // II — MATHÉMATIQUES : /30
        $this->createSubject($niveau, [
            'name' => 'MATHÉMATIQUES', 'code' => 'MATHS', 'classroom_code' => 'CP',
            'max_score' => 30, 'scale_type' => $N, 'order' => 2,
        ], [
            ['code' => 'CB1', 'description' => "Numération : Résoudre une situation problème faisant intervenir l'écriture des nombres de 0 à 100 et des opérations simples.",  'max_score' => 10, 'order' => 1],
            ['code' => 'CB2', 'description' => "Géométrie : Se situer ou situer des objets dans l'espace et s'orienter selon son itinéraire.",                                   'max_score' => 10, 'order' => 2],
            ['code' => 'CB3', 'description' => "Mesure : Trier, comparer et ranger des objets selon un critère donné (nature, longueur...).",                                    'max_score' => 10, 'order' => 3],
        ]);

        // III — ARABE / ANGLAIS : /30
        $this->createSubject($niveau, [
            'name' => 'ARABE / ANGLAIS', 'code' => 'AR_ANG', 'classroom_code' => 'CP',
            'max_score' => 30, 'scale_type' => $N, 'order' => 3,
        ], [
            ['code' => 'CB1', 'description' => "Arabe : compréhension et expression orales/écrites de base.",                                                                                        'max_score' => 20, 'order' => 1],
            ['code' => 'CB2', 'description' => "Anglais : saluer, écrire les nombres de 1 à 12, apprendre les jours et mois, prononciation des lettres alphabétiques.",                              'max_score' => 10, 'order' => 2],
        ]);

        // IV — SCIENCES : /5
        $this->createSubject($niveau, [
            'name' => 'SCIENCES', 'code' => 'SCI', 'classroom_code' => 'CP',
            'max_score' => 5, 'scale_type' => $N, 'order' => 4,
        ], [
            ['code' => 'CB1', 'description' => "Identifier et reconnaître les différentes parties de son corps.",           'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Identifier les animaux et les végétaux de son milieu.",                     'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Nommer et sélectionner des objets d'une collection type.",                  'max_score' => null, 'order' => 3],
        ]);

        // VI — EPS : /5  (numbered VI in the carnet; V is absent at CP level)
        $this->createSubject($niveau, [
            'name' => 'ÉDUCATION PHYSIQUE ET SPORTIVE', 'code' => 'EPS', 'classroom_code' => 'CP',
            'max_score' => 5, 'scale_type' => $N, 'order' => 5,
        ], [
            ['code' => 'CB1', 'description' => "Produire des actions adaptées au milieu en appliquant les règles du jeu.",  'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Lancer et rattraper des objets variés.",                                    'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Reproduire des formes corporelles simples.",                                'max_score' => null, 'order' => 3],
        ]);

        // VII — TECHNOLOGIE : /20
        $this->createSubject($niveau, [
            'name' => 'TECHNOLOGIE', 'code' => 'TECH', 'classroom_code' => 'CP',
            'max_score' => 20, 'scale_type' => $N, 'order' => 6,
        ], [
            ['code' => 'CB1', 'description' => "Informatique : Allumer et éteindre l'outil informatique, s'approprier l'usage de la souris, taper sur le clavier.",                     'max_score' => 10, 'order' => 1],
            ['code' => 'CB2', 'description' => "Robotique : Introduire les différents types de robots, discuter de l'utilité des écrans et de leur usage.",                              'max_score' => 10, 'order' => 2],
        ]);

        // VIII — DICTÉE : /10
        $this->createSubject($niveau, [
            'name' => 'DICTÉE', 'code' => 'DICT', 'classroom_code' => 'CP',
            'max_score' => 10, 'scale_type' => $N, 'order' => 7,
        ], [
            ['code' => 'CB1', 'description' => "Maîtriser l'orthographe de la langue française. Respecter les règles d'orthographe grammaticale.",  'max_score' => 10, 'order' => 1],
        ]);
    }

    // ── CE1 ───────────────────────────────────────────────────────────────────
    // Source: ANAS_ABDO___ce1_a.docx (CE1 A) + ABDOURAHMAN_ce1_b.docx (CE1 B)
    // Total: 170 pts  (FR/30 + AR/30 + ANG/10 + OUTILS/40 + MATHS/30 + GEO/10 + HIST/10 + SCI/10 + EPS/10 + TECH/20)
    private function seedCE1(Niveau $niveau): void
    {
        $N = ScaleTypeEnum::NUMERIC->value;

        // I — FRANÇAIS : /30
        $this->createSubject($niveau, [
            'name' => 'FRANÇAIS', 'code' => 'FR', 'classroom_code' => 'CE1',
            'max_score' => 30, 'scale_type' => $N, 'order' => 1,
        ], [
            ['code' => 'CB1', 'description' => "Lecture : Récolter des informations pertinentes dans un texte de 9 à 10 phrases en vue d'accéder à la compréhension.",  'max_score' => 10, 'order' => 1],
            ['code' => 'CB2', 'description' => "Production écrite : Produire un discours cohérent de 5 à 6 phrases dans un contexte de communication écrite.",           'max_score' => 10, 'order' => 2],
            ['code' => 'CB3', 'description' => "Production orale : Produire un discours cohérent dans un contexte de communication orale.",                               'max_score' => 10, 'order' => 3],
        ]);

        // II — ARABE : /30
        $this->createSubject($niveau, [
            'name' => 'ARABE', 'code' => 'AR', 'classroom_code' => 'CE1',
            'max_score' => 30, 'scale_type' => $N, 'order' => 2,
        ], [
            ['code' => 'CB1', 'description' => "Lecture.",           'max_score' => 10, 'order' => 1],
            ['code' => 'CB2', 'description' => "Expression orale.",  'max_score' => 10, 'order' => 2],
            ['code' => 'CB3', 'description' => "Expression écrite.", 'max_score' => 10, 'order' => 3],
        ]);

        // III — ANGLAIS : /10
        $this->createSubject($niveau, [
            'name' => 'ANGLAIS', 'code' => 'ANG', 'classroom_code' => 'CE1',
            'max_score' => 10, 'scale_type' => $N, 'order' => 3,
        ], [
            ['code' => 'CB1', 'description' => "Saluer, écrire les nombres de 1 à 12. Apprendre les jours et les mois. Prononciation des lettres alphabétiques.",                         'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Reconnaître les fournitures scolaires, les mobiliers scolaires. Prononciation des lettres alphabétiques.",                                 'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Reconnaître les différentes parties du corps. Apprendre les adjectifs qualificatifs. Révision. Prononciation des lettres alphabétiques.", 'max_score' => null, 'order' => 3],
        ]);

        // IV — OUTILS DE LA LANGUE : /40
        $this->createSubject($niveau, [
            'name' => 'OUTILS DE LA LANGUE', 'code' => 'OUTILS', 'classroom_code' => 'CE1',
            'max_score' => 40, 'scale_type' => $N, 'order' => 4,
        ], [
            ['code' => 'CB1', 'description' => "Vocabulaire : Décrire une personne, décrire un lieu, décrire un métier, raconter un événement.",                                                  'max_score' => 10, 'order' => 1],
            ['code' => 'CB2', 'description' => "Conjugaison : Reconnaître le temps des verbes du 1er gr, 2ème gr et 3ème groupe au présent, passé composé, futur...",                              'max_score' => 10, 'order' => 2],
            ['code' => 'CB3', 'description' => "Grammaire : Reconnaître les noms, les adjectifs, les adverbes...",                                                                                 'max_score' => 10, 'order' => 3],
            ['code' => 'CB4', 'description' => "Orthographe : Reconnaître le genre, le nombre des noms, des adjectifs...",                                                                        'max_score' => 10, 'order' => 4],
        ]);

        // V — MATHÉMATIQUES : /30
        $this->createSubject($niveau, [
            'name' => 'MATHÉMATIQUES', 'code' => 'MATHS', 'classroom_code' => 'CE1',
            'max_score' => 30, 'scale_type' => $N, 'order' => 5,
        ], [
            ['code' => 'CB1', 'description' => "Numération : Résoudre une situation faisant intervenir les 3 opérations (+, −, ×) avec des nombres entiers de 1 à 1 000.",             'max_score' => 10, 'order' => 1],
            ['code' => 'CB2', 'description' => "Géométrie : Résoudre une situation problème faisant appel à la représentation et la construction d'une figure.",                        'max_score' => 10, 'order' => 2],
            ['code' => 'CB3', 'description' => "Mesure : Résoudre une situation problème nécessitant le rangement et le classement d'événements dans le temps ou des objets.",         'max_score' => 10, 'order' => 3],
        ]);

        // VI — GÉOGRAPHIE : /10
        $this->createSubject($niveau, [
            'name' => 'GÉOGRAPHIE', 'code' => 'GEO', 'classroom_code' => 'CE1',
            'max_score' => 10, 'scale_type' => $N, 'order' => 6,
        ], [
            ['code' => 'CB1', 'description' => "Utiliser des outils de géographie (croquis, plan) pour représenter un élément de son environnement immédiat.", 'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Utiliser les notions générales de géographie (relief et climat).",                                              'max_score' => null, 'order' => 2],
        ]);

        // VII — HISTOIRE : /10
        $this->createSubject($niveau, [
            'name' => 'HISTOIRE', 'code' => 'HIST', 'classroom_code' => 'CE1',
            'max_score' => 10, 'scale_type' => $N, 'order' => 7,
        ], [
            ['code' => 'CB1', 'description' => "Situer sur une frise chronologique un événement.",                                                                                           'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Relever les caractéristiques essentielles d'une période donnée et les comparer à la réalité djiboutienne actuelle.",                         'max_score' => null, 'order' => 2],
        ]);

        // VIII — SCIENCES EXPÉRIMENTALES : /10
        $this->createSubject($niveau, [
            'name' => 'SCIENCES EXPÉRIMENTALES', 'code' => 'SCI', 'classroom_code' => 'CE1',
            'max_score' => 10, 'scale_type' => $N, 'order' => 8,
        ], [
            ['code' => 'CB1', 'description' => "Connaître la structure du corps humain (squelette et muscles) en vue d'améliorer son hygiène de vie.",                 'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Identifier les besoins alimentaires des animaux et des plantes.",                                                      'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Identifier l'état physique d'un élément du milieu (liquide ou solide).",                                              'max_score' => null, 'order' => 3],
        ]);

        // IX — EPS : /10
        $this->createSubject($niveau, [
            'name' => 'ÉDUCATION PHYSIQUE ET SPORTIVE', 'code' => 'EPS', 'classroom_code' => 'CE1',
            'max_score' => 10, 'scale_type' => $N, 'order' => 9,
        ], [
            ['code' => 'CB1', 'description' => "Adapter et varier ses courses et ses bonds sur une distance donnée.",                             'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Lancer ou rattraper des objets de forme et dimension variées.",                                   'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Coordonner ses capacités motrices pour contribuer au projet de son équipe.",                      'max_score' => null, 'order' => 3],
            ['code' => 'CB4', 'description' => "Produire et adapter ses mouvements ou ses déplacements en tempo collectif.",                      'max_score' => null, 'order' => 4],
        ]);

        // X — TECHNOLOGIE : /20
        $this->createSubject($niveau, [
            'name' => 'TECHNOLOGIE', 'code' => 'TECH', 'classroom_code' => 'CE1',
            'max_score' => 20, 'scale_type' => $N, 'order' => 10,
        ], [
            ['code' => 'CB1', 'description' => "Informatique : Allumer et éteindre un outil informatique, lancer et quitter un logiciel, s'approprier l'usage de la souris, taper sur le clavier, accéder à un dossier.", 'max_score' => 10, 'order' => 1],
            ['code' => 'CB2', 'description' => "Robotique : Découvrir les différents composants du robot, aborder le fonctionnement du robot, appréhender le système binaire et de code.",                                  'max_score' => 10, 'order' => 2],
        ]);
    }

    // ── CE2 ───────────────────────────────────────────────────────────────────
    // Source: Structure from CE2 carnet headers (the .doc files were theme-only files).
    // The CE2 structure follows the CE1/CM1 pattern: same subjects, updated CB descriptions.
    // Total: 160 pts  (FR/40 + OUTILS/40 + AR/20 + ANG/20 + MATHS/30 + GEO/10 + HIST/10 + SCI/10 + TECH/20)
    private function seedCE2(Niveau $niveau): void
    {
        $N = ScaleTypeEnum::NUMERIC->value;

        // I — FRANÇAIS : /40
        $this->createSubject($niveau, [
            'name' => 'FRANÇAIS', 'code' => 'FR', 'classroom_code' => 'CE2',
            'max_score' => 40, 'scale_type' => $N, 'order' => 1,
        ], [
            ['code' => 'CB1', 'description' => "Lecture : Récolter des informations pertinentes dans un texte de 10 à 15 phrases. Lecture oralisée.",                           'max_score' => 20, 'order' => 1],
            ['code' => 'CB2', 'description' => "Production écrite : Produire un écrit cohérent de 8 phrases en fonction d'un support écrit.",                                    'max_score' => 10, 'order' => 2],
            ['code' => 'CB3', 'description' => "Production orale : Utiliser les moyens linguistiques en vue de produire un discours oral ou de dialogue.",                       'max_score' => 10, 'order' => 3],
        ]);

        // II — OUTILS DE LA LANGUE : /40
        $this->createSubject($niveau, [
            'name' => 'OUTILS DE LA LANGUE', 'code' => 'OUTILS', 'classroom_code' => 'CE2',
            'max_score' => 40, 'scale_type' => $N, 'order' => 2,
        ], [
            ['code' => 'CB1', 'description' => "Vocabulaire : Décrire une personne, localiser un lieu, donner des informations, raconter un événement.",                                                    'max_score' => 10, 'order' => 1],
            ['code' => 'CB2', 'description' => "Conjugaison : Reconnaître le temps des verbes du 1er gr, 2ème gr et 3ème gr au passé composé, présent, futur et imparfait.",                               'max_score' => 10, 'order' => 2],
            ['code' => 'CB3', 'description' => "Grammaire : Reconnaître le type de phrase, la forme et les adjectifs.",                                                                                    'max_score' => 10, 'order' => 3],
            ['code' => 'CB4', 'description' => "Orthographe : Reconnaître les différentes graphies, le genre et nombre des adjectifs et les mots invariables.",                                            'max_score' => 10, 'order' => 4],
        ]);

        // III — ARABE : /20
        $this->createSubject($niveau, [
            'name' => 'ARABE', 'code' => 'AR', 'classroom_code' => 'CE2',
            'max_score' => 20, 'scale_type' => $N, 'order' => 3,
        ], [
            ['code' => 'CB1', 'description' => "Expression écrite.", 'max_score' => 10, 'order' => 1],
            ['code' => 'CB2', 'description' => "Expression orale.",  'max_score' => 10, 'order' => 2],
        ]);

        // IV — ANGLAIS : /20
        $this->createSubject($niveau, [
            'name' => 'ANGLAIS', 'code' => 'ANG', 'classroom_code' => 'CE2',
            'max_score' => 20, 'scale_type' => $N, 'order' => 4,
        ], [
            ['code' => 'CB1', 'description' => "Saluer, écrire les nombres de 1 à 12. Apprendre les jours et les mois. Prononciation des lettres alphabétiques.",                              'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Reconnaître les fournitures scolaires, les mobiliers scolaires. Identifier les membres de sa famille.",                                        'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Reconnaître les différentes parties du corps. Apprendre les adjectifs qualificatifs. Révision.",                                               'max_score' => null, 'order' => 3],
        ]);

        // V — MATHÉMATIQUES : /30
        $this->createSubject($niveau, [
            'name' => 'MATHÉMATIQUES', 'code' => 'MATHS', 'classroom_code' => 'CE2',
            'max_score' => 30, 'scale_type' => $N, 'order' => 5,
        ], [
            ['code' => 'CB1', 'description' => "Numération : Résoudre des situations significatives mobilisant les 3 opérations (+, −, ×) et la division sur des nombres entiers à 999 999.",    'max_score' => 10, 'order' => 1],
            ['code' => 'CB2', 'description' => "Géométrie : Décrire ou reproduire des figures simples avec les instruments adéquats (règle, équerre et compas).",                                'max_score' => 10, 'order' => 2],
            ['code' => 'CB3', 'description' => "Mesure : Évaluer, comparer des mesures (longueurs, masses et durées) et élaborer des chronologies d'événements dans le temps.",                 'max_score' => 10, 'order' => 3],
        ]);

        // VI — GÉOGRAPHIE : /10
        $this->createSubject($niveau, [
            'name' => 'GÉOGRAPHIE', 'code' => 'GEO', 'classroom_code' => 'CE2',
            'max_score' => 10, 'scale_type' => $N, 'order' => 6,
        ], [
            ['code' => 'CB1', 'description' => "Face à une situation-problème, émettre des propositions sur la base de schémas, de cartes et des croquis.",                                    'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "À l'aide de tableaux relatifs aux données climatiques, résoudre une situation-problème.",                                                       'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Face à une situation-problème, identifier des actions néfastes et proposer des actions de protection de l'environnement.",                     'max_score' => null, 'order' => 3],
        ]);

        // VII — HISTOIRE : /10
        $this->createSubject($niveau, [
            'name' => 'HISTOIRE', 'code' => 'HIST', 'classroom_code' => 'CE2',
            'max_score' => 10, 'scale_type' => $N, 'order' => 7,
        ], [
            ['code' => 'CB1', 'description' => "Évoquer deux faits, personnages ou lieux et les situer sur une frise chronologique.",  'max_score' => 10, 'order' => 1],
        ]);

        // VIII — SCIENCES EXPÉRIMENTALES : /10
        $this->createSubject($niveau, [
            'name' => 'SCIENCES EXPÉRIMENTALES', 'code' => 'SCI', 'classroom_code' => 'CE2',
            'max_score' => 10, 'scale_type' => $N, 'order' => 8,
        ], [
            ['code' => 'CB1', 'description' => "Identifier les besoins alimentaires nécessaires à son organisme en vue d'améliorer son hygiène de vie.",          'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Déterminer les conditions nécessaires à la croissance des animaux et des plantes.",                               'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Dégager les applications pratiques de quelques phénomènes physiques usuels (électricité, air, son).",             'max_score' => null, 'order' => 3],
        ]);

        // IX — TECHNOLOGIE : /20
        $this->createSubject($niveau, [
            'name' => 'TECHNOLOGIE', 'code' => 'TECH', 'classroom_code' => 'CE2',
            'max_score' => 20, 'scale_type' => $N, 'order' => 9,
        ], [
            ['code' => 'CB1', 'description' => "Informatique : Allumer et éteindre un outil informatique, lancer et quitter un logiciel, s'approprier l'usage de la souris, taper sur le clavier, accéder à un dossier.",            'max_score' => 10, 'order' => 1],
            ['code' => 'CB2', 'description' => "Robotique : Découvrir les différents composants du robot, aborder le fonctionnement du robot, appréhender le système binaire et de code, imaginer un dessin binaire.",               'max_score' => 10, 'order' => 2],
        ]);
    }

    // ── CM1 ───────────────────────────────────────────────────────────────────
    // Source: Carnet_CM1__AFNAN-cm1.docx
    // Total: 200 pts  (FR/40 + OUTILS/40 + AR_EI/20 + ANG/20 + MATHS/30 + GEO/10 + HIST/10 + SCI/10 + TECH/20)
    // Note: the carnet shows total /200, moyenne /20 — the max is therefore 200.
    private function seedCM1(Niveau $niveau): void
    {
        $N = ScaleTypeEnum::NUMERIC->value;

        // I — FRANÇAIS : /40
        $this->createSubject($niveau, [
            'name' => 'FRANÇAIS', 'code' => 'FR', 'classroom_code' => 'CM1',
            'max_score' => 40, 'scale_type' => $N, 'order' => 1,
        ], [
            ['code' => 'CB1', 'description' => "Lecture : Récolter des informations pertinentes dans un texte de 20 à 30 phrases. Lecture oralisée.",                            'max_score' => 20, 'order' => 1],
            ['code' => 'CB2', 'description' => "Production écrite : Produire un écrit cohérent d'une dizaine de phrases en fonction d'un support écrit.",                         'max_score' => 10, 'order' => 2],
            ['code' => 'CB3', 'description' => "Production orale : Présenter un exposé individuel utilisant les différents modes de discours.",                                   'max_score' => 10, 'order' => 3],
        ]);

        // II — OUTILS DE LA LANGUE : /40
        $this->createSubject($niveau, [
            'name' => 'OUTILS DE LA LANGUE', 'code' => 'OUTILS', 'classroom_code' => 'CM1',
            'max_score' => 40, 'scale_type' => $N, 'order' => 2,
        ], [
            ['code' => 'CB1', 'description' => "Vocabulaire : Décrire une personne, localiser un lieu, donner des informations, raconter un événement, les contraires, synonymes, homonymes, champ lexical.", 'max_score' => 10, 'order' => 1],
            ['code' => 'CB2', 'description' => "Conjugaison : Reconnaître le temps des verbes du 1er gr, 2ème gr et 3ème gr au passé composé, présent, futur, imparfait et passé simple.",                     'max_score' => 10, 'order' => 2],
            ['code' => 'CB3', 'description' => "Grammaire : Reconnaître le type de phrase, la forme, les adjectifs, les pronoms.",                                                                              'max_score' => 10, 'order' => 3],
            ['code' => 'CB4', 'description' => "Orthographe : Reconnaître les différentes graphies, le genre et nombre des adjectifs, les mots invariables et les homophones.",                                'max_score' => 10, 'order' => 4],
        ]);

        // III — ARABE / ÉDUCATION ISLAMIQUE : /20
        $this->createSubject($niveau, [
            'name' => 'ARABE / ÉDUCATION ISLAMIQUE', 'code' => 'AR_EI', 'classroom_code' => 'CM1',
            'max_score' => 20, 'scale_type' => $N, 'order' => 3,
        ], [
            ['code' => 'CB1', 'description' => "Lecture.",           'max_score' => 10, 'order' => 1],
            ['code' => 'CB2', 'description' => "Expression écrite.", 'max_score' => 10, 'order' => 2],
        ]);

        // IV — ANGLAIS : /20
        $this->createSubject($niveau, [
            'name' => 'ANGLAIS', 'code' => 'ANG', 'classroom_code' => 'CM1',
            'max_score' => 20, 'scale_type' => $N, 'order' => 4,
        ], [
            ['code' => 'CB1', 'description' => "Saluer, écrire les nombres de 1 à 20. Apprendre les jours et les mois. Prononciation des lettres alphabétiques.",                         'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Reconnaître les fournitures scolaires, les mobiliers scolaires. Identifier les membres de sa famille. Prononciation des lettres.",         'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Reconnaître les différentes parties du corps. Apprendre les adjectifs qualificatifs. Révision. Prononciation des lettres alphabétiques.", 'max_score' => null, 'order' => 3],
        ]);

        // V — MATHÉMATIQUES : /30
        $this->createSubject($niveau, [
            'name' => 'MATHÉMATIQUES', 'code' => 'MATHS', 'classroom_code' => 'CM1',
            'max_score' => 30, 'scale_type' => $N, 'order' => 5,
        ], [
            ['code' => 'CB1', 'description' => "Numération : Résoudre des situations significatives mobilisant les 4 opérations (+, −, ×, ÷) sur des nombres entiers à 999 999 999.",                                              'max_score' => 10, 'order' => 1],
            ['code' => 'CB2', 'description' => "Géométrie : Résoudre des situations problèmes nécessitant la description et la construction des figures planes et des solides.",                                                    'max_score' => 10, 'order' => 2],
            ['code' => 'CB3', 'description' => "Mesure : Résoudre des situations problèmes faisant intervenir des unités de mesure de longueur et d'aires, ainsi que de masses, des angles et des durées.",                       'max_score' => 10, 'order' => 3],
        ]);

        // VI — GÉOGRAPHIE : /10
        $this->createSubject($niveau, [
            'name' => 'GÉOGRAPHIE', 'code' => 'GEO', 'classroom_code' => 'CM1',
            'max_score' => 10, 'scale_type' => $N, 'order' => 6,
        ], [
            ['code' => 'CB1', 'description' => "Face à une situation-problème, émettre des propositions sur la base de schémas, de cartes et des croquis.",                                    'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "À l'aide de tableaux relatifs aux données climatiques, résoudre une situation-problème.",                                                       'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Face à une situation-problème, identifier des actions néfastes et proposer des actions de protection de l'environnement.",                     'max_score' => null, 'order' => 3],
        ]);

        // VII — HISTOIRE : /10
        $this->createSubject($niveau, [
            'name' => 'HISTOIRE', 'code' => 'HIST', 'classroom_code' => 'CM1',
            'max_score' => 10, 'scale_type' => $N, 'order' => 7,
        ], [
            ['code' => 'CB1', 'description' => "Évoquer deux faits, personnages ou lieux et les situer sur une frise chronologique.",  'max_score' => 10, 'order' => 1],
        ]);

        // VIII — SCIENCES EXPÉRIMENTALES : /10
        $this->createSubject($niveau, [
            'name' => 'SCIENCES EXPÉRIMENTALES', 'code' => 'SCI', 'classroom_code' => 'CM1',
            'max_score' => 10, 'scale_type' => $N, 'order' => 8,
        ], [
            ['code' => 'CB1', 'description' => "Identifier les besoins alimentaires nécessaires à son organisme en vue d'améliorer son hygiène de vie.",          'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Déterminer les conditions nécessaires à la croissance des animaux et des plantes.",                               'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Dégager les applications pratiques de quelques phénomènes physiques usuels (électricité, air, son).",             'max_score' => null, 'order' => 3],
        ]);

        // IX — TECHNOLOGIE : /20
        $this->createSubject($niveau, [
            'name' => 'TECHNOLOGIE', 'code' => 'TECH', 'classroom_code' => 'CM1',
            'max_score' => 20, 'scale_type' => $N, 'order' => 9,
        ], [
            ['code' => 'CB1', 'description' => "Informatique : Allumer et éteindre un outil informatique, lancer et quitter un logiciel, s'approprier l'usage de la souris, taper sur le clavier, accéder à un dossier.", 'max_score' => 10, 'order' => 1],
            ['code' => 'CB2', 'description' => "Robotique : Découvrir les différents composants du robot, aborder le fonctionnement du robot, appréhender le système binaire et de code, imaginer un dessin binaire.",    'max_score' => 10, 'order' => 2],
        ]);
    }

    // ── CM2 ───────────────────────────────────────────────────────────────────
    // Source: RAYSSO_ISMAEL-cm2.docx
    // Total: 200 pts  (same structure as CM1; only Numération extends to 999 999 999 999)
    private function seedCM2(Niveau $niveau): void
    {
        $N = ScaleTypeEnum::NUMERIC->value;

        // I — FRANÇAIS : /40
        $this->createSubject($niveau, [
            'name' => 'FRANÇAIS', 'code' => 'FR', 'classroom_code' => 'CM2',
            'max_score' => 40, 'scale_type' => $N, 'order' => 1,
        ], [
            ['code' => 'CB1', 'description' => "Lecture : Récolter des informations pertinentes dans un texte de 20 à 30 phrases. Lecture oralisée.",                            'max_score' => 20, 'order' => 1],
            ['code' => 'CB2', 'description' => "Production écrite : Produire un écrit cohérent d'une dizaine de phrases en fonction d'un support écrit.",                         'max_score' => 10, 'order' => 2],
            ['code' => 'CB3', 'description' => "Production orale : Présenter un exposé individuel utilisant les différents modes de discours.",                                   'max_score' => 10, 'order' => 3],
        ]);

        // II — OUTILS DE LA LANGUE : /40
        $this->createSubject($niveau, [
            'name' => 'OUTILS DE LA LANGUE', 'code' => 'OUTILS', 'classroom_code' => 'CM2',
            'max_score' => 40, 'scale_type' => $N, 'order' => 2,
        ], [
            ['code' => 'CB1', 'description' => "Vocabulaire : Décrire une personne, localiser un lieu, donner des informations, raconter un événement, les contraires, synonymes, homonymes, champ lexical.", 'max_score' => 10, 'order' => 1],
            ['code' => 'CB2', 'description' => "Conjugaison : Reconnaître le temps des verbes du 1er gr, 2ème gr et 3ème gr au passé composé, présent, futur, imparfait et passé simple.",                     'max_score' => 10, 'order' => 2],
            ['code' => 'CB3', 'description' => "Grammaire : Reconnaître le type de phrase, la forme, les adjectifs, les pronoms.",                                                                              'max_score' => 10, 'order' => 3],
            ['code' => 'CB4', 'description' => "Orthographe : Reconnaître les différentes graphies, le genre et nombre des adjectifs, les mots invariables et les homophones.",                                'max_score' => 10, 'order' => 4],
        ]);

        // III — ARABE / ÉDUCATION ISLAMIQUE : /20
        $this->createSubject($niveau, [
            'name' => 'ARABE / ÉDUCATION ISLAMIQUE', 'code' => 'AR_EI', 'classroom_code' => 'CM2',
            'max_score' => 20, 'scale_type' => $N, 'order' => 3,
        ], [
            ['code' => 'CB1', 'description' => "Lecture.",           'max_score' => 10, 'order' => 1],
            ['code' => 'CB2', 'description' => "Expression écrite.", 'max_score' => 10, 'order' => 2],
        ]);

        // IV — ANGLAIS : /20
        $this->createSubject($niveau, [
            'name' => 'ANGLAIS', 'code' => 'ANG', 'classroom_code' => 'CM2',
            'max_score' => 20, 'scale_type' => $N, 'order' => 4,
        ], [
            ['code' => 'CB1', 'description' => "Saluer, écrire les nombres de 1 à 20. Apprendre les jours et les mois. Prononciation des lettres alphabétiques.",                         'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Reconnaître les fournitures scolaires, les mobiliers scolaires. Identifier les membres de sa famille. Prononciation des lettres.",         'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Reconnaître les différentes parties du corps. Apprendre les adjectifs qualificatifs. Révision. Prononciation des lettres alphabétiques.", 'max_score' => null, 'order' => 3],
        ]);

        // V — MATHÉMATIQUES : /30
        $this->createSubject($niveau, [
            'name' => 'MATHÉMATIQUES', 'code' => 'MATHS', 'classroom_code' => 'CM2',
            'max_score' => 30, 'scale_type' => $N, 'order' => 5,
        ], [
            // CM2 differs from CM1: numbers go to 999 999 999 999 (12 digits)
            ['code' => 'CB1', 'description' => "Numération : Résoudre des situations significatives mobilisant les 4 opérations (+, −, ×, ÷) sur des nombres entiers à 999 999 999 999.", 'max_score' => 10, 'order' => 1],
            ['code' => 'CB2', 'description' => "Géométrie : Résoudre des situations problèmes nécessitant la description et la construction des figures planes et des solides.",          'max_score' => 10, 'order' => 2],
            ['code' => 'CB3', 'description' => "Mesure : Résoudre des situations problèmes faisant intervenir des unités de mesure de longueur et d'aires, ainsi que de masses, des angles et des durées.", 'max_score' => 10, 'order' => 3],
        ]);

        // VI — GÉOGRAPHIE : /10
        $this->createSubject($niveau, [
            'name' => 'GÉOGRAPHIE', 'code' => 'GEO', 'classroom_code' => 'CM2',
            'max_score' => 10, 'scale_type' => $N, 'order' => 6,
        ], [
            ['code' => 'CB1', 'description' => "Face à une situation-problème, émettre des propositions sur la base de schémas, de cartes et des croquis.",                                    'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "À l'aide de tableaux relatifs aux données climatiques, résoudre une situation-problème.",                                                       'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Face à une situation-problème, identifier des actions néfastes et proposer des actions de protection de l'environnement.",                     'max_score' => null, 'order' => 3],
        ]);

        // VII — HISTOIRE : /10
        $this->createSubject($niveau, [
            'name' => 'HISTOIRE', 'code' => 'HIST', 'classroom_code' => 'CM2',
            'max_score' => 10, 'scale_type' => $N, 'order' => 7,
        ], [
            ['code' => 'CB1', 'description' => "Évoquer deux faits, personnages ou lieux et les situer sur une frise chronologique.",  'max_score' => 10, 'order' => 1],
        ]);

        // VIII — SCIENCES EXPÉRIMENTALES : /10
        $this->createSubject($niveau, [
            'name' => 'SCIENCES EXPÉRIMENTALES', 'code' => 'SCI', 'classroom_code' => 'CM2',
            'max_score' => 10, 'scale_type' => $N, 'order' => 8,
        ], [
            ['code' => 'CB1', 'description' => "Identifier les besoins alimentaires nécessaires à son organisme en vue d'améliorer son hygiène de vie.",          'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Déterminer les conditions nécessaires à la croissance des animaux et des plantes.",                               'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Dégager les applications pratiques de quelques phénomènes physiques usuels (électricité, air, son).",             'max_score' => null, 'order' => 3],
        ]);

        // IX — TECHNOLOGIE : /20
        $this->createSubject($niveau, [
            'name' => 'TECHNOLOGIE', 'code' => 'TECH', 'classroom_code' => 'CM2',
            'max_score' => 20, 'scale_type' => $N, 'order' => 9,
        ], [
            ['code' => 'CB1', 'description' => "Informatique : Allumer et éteindre un outil informatique, lancer et quitter un logiciel, s'approprier l'usage de la souris, taper sur le clavier, accéder à un dossier.", 'max_score' => 10, 'order' => 1],
            ['code' => 'CB2', 'description' => "Robotique : Découvrir les différents composants du robot, aborder le fonctionnement du robot, appréhender le système binaire et de code, imaginer un dessin binaire.",    'max_score' => 10, 'order' => 2],
        ]);
    }

    // =========================================================================
    // COLLÈGE / LYCÉE
    // =========================================================================

    private function seedCollegeLycee(): void
    {
        $college = Niveau::firstOrCreate(
            ['code' => AcademicLevelEnum::COLLEGE->value],
            ['label' => AcademicLevelEnum::COLLEGE->label()]
        );
        $lycee = Niveau::firstOrCreate(
            ['code' => AcademicLevelEnum::LYCEE->value],
            ['label' => AcademicLevelEnum::LYCEE->label()]
        );

        foreach ([$college, $lycee] as $niveau) {
            $this->createSubject($niveau, ['name' => 'FRANÇAIS',          'code' => 'FR',   'max_score' => 20, 'scale_type' => ScaleTypeEnum::NUMERIC->value, 'order' => 1], [
                ['code' => 'CB1', 'description' => "Lecture et compréhension de texte.",           'max_score' => 10, 'order' => 1],
                ['code' => 'CB2', 'description' => "Expression écrite et production textuelle.",   'max_score' => 10, 'order' => 2],
            ]);
            $this->createSubject($niveau, ['name' => 'MATHÉMATIQUES',     'code' => 'MATHS','max_score' => 20, 'scale_type' => ScaleTypeEnum::NUMERIC->value, 'order' => 2], [
                ['code' => 'CB1', 'description' => "Algèbre et calcul numérique.",                 'max_score' => 10, 'order' => 1],
                ['code' => 'CB2', 'description' => "Géométrie et résolution de problèmes.",        'max_score' => 10, 'order' => 2],
            ]);
            $this->createSubject($niveau, ['name' => 'ANGLAIS',           'code' => 'ANG',  'max_score' => 20, 'scale_type' => ScaleTypeEnum::NUMERIC->value, 'order' => 3], [
                ['code' => 'CB1', 'description' => "Compréhension écrite et orale.",               'max_score' => 10, 'order' => 1],
                ['code' => 'CB2', 'description' => "Expression écrite et production.",             'max_score' => 10, 'order' => 2],
            ]);
            $this->createSubject($niveau, ['name' => 'SVT / SCIENCES',    'code' => 'SVT',  'max_score' => 20, 'scale_type' => ScaleTypeEnum::NUMERIC->value, 'order' => 4], [
                ['code' => 'CB1', 'description' => "Connaissance des faits scientifiques.",        'max_score' => 10, 'order' => 1],
                ['code' => 'CB2', 'description' => "Démarche scientifique et expérimentation.",    'max_score' => 10, 'order' => 2],
            ]);
            $this->createSubject($niveau, ['name' => 'HISTOIRE-GÉOGRAPHIE','code' => 'HG',  'max_score' => 20, 'scale_type' => ScaleTypeEnum::NUMERIC->value, 'order' => 5], [
                ['code' => 'CB1', 'description' => "Maîtrise des faits historiques et géographiques.",      'max_score' => 10, 'order' => 1],
                ['code' => 'CB2', 'description' => "Analyse de documents et expression écrite.",             'max_score' => 10, 'order' => 2],
            ]);
        }
    }

    // =========================================================================
    // Utility
    // =========================================================================

    private function createSubject(Niveau $niveau, array $subjectData, array $competencesData): void
    {
        $subjectData['niveau_id'] = $niveau->id;

        $subject = Subject::firstOrCreate(
            [
                'code'           => $subjectData['code'],
                'niveau_id'      => $niveau->id,
                'classroom_code' => $subjectData['classroom_code'] ?? null,
            ],
            $subjectData
        );

        foreach ($competencesData as $comp) {
            Competence::firstOrCreate(
                ['subject_id' => $subject->id, 'code' => $comp['code']],
                array_merge($comp, ['subject_id' => $subject->id])
            );
        }
    }
}
