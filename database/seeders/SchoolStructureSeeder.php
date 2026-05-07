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
 * Data sourced directly from the INTEC ECOLE carnets d'évaluation 2025/2026
 * and the Catalogue_Competences_2025-2026_v4.xlsx (authoritative source for
 * NOTE MAX matière and NOTE MAX compétence values).
 *
 * ─── PRIMAIRE — per-sheet authority (xlsx) ──────────────────────────────────
 *
 *   CPA / CPB sheet  → CP  (both sheets identical in structure)
 *   CE1A / CE1B      → CE1 (both identical)
 *   CE2A / CE2B      → CE2 (both identical)
 *   CM1              → CM1
 *   CM2              → CM2
 *
 * Scale rules:
 *   Préscolaire : A / EVA / NA  → scale_type = COMPETENCE, max_score = 0
 *   Primaire    : numeric       → scale_type = NUMERIC,    max_score = as per catalogue
 *
 * ─── KEY CORRECTIONS vs. previous version ───────────────────────────────────
 *
 *  CP
 *    - MATHS : competence max_score = null (shared pool /30, no per-CB split)
 *    - ARABE/ANGLAIS : competence max_score = null (shared pool /30)
 *    - SCIENCES / EPS : competence max_score = null (shared pool /5)
 *    - TECHNOLOGIE : competence max_score = null (shared pool /20)
 *    - DICTÉE : code stays 'DICTEE', max_score = 10 (competence = null)
 *
 *  CE1
 *    - ANGLAIS becomes its own subject at /40 (not merged with OUTILS)
 *    - CB2 of ANGLAIS is the CONJUGAISON competence (per the catalogue)
 *    - No separate OUTILS DE LA LANGUE subject (merged into FRANÇAIS/ANGLAIS)
 *    - HISTOIRE and GÉOGRAPHIE merged → HISTOIRE-GÉOGRAPHIE /10
 *    - Competence max_score = null for subjects with shared pool (MATHS, ARABE,
 *      ANGLAIS, SCIENCES, EPS, HISTOIRE-GÉOGRAPHIE, TECHNOLOGIE)
 *    - FRANÇAIS competence max_score = null (shared /30)
 *
 *  CE2
 *    - FRANÇAIS : 3 CBs (LECTURE, CONJUGAISON, ORTHOGRAPHE), no OUTILS split
 *    - No OUTILS DE LA LANGUE subject
 *    - ARABE : 2 CBs coded CB2 (EXPRESSION ECRITE) + CB3 (EXPRESSION ORALE)
 *    - ANGLAIS : 3 CBs, competence max_score = null (shared /20)
 *    - No EPS subject
 *    - HISTOIRE-GÉOGRAPHIE merged /10
 *    - All competence max_score = null (shared pool per matière)
 *
 *  CM1 / CM2
 *    - FRANÇAIS : 3 CBs (VOCABULAIRE, CONJUGAISON, ORTHOGRAPHE) — no OUTILS split
 *    - No OUTILS DE LA LANGUE subject
 *    - ARABE only (no AR_EI combined subject) : CB1 LECTURE + CB2 EXPRESSION ECRITE
 *    - ANGLAIS : 3 CBs, competence max_score = null (shared /20)
 *    - HISTOIRE-GÉOGRAPHIE merged /10
 *    - All competence max_score = null (shared pool per matière)
 *    - CM2 MATHS CB1 extends to 999 999 999 999
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

    private function seedPrescolaire(): void
    {
        $niveau = Niveau::firstOrCreate(
            ['code' => AcademicLevelEnum::PRESCOLAIRE->value],
            ['label' => AcademicLevelEnum::PRESCOLAIRE->label()]
        );

        $C = ScaleTypeEnum::COMPETENCE->value;

        // ── 1 · LANGAGE ORAL ─────────────────────────────────────────────────
        $this->createSubject($niveau, [
            'name'       => 'LANGAGE ORAL',
            'code'       => 'LANG',
            'max_score'  => 0,
            'scale_type' => $C,
            'order'      => 1,
        ], [
            ['code' => 'CB1',  'description' => "Saluer et prendre congé.",                                                                                     'max_score' => null, 'order' => 1],
            ['code' => 'CB2',  'description' => "Identifier le personnel de la ferme / de l'école.",                                                            'max_score' => null, 'order' => 2],
            ['code' => 'CB3',  'description' => "Nommer et décrire les animaux familiers.",                                                                      'max_score' => null, 'order' => 3],
            ['code' => 'CB4',  'description' => "Reconnaître les bâtiments de la ferme.",                                                                        'max_score' => null, 'order' => 4],
            ['code' => 'CB5',  'description' => "Identifier et nommer les animaux sauvages.",                                                                    'max_score' => null, 'order' => 5],
            ['code' => 'CB6',  'description' => "Décrire et comparer quelques animaux sauvages.",                                                                'max_score' => null, 'order' => 6],
            ['code' => 'CB7',  'description' => "Situer dans l'espace (dessus, dessous, à côté...).",                                                            'max_score' => null, 'order' => 7],
            ['code' => 'CB8',  'description' => "Demander une information. Nommer des légumes et des fruits.",                                                   'max_score' => null, 'order' => 8],
            ['code' => 'CB9',  'description' => "Demander un service. Exprimer sa préférence. Donner des conseils. Exprimer l'admiration.",                      'max_score' => null, 'order' => 9],
            ['code' => 'CB10', 'description' => "Décrire un animal. Demander la permission. Demander des explications.",                                         'max_score' => null, 'order' => 10],
            ['code' => 'CB11', 'description' => "S'habiller. La propreté du corps. La santé. Le corps. Les fruits. Les légumes. De la graine à la plante.",      'max_score' => null, 'order' => 11],
        ]);

        // ── 2 · PRÉ-LECTURE ──────────────────────────────────────────────────
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
        $this->createSubject($niveau, [
            'name'       => 'GRAPHISME / ÉCRITURE',
            'code'       => 'GRAPH',
            'max_score'  => 0,
            'scale_type' => $C,
            'order'      => 3,
        ], [
            ['code' => 'CB1',  'description' => "Tracer des lignes horizontales.",                                                                                 'max_score' => null, 'order' => 1],
            ['code' => 'CB2',  'description' => "Tracer des lignes verticales.",                                                                                   'max_score' => null, 'order' => 2],
            ['code' => 'CB3',  'description' => "Tracer des lignes obliques.",                                                                                     'max_score' => null, 'order' => 3],
            ['code' => 'CB4',  'description' => "Tracer des boucles.",                                                                                             'max_score' => null, 'order' => 4],
            ['code' => 'CB5',  'description' => "Tracer des ponts.",                                                                                               'max_score' => null, 'order' => 5],
            ['code' => 'CB6',  'description' => "Calligraphier correctement la lettre (g, b, j, s / a, i, u, e, o, p, m, t, r, c, l).",                           'max_score' => null, 'order' => 6],
            ['code' => 'CB7',  'description' => "Affiner son geste graphique : tracer des demi-ronds (C), des ronds (O), une canne (J), les lettres E, F, H, U.", 'max_score' => null, 'order' => 7],
            ['code' => 'CB8',  'description' => "Tracer une ligne ondulée.",                                                                                       'max_score' => null, 'order' => 8],
            ['code' => 'CB9',  'description' => "Les lignes horizontales. Colorier. Chiffre 1 et lettres A, B, C, D. Les lignes verticales. Chiffre 2.",          'max_score' => null, 'order' => 9],
            ['code' => 'CB10', 'description' => "Repasser sur une ligne.",                                                                                         'max_score' => null, 'order' => 10],
        ]);

        // ── 4 · LOGICO-MATHS ─────────────────────────────────────────────────
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

        // ── 5 · VIVRE ENSEMBLE ───────────────────────────────────────────────
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
        $this->createSubject($niveau, [
            'name'       => 'ÉDUCATION ISLAMIQUE',
            'code'       => 'EI',
            'max_score'  => 0,
            'scale_type' => $C,
            'order'      => 6,
        ], [
            ['code' => 'CB1', 'description' => "Connaître et pratiquer les fondements de l'éducation islamique adaptés à l'âge préscolaire.", 'max_score' => null, 'order' => 1],
        ]);

        // ── 7 · ÉVEIL ARTISTIQUE ─────────────────────────────────────────────
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

        // ── 8 · EXPLORER LE MONDE ────────────────────────────────────────────
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

        // ── 9 · STRUCTURER SA PENSÉE (PS) ────────────────────────────────────
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

        // ── 10 · DÉCOUVRIR LE MONDE (MS/PS) ──────────────────────────────────
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

        // ── 11 · EXPLORER LE MONDE — Corps & Espace (PS) ─────────────────────
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
    // Source : CPA / CPB sheets (identical structure)
    // Total  : 110 pts  (FR/40 + MATHS/30 + AR_ANG/30 + SCI/5 + EPS/5 + TECH/20 + DICTÉE/10)
    //
    // NOTE MAX (compétence) rules from the catalogue:
    //   FRANÇAIS  : CB1=20, CB2=10, CB3=5, ECRITURE=5  (individual per-CB split)
    //   MATHS     : all CBs share the subject pool → competence max_score = null
    //   AR_ANG    : all CBs share the subject pool → competence max_score = null
    //   SCIENCES  : all CBs share the subject pool → competence max_score = null
    //   EPS       : all CBs share the subject pool → competence max_score = null
    //   TECHNOLOGIE : all CBs share the subject pool → competence max_score = null
    //   DICTÉE    : single competence, max_score = 10
    // ─────────────────────────────────────────────────────────────────────────
    private function seedCP(Niveau $niveau): void
    {
        $N = ScaleTypeEnum::NUMERIC->value;

        // I — FRANÇAIS : /40
        $this->createSubject($niveau, [
            'name'           => 'FRANÇAIS',
            'code'           => 'FR',
            'classroom_code' => 'CP',
            'max_score'      => 40,
            'scale_type'     => $N,
            'order'          => 1,
        ], [
            ['code' => 'CB1',      'description' => "Lecture : Appréhender le sens général d'un écrit et oraliser un court texte en maîtrisant le système de correspondance graphie/phonie.",  'max_score' => 20, 'order' => 1],
            ['code' => 'CB2',      'description' => "Production écrite : Créer un court message signifiant d'au moins trois phrases.",                                                           'max_score' => 10, 'order' => 2],
            ['code' => 'CB3',      'description' => "Langage : Produire un court énoncé en utilisant les formulations adaptées.",                                                                'max_score' => 5,  'order' => 3],
            ['code' => 'ECRITURE', 'description' => "Écriture : Écrire en cursive des lettres, syllabes, mots et phrases.",                                                                     'max_score' => 5,  'order' => 4],
        ]);

        // II — MATHÉMATIQUES : /30
        // Competence max_score = null (3 CBs share the /30 pool — no per-CB split in catalogue)
        $this->createSubject($niveau, [
            'name'           => 'MATHÉMATIQUES',
            'code'           => 'MATHS',
            'classroom_code' => 'CP',
            'max_score'      => 30,
            'scale_type'     => $N,
            'order'          => 2,
        ], [
            ['code' => 'CB1', 'description' => "Numération : Résoudre une situation problème faisant intervenir l'écriture des nombres de 0 à 100 et des opérations simples.",  'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Géométrie : Se situer ou situer des objets dans l'espace et s'orienter selon son itinéraire.",                                   'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Mesure : Trier, comparer et ranger des objets selon un critère donné (nature, longueur...).",                                    'max_score' => null, 'order' => 3],
        ]);

        // III — ARABE / ANGLAIS : /30
        // Competence max_score = null (CBs share the /30 pool — no per-CB split in catalogue)
        $this->createSubject($niveau, [
            'name'           => 'ARABE / ANGLAIS',
            'code'           => 'AR_ANG',
            'classroom_code' => 'CP',
            'max_score'      => 30,
            'scale_type'     => $N,
            'order'          => 3,
        ], [
            ['code' => 'CB1', 'description' => "Arabe : compréhension et expression orales/écrites de base.",                                                                                        'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Anglais : saluer, écrire les nombres de 1 à 12, apprendre les jours et mois, prononciation des lettres alphabétiques.",                              'max_score' => null, 'order' => 2],
        ]);

        // IV — SCIENCES : /5
        // Competence max_score = null (CBs share the /5 pool)
        $this->createSubject($niveau, [
            'name'           => 'SCIENCES',
            'code'           => 'SCI',
            'classroom_code' => 'CP',
            'max_score'      => 5,
            'scale_type'     => $N,
            'order'          => 4,
        ], [
            ['code' => 'CB1', 'description' => "Identifier et reconnaître les différentes parties de son corps.",  'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Identifier les animaux et les végétaux de son milieu.",            'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Nommer et sélectionner des objets d'une collection type.",         'max_score' => null, 'order' => 3],
        ]);

        // V — EPS : /5
        // Competence max_score = null (CBs share the /5 pool)
        $this->createSubject($niveau, [
            'name'           => 'ÉDUCATION PHYSIQUE ET SPORTIVE',
            'code'           => 'EPS',
            'classroom_code' => 'CP',
            'max_score'      => 5,
            'scale_type'     => $N,
            'order'          => 5,
        ], [
            ['code' => 'CB1', 'description' => "Produire des actions adaptées au milieu en appliquant les règles du jeu.",  'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Lancer et rattraper des objets variés.",                                    'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Reproduire des formes corporelles simples.",                                'max_score' => null, 'order' => 3],
        ]);

        // VI — TECHNOLOGIE : /20
        // Competence max_score = null (CBs share the /20 pool)
        $this->createSubject($niveau, [
            'name'           => 'TECHNOLOGIE',
            'code'           => 'TECH',
            'classroom_code' => 'CP',
            'max_score'      => 20,
            'scale_type'     => $N,
            'order'          => 6,
        ], [
            ['code' => 'CB1', 'description' => "Informatique : Allumer et éteindre l'outil informatique, s'approprier l'usage de la souris, taper sur le clavier.",          'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Robotique : Introduire les différents types de robots, discuter de l'utilité des écrans et de leur usage.",                   'max_score' => null, 'order' => 2],
        ]);

        // VII — DICTÉE : /10
        // Single competence with its own max_score = 10
        $this->createSubject($niveau, [
            'name'           => 'DICTÉE',
            'code'           => 'DICT',
            'classroom_code' => 'CP',
            'max_score'      => 10,
            'scale_type'     => $N,
            'order'          => 7,
        ], [
            ['code' => 'DICTEE', 'description' => "Maîtriser l'orthographe de la langue française. Respecter les règles d'orthographe grammaticale.",  'max_score' => 10, 'order' => 1],
        ]);
    }

    // ── CE1 ───────────────────────────────────────────────────────────────────
    // Source : CE1A / CE1B sheets (identical structure)
    // Total  : 180 pts  (FR/30 + MATHS/30 + AR/30 + ANG/40 + SCI/10 + EPS/10
    //                    + HG/10 + TECH/20)
    //
    // KEY CHANGES vs. previous version:
    //   • ANGLAIS is /40 (not /10), it absorbs what was formerly OUTILS
    //   • CB2 of ANGLAIS = the CONJUGAISON competence (per catalogue CE1A/B)
    //   • No separate OUTILS DE LA LANGUE subject
    //   • HISTOIRE + GÉOGRAPHIE merged → HISTOIRE-GÉOGRAPHIE /10
    //   • All competence max_score = null (shared pool per matière)
    // ─────────────────────────────────────────────────────────────────────────
    private function seedCE1(Niveau $niveau): void
    {
        $N = ScaleTypeEnum::NUMERIC->value;

        // I — FRANÇAIS : /30
        $this->createSubject($niveau, [
            'name'           => 'FRANÇAIS',
            'code'           => 'FR',
            'classroom_code' => 'CE1',
            'max_score'      => 30,
            'scale_type'     => $N,
            'order'          => 1,
        ], [
            ['code' => 'CB1', 'description' => "Lecture : Récolter des informations pertinentes dans un texte de 9 à 10 phrases en vue d'accéder à la compréhension.",  'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Production écrite : Produire un discours cohérent de 5 à 6 phrases dans un contexte de communication écrite.",           'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Production orale : Produire un discours cohérent dans un contexte de communication orale.",                               'max_score' => null, 'order' => 3],
        ]);

        // II — MATHÉMATIQUES : /30
        $this->createSubject($niveau, [
            'name'           => 'MATHÉMATIQUES',
            'code'           => 'MATHS',
            'classroom_code' => 'CE1',
            'max_score'      => 30,
            'scale_type'     => $N,
            'order'          => 2,
        ], [
            ['code' => 'CB1', 'description' => "Numération : Résoudre une situation faisant intervenir les 3 opérations (+, −, ×) avec des nombres entiers de 1 à 1 000.",             'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Géométrie : Résoudre une situation problème faisant appel à la représentation et la construction d'une figure.",                        'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Mesure : Résoudre une situation problème nécessitant le rangement et le classement d'événements dans le temps ou des objets.",         'max_score' => null, 'order' => 3],
        ]);

        // III — ARABE : /30
        $this->createSubject($niveau, [
            'name'           => 'ARABE',
            'code'           => 'AR',
            'classroom_code' => 'CE1',
            'max_score'      => 30,
            'scale_type'     => $N,
            'order'          => 3,
        ], [
            ['code' => 'CB1', 'description' => "Lecture.",           'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Expression orale.",  'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Expression écrite.", 'max_score' => null, 'order' => 3],
        ]);

        // IV — ANGLAIS : /40
        // CB2 is the Conjugaison competence as listed in the CE1A/B catalogue sheets.
        $this->createSubject($niveau, [
            'name'           => 'ANGLAIS',
            'code'           => 'ANG',
            'classroom_code' => 'CE1',
            'max_score'      => 40,
            'scale_type'     => $N,
            'order'          => 4,
        ], [
            ['code' => 'CB1', 'description' => "Saluer, écrire les nombres de 1 à 12. Apprendre les jours et les mois. Prononciation des lettres alphabétiques.",                                                      'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Conjugaison : Reconnaître le temps des verbes du 1er gr, 2ème gr et 3ème groupe au présent, passé composé, futur.",                                                    'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Reconnaître les différentes parties du corps. Apprendre les adjectifs qualificatifs. Révision. Prononciation des lettres alphabétiques.",                              'max_score' => null, 'order' => 3],
        ]);

        // V — SCIENCES : /10
        $this->createSubject($niveau, [
            'name'           => 'SCIENCES',
            'code'           => 'SCI',
            'classroom_code' => 'CE1',
            'max_score'      => 10,
            'scale_type'     => $N,
            'order'          => 5,
        ], [
            ['code' => 'CB1', 'description' => "Connaître la structure du corps humain (squelette et muscles) en vue d'améliorer son hygiène de vie.",  'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Identifier les besoins alimentaires des animaux et des plantes.",                                        'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Identifier l'état physique d'un élément du milieu (liquide ou solide).",                                'max_score' => null, 'order' => 3],
        ]);

        // VI — EPS : /10
        $this->createSubject($niveau, [
            'name'           => 'ÉDUCATION PHYSIQUE ET SPORTIVE',
            'code'           => 'EPS',
            'classroom_code' => 'CE1',
            'max_score'      => 10,
            'scale_type'     => $N,
            'order'          => 6,
        ], [
            ['code' => 'CB1', 'description' => "Adapter et varier ses courses et ses bonds sur une distance donnée.",              'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Lancer ou rattraper des objets de forme et dimension variées.",                    'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Coordonner ses capacités motrices pour contribuer au projet de son équipe.",       'max_score' => null, 'order' => 3],
            ['code' => 'CB4', 'description' => "Produire et adapter ses mouvements ou ses déplacements en tempo collectif.",       'max_score' => null, 'order' => 4],
        ]);

        // VII — HISTOIRE-GÉOGRAPHIE : /10
        // Merged subject (catalogue lists it as a single HISTOIRE-GEOGRAPHIE row)
        $this->createSubject($niveau, [
            'name'           => 'HISTOIRE-GÉOGRAPHIE',
            'code'           => 'HG',
            'classroom_code' => 'CE1',
            'max_score'      => 10,
            'scale_type'     => $N,
            'order'          => 7,
        ], [
            ['code' => 'CB1', 'description' => "Utiliser des outils de géographie (croquis, plan) pour représenter un élément de son environnement immédiat.", 'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Utiliser les notions générales de géographie (relief et climat).",                                              'max_score' => null, 'order' => 2],
        ]);

        // VIII — TECHNOLOGIE : /20
        $this->createSubject($niveau, [
            'name'           => 'TECHNOLOGIE',
            'code'           => 'TECH',
            'classroom_code' => 'CE1',
            'max_score'      => 20,
            'scale_type'     => $N,
            'order'          => 8,
        ], [
            ['code' => 'CB1', 'description' => "Informatique : Allumer et éteindre un outil informatique, lancer et quitter un logiciel, s'approprier l'usage de la souris, taper sur le clavier, accéder à un dossier.", 'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Robotique : Découvrir les différents composants du robot, aborder le fonctionnement du robot, appréhender le système binaire et de code.",                                  'max_score' => null, 'order' => 2],
        ]);
    }

    // ── CE2 ───────────────────────────────────────────────────────────────────
    // Source : CE2A / CE2B sheets (identical structure)
    // Total  : 150 pts  (FR/40 + MATHS/30 + AR/20 + ANG/20 + SCI/10
    //                    + HG/10 + TECH/20)
    //
    // KEY CHANGES vs. previous version:
    //   • FRANÇAIS has 3 CBs: LECTURE, CONJUGAISON, ORTHOGRAPHE (no OUTILS split)
    //   • No OUTILS DE LA LANGUE subject
    //   • ARABE : 2 CBs coded CB2 (EXPRESSION ECRITE) + CB3 (EXPRESSION ORALE)
    //   • No EPS subject
    //   • HISTOIRE + GÉOGRAPHIE merged → HISTOIRE-GÉOGRAPHIE /10
    //   • All competence max_score = null (shared pool per matière)
    // ─────────────────────────────────────────────────────────────────────────
    private function seedCE2(Niveau $niveau): void
    {
        $N = ScaleTypeEnum::NUMERIC->value;

        // I — FRANÇAIS : /40
        $this->createSubject($niveau, [
            'name'           => 'FRANÇAIS',
            'code'           => 'FR',
            'classroom_code' => 'CE2',
            'max_score'      => 40,
            'scale_type'     => $N,
            'order'          => 1,
        ], [
            ['code' => 'CB1', 'description' => "Lecture : Récolter des informations pertinentes dans un texte de 10 à 15 phrases. Lecture oralisée.",                           'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Conjugaison : Reconnaître le temps des verbes du 1er gr, 2ème gr et 3ème gr au passé composé, présent, futur et imparfait.",    'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Orthographe : Reconnaître les différentes graphies, le genre et nombre des adjectifs et les mots invariables.",                  'max_score' => null, 'order' => 3],
        ]);

        // II — MATHÉMATIQUES : /30
        $this->createSubject($niveau, [
            'name'           => 'MATHÉMATIQUES',
            'code'           => 'MATHS',
            'classroom_code' => 'CE2',
            'max_score'      => 30,
            'scale_type'     => $N,
            'order'          => 2,
        ], [
            ['code' => 'CB1', 'description' => "Numération : Résoudre des situations significatives mobilisant les 3 opérations (+, −, ×) et la division sur des nombres entiers à 999 999.",  'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Géométrie : Décrire ou reproduire des figures simples avec les instruments adéquats (règle, équerre et compas).",                              'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Mesure : Évaluer, comparer des mesures (longueurs, masses et durées) et élaborer des chronologies d'événements dans le temps.",               'max_score' => null, 'order' => 3],
        ]);

        // III — ARABE : /20
        // CB codes follow the catalogue exactly: CB2 = EXPRESSION ECRITE, CB3 = EXPRESSION ORALE
        $this->createSubject($niveau, [
            'name'           => 'ARABE',
            'code'           => 'AR',
            'classroom_code' => 'CE2',
            'max_score'      => 20,
            'scale_type'     => $N,
            'order'          => 3,
        ], [
            ['code' => 'CB2', 'description' => "Expression écrite.",  'max_score' => null, 'order' => 1],
            ['code' => 'CB3', 'description' => "Expression orale.",   'max_score' => null, 'order' => 2],
        ]);

        // IV — ANGLAIS : /20
        $this->createSubject($niveau, [
            'name'           => 'ANGLAIS',
            'code'           => 'ANG',
            'classroom_code' => 'CE2',
            'max_score'      => 20,
            'scale_type'     => $N,
            'order'          => 4,
        ], [
            ['code' => 'CB1', 'description' => "Saluer, écrire les nombres de 1 à 12. Apprendre les jours et les mois. Prononciation des lettres alphabétiques.",                              'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Reconnaître les fournitures scolaires, les mobiliers scolaires. Identifier les membres de sa famille. Prononciation des lettres alphabétiques.", 'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Reconnaître les différentes parties du corps. Apprendre les adjectifs qualificatifs. Révision. Prononciation des lettres alphabétiques.",       'max_score' => null, 'order' => 3],
        ]);

        // V — SCIENCES : /10
        $this->createSubject($niveau, [
            'name'           => 'SCIENCES',
            'code'           => 'SCI',
            'classroom_code' => 'CE2',
            'max_score'      => 10,
            'scale_type'     => $N,
            'order'          => 5,
        ], [
            ['code' => 'CB1', 'description' => "Identifier les besoins alimentaires nécessaires à son organisme en vue d'améliorer son hygiène de vie.",  'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Déterminer les conditions nécessaires à la croissance des animaux et des plantes.",                        'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Dégager les applications pratiques de quelques phénomènes physiques usuels (électricité, air, son).",     'max_score' => null, 'order' => 3],
        ]);

        // VI — HISTOIRE-GÉOGRAPHIE : /10
        $this->createSubject($niveau, [
            'name'           => 'HISTOIRE-GÉOGRAPHIE',
            'code'           => 'HG',
            'classroom_code' => 'CE2',
            'max_score'      => 10,
            'scale_type'     => $N,
            'order'          => 6,
        ], [
            ['code' => 'CB1', 'description' => "Face à une situation-problème, émettre des propositions sur la base de schémas, de cartes et des croquis.",                     'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "À l'aide de tableaux relatifs aux données climatiques, résoudre une situation-problème.",                                        'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Face à une situation-problème, identifier des actions néfastes et proposer des actions de protection de l'environnement.",      'max_score' => null, 'order' => 3],
        ]);

        // VII — TECHNOLOGIE : /20
        $this->createSubject($niveau, [
            'name'           => 'TECHNOLOGIE',
            'code'           => 'TECH',
            'classroom_code' => 'CE2',
            'max_score'      => 20,
            'scale_type'     => $N,
            'order'          => 7,
        ], [
            ['code' => 'CB1', 'description' => "Informatique : Allumer et éteindre un outil informatique, lancer et quitter un logiciel, s'approprier l'usage de la souris, taper sur le clavier, accéder à un dossier.",          'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Robotique : Découvrir les différents composants du robot, aborder le fonctionnement du robot, appréhender le système binaire et de code, imaginer un dessin binaire.",             'max_score' => null, 'order' => 2],
        ]);
    }

    // ── CM1 ───────────────────────────────────────────────────────────────────
    // Source : CM1 sheet
    // Total  : 150 pts  (FR/40 + MATHS/30 + AR/20 + ANG/20 + SCI/10
    //                    + HG/10 + TECH/20)
    //
    // KEY CHANGES vs. previous version:
    //   • FRANÇAIS has 3 CBs: VOCABULAIRE, CONJUGAISON, ORTHOGRAPHE
    //   • No OUTILS DE LA LANGUE subject
    //   • ARABE only (code AR), CB1=LECTURE, CB2=EXPRESSION ECRITE
    //   • HISTOIRE-GÉOGRAPHIE merged /10
    //   • All competence max_score = null (shared pool per matière)
    // ─────────────────────────────────────────────────────────────────────────
    private function seedCM1(Niveau $niveau): void
    {
        $N = ScaleTypeEnum::NUMERIC->value;

        // I — FRANÇAIS : /40
        $this->createSubject($niveau, [
            'name'           => 'FRANÇAIS',
            'code'           => 'FR',
            'classroom_code' => 'CM1',
            'max_score'      => 40,
            'scale_type'     => $N,
            'order'          => 1,
        ], [
            ['code' => 'CB1', 'description' => "Vocabulaire : Décrire une personne, localiser un lieu, donner des informations, raconter un événement, les contraires, synonymes, homonymes, champ lexical.", 'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Conjugaison : Reconnaître le temps des verbes du 1er gr, 2ème gr et 3ème gr au passé composé, présent, futur, imparfait et passé simple.",                     'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Orthographe : Reconnaître les différentes graphies, le genre et nombre des adjectifs, les mots invariables et les homophones.",                                'max_score' => null, 'order' => 3],
        ]);

        // II — MATHÉMATIQUES : /30
        $this->createSubject($niveau, [
            'name'           => 'MATHÉMATIQUES',
            'code'           => 'MATHS',
            'classroom_code' => 'CM1',
            'max_score'      => 30,
            'scale_type'     => $N,
            'order'          => 2,
        ], [
            ['code' => 'CB1', 'description' => "Numération : Résoudre des situations significatives mobilisant les 4 opérations (+, −, ×, ÷) sur des nombres entiers à 999 999 999.",                                              'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Géométrie : Résoudre des situations problèmes nécessitant la description et la construction des figures planes et des solides.",                                                    'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Mesure : Résoudre des situations problèmes faisant intervenir des unités de mesure de longueur et d'aires, ainsi que de masses, des angles et des durées.",                       'max_score' => null, 'order' => 3],
        ]);

        // III — ARABE : /20
        $this->createSubject($niveau, [
            'name'           => 'ARABE',
            'code'           => 'AR',
            'classroom_code' => 'CM1',
            'max_score'      => 20,
            'scale_type'     => $N,
            'order'          => 3,
        ], [
            ['code' => 'CB1', 'description' => "Lecture.",           'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Expression écrite.", 'max_score' => null, 'order' => 2],
        ]);

        // IV — ANGLAIS : /20
        $this->createSubject($niveau, [
            'name'           => 'ANGLAIS',
            'code'           => 'ANG',
            'classroom_code' => 'CM1',
            'max_score'      => 20,
            'scale_type'     => $N,
            'order'          => 4,
        ], [
            ['code' => 'CB1', 'description' => "Saluer, écrire les nombres de 1 à 20. Apprendre les jours et les mois. Prononciation des lettres alphabétiques.",                          'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Reconnaître les fournitures scolaires, les mobiliers scolaires. Identifier les membres de sa famille. Prononciation des lettres.",          'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Reconnaître les différentes parties du corps. Apprendre les adjectifs qualificatifs. Révision. Prononciation des lettres alphabétiques.",  'max_score' => null, 'order' => 3],
        ]);

        // V — SCIENCES : /10
        $this->createSubject($niveau, [
            'name'           => 'SCIENCES',
            'code'           => 'SCI',
            'classroom_code' => 'CM1',
            'max_score'      => 10,
            'scale_type'     => $N,
            'order'          => 5,
        ], [
            ['code' => 'CB1', 'description' => "Identifier les besoins alimentaires nécessaires à son organisme en vue d'améliorer son hygiène de vie.",  'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Déterminer les conditions nécessaires à la croissance des animaux et des plantes.",                        'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Dégager les applications pratiques de quelques phénomènes physiques usuels (électricité, air, son).",     'max_score' => null, 'order' => 3],
        ]);

        // VI — HISTOIRE-GÉOGRAPHIE : /10
        $this->createSubject($niveau, [
            'name'           => 'HISTOIRE-GÉOGRAPHIE',
            'code'           => 'HG',
            'classroom_code' => 'CM1',
            'max_score'      => 10,
            'scale_type'     => $N,
            'order'          => 6,
        ], [
            ['code' => 'CB1', 'description' => "Face à une situation-problème, émettre des propositions sur la base de schémas, de cartes et des croquis.",                     'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "À l'aide de tableaux relatifs aux données climatiques, résoudre une situation-problème.",                                        'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Face à une situation-problème, identifier des actions néfastes et proposer des actions de protection de l'environnement.",      'max_score' => null, 'order' => 3],
        ]);

        // VII — TECHNOLOGIE : /20
        $this->createSubject($niveau, [
            'name'           => 'TECHNOLOGIE',
            'code'           => 'TECH',
            'classroom_code' => 'CM1',
            'max_score'      => 20,
            'scale_type'     => $N,
            'order'          => 7,
        ], [
            ['code' => 'CB1', 'description' => "Informatique : Allumer et éteindre un outil informatique, lancer et quitter un logiciel, s'approprier l'usage de la souris, taper sur le clavier, accéder à un dossier.", 'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Robotique : Découvrir les différents composants du robot, aborder le fonctionnement du robot, appréhender le système binaire et de code, imaginer un dessin binaire.",    'max_score' => null, 'order' => 2],
        ]);
    }

    // ── CM2 ───────────────────────────────────────────────────────────────────
    // Source : CM2 sheet
    // Total  : 150 pts  (same structure as CM1)
    // Unique difference : MATHS CB1 numbers extend to 999 999 999 999 (12 digits)
    // ─────────────────────────────────────────────────────────────────────────
    private function seedCM2(Niveau $niveau): void
    {
        $N = ScaleTypeEnum::NUMERIC->value;

        // I — FRANÇAIS : /40
        $this->createSubject($niveau, [
            'name'           => 'FRANÇAIS',
            'code'           => 'FR',
            'classroom_code' => 'CM2',
            'max_score'      => 40,
            'scale_type'     => $N,
            'order'          => 1,
        ], [
            ['code' => 'CB1', 'description' => "Vocabulaire : Décrire une personne, localiser un lieu, donner des informations, raconter un événement, les contraires, synonymes, homonymes, champ lexical.", 'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Conjugaison : Reconnaître le temps des verbes du 1er gr, 2ème gr et 3ème gr au passé composé, présent, futur, imparfait et passé simple.",                     'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Orthographe : Reconnaître les différentes graphies, le genre et nombre des adjectifs, les mots invariables et les homophones.",                                'max_score' => null, 'order' => 3],
        ]);

        // II — MATHÉMATIQUES : /30
        $this->createSubject($niveau, [
            'name'           => 'MATHÉMATIQUES',
            'code'           => 'MATHS',
            'classroom_code' => 'CM2',
            'max_score'      => 30,
            'scale_type'     => $N,
            'order'          => 2,
        ], [
            // CM2 only: numbers extend to 999 999 999 999 (12 digits, vs CM1's 9 digits)
            ['code' => 'CB1', 'description' => "Numération : Résoudre des situations significatives mobilisant les 4 opérations (+, −, ×, ÷) sur des nombres entiers à 999 999 999 999.", 'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Géométrie : Résoudre des situations problèmes nécessitant la description et la construction des figures planes et des solides.",          'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Mesure : Résoudre des situations problèmes faisant intervenir des unités de mesure de longueur et d'aires, ainsi que de masses, des angles et des durées.", 'max_score' => null, 'order' => 3],
        ]);

        // III — ARABE : /20
        $this->createSubject($niveau, [
            'name'           => 'ARABE',
            'code'           => 'AR',
            'classroom_code' => 'CM2',
            'max_score'      => 20,
            'scale_type'     => $N,
            'order'          => 3,
        ], [
            ['code' => 'CB1', 'description' => "Lecture.",           'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Expression écrite.", 'max_score' => null, 'order' => 2],
        ]);

        // IV — ANGLAIS : /20
        $this->createSubject($niveau, [
            'name'           => 'ANGLAIS',
            'code'           => 'ANG',
            'classroom_code' => 'CM2',
            'max_score'      => 20,
            'scale_type'     => $N,
            'order'          => 4,
        ], [
            ['code' => 'CB1', 'description' => "Saluer, écrire les nombres de 1 à 20. Apprendre les jours et les mois. Prononciation des lettres alphabétiques.",                          'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Reconnaître les fournitures scolaires, les mobiliers scolaires. Identifier les membres de sa famille. Prononciation des lettres.",          'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Reconnaître les différentes parties du corps. Apprendre les adjectifs qualificatifs. Révision. Prononciation des lettres alphabétiques.",  'max_score' => null, 'order' => 3],
        ]);

        // V — SCIENCES : /10
        $this->createSubject($niveau, [
            'name'           => 'SCIENCES',
            'code'           => 'SCI',
            'classroom_code' => 'CM2',
            'max_score'      => 10,
            'scale_type'     => $N,
            'order'          => 5,
        ], [
            ['code' => 'CB1', 'description' => "Identifier les besoins alimentaires nécessaires à son organisme en vue d'améliorer son hygiène de vie.",  'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Déterminer les conditions nécessaires à la croissance des animaux et des plantes.",                        'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Dégager les applications pratiques de quelques phénomènes physiques usuels (électricité, air, son).",     'max_score' => null, 'order' => 3],
        ]);

        // VI — HISTOIRE-GÉOGRAPHIE : /10
        $this->createSubject($niveau, [
            'name'           => 'HISTOIRE-GÉOGRAPHIE',
            'code'           => 'HG',
            'classroom_code' => 'CM2',
            'max_score'      => 10,
            'scale_type'     => $N,
            'order'          => 6,
        ], [
            ['code' => 'CB1', 'description' => "Face à une situation-problème, émettre des propositions sur la base de schémas, de cartes et des croquis.",                     'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "À l'aide de tableaux relatifs aux données climatiques, résoudre une situation-problème.",                                        'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Face à une situation-problème, identifier des actions néfastes et proposer des actions de protection de l'environnement.",      'max_score' => null, 'order' => 3],
        ]);

        // VII — TECHNOLOGIE : /20
        $this->createSubject($niveau, [
            'name'           => 'TECHNOLOGIE',
            'code'           => 'TECH',
            'classroom_code' => 'CM2',
            'max_score'      => 20,
            'scale_type'     => $N,
            'order'          => 7,
        ], [
            ['code' => 'CB1', 'description' => "Informatique : Allumer et éteindre un outil informatique, lancer et quitter un logiciel, s'approprier l'usage de la souris, taper sur le clavier, accéder à un dossier.", 'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Robotique : Découvrir les différents composants du robot, aborder le fonctionnement du robot, appréhender le système binaire et de code, imaginer un dessin binaire.",    'max_score' => null, 'order' => 2],
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
            $this->createSubject($niveau, ['name' => 'FRANÇAIS',           'code' => 'FR',   'max_score' => 20, 'scale_type' => ScaleTypeEnum::NUMERIC->value, 'order' => 1], [
                ['code' => 'CB1', 'description' => "Lecture et compréhension de texte.",          'max_score' => 10, 'order' => 1],
                ['code' => 'CB2', 'description' => "Expression écrite et production textuelle.",  'max_score' => 10, 'order' => 2],
            ]);
            $this->createSubject($niveau, ['name' => 'MATHÉMATIQUES',      'code' => 'MATHS','max_score' => 20, 'scale_type' => ScaleTypeEnum::NUMERIC->value, 'order' => 2], [
                ['code' => 'CB1', 'description' => "Algèbre et calcul numérique.",                'max_score' => 10, 'order' => 1],
                ['code' => 'CB2', 'description' => "Géométrie et résolution de problèmes.",       'max_score' => 10, 'order' => 2],
            ]);
            $this->createSubject($niveau, ['name' => 'ANGLAIS',            'code' => 'ANG',  'max_score' => 20, 'scale_type' => ScaleTypeEnum::NUMERIC->value, 'order' => 3], [
                ['code' => 'CB1', 'description' => "Compréhension écrite et orale.",              'max_score' => 10, 'order' => 1],
                ['code' => 'CB2', 'description' => "Expression écrite et production.",            'max_score' => 10, 'order' => 2],
            ]);
            $this->createSubject($niveau, ['name' => 'SVT / SCIENCES',     'code' => 'SVT',  'max_score' => 20, 'scale_type' => ScaleTypeEnum::NUMERIC->value, 'order' => 4], [
                ['code' => 'CB1', 'description' => "Connaissance des faits scientifiques.",       'max_score' => 10, 'order' => 1],
                ['code' => 'CB2', 'description' => "Démarche scientifique et expérimentation.",   'max_score' => 10, 'order' => 2],
            ]);
            $this->createSubject($niveau, ['name' => 'HISTOIRE-GÉOGRAPHIE','code' => 'HG',   'max_score' => 20, 'scale_type' => ScaleTypeEnum::NUMERIC->value, 'order' => 5], [
                ['code' => 'CB1', 'description' => "Maîtrise des faits historiques et géographiques.",  'max_score' => 10, 'order' => 1],
                ['code' => 'CB2', 'description' => "Analyse de documents et expression écrite.",         'max_score' => 10, 'order' => 2],
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
