<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReferenceTablesSeeder extends Seeder
{
    public function run(): void
    {
        // ── Statuts ──────────────────────────────────────────────────────────
        $statuts = [
            ['designation' => 'Concept Note'],
            ['designation' => 'Funding Proposal'],
            ['designation' => 'En cours'],
            ['designation' => 'Clôturé'],
        ];
        foreach ($statuts as $s) {
            DB::table('statuses')->updateOrInsert(
                ['designation' => $s['designation']],
                array_merge($s, ['created_at' => now(), 'updated_at' => now()])
            );
        }
        $this->command->info('✅ ' . count($statuts) . ' statuts insérés.');

        // ── Classifications ───────────────────────────────────────────────────
        $classifications = [
            ['designation' => 'Adaptation'],
            ['designation' => 'Atténuation'],
            ['designation' => 'Transversal (Cross-cutting)'],
            ['designation' => 'Renforcement de capacités / Readiness'],
        ];
        foreach ($classifications as $c) {
            DB::table('classifications')->updateOrInsert(
                ['designation' => $c['designation']],
                array_merge($c, ['created_at' => now(), 'updated_at' => now()])
            );
        }
        $this->command->info('✅ ' . count($classifications) . ' classifications insérées.');

        // ── Domaines d'intervention ───────────────────────────────────────────
        $domaines = [
            ['designation' => 'Gestion durable des forêts / REDD+'],
            ['designation' => 'Résilience des zones côtières'],
            ['designation' => 'Agriculture climato-intelligente'],
            ['designation' => 'Énergie renouvelable'],
            ['designation' => 'Gestion des ressources en eau'],
            ['designation' => 'Protection de la biodiversité'],
            ['designation' => 'Transport durable'],
            ['designation' => 'Réduction des risques de catastrophes'],
            ['designation' => 'Agriculture'],
            ['designation' => 'Gestion des déchets'],
            ['designation' => 'Urbanisme et aménagement du territoire'],
            ['designation' => 'Santé et bien-être'],
            ['designation' => 'Éducation et sensibilisation'],
            ['designation' => 'Tourisme durable'],
        ];
        foreach ($domaines as $d) {
            DB::table('domaine_interventions')->updateOrInsert(
                ['designation' => $d['designation']],
                array_merge($d, ['created_at' => now(), 'updated_at' => now()])
            );
        }
        $this->command->info('✅ ' . count($domaines) . ' domaines d\'intervention insérés.');

        // ── Entités accréditées ───────────────────────────────────────────────
        $entites = [
            ['designation' => 'Banque Centrale de Madagascar',      'sigle' => 'BCM'],
            ['designation' => 'Banque Africaine de Développement',  'sigle' => 'BAD'],
            ['designation' => 'Programme des Nations Unies pour le Développement', 'sigle' => 'PNUD'],
            ['designation' => 'Fonds International de Développement Agricole', 'sigle' => 'FIDA'],
            ['designation' => 'Agence Française de Développement',  'sigle' => 'AFD'],
            ['designation' => 'Société Financière Internationale',  'sigle' => 'SFI'],
            ['designation' => 'World Wide Fund for Nature',         'sigle' => 'WWF'],
            ['designation' => 'Conservation International',         'sigle' => 'CI'],
        ];
        foreach ($entites as $e) {
            DB::table('entite_accreditees')->updateOrInsert(
                ['designation' => $e['designation']],
                array_merge($e, ['created_at' => now(), 'updated_at' => now()])
            );
        }
        $this->command->info('✅ ' . count($entites) . ' entités accréditées insérées.');
    }
}
