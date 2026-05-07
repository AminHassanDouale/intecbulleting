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
 *
 * ─── AUTHORITATIVE SOURCE ───────────────────────────────────────────────────
 *   Catalogue_Competences_2025-2026_v4.xlsx
 *
 * ─── EXCEL-ACCURATE NOTES (per sheet, verbatim) ─────────────────────────────
 *
 *  CPA  — FRANÇAIS: individual max_scores (CB1=20, CB2=10, CB3=5, ECRITURE=5)
 *          MATHS/SCIENCES/EPS: all CBs share pool (max_score = null on competence)
 *          ARABE/ANGLAIS: all CBs share pool (null)
 *          TECHNOLOGIE: all CBs share pool (null)
 *          DICTÉE: competence max_score = 10
 *
 *  CPB  — FRANÇAIS: all CBs max_score = null (Excel shows 40 for all → shared pool)
 *          All other subjects: identical to CPA
 *
 *  CE1A — FRANÇAIS: CB1=10, CB2=10, CB3=10 (individual)
 *          MATHÉMATIQUES: CB1=10, CB2=10, CB3=10 (individual)
 *          ARABE /30: CB1=Lecture/10, CB2=Expression orale/10, CB3=Expression écrite/10
 *          ANGLAIS /40: CB1=10, CB2=10, CB3=10 (individual, sum=30; remaining 10 oral)
 *          SCIENCES: all CBs max_score=10 (shared pool)
 *          EPS: all CBs max_score=10 (shared pool)
 *          GEOGRAPHIE /10: CB1 only (no CB2 in CE1A — row 22 has no code)
 *          TECHNOLOGIE /20: CB1=10, CB2=10 (individual)
 *          OUTILS /40: CB1=10, CB2=10, CB3=10, CB4=10 (individual) ← NEW subject
 *          HISTOIRE /10: CB1=10 ← NEW subject
 *
 *  CE1B — Same as CE1A except:
 *          HISTOIRE-GEOGRAPHIE /10: CB1=10, CB2=10 (both rows have codes)
 *          No HISTOIRE separate subject
 *          OUTILS /40: CB1–CB4 (individual) ← NEW subject
 *
 *  CE2A/CE2B — FRANÇAIS: CB1=20, CB2=20, CB3=20 (individual per Excel)
 *              MATHÉMATIQUES: all CBs max_score=30 (shared pool)
 *              ARABE /20: CB2=EXPRESSION ECRITE/20, CB3=EXPRESSION ORALE/20 (shared pool, no CB1)
 *              ANGLAIS /20: all CBs max_score=20 (shared pool)
 *              SCIENCES /10: all CBs shared pool
 *              GEOGRAPHIE /10: CB1=10, CB2=10, CB3=10 (shared pool)
 *              TECHNOLOGIE /20: all CBs shared pool
 *              OUTILS /40: CB1=10, CB2=10, CB3=10, CB4=10 ← NEW subject
 *              HISTOIRE /10: CB1=10 ← NEW subject
 *
 *  CM1  — FRANÇAIS /40: all CBs max_score=40 (shared pool)
 *          MATHÉMATIQUES /30: CB1=10, CB2=10, CB3=10 (individual)
 *          ARABE /20: CB1=LECTURE/10, CB2=EXPRESSION ECRITE/10 (individual)
 *          ANGLAIS /20: CB1=10, CB2=10, CB3=10 (individual)
 *          SCIENCES /10: all CBs max_score=10 (shared pool)
 *          GEOGRAPHIE /10: CB1=10, CB2=10, CB3=10 (shared pool)
 *          TECHNOLOGIE /20: CB1=10, CB2=10 (individual)
 *          OUTILS /40: CB1=10, CB2=10, CB3=10, CB4=10 ← NEW subject
 *          HISTOIRE /10: CB1=10 ← NEW subject
 *          Note: Excel has GEOGRAPHIE duplicated (rows 16-18 and 21-23); seeded once.
 *
 *  CM2  — FRANÇAIS /40: all CBs max_score=40 (shared pool)
 *          MATHÉMATIQUES /30: all CBs max_score=30 (shared pool)
 *          ARABE /20: CB1=LECTURE/20, CB2=EXPRESSION ECRITE/20 (shared pool)
 *          ANGLAIS /20: all CBs max_score=20 (shared pool)
 *          SCIENCES /10: all CBs shared pool
 *          HISTOIRE-GEOGRAPHIE /10: CB1=10, CB2=10, CB3=10 (shared pool)
 *          TECHNOLOGIE /20: all CBs shared pool
 *          OUTILS /40: CB1=10, CB2=10, CB3=10, CB4=10 ← NEW subject
 *          HISTOIRE /10: CB1=10 ← NEW subject
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

        $this->createSubject($niveau, [
            'name' => 'LANGAGE ORAL', 'code' => 'LANG',
            'max_score' => 0, 'scale_type' => $C, 'order' => 1,
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

        $this->createSubject($niveau, [
            'name' => 'PRÉ-LECTURE', 'code' => 'PRELEC',
            'max_score' => 0, 'scale_type' => $C, 'order' => 2,
        ], [
            ['code' => 'CB1', 'description' => "Lire des images et des mots.",                                                              'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Reconnaître un mot à partir d'un référent.",                                               'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Associer des mots à des images.",                                                          'max_score' => null, 'order' => 3],
            ['code' => 'CB4', 'description' => "Découvrir et repérer le phonème à l'étude dans des mots.",                                 'max_score' => null, 'order' => 4],
            ['code' => 'CB5', 'description' => "Lire avec fluidité des sons, syllabes et les phrases étudiés.",                            'max_score' => null, 'order' => 5],
            ['code' => 'CB6', 'description' => "Identifier des images. Affiner sa perception visuelle, comparer des images et des mots.", 'max_score' => null, 'order' => 6],
            ['code' => 'CB7', 'description' => "Lire globalement quelques mots et des prénoms.",                                           'max_score' => null, 'order' => 7],
        ]);

        $this->createSubject($niveau, [
            'name' => 'GRAPHISME / ÉCRITURE', 'code' => 'GRAPH',
            'max_score' => 0, 'scale_type' => $C, 'order' => 3,
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

        $this->createSubject($niveau, [
            'name' => 'LOGICO-MATHS', 'code' => 'MATHS',
            'max_score' => 0, 'scale_type' => $C, 'order' => 4,
        ], [
            ['code' => 'CB1',  'description' => "Les nombres de 1 à 7.",                                                                     'max_score' => null, 'order' => 1],
            ['code' => 'CB2',  'description' => "Classement des aliments.",                                                                  'max_score' => null, 'order' => 2],
            ['code' => 'CB3',  'description' => "Comparer les quantités.",                                                                   'max_score' => null, 'order' => 3],
            ['code' => 'CB4',  'description' => "Situations additives.",                                                                     'max_score' => null, 'order' => 4],
            ['code' => 'CB5',  'description' => "Les animaux de la ferme.",                                                                  'max_score' => null, 'order' => 5],
            ['code' => 'CB6',  'description' => "Les petits des animaux.",                                                                   'max_score' => null, 'order' => 6],
            ['code' => 'CB7',  'description' => "Le goût.",                                                                                  'max_score' => null, 'order' => 7],
            ['code' => 'CB8',  'description' => "Près de, loin de...",                                                                       'max_score' => null, 'order' => 8],
            ['code' => 'CB9',  'description' => "Comparer deux collections sans comptage : « plus que » / « moins que ».",                  'max_score' => null, 'order' => 9],
            ['code' => 'CB10', 'description' => "Comparer des collections selon le critère « autant que ».",                               'max_score' => null, 'order' => 10],
            ['code' => 'CB11', 'description' => "Découvrir et dénombrer les nombres de 1 à 5.",                                             'max_score' => null, 'order' => 11],
            ['code' => 'CB12', 'description' => "Continuer une suite logique complexe.",                                                    'max_score' => null, 'order' => 12],
            ['code' => 'CB13', 'description' => "Respecter un codage.",                                                                     'max_score' => null, 'order' => 13],
        ]);

        $this->createSubject($niveau, [
            'name' => 'VIVRE ENSEMBLE', 'code' => 'VIE',
            'max_score' => 0, 'scale_type' => $C, 'order' => 5,
        ], [
            ['code' => 'CB1', 'description' => "Se sensibiliser au respect de la nature.",           'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Se sensibiliser au respect du règlement.",           'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Apprendre à vivre avec ses camarades à l'école.",   'max_score' => null, 'order' => 3],
        ]);

        $this->createSubject($niveau, [
            'name' => 'ÉDUCATION ISLAMIQUE', 'code' => 'EI',
            'max_score' => 0, 'scale_type' => $C, 'order' => 6,
        ], [
            ['code' => 'CB1', 'description' => "Connaître et pratiquer les fondements de l'éducation islamique adaptés à l'âge préscolaire.", 'max_score' => null, 'order' => 1],
        ]);

        $this->createSubject($niveau, [
            'name' => 'ÉVEIL ARTISTIQUE', 'code' => 'ART',
            'max_score' => 0, 'scale_type' => $C, 'order' => 7,
        ], [
            ['code' => 'CB1', 'description' => "Discriminer et produire des sons d'intensité variables : fort, faible.", 'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Dessiner selon un modèle.",                                              'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Gribouiller librement.",                                                 'max_score' => null, 'order' => 3],
        ]);

        $this->createSubject($niveau, [
            'name' => 'EXPLORER LE MONDE', 'code' => 'MONDE',
            'max_score' => 0, 'scale_type' => $C, 'order' => 8,
        ], [
            ['code' => 'CB1', 'description' => "Se repérer dans l'espace : s'orienter sur un plan, suivre un chemin sur un labyrinthe.",                     'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Distinguer le jour de la nuit.",                                                                              'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Connaître la fonction de chacun des cinq sens.",                                                              'max_score' => null, 'order' => 3],
            ['code' => 'CB4', 'description' => "Se situer dans l'espace : utiliser correctement devant / derrière pour localiser un objet ou une personne.", 'max_score' => null, 'order' => 4],
            ['code' => 'CB5', 'description' => "Nommer et situer les différentes parties de son corps.",                                                      'max_score' => null, 'order' => 5],
            ['code' => 'CB6', 'description' => "Se repérer dans le temps : matin, midi, soir.",                                                               'max_score' => null, 'order' => 6],
            ['code' => 'CB7', 'description' => "Reconnaître les étapes de la croissance d'un animal domestique.",                                             'max_score' => null, 'order' => 7],
            ['code' => 'CB8', 'description' => "Associer les animaux domestiques à leurs petits.",                                                            'max_score' => null, 'order' => 8],
        ]);

        $this->createSubject($niveau, [
            'name' => 'STRUCTURER SA PENSÉE', 'code' => 'PENSEE',
            'max_score' => 0, 'scale_type' => $C, 'order' => 9,
        ], [
            ['code' => 'CB1', 'description' => "Petit, moyen, grand.",           'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Le carré.",                      'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Le nombre 1 et 2.",              'max_score' => null, 'order' => 3],
            ['code' => 'CB4', 'description' => "Plus de, moins de.",             'max_score' => null, 'order' => 4],
            ['code' => 'CB5', 'description' => "Le triangle.",                   'max_score' => null, 'order' => 5],
            ['code' => 'CB6', 'description' => "Autant de.",                     'max_score' => null, 'order' => 6],
            ['code' => 'CB7', 'description' => "Les nombres jusqu'à 3.",         'max_score' => null, 'order' => 7],
        ]);

        $this->createSubject($niveau, [
            'name' => 'DÉCOUVRIR LE MONDE', 'code' => 'DEC',
            'max_score' => 0, 'scale_type' => $C, 'order' => 10,
        ], [
            ['code' => 'CB1', 'description' => "Comparer deux collections sans comptage. Critères « plus que » ou « moins que ».",                         'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Comparer des collections. Réaliser une collection selon le critère « autant de... que de... ».",           'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Découvrir le nombre 3. Savoir dénombrer des collections de 1 à 3 éléments.",                              'max_score' => null, 'order' => 3],
            ['code' => 'CB4', 'description' => "Respecter un codage.",                                                                                     'max_score' => null, 'order' => 4],
            ['code' => 'CB5', 'description' => "Découvrir le nombre 4. Savoir dénombrer et constituer une collection de 4 éléments.",                     'max_score' => null, 'order' => 5],
            ['code' => 'CB6', 'description' => "Découvrir le nombre 5. Savoir dénombrer et constituer une collection de 5 éléments.",                     'max_score' => null, 'order' => 6],
            ['code' => 'CB7', 'description' => "Continuer une suite logique complexe.",                                                                    'max_score' => null, 'order' => 7],
            ['code' => 'CB8', 'description' => "Réaliser des collections de 2 à 5 éléments.",                                                             'max_score' => null, 'order' => 8],
        ]);

        $this->createSubject($niveau, [
            'name' => 'EXPLORER LE MONDE (Corps & Espace)', 'code' => 'MONDE_PS',
            'max_score' => 0, 'scale_type' => $C, 'order' => 11,
        ], [
            ['code' => 'CB1', 'description' => "Se repérer dans la journée.",                                                                         'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "À côté, entre.",                                                                                      'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Les parties du corps et du visage.",                                                                  'max_score' => null, 'order' => 3],
            ['code' => 'CB4', 'description' => "Voir, entendre, sentir.",                                                                             'max_score' => null, 'order' => 4],
            ['code' => 'CB5', 'description' => "Avant, après. En haut, en bas.",                                                                      'max_score' => null, 'order' => 5],
            ['code' => 'CB6', 'description' => "L'ombre et la lumière.",                                                                              'max_score' => null, 'order' => 6],
            ['code' => 'CB7', 'description' => "Le goût.",                                                                                            'max_score' => null, 'order' => 7],
            ['code' => 'CB8', 'description' => "S'habiller. La propreté du corps. La santé. Les fruits. Les légumes. De la graine à la plante.",      'max_score' => null, 'order' => 8],
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

        $this->seedCPA($niveau);
        $this->seedCPB($niveau);
        $this->seedCE1A($niveau);
        $this->seedCE1B($niveau);
        $this->seedCE2($niveau);
        $this->seedCM1($niveau);
        $this->seedCM2($niveau);
    }

    // =========================================================================
    // CP A  — Source: CPA sheet
    // =========================================================================
    // FRANÇAIS: individual per-CB max_scores (CB1=20, CB2=10, CB3=5, ECRITURE=5)
    // MATHS/ARABE-ANGLAIS/SCIENCES/EPS/TECHNOLOGIE: shared pool (null per competence)
    // DICTÉE: competence max_score = 10
    // Total: 110 pts
    // =========================================================================
    private function seedCPA(Niveau $niveau): void
    {
        $N = ScaleTypeEnum::NUMERIC->value;

        // I — FRANÇAIS /40 — individual competence scores
        $this->createSubject($niveau, [
            'name'           => 'FRANÇAIS',
            'code'           => 'FR',
            'classroom_code' => 'CP',
            'section_code'   => 'CPA',
            'max_score'      => 40,
            'scale_type'     => $N,
            'order'          => 1,
        ], [
            ['code' => 'CB1',      'description' => "Lecture : Appréhender le sens général d'un écrit et oraliser un court texte en maîtrisant le système de correspondance graphie/phonie.",  'max_score' => 20, 'order' => 1],
            ['code' => 'CB2',      'description' => "Production écrite : Créer un court message signifiant d'au moins trois phrases.",                                                           'max_score' => 10, 'order' => 2],
            ['code' => 'CB3',      'description' => "Langage : Produire un court énoncé en utilisant les formulations adaptées.",                                                                'max_score' => 5,  'order' => 3],
            ['code' => 'ECRITURE', 'description' => "Écriture : Écrire en cursive des lettres, syllabes, mots et phrases.",                                                                     'max_score' => 5,  'order' => 4],
        ]);

        // II — MATHÉMATIQUES /30 — shared pool
        $this->createSubject($niveau, [
            'name'           => 'MATHÉMATIQUES',
            'code'           => 'MATHS',
            'classroom_code' => 'CP',
            'section_code'   => 'CPA',
            'max_score'      => 30,
            'scale_type'     => $N,
            'order'          => 2,
        ], [
            ['code' => 'CB1', 'description' => "Numération : Résoudre une situation problème faisant intervenir l'écriture des nombres de 0 à 100 et des opérations simples.", 'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Géométrie : Se situer ou situer des objets dans l'espace et s'orienter selon son itinéraire.",                                 'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Mesure : Trier, comparer et ranger des objets selon un critère donné (nature, longueur...).",                                  'max_score' => null, 'order' => 3],
        ]);

        // III — ARABE / ANGLAIS /30 — shared pool
        $this->createSubject($niveau, [
            'name'           => 'ARABE / ANGLAIS',
            'code'           => 'AR_ANG',
            'classroom_code' => 'CP',
            'section_code'   => 'CPA',
            'max_score'      => 30,
            'scale_type'     => $N,
            'order'          => 3,
        ], [
            ['code' => 'CB1', 'description' => "ARABE /20",   'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "ANGLAIS /10", 'max_score' => null, 'order' => 2],
        ]);

        // IV — SCIENCES /5 — shared pool
        $this->createSubject($niveau, [
            'name'           => 'SCIENCES',
            'code'           => 'SCI',
            'classroom_code' => 'CP',
            'section_code'   => 'CPA',
            'max_score'      => 5,
            'scale_type'     => $N,
            'order'          => 4,
        ], [
            ['code' => 'CB1', 'description' => "Identifier et reconnaître les différentes parties de son corps.",  'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Identifier les animaux et les végétaux de son milieu.",            'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Nommer et sélectionner des objets d'une collection type.",         'max_score' => null, 'order' => 3],
        ]);

        // V — EPS /5 — shared pool
        $this->createSubject($niveau, [
            'name'           => 'ÉDUCATION PHYSIQUE ET SPORTIVE',
            'code'           => 'EPS',
            'classroom_code' => 'CP',
            'section_code'   => 'CPA',
            'max_score'      => 5,
            'scale_type'     => $N,
            'order'          => 5,
        ], [
            ['code' => 'CB1', 'description' => "Produire des actions adaptées au milieu en appliquant les règles du jeu.", 'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Lancer et rattraper des objets variés.",                                   'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Reproduire des formes corporelles simples.",                               'max_score' => null, 'order' => 3],
        ]);

        // VI — TECHNOLOGIE /20 — shared pool
        $this->createSubject($niveau, [
            'name'           => 'TECHNOLOGIE',
            'code'           => 'TECH',
            'classroom_code' => 'CP',
            'section_code'   => 'CPA',
            'max_score'      => 20,
            'scale_type'     => $N,
            'order'          => 6,
        ], [
            ['code' => 'CB1', 'description' => "Informatique : Allumer et éteindre l'outil informatique, s'approprier l'usage de la souris, taper sur le clavier.", 'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Robotique : Introduire les différents types de robots, discuter de l'utilité des écrans et de leur usage.",          'max_score' => null, 'order' => 2],
        ]);

        // VII — DICTÉE /10
        $this->createSubject($niveau, [
            'name'           => 'DICTÉE',
            'code'           => 'DICT',
            'classroom_code' => 'CP',
            'section_code'   => 'CPA',
            'max_score'      => 10,
            'scale_type'     => $N,
            'order'          => 7,
        ], [
            ['code' => 'DICTEE', 'description' => "Maîtriser l'orthographe de la langue française. Respecter les règles d'orthographe grammaticale.", 'max_score' => 10, 'order' => 1],
        ]);
    }

    // =========================================================================
    // CP B  — Source: CPB sheet
    // =========================================================================
    // FRANÇAIS: all CBs share pool (Excel shows 40 for all → max_score = null)
    // All other subjects: identical to CPA
    // Total: 110 pts
    // =========================================================================
    private function seedCPB(Niveau $niveau): void
    {
        $N = ScaleTypeEnum::NUMERIC->value;

        // I — FRANÇAIS /40 — shared pool (CPB differs from CPA here)
        $this->createSubject($niveau, [
            'name'           => 'FRANÇAIS',
            'code'           => 'FR',
            'classroom_code' => 'CP',
            'section_code'   => 'CPB',
            'max_score'      => 40,
            'scale_type'     => $N,
            'order'          => 1,
        ], [
            ['code' => 'CB1',      'description' => "Lecture : Appréhender le sens général d'un écrit et oraliser un court texte en maîtrisant le système de correspondance graphie/phonie.", 'max_score' => null, 'order' => 1],
            ['code' => 'CB2',      'description' => "Production écrite : Créer un court message signifiant d'au moins trois phrases.",                                                          'max_score' => null, 'order' => 2],
            ['code' => 'CB3',      'description' => "Langage : Produire un court énoncé en utilisant les formulations adaptées.",                                                               'max_score' => null, 'order' => 3],
            ['code' => 'ECRITURE', 'description' => "Écriture : Écrire en cursive des lettres, syllabes, mots et phrases.",                                                                    'max_score' => null, 'order' => 4],
        ]);

        // II — MATHÉMATIQUES /30 — shared pool
        $this->createSubject($niveau, [
            'name'           => 'MATHÉMATIQUES',
            'code'           => 'MATHS',
            'classroom_code' => 'CP',
            'section_code'   => 'CPB',
            'max_score'      => 30,
            'scale_type'     => $N,
            'order'          => 2,
        ], [
            ['code' => 'CB1', 'description' => "Numération : Résoudre une situation problème faisant intervenir l'écriture des nombres de 0 à 100 et des opérations simples.", 'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Géométrie : Se situer ou situer des objets dans l'espace et s'orienter selon son itinéraire.",                                 'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Mesure : Trier, comparer et ranger des objets selon un critère donné (nature, longueur...).",                                  'max_score' => null, 'order' => 3],
        ]);

        // III — ARABE / ANGLAIS /30 — shared pool
        $this->createSubject($niveau, [
            'name'           => 'ARABE / ANGLAIS',
            'code'           => 'AR_ANG',
            'classroom_code' => 'CP',
            'section_code'   => 'CPB',
            'max_score'      => 30,
            'scale_type'     => $N,
            'order'          => 3,
        ], [
            ['code' => 'CB1', 'description' => "ARABE /20",   'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "ANGLAIS /10", 'max_score' => null, 'order' => 2],
        ]);

        // IV — SCIENCES /5 — shared pool
        $this->createSubject($niveau, [
            'name'           => 'SCIENCES',
            'code'           => 'SCI',
            'classroom_code' => 'CP',
            'section_code'   => 'CPB',
            'max_score'      => 5,
            'scale_type'     => $N,
            'order'          => 4,
        ], [
            ['code' => 'CB1', 'description' => "Identifier et reconnaître les différentes parties de son corps.", 'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Identifier les animaux et les végétaux de son milieu.",           'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Nommer et sélectionner des objets d'une collection type.",        'max_score' => null, 'order' => 3],
        ]);

        // V — EPS /5 — shared pool
        $this->createSubject($niveau, [
            'name'           => 'ÉDUCATION PHYSIQUE ET SPORTIVE',
            'code'           => 'EPS',
            'classroom_code' => 'CP',
            'section_code'   => 'CPB',
            'max_score'      => 5,
            'scale_type'     => $N,
            'order'          => 5,
        ], [
            ['code' => 'CB1', 'description' => "Produire des actions adaptées au milieu en appliquant les règles du jeu.", 'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Lancer et rattraper des objets variés.",                                   'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Reproduire des formes corporelles simples.",                               'max_score' => null, 'order' => 3],
        ]);

        // VI — TECHNOLOGIE /20 — shared pool
        $this->createSubject($niveau, [
            'name'           => 'TECHNOLOGIE',
            'code'           => 'TECH',
            'classroom_code' => 'CP',
            'section_code'   => 'CPB',
            'max_score'      => 20,
            'scale_type'     => $N,
            'order'          => 6,
        ], [
            ['code' => 'CB1', 'description' => "Informatique : Allumer et éteindre l'outil informatique, s'approprier l'usage de la souris, taper sur le clavier.", 'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Robotique : Introduire les différents types de robots, discuter de l'utilité des écrans et de leur usage.",          'max_score' => null, 'order' => 2],
        ]);

        // VII — DICTÉE /10
        $this->createSubject($niveau, [
            'name'           => 'DICTÉE',
            'code'           => 'DICT',
            'classroom_code' => 'CP',
            'section_code'   => 'CPB',
            'max_score'      => 10,
            'scale_type'     => $N,
            'order'          => 7,
        ], [
            ['code' => 'DICTEE', 'description' => "Maîtriser l'orthographe de la langue française. Respecter les règles d'orthographe grammaticale.", 'max_score' => 10, 'order' => 1],
        ]);
    }

    // =========================================================================
    // CE1A  — Source: CE1A sheet
    // =========================================================================
    // Total: 180 pts (FR/30 + MATHS/30 + AR/30 + ANG/40 + SCI/10 + EPS/10
    //               + GEO/10 + TECH/20 + OUTILS/40 + HISTOIRE/10)
    // FRANÇAIS: CB1=10, CB2=10, CB3=10 (individual)
    // MATHÉMATIQUES: CB1=10, CB2=10, CB3=10 (individual)
    // ARABE /30: CB1=Lecture/10, CB2=Expression orale/10, CB3=Expression écrite/10
    // ANGLAIS /40: CB1=10, CB2=10, CB3=10 (individual)
    // SCIENCES: shared pool (all CBs = 10)
    // EPS: shared pool (all CBs = 10)
    // GEOGRAPHIE /10: CB1 only (row 22 in Excel has no code)
    // TECHNOLOGIE /20: CB1=10, CB2=10 (individual)
    // OUTILS /40: CB1=10, CB2=10, CB3=10, CB4=10 (individual) ← new subject
    // HISTOIRE /10: CB1=10 ← new subject
    // =========================================================================
    private function seedCE1A(Niveau $niveau): void
    {
        $N = ScaleTypeEnum::NUMERIC->value;

        // I — FRANÇAIS /30
        $this->createSubject($niveau, [
            'name'           => 'FRANÇAIS',
            'code'           => 'FR',
            'classroom_code' => 'CE1',
            'section_code'   => 'CE1A',
            'max_score'      => 30,
            'scale_type'     => $N,
            'order'          => 1,
        ], [
            ['code' => 'CB1', 'description' => "Lecture : récolter des informations pertinentes dans un texte de 9 à 10 phrases en vue d'accéder à la compréhension.",  'max_score' => 10, 'order' => 1],
            ['code' => 'CB2', 'description' => "Production écrite : Produire un discours cohérent de 5 à 6 phrases dans un contexte de communication écrite.",           'max_score' => 10, 'order' => 2],
            ['code' => 'CB3', 'description' => "Production orale : Produire un discours cohérent dans un contexte de communication orale.",                               'max_score' => 10, 'order' => 3],
        ]);

        // II — MATHÉMATIQUES /30
        $this->createSubject($niveau, [
            'name'           => 'MATHÉMATIQUES',
            'code'           => 'MATHS',
            'classroom_code' => 'CE1',
            'section_code'   => 'CE1A',
            'max_score'      => 30,
            'scale_type'     => $N,
            'order'          => 2,
        ], [
            ['code' => 'CB1', 'description' => "Numération : Résoudre une situation faisant intervenir les 3 opérations (+ ; – ; x) avec des nombres entiers de 1 à 1 000.", 'max_score' => 10, 'order' => 1],
            ['code' => 'CB2', 'description' => "Géométrie : Résoudre une situation problème faisant appel à la représentation et la construction d'une figure.",              'max_score' => 10, 'order' => 2],
            ['code' => 'CB3', 'description' => "Mesure : Résoudre une situation problème nécessitant le rangement et le classement d'événements dans le temps ou des objets.", 'max_score' => 10, 'order' => 3],
        ]);

        // III — ARABE /30
        $this->createSubject($niveau, [
            'name'           => 'ARABE',
            'code'           => 'AR',
            'classroom_code' => 'CE1',
            'section_code'   => 'CE1A',
            'max_score'      => 30,
            'scale_type'     => $N,
            'order'          => 3,
        ], [
            ['code' => 'CB1', 'description' => "Lecture.",           'max_score' => 10, 'order' => 1],
            ['code' => 'CB2', 'description' => "Expression orale.",  'max_score' => 10, 'order' => 2],
            ['code' => 'CB3', 'description' => "Expression écrite.", 'max_score' => 10, 'order' => 3],
        ]);

        // IV — ANGLAIS /40
        $this->createSubject($niveau, [
            'name'           => 'ANGLAIS',
            'code'           => 'ANG',
            'classroom_code' => 'CE1',
            'section_code'   => 'CE1A',
            'max_score'      => 40,
            'scale_type'     => $N,
            'order'          => 4,
        ], [
            ['code' => 'CB1', 'description' => "Saluer, écrire les nombres de 1 à 12. Apprendre les jours et les mois. Prononciation des lettres alphabétiques.",                                                'max_score' => 10, 'order' => 1],
            ['code' => 'CB2', 'description' => "CONJUGAISON : Reconnaître le temps des verbes du 1er gr, 2ème gr et 3ème groupe au présent, passé composé, futur.",                                             'max_score' => 10, 'order' => 2],
            ['code' => 'CB3', 'description' => "Reconnaître les différentes parties du corps. Apprendre les adjectifs qualificatifs. Révision. Prononciation des lettres alphabétiques.",                       'max_score' => 10, 'order' => 3],
        ]);

        // V — SCIENCES /10 — shared pool
        $this->createSubject($niveau, [
            'name'           => 'SCIENCES',
            'code'           => 'SCI',
            'classroom_code' => 'CE1',
            'section_code'   => 'CE1A',
            'max_score'      => 10,
            'scale_type'     => $N,
            'order'          => 5,
        ], [
            ['code' => 'CB1', 'description' => "Connaître la structure du corps humain (squelette et muscles) nécessaires à son organisme en vue d'améliorer son hygiène de vie.", 'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Identifier les besoins alimentaires des animaux et des plantes.",                                                                   'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Identifier l'état physique d'un élément du milieu (liquide ou solide).",                                                           'max_score' => null, 'order' => 3],
        ]);

        // VI — EPS /10 — shared pool
        $this->createSubject($niveau, [
            'name'           => 'ÉDUCATION PHYSIQUE ET SPORTIVE',
            'code'           => 'EPS',
            'classroom_code' => 'CE1',
            'section_code'   => 'CE1A',
            'max_score'      => 10,
            'scale_type'     => $N,
            'order'          => 6,
        ], [
            ['code' => 'CB1', 'description' => "Adapter et varier ses courses et ses bonds sur une distance donnée.",           'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Lancer ou rattraper des objets de forme et dimension variées.",                 'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Coordonner ses capacités motrices pour contribuer au projet de son équipe.",    'max_score' => null, 'order' => 3],
            ['code' => 'CB4', 'description' => "Produire et adapter ses mouvements ou ses déplacements en tempo collectif.",    'max_score' => null, 'order' => 4],
        ]);

        // VII — GEOGRAPHIE /10 — CB1 only (CE1A Excel row 22 has no code for 2nd competence)
        $this->createSubject($niveau, [
            'name'           => 'GÉOGRAPHIE',
            'code'           => 'GEO',
            'classroom_code' => 'CE1',
            'section_code'   => 'CE1A',
            'max_score'      => 10,
            'scale_type'     => $N,
            'order'          => 7,
        ], [
            ['code' => 'CB1', 'description' => "Utiliser des outils de géographie (croquis, plan) pour représenter un élément de son environnement immédiat.", 'max_score' => null, 'order' => 1],
        ]);

        // VIII — TECHNOLOGIE /20
        $this->createSubject($niveau, [
            'name'           => 'TECHNOLOGIE',
            'code'           => 'TECH',
            'classroom_code' => 'CE1',
            'section_code'   => 'CE1A',
            'max_score'      => 20,
            'scale_type'     => $N,
            'order'          => 8,
        ], [
            ['code' => 'CB1', 'description' => "Informatique : Allumer et éteindre un outil informatique, lancer et quitter un logiciel, s'approprier l'usage de la souris, taper sur le clavier, accéder à un dossier.", 'max_score' => 10, 'order' => 1],
            ['code' => 'CB2', 'description' => "Robotique : Découvrir les différents composants du robot, aborder le fonctionnement du robot, appréhender le système binaire et de code.",                                  'max_score' => 10, 'order' => 2],
        ]);

        // IX — OUTILS /40 ← new subject from Excel
        $this->createSubject($niveau, [
            'name'           => 'OUTILS',
            'code'           => 'OUTILS',
            'classroom_code' => 'CE1',
            'section_code'   => 'CE1A',
            'max_score'      => 40,
            'scale_type'     => $N,
            'order'          => 9,
        ], [
            ['code' => 'CB1', 'description' => "VOCABULAIRE : Décrire une personne, décrire un lieu, décrire un métier, raconter un événement.",                                    'max_score' => 10, 'order' => 1],
            ['code' => 'CB2', 'description' => "CONJUGAISON : Reconnaître le temps des verbes du 1er gr, 2ème gr et 3ème groupe au présent, passé composé, futur.",                 'max_score' => 10, 'order' => 2],
            ['code' => 'CB3', 'description' => "GRAMMAIRE : Reconnaître les noms, les adjectifs, les adverbes.",                                                                    'max_score' => 10, 'order' => 3],
            ['code' => 'CB4', 'description' => "ORTHOGRAPHE : Reconnaître le genre, le nombre des noms, des adjectifs.",                                                            'max_score' => 10, 'order' => 4],
        ]);

        // X — HISTOIRE /10 ← new subject from Excel
        $this->createSubject($niveau, [
            'name'           => 'HISTOIRE',
            'code'           => 'HIST',
            'classroom_code' => 'CE1',
            'section_code'   => 'CE1A',
            'max_score'      => 10,
            'scale_type'     => $N,
            'order'          => 10,
        ], [
            ['code' => 'CB1', 'description' => "Évoquer deux faits, personnages ou lieux et de le situer sur une frise chronologique.", 'max_score' => 10, 'order' => 1],
        ]);
    }

    // =========================================================================
    // CE1B  — Source: CE1B sheet
    // =========================================================================
    // Same as CE1A except:
    //   HISTOIRE-GEOGRAPHIE /10: CB1 + CB2 (both coded in Excel)
    //   No standalone HISTOIRE subject
    //   OUTILS /40: CB1–CB4 (identical to CE1A)
    // =========================================================================
    private function seedCE1B(Niveau $niveau): void
    {
        $N = ScaleTypeEnum::NUMERIC->value;

        // I — FRANÇAIS /30
        $this->createSubject($niveau, [
            'name'           => 'FRANÇAIS',
            'code'           => 'FR',
            'classroom_code' => 'CE1',
            'section_code'   => 'CE1B',
            'max_score'      => 30,
            'scale_type'     => $N,
            'order'          => 1,
        ], [
            ['code' => 'CB1', 'description' => "Lecture : récolter des informations pertinentes dans un texte de 9 à 10 phrases en vue d'accéder à la compréhension.",  'max_score' => 10, 'order' => 1],
            ['code' => 'CB2', 'description' => "Production écrite : Produire un discours cohérent de 5 à 6 phrases dans un contexte de communication écrite.",           'max_score' => 10, 'order' => 2],
            ['code' => 'CB3', 'description' => "Production orale : Produire un discours cohérent dans un contexte de communication orale.",                               'max_score' => 10, 'order' => 3],
        ]);

        // II — MATHÉMATIQUES /30
        $this->createSubject($niveau, [
            'name'           => 'MATHÉMATIQUES',
            'code'           => 'MATHS',
            'classroom_code' => 'CE1',
            'section_code'   => 'CE1B',
            'max_score'      => 30,
            'scale_type'     => $N,
            'order'          => 2,
        ], [
            ['code' => 'CB1', 'description' => "Numération : Résoudre une situation faisant intervenir les 3 opérations (+ ; – ; x) avec des nombres entiers de 1 à 1 000.", 'max_score' => 10, 'order' => 1],
            ['code' => 'CB2', 'description' => "Géométrie : Résoudre une situation problème faisant appel à la représentation et la construction d'une figure.",              'max_score' => 10, 'order' => 2],
            ['code' => 'CB3', 'description' => "Mesure : Résoudre une situation problème nécessitant le rangement et le classement d'événements dans le temps ou des objets.", 'max_score' => 10, 'order' => 3],
        ]);

        // III — ARABE /30
        $this->createSubject($niveau, [
            'name'           => 'ARABE',
            'code'           => 'AR',
            'classroom_code' => 'CE1',
            'section_code'   => 'CE1B',
            'max_score'      => 30,
            'scale_type'     => $N,
            'order'          => 3,
        ], [
            ['code' => 'CB1', 'description' => "Lecture.",           'max_score' => 10, 'order' => 1],
            ['code' => 'CB2', 'description' => "Expression orale.",  'max_score' => 10, 'order' => 2],
            ['code' => 'CB3', 'description' => "Expression écrite.", 'max_score' => 10, 'order' => 3],
        ]);

        // IV — ANGLAIS /40
        $this->createSubject($niveau, [
            'name'           => 'ANGLAIS',
            'code'           => 'ANG',
            'classroom_code' => 'CE1',
            'section_code'   => 'CE1B',
            'max_score'      => 40,
            'scale_type'     => $N,
            'order'          => 4,
        ], [
            ['code' => 'CB1', 'description' => "Saluer, écrire les nombres de 1 à 12. Apprendre les jours et les mois. Prononciation des lettres alphabétiques.",                                                'max_score' => 10, 'order' => 1],
            ['code' => 'CB2', 'description' => "CONJUGAISON : Reconnaître le temps des verbes du 1er gr, 2ème gr et 3ème groupe au présent, passé composé, futur.",                                             'max_score' => 10, 'order' => 2],
            ['code' => 'CB3', 'description' => "Reconnaître les différentes parties du corps. Apprendre les adjectifs qualificatifs. Révision. Prononciation des lettres alphabétiques.",                       'max_score' => 10, 'order' => 3],
        ]);

        // V — SCIENCES /10 — shared pool
        $this->createSubject($niveau, [
            'name'           => 'SCIENCES',
            'code'           => 'SCI',
            'classroom_code' => 'CE1',
            'section_code'   => 'CE1B',
            'max_score'      => 10,
            'scale_type'     => $N,
            'order'          => 5,
        ], [
            ['code' => 'CB1', 'description' => "Connaître la structure du corps humain (squelette et muscles) nécessaires à son organisme en vue d'améliorer son hygiène de vie.", 'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Identifier les besoins alimentaires des animaux et des plantes.",                                                                   'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Identifier l'état physique d'un élément du milieu (liquide ou solide).",                                                           'max_score' => null, 'order' => 3],
        ]);

        // VI — EPS /10 — shared pool
        $this->createSubject($niveau, [
            'name'           => 'ÉDUCATION PHYSIQUE ET SPORTIVE',
            'code'           => 'EPS',
            'classroom_code' => 'CE1',
            'section_code'   => 'CE1B',
            'max_score'      => 10,
            'scale_type'     => $N,
            'order'          => 6,
        ], [
            ['code' => 'CB1', 'description' => "Adapter et varier ses courses et ses bonds sur une distance donnée.",           'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Lancer ou rattraper des objets de forme et dimension variées.",                 'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Coordonner ses capacités motrices pour contribuer au projet de son équipe.",    'max_score' => null, 'order' => 3],
            ['code' => 'CB4', 'description' => "Produire et adapter ses mouvements ou ses déplacements en tempo collectif.",    'max_score' => null, 'order' => 4],
        ]);

        // VII — HISTOIRE-GÉOGRAPHIE /10 — CB1 + CB2 (both present in CE1B)
        $this->createSubject($niveau, [
            'name'           => 'HISTOIRE-GÉOGRAPHIE',
            'code'           => 'HG',
            'classroom_code' => 'CE1',
            'section_code'   => 'CE1B',
            'max_score'      => 10,
            'scale_type'     => $N,
            'order'          => 7,
        ], [
            ['code' => 'CB1', 'description' => "Utiliser des outils de géographie (croquis, plan) pour représenter un élément de son environnement immédiat.", 'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Utiliser les notions générales de géographie (relief et climat).",                                              'max_score' => null, 'order' => 2],
        ]);

        // VIII — TECHNOLOGIE /20
        $this->createSubject($niveau, [
            'name'           => 'TECHNOLOGIE',
            'code'           => 'TECH',
            'classroom_code' => 'CE1',
            'section_code'   => 'CE1B',
            'max_score'      => 20,
            'scale_type'     => $N,
            'order'          => 8,
        ], [
            ['code' => 'CB1', 'description' => "Informatique : Allumer et éteindre un outil informatique, lancer et quitter un logiciel, s'approprier l'usage de la souris, taper sur le clavier, accéder à un dossier.", 'max_score' => 10, 'order' => 1],
            ['code' => 'CB2', 'description' => "Robotique : Découvrir les différents composants du robot, aborder le fonctionnement du robot, appréhender le système binaire et de code.",                                  'max_score' => 10, 'order' => 2],
        ]);

        // IX — OUTILS /40 ← new subject from Excel
        $this->createSubject($niveau, [
            'name'           => 'OUTILS',
            'code'           => 'OUTILS',
            'classroom_code' => 'CE1',
            'section_code'   => 'CE1B',
            'max_score'      => 40,
            'scale_type'     => $N,
            'order'          => 9,
        ], [
            ['code' => 'CB1', 'description' => "VOCABULAIRE : Décrire une personne, décrire un lieu, décrire un métier, raconter un événement.",                                    'max_score' => 10, 'order' => 1],
            ['code' => 'CB2', 'description' => "CONJUGAISON : Reconnaître le temps des verbes du 1er gr, 2ème gr et 3ème groupe au présent, passé composé, futur.",                 'max_score' => 10, 'order' => 2],
            ['code' => 'CB3', 'description' => "GRAMMAIRE : Reconnaître les noms, les adjectifs, les adverbes.",                                                                    'max_score' => 10, 'order' => 3],
            ['code' => 'CB4', 'description' => "ORTHOGRAPHE : Reconnaître le genre, le nombre des noms, des adjectifs.",                                                            'max_score' => 10, 'order' => 4],
        ]);
    }

    // =========================================================================
    // CE2  — Source: CE2A and CE2B sheets (identical structure)
    // =========================================================================
    // Total: 210 pts (FR/40 + MATHS/30 + AR/20 + ANG/20 + SCI/10
    //               + GEO/10 + TECH/20 + OUTILS/40 + HISTOIRE/10)
    // FRANÇAIS: CB1=20, CB2=20, CB3=20 (individual per Excel)
    // MATHÉMATIQUES: all CBs max_score=30 (shared pool)
    // ARABE /20: CB2=EXPRESSION ECRITE/20, CB3=EXPRESSION ORALE/20 (no CB1, shared pool)
    // ANGLAIS /20: all CBs max_score=20 (shared pool)
    // GEOGRAPHIE: named "GEOGRAPHIE" (not HISTOIRE-GÉOGRAPHIE) in Excel
    // OUTILS /40: CB1=10, CB2=10, CB3=10, CB4=10 ← new subject
    // HISTOIRE /10: CB1=10 ← new subject
    // =========================================================================
    private function seedCE2(Niveau $niveau): void
    {
        $N = ScaleTypeEnum::NUMERIC->value;

        foreach (['CE2A', 'CE2B'] as $section) {
            // I — FRANÇAIS /40
            $this->createSubject($niveau, [
                'name'           => 'FRANÇAIS',
                'code'           => 'FR',
                'classroom_code' => 'CE2',
                'section_code'   => $section,
                'max_score'      => 40,
                'scale_type'     => $N,
                'order'          => 1,
            ], [
                ['code' => 'CB1', 'description' => "LECTURE : récolter des informations pertinentes dans un texte de 10 à 15 phrases. Lecture oralisée.",                             'max_score' => 20, 'order' => 1],
                ['code' => 'CB2', 'description' => "CONJUGAISON : reconnaître le temps des verbes du 1er gr, 2ème gr et 3ème gr au passé composé, présent, futur et imparfait.",     'max_score' => 20, 'order' => 2],
                ['code' => 'CB3', 'description' => "ORTHOGRAPHE : reconnaître les différentes graphies, le genre et nombre des adjectifs et les mots invariables.",                  'max_score' => 20, 'order' => 3],
            ]);

            // II — MATHÉMATIQUES /30 — shared pool
            $this->createSubject($niveau, [
                'name'           => 'MATHÉMATIQUES',
                'code'           => 'MATHS',
                'classroom_code' => 'CE2',
                'section_code'   => $section,
                'max_score'      => 30,
                'scale_type'     => $N,
                'order'          => 2,
            ], [
                ['code' => 'CB1', 'description' => "Numération : Résoudre des situations significatives mobilisant les 3 opérations (+ ; – ; x) et la division sur des nombres entiers à 999 999.", 'max_score' => null, 'order' => 1],
                ['code' => 'CB2', 'description' => "Géométrie : Décrire ou reproduire des figures simples avec les instruments adéquats (règle, équerre et compas).",                              'max_score' => null, 'order' => 2],
                ['code' => 'CB3', 'description' => "Mesure : Évaluer, comparer des mesures (longueurs, masses et durées) et élaborer des chronologies d'événements dans le temps.",               'max_score' => null, 'order' => 3],
            ]);

            // III — ARABE /20 — CB2 + CB3 only (no CB1), shared pool
            $this->createSubject($niveau, [
                'name'           => 'ARABE',
                'code'           => 'AR',
                'classroom_code' => 'CE2',
                'section_code'   => $section,
                'max_score'      => 20,
                'scale_type'     => $N,
                'order'          => 3,
            ], [
                ['code' => 'CB2', 'description' => "EXPRESSION ECRITE.", 'max_score' => null, 'order' => 1],
                ['code' => 'CB3', 'description' => "EXPRESSION ORALE.",  'max_score' => null, 'order' => 2],
            ]);

            // IV — ANGLAIS /20 — shared pool
            $this->createSubject($niveau, [
                'name'           => 'ANGLAIS',
                'code'           => 'ANG',
                'classroom_code' => 'CE2',
                'section_code'   => $section,
                'max_score'      => 20,
                'scale_type'     => $N,
                'order'          => 4,
            ], [
                ['code' => 'CB1', 'description' => "Saluer, écrire les nombres de 1 à 12. Apprendre les jours et les mois. Prononciation des lettres alphabétiques.",                              'max_score' => null, 'order' => 1],
                ['code' => 'CB2', 'description' => "Reconnaître les fournitures scolaires, les mobiliers scolaires. Identifier les membres de sa famille. Prononciation des lettres alphabétiques.", 'max_score' => null, 'order' => 2],
                ['code' => 'CB3', 'description' => "Reconnaître les différentes parties du corps. Apprendre les adjectifs qualificatifs. Révision. Prononciation des lettres alphabétiques.",       'max_score' => null, 'order' => 3],
            ]);

            // V — SCIENCES /10 — shared pool
            $this->createSubject($niveau, [
                'name'           => 'SCIENCES',
                'code'           => 'SCI',
                'classroom_code' => 'CE2',
                'section_code'   => $section,
                'max_score'      => 10,
                'scale_type'     => $N,
                'order'          => 5,
            ], [
                ['code' => 'CB1', 'description' => "Identifier les besoins alimentaires nécessaires à son organisme en vue d'améliorer son hygiène de vie.", 'max_score' => null, 'order' => 1],
                ['code' => 'CB2', 'description' => "Déterminer les conditions nécessaires à la croissance des animaux et des plantes.",                       'max_score' => null, 'order' => 2],
                ['code' => 'CB3', 'description' => "Dégager les applications pratiques de quelques phénomènes physiques usuels (électricité, air, son).",    'max_score' => null, 'order' => 3],
            ]);

            // VI — GÉOGRAPHIE /10 — shared pool (named GEOGRAPHIE in CE2 Excel sheets)
            $this->createSubject($niveau, [
                'name'           => 'GÉOGRAPHIE',
                'code'           => 'GEO',
                'classroom_code' => 'CE2',
                'section_code'   => $section,
                'max_score'      => 10,
                'scale_type'     => $N,
                'order'          => 6,
            ], [
                ['code' => 'CB1', 'description' => "Face à une situation-problème, émettre des propositions sur la base de schémas, de cartes et des croquis.",                  'max_score' => null, 'order' => 1],
                ['code' => 'CB2', 'description' => "À l'aide de tableaux relatifs aux données climatiques, résoudre une situation-problème.",                                     'max_score' => null, 'order' => 2],
                ['code' => 'CB3', 'description' => "Face à une situation-problème, identifier des actions néfastes et proposer des actions de protection de l'environnement.",   'max_score' => null, 'order' => 3],
            ]);

            // VII — TECHNOLOGIE /20 — shared pool
            $this->createSubject($niveau, [
                'name'           => 'TECHNOLOGIE',
                'code'           => 'TECH',
                'classroom_code' => 'CE2',
                'section_code'   => $section,
                'max_score'      => 20,
                'scale_type'     => $N,
                'order'          => 7,
            ], [
                ['code' => 'CB1', 'description' => "Informatique : Allumer et éteindre un outil informatique, lancer et quitter un logiciel, s'approprier l'usage de la souris, taper sur le clavier, accéder à un dossier.",     'max_score' => null, 'order' => 1],
                ['code' => 'CB2', 'description' => "Robotique : Découvrir les différents composants du robot, aborder le fonctionnement du robot, appréhender le système binaire et de code, imaginer un dessin binaire.",         'max_score' => null, 'order' => 2],
            ]);

            // VIII — OUTILS /40 ← new subject from Excel
            $this->createSubject($niveau, [
                'name'           => 'OUTILS',
                'code'           => 'OUTILS',
                'classroom_code' => 'CE2',
                'section_code'   => $section,
                'max_score'      => 40,
                'scale_type'     => $N,
                'order'          => 8,
            ], [
                ['code' => 'CB1', 'description' => "VOCABULAIRE : Décrire une personne, localiser un lieu, donner des informations, raconter un événement.",             'max_score' => 10, 'order' => 1],
                ['code' => 'CB2', 'description' => "CONJUGAISON : reconnaître le temps des verbes du 1er gr, 2ème gr et 3ème gr au passé composé, présent, futur et imparfait.", 'max_score' => 10, 'order' => 2],
                ['code' => 'CB3', 'description' => "GRAMMAIRE : reconnaître le type de phrase, la forme et les adjectifs.",                                              'max_score' => 10, 'order' => 3],
                ['code' => 'CB4', 'description' => "ORTHOGRAPHE : reconnaître les différentes graphies et reconnaître le genre et nombre des adjectifs et les mots invariables.", 'max_score' => 10, 'order' => 4],
            ]);

            // IX — HISTOIRE /10 ← new subject from Excel
            $this->createSubject($niveau, [
                'name'           => 'HISTOIRE',
                'code'           => 'HIST',
                'classroom_code' => 'CE2',
                'section_code'   => $section,
                'max_score'      => 10,
                'scale_type'     => $N,
                'order'          => 9,
            ], [
                ['code' => 'CB1', 'description' => "Évoquer deux faits, personnages ou lieux et de le situer sur une frise chronologique.", 'max_score' => 10, 'order' => 1],
            ]);
        }
    }

    // =========================================================================
    // CM1  — Source: CM1 sheet
    // =========================================================================
    // Total: 210 pts (FR/40 + MATHS/30 + AR/20 + ANG/20 + SCI/10
    //               + GEO/10 + TECH/20 + OUTILS/40 + HISTOIRE/10)
    // FRANÇAIS /40: all CBs max_score=40 (shared pool)
    // MATHÉMATIQUES /30: CB1=10, CB2=10, CB3=10 (individual per Excel)
    // ARABE /20: CB1=LECTURE/10, CB2=EXPRESSION ECRITE/10 (individual)
    // ANGLAIS /20: CB1=10, CB2=10, CB3=10 (individual)
    // GÉOGRAPHIE: shared pool (all CBs null)
    // TECHNOLOGIE /20: CB1=10, CB2=10 (individual)
    // OUTILS /40: CB1=10, CB2=10, CB3=10, CB4=10 ← new subject
    // HISTOIRE /10: CB1=10 ← new subject
    // Note: Excel sheet has GEOGRAPHIE listed twice (rows 16-18 and 21-23); seeded once.
    // =========================================================================
    private function seedCM1(Niveau $niveau): void
    {
        $N = ScaleTypeEnum::NUMERIC->value;

        // I — FRANÇAIS /40 — shared pool
        $this->createSubject($niveau, [
            'name'           => 'FRANÇAIS',
            'code'           => 'FR',
            'classroom_code' => 'CM1',
            'max_score'      => 40,
            'scale_type'     => $N,
            'order'          => 1,
        ], [
            ['code' => 'CB1', 'description' => "VOCABULAIRE : Décrire une personne, localiser un lieu, donner des informations, raconter un événement, les contraires, synonymes, homonymes, champ lexical.", 'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "CONJUGAISON : reconnaître le temps des verbes du 1er gr, 2ème gr et 3ème gr au passé composé, présent, futur, imparfait et passé simple.",                     'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "ORTHOGRAPHE : reconnaître les différentes graphies, le genre et nombre des adjectifs, les mots invariables et les homophones.",                                'max_score' => null, 'order' => 3],
        ]);

        // II — MATHÉMATIQUES /30 — individual per CB (Excel shows 10 each)
        $this->createSubject($niveau, [
            'name'           => 'MATHÉMATIQUES',
            'code'           => 'MATHS',
            'classroom_code' => 'CM1',
            'max_score'      => 30,
            'scale_type'     => $N,
            'order'          => 2,
        ], [
            ['code' => 'CB1', 'description' => "Numération : Résoudre des situations significatives mobilisant les 4 opérations (+ ; – ; x ; :) sur des nombres entiers à 999 999 999.",                           'max_score' => 10, 'order' => 1],
            ['code' => 'CB2', 'description' => "Géométrie : Résoudre des situations problèmes nécessitant la description et la construction des figures planes et des solides.",                                   'max_score' => 10, 'order' => 2],
            ['code' => 'CB3', 'description' => "Mesure : Résoudre des situations problèmes faisant intervenir des unités de mesure de longueur et d'aires, ainsi que de masses, des angles et des durées.",      'max_score' => 10, 'order' => 3],
        ]);

        // III — ARABE /20 — individual (CB1=10, CB2=10)
        $this->createSubject($niveau, [
            'name'           => 'ARABE',
            'code'           => 'AR',
            'classroom_code' => 'CM1',
            'max_score'      => 20,
            'scale_type'     => $N,
            'order'          => 3,
        ], [
            ['code' => 'CB1', 'description' => "LECTURE.",           'max_score' => 10, 'order' => 1],
            ['code' => 'CB2', 'description' => "EXPRESSION ECRITE.", 'max_score' => 10, 'order' => 2],
        ]);

        // IV — ANGLAIS /20 — individual (CB1=10, CB2=10, CB3=10; sum=30 across 3 CBs but subject /20)
        $this->createSubject($niveau, [
            'name'           => 'ANGLAIS',
            'code'           => 'ANG',
            'classroom_code' => 'CM1',
            'max_score'      => 20,
            'scale_type'     => $N,
            'order'          => 4,
        ], [
            ['code' => 'CB1', 'description' => "Saluer, écrire les nombres de 1 à 20. Apprendre les jours et les mois. Prononciation des lettres alphabétiques.",                          'max_score' => 10, 'order' => 1],
            ['code' => 'CB2', 'description' => "Reconnaître les fournitures scolaires, les mobiliers scolaires. Identifier les membres de sa famille. Prononciation des lettres.",          'max_score' => 10, 'order' => 2],
            ['code' => 'CB3', 'description' => "Reconnaître les différentes parties du corps. Apprendre les adjectifs qualificatifs. Révision. Prononciation des lettres alphabétiques.",  'max_score' => 10, 'order' => 3],
        ]);

        // V — SCIENCES /10 — shared pool
        $this->createSubject($niveau, [
            'name'           => 'SCIENCES',
            'code'           => 'SCI',
            'classroom_code' => 'CM1',
            'max_score'      => 10,
            'scale_type'     => $N,
            'order'          => 5,
        ], [
            ['code' => 'CB1', 'description' => "Identifier les besoins alimentaires nécessaires à son organisme en vue d'améliorer son hygiène de vie.", 'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Déterminer les conditions nécessaires à la croissance des animaux et des plantes.",                       'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Dégager les applications pratiques de quelques phénomènes physiques usuels (électricité, air, son).",    'max_score' => null, 'order' => 3],
        ]);

        // VI — GÉOGRAPHIE /10 — shared pool (seeded once despite duplicate in Excel)
        $this->createSubject($niveau, [
            'name'           => 'GÉOGRAPHIE',
            'code'           => 'GEO',
            'classroom_code' => 'CM1',
            'max_score'      => 10,
            'scale_type'     => $N,
            'order'          => 6,
        ], [
            ['code' => 'CB1', 'description' => "Face à une situation-problème, émettre des propositions sur la base de schémas, de cartes et des croquis.",                  'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "À l'aide de tableaux relatifs aux données climatiques, résoudre une situation-problème.",                                     'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Face à une situation-problème, identifier des actions néfastes et proposer des actions de protection de l'environnement.",   'max_score' => null, 'order' => 3],
        ]);

        // VII — TECHNOLOGIE /20 — individual (CB1=10, CB2=10)
        $this->createSubject($niveau, [
            'name'           => 'TECHNOLOGIE',
            'code'           => 'TECH',
            'classroom_code' => 'CM1',
            'max_score'      => 20,
            'scale_type'     => $N,
            'order'          => 7,
        ], [
            ['code' => 'CB1', 'description' => "Informatique : Allumer et éteindre un outil informatique, lancer et quitter un logiciel, s'approprier l'usage de la souris, taper sur le clavier, accéder à un dossier.", 'max_score' => 10, 'order' => 1],
            ['code' => 'CB2', 'description' => "Robotique : Découvrir les différents composants du robot, aborder le fonctionnement du robot, appréhender le système binaire et de code, imaginer un dessin binaire.",    'max_score' => 10, 'order' => 2],
        ]);

        // VIII — OUTILS /40 ← new subject from Excel
        $this->createSubject($niveau, [
            'name'           => 'OUTILS',
            'code'           => 'OUTILS',
            'classroom_code' => 'CM1',
            'max_score'      => 40,
            'scale_type'     => $N,
            'order'          => 8,
        ], [
            ['code' => 'CB1', 'description' => "VOCABULAIRE : Décrire une personne, localiser un lieu, donner des informations, raconter un événement, les contraires, synonymes, homonymes, champ lexical.", 'max_score' => 10, 'order' => 1],
            ['code' => 'CB2', 'description' => "CONJUGAISON : reconnaître le temps des verbes du 1er gr, 2ème gr et 3ème gr au passé composé, présent, futur, imparfait et passé simple.",                    'max_score' => 10, 'order' => 2],
            ['code' => 'CB3', 'description' => "GRAMMAIRE : reconnaître le type de phrase, la forme, les adjectifs, les pronoms.",                                                                             'max_score' => 10, 'order' => 3],
            ['code' => 'CB4', 'description' => "ORTHOGRAPHE : reconnaître les différentes graphies, le genre et nombre des adjectifs, les mots invariables et les homophones.",                               'max_score' => 10, 'order' => 4],
        ]);

        // IX — HISTOIRE /10 ← new subject from Excel
        $this->createSubject($niveau, [
            'name'           => 'HISTOIRE',
            'code'           => 'HIST',
            'classroom_code' => 'CM1',
            'max_score'      => 10,
            'scale_type'     => $N,
            'order'          => 9,
        ], [
            ['code' => 'CB1', 'description' => "Évoquer deux faits, personnages ou lieux et de le situer sur une frise chronologique.", 'max_score' => 10, 'order' => 1],
        ]);
    }

    // =========================================================================
    // CM2  — Source: CM2 sheet
    // =========================================================================
    // Total: 210 pts (FR/40 + MATHS/30 + AR/20 + ANG/20 + SCI/10
    //               + HG/10 + TECH/20 + OUTILS/40 + HISTOIRE/10)
    // FRANÇAIS /40: all CBs max_score=40 (shared pool)
    // MATHÉMATIQUES /30: all CBs max_score=30 (shared pool); numbers to 999 999 999 999
    // ARABE /20: CB1=LECTURE/20, CB2=EXPRESSION ECRITE/20 (shared pool)
    // ANGLAIS /20: all CBs max_score=20 (shared pool)
    // HISTOIRE-GEOGRAPHIE /10: named correctly in CM2 Excel (unlike CM1/CE2)
    // OUTILS /40: CB1=10, CB2=10, CB3=10, CB4=10 ← new subject
    // HISTOIRE /10: CB1=10 ← new subject
    // =========================================================================
    private function seedCM2(Niveau $niveau): void
    {
        $N = ScaleTypeEnum::NUMERIC->value;

        // I — FRANÇAIS /40 — shared pool
        $this->createSubject($niveau, [
            'name'           => 'FRANÇAIS',
            'code'           => 'FR',
            'classroom_code' => 'CM2',
            'max_score'      => 40,
            'scale_type'     => $N,
            'order'          => 1,
        ], [
            ['code' => 'CB1', 'description' => "VOCABULAIRE : Décrire une personne, localiser un lieu, donner des informations, raconter un événement, les contraires, synonymes, homonymes, champ lexical.", 'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "CONJUGAISON : reconnaître le temps des verbes du 1er gr, 2ème gr et 3ème gr au passé composé, présent, futur, imparfait et passé simple.",                     'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "ORTHOGRAPHE : reconnaître les différentes graphies, le genre et nombre des adjectifs, les mots invariables et les homophones.",                                'max_score' => null, 'order' => 3],
        ]);

        // II — MATHÉMATIQUES /30 — shared pool; CM2 extends to 999 999 999 999 (12 digits)
        $this->createSubject($niveau, [
            'name'           => 'MATHÉMATIQUES',
            'code'           => 'MATHS',
            'classroom_code' => 'CM2',
            'max_score'      => 30,
            'scale_type'     => $N,
            'order'          => 2,
        ], [
            ['code' => 'CB1', 'description' => "Numération : Résoudre des situations significatives mobilisant les 4 opérations (+ ; – ; x ; :) sur des nombres entiers à 999 999 999 999.", 'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Géométrie : Résoudre des situations problèmes nécessitant la description et la construction des figures planes et des solides.",              'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Mesure : Résoudre des situations problèmes faisant intervenir des unités de mesure de longueur et d'aires, ainsi que de masses, des angles et des durées.", 'max_score' => null, 'order' => 3],
        ]);

        // III — ARABE /20 — shared pool (CB1+CB2)
        $this->createSubject($niveau, [
            'name'           => 'ARABE',
            'code'           => 'AR',
            'classroom_code' => 'CM2',
            'max_score'      => 20,
            'scale_type'     => $N,
            'order'          => 3,
        ], [
            ['code' => 'CB1', 'description' => "LECTURE.",           'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "EXPRESSION ECRITE.", 'max_score' => null, 'order' => 2],
        ]);

        // IV — ANGLAIS /20 — shared pool
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

        // V — SCIENCES /10 — shared pool
        $this->createSubject($niveau, [
            'name'           => 'SCIENCES',
            'code'           => 'SCI',
            'classroom_code' => 'CM2',
            'max_score'      => 10,
            'scale_type'     => $N,
            'order'          => 5,
        ], [
            ['code' => 'CB1', 'description' => "Identifier les besoins alimentaires nécessaires à son organisme en vue d'améliorer son hygiène de vie.", 'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "Déterminer les conditions nécessaires à la croissance des animaux et des plantes.",                       'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Dégager les applications pratiques de quelques phénomènes physiques usuels (électricité, air, son).",    'max_score' => null, 'order' => 3],
        ]);

        // VI — HISTOIRE-GÉOGRAPHIE /10 — shared pool (correctly named in CM2 Excel)
        $this->createSubject($niveau, [
            'name'           => 'HISTOIRE-GÉOGRAPHIE',
            'code'           => 'HG',
            'classroom_code' => 'CM2',
            'max_score'      => 10,
            'scale_type'     => $N,
            'order'          => 6,
        ], [
            ['code' => 'CB1', 'description' => "Face à une situation-problème, émettre des propositions sur la base de schémas, de cartes et des croquis.",                  'max_score' => null, 'order' => 1],
            ['code' => 'CB2', 'description' => "À l'aide de tableaux relatifs aux données climatiques, résoudre une situation-problème.",                                     'max_score' => null, 'order' => 2],
            ['code' => 'CB3', 'description' => "Face à une situation-problème, identifier des actions néfastes et proposer des actions de protection de l'environnement.",   'max_score' => null, 'order' => 3],
        ]);

        // VII — TECHNOLOGIE /20 — shared pool
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

        // VIII — OUTILS /40 ← new subject from Excel
        $this->createSubject($niveau, [
            'name'           => 'OUTILS',
            'code'           => 'OUTILS',
            'classroom_code' => 'CM2',
            'max_score'      => 40,
            'scale_type'     => $N,
            'order'          => 8,
        ], [
            ['code' => 'CB1', 'description' => "VOCABULAIRE : Décrire une personne, localiser un lieu, donner des informations, raconter un événement, les contraires, synonymes, homonymes, champ lexical.", 'max_score' => 10, 'order' => 1],
            ['code' => 'CB2', 'description' => "CONJUGAISON : reconnaître le temps des verbes du 1er gr, 2ème gr et 3ème gr au passé composé, présent, futur, imparfait et passé simple.",                    'max_score' => 10, 'order' => 2],
            ['code' => 'CB3', 'description' => "GRAMMAIRE : reconnaître le type de phrase, la forme, les adjectifs, les pronoms.",                                                                             'max_score' => 10, 'order' => 3],
            ['code' => 'CB4', 'description' => "ORTHOGRAPHE : reconnaître les différentes graphies, le genre et nombre des adjectifs, les mots invariables et les homophones.",                               'max_score' => 10, 'order' => 4],
        ]);

        // IX — HISTOIRE /10 ← new subject from Excel
        $this->createSubject($niveau, [
            'name'           => 'HISTOIRE',
            'code'           => 'HIST',
            'classroom_code' => 'CM2',
            'max_score'      => 10,
            'scale_type'     => $N,
            'order'          => 9,
        ], [
            ['code' => 'CB1', 'description' => "Évoquer deux faits, personnages ou lieux et de le situer sur une frise chronologique.", 'max_score' => 10, 'order' => 1],
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
            $this->createSubject($niveau, ['name' => 'FRANÇAIS',            'code' => 'FR',   'max_score' => 20, 'scale_type' => ScaleTypeEnum::NUMERIC->value, 'order' => 1], [
                ['code' => 'CB1', 'description' => "Lecture et compréhension de texte.",          'max_score' => 10, 'order' => 1],
                ['code' => 'CB2', 'description' => "Expression écrite et production textuelle.",  'max_score' => 10, 'order' => 2],
            ]);
            $this->createSubject($niveau, ['name' => 'MATHÉMATIQUES',       'code' => 'MATHS','max_score' => 20, 'scale_type' => ScaleTypeEnum::NUMERIC->value, 'order' => 2], [
                ['code' => 'CB1', 'description' => "Algèbre et calcul numérique.",                'max_score' => 10, 'order' => 1],
                ['code' => 'CB2', 'description' => "Géométrie et résolution de problèmes.",       'max_score' => 10, 'order' => 2],
            ]);
            $this->createSubject($niveau, ['name' => 'ANGLAIS',             'code' => 'ANG',  'max_score' => 20, 'scale_type' => ScaleTypeEnum::NUMERIC->value, 'order' => 3], [
                ['code' => 'CB1', 'description' => "Compréhension écrite et orale.",              'max_score' => 10, 'order' => 1],
                ['code' => 'CB2', 'description' => "Expression écrite et production.",            'max_score' => 10, 'order' => 2],
            ]);
            $this->createSubject($niveau, ['name' => 'SVT / SCIENCES',      'code' => 'SVT',  'max_score' => 20, 'scale_type' => ScaleTypeEnum::NUMERIC->value, 'order' => 4], [
                ['code' => 'CB1', 'description' => "Connaissance des faits scientifiques.",       'max_score' => 10, 'order' => 1],
                ['code' => 'CB2', 'description' => "Démarche scientifique et expérimentation.",   'max_score' => 10, 'order' => 2],
            ]);
            $this->createSubject($niveau, ['name' => 'HISTOIRE-GÉOGRAPHIE', 'code' => 'HG',   'max_score' => 20, 'scale_type' => ScaleTypeEnum::NUMERIC->value, 'order' => 5], [
                ['code' => 'CB1', 'description' => "Maîtrise des faits historiques et géographiques.", 'max_score' => 10, 'order' => 1],
                ['code' => 'CB2', 'description' => "Analyse de documents et expression écrite.",        'max_score' => 10, 'order' => 2],
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
                'section_code'   => $subjectData['section_code']   ?? null,
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
