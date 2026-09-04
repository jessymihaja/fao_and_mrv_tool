<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;


class FaqSeeder extends Seeder
{
    public function run(): void
    {
        $faqs = [

            // ── GCF Madagascar ─────────────────────────────────────
            [
                'question'  => "Qu'est-ce que le GCF Madagascar ?",
                'reponse'   => "Le GCF Madagascar est la plateforme nationale de gestion des financements climatiques à Madagascar. Elle coordonne l'accès aux ressources du Green Climate Fund (GCF) et d'autres mécanismes internationaux de financement climatique, en vue de soutenir les efforts d'adaptation et d'atténuation des changements climatiques dans le pays.",
                'categorie' => 'GCF Madagascar',
                'ordre'     => 1,
                'is_active' => true,
            ],
            [
                'question'  => "Quelle est la mission principale de GCF Madagascar ?",
                'reponse'   => "La mission principale est de coordonner, mobiliser et suivre les financements climatiques internationaux afin qu'ils bénéficient aux communautés malgaches les plus vulnérables aux effets des changements climatiques. Cela inclut la préparation des projets, la gestion des fonds alloués, et le suivi de leur impact sur le terrain.",
                'categorie' => 'GCF Madagascar',
                'ordre'     => 2,
                'is_active' => true,
            ],
            [
                'question'  => "Qui peut soumettre un projet à GCF Madagascar ?",
                'reponse'   => "Les ministères, institutions publiques, collectivités territoriales, ONG nationales et internationales, ainsi que le secteur privé peuvent soumettre des projets. Ces projets doivent être alignés avec les priorités climatiques nationales et les critères du Green Climate Fund. La soumission se fait via l'Autorité Nationale Désignée (AND).",
                'categorie' => 'GCF Madagascar',
                'ordre'     => 3,
                'is_active' => true,
            ],
            [
                'question'  => "Comment GCF Madagascar assure-t-il la transparence ?",
                'reponse'   => "GCF Madagascar publie toutes les données relatives aux projets, financements et indicateurs d'impact sur cette plateforme numérique. Les rapports de suivi, les montants alloués, les zones d'intervention et les résultats sont accessibles au public, conformément aux standards de redevabilité du Green Climate Fund.",
                'categorie' => 'GCF Madagascar',
                'ordre'     => 4,
                'is_active' => true,
            ],

            // ── AND ────────────────────────────────────────────────
            [
                'question'  => "Qu'est-ce que l'AND (Autorité Nationale Désignée) ?",
                'reponse'   => "L'AND est le point focal officiel désigné par le gouvernement malgache auprès du Green Climate Fund. Elle est rattachée au Bureau National des Changements Climatiques et de la REDD+ (BNCC-REDD+) au sein du Ministère de l'Environnement et du Développement Durable (MEDD). Son rôle est de valider les projets soumis au GCF et de s'assurer de leur cohérence avec les priorités nationales.",
                'categorie' => 'AND',
                'ordre'     => 5,
                'is_active' => true,
            ],
            [
                'question'  => "Quel est le rôle de l'AND dans l'approbation des projets ?",
                'reponse'   => "L'AND émet une lettre de non-objection (LNO) indispensable pour toute soumission de projet au GCF. Elle évalue l'alignement du projet avec les politiques nationales d'adaptation et d'atténuation, vérifie les capacités de l'entité accréditée et s'assure que le projet bénéficiera bien aux populations ciblées à Madagascar.",
                'categorie' => 'AND',
                'ordre'     => 6,
                'is_active' => true,
            ],
            [
                'question'  => "Comment contacter l'AND pour soumettre un projet ?",
                'reponse'   => "Pour soumettre un projet ou obtenir une lettre de non-objection, vous pouvez contacter l'AND via le formulaire de contact de cette plateforme, ou directement par email à info@gcf-madagascar.org. L'équipe de l'AND vous guidera à travers les étapes de préparation et de soumission de votre projet.",
                'categorie' => 'AND',
                'ordre'     => 7,
                'is_active' => true,
            ],
            [
                'question'  => "Quels secteurs prioritaires l'AND soutient-elle ?",
                'reponse'   => "L'AND soutient prioritairement les projets dans les domaines suivants : gestion durable des forêts et REDD+, résilience des zones côtières, agriculture climato-intelligente, accès à l'énergie renouvelable, gestion intégrée des ressources en eau, et protection de la biodiversité. Ces secteurs reflètent les vulnérabilités spécifiques de Madagascar face aux changements climatiques.",
                'categorie' => 'AND',
                'ordre'     => 8,
                'is_active' => true,
            ],

            // ── Financement climatique ─────────────────────────────
            [
                'question'  => "Qu'est-ce que le financement climatique ?",
                'reponse'   => "Le financement climatique désigne les ressources financières mobilisées pour aider les pays en développement à réduire leurs émissions de gaz à effet de serre (atténuation) et à s'adapter aux impacts déjà inévitables du changement climatique (adaptation). Ces fonds proviennent de mécanismes multilatéraux comme le GCF, le FEM, le Fonds d'adaptation, ainsi que de contributions bilatérales.",
                'categorie' => 'Financement climatique',
                'ordre'     => 9,
                'is_active' => true,
            ],
            [
                'question'  => "Qu'est-ce que le Green Climate Fund (GCF) international ?",
                'reponse'   => "Le Green Climate Fund est le principal mécanisme financier établi dans le cadre de la Convention-cadre des Nations Unies sur les changements climatiques (CCNUCC). Créé en 2010 et opérationnel depuis 2015, il vise à mobiliser 100 milliards de dollars par an d'ici 2020 des pays développés vers les pays en développement pour des projets climatiques. Son siège est à Incheon, en Corée du Sud.",
                'categorie' => 'Financement climatique',
                'ordre'     => 10,
                'is_active' => true,
            ],
            [
                'question'  => "Quelles sont les différentes sources de financement climatique disponibles pour Madagascar ?",
                'reponse'   => "Madagascar peut accéder à plusieurs sources de financement climatique : le Green Climate Fund (GCF), le Fonds pour l'Environnement Mondial (FEM/GEF), le Fonds d'Adaptation, le Fonds Vert pour le Climat Bilatéral (AFD, GIZ, USAID), le PNUD, la Banque Mondiale et la Banque Africaine de Développement. Chaque fonds a ses propres critères d'éligibilité et processus de soumission.",
                'categorie' => 'Financement climatique',
                'ordre'     => 11,
                'is_active' => true,
            ],
            [
                'question'  => "Quelle est la différence entre adaptation et atténuation dans le contexte du financement climatique ?",
                'reponse'   => "L'atténuation désigne les actions visant à réduire ou éviter les émissions de gaz à effet de serre (ex. : énergies renouvelables, reboisement, efficacité énergétique). L'adaptation désigne les mesures permettant d'ajuster les systèmes naturels et humains aux changements climatiques actuels ou attendus (ex. : protection côtière, agriculture résiliente, gestion des inondations). Madagascar, en tant que pays très vulnérable, bénéficie de financements pour les deux volets.",
                'categorie' => 'Financement climatique',
                'ordre'     => 12,
                'is_active' => true,
            ],
            [
                'question'  => "Comment les projets financés par le GCF sont-ils suivis et évalués ?",
                'reponse'   => "Chaque projet financé par le GCF fait l'objet d'un cadre de résultats avec des indicateurs précis, des rapports annuels de performance, et des évaluations mi-parcours et finales. L'AND assure le suivi national, tandis que les entités accréditées rapportent directement au GCF. Les données de suivi sont publiées sur cette plateforme pour garantir la transparence.",
                'categorie' => 'Financement climatique',
                'ordre'     => 13,
                'is_active' => true,
            ],
            [
                'question'  => "Madagascar est-il éligible aux financements du GCF ?",
                'reponse'   => "Oui, Madagascar est un pays éligible aux financements du GCF en tant que pays en développement particulièrement vulnérable aux changements climatiques. Madagascar figure parmi les pays les moins avancés (PMA) et bénéficie de modalités d'accès simplifiées (accès direct, accès amélioré) pour recevoir des financements destinés à ses projets d'adaptation et d'atténuation.",
                'categorie' => 'Financement climatique',
                'ordre'     => 14,
                'is_active' => true,
            ],
        ];

        // Éviter les doublons si le seeder est rejoué
        foreach ($faqs as $faq) {
            DB::table('faqs')->updateOrInsert(
                ['question' => $faq['question']],
                array_merge($faq, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        $this->command->info('✅ ' . count($faqs) . ' FAQs insérées avec succès.');
    }
}