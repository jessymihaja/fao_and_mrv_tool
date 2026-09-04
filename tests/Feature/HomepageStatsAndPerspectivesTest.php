<?php

namespace Tests\Feature;

use App\Models\Financement;
use App\Models\FinancementContribution;
use App\Models\OrganismeContributeur;
use App\Models\Project;
use App\Models\ProjectPerspective;
use App\Models\Region;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageStatsAndPerspectivesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function actingAsAdmin()
    {
        return $this->actingAs($this->admin, 'sanctum');
    }

        public function test_public_stats_returns_gcf_and_autres_financements_columns_with_last_updated(): void
    {
        $province = Province::create(['nom' => 'Analamanga', 'code' => 'ANA']);
        $region   = Region::create(['nom' => 'Analamanga', 'code' => 'ANA', 'province_id' => $province->id]);

        $project = Project::create([
            'titre' => 'Projet Test Homepage', 'statut' => 'En cours',
            'is_published' => true, 'region_id' => $region->id, 'nombre_beneficiaires' => 1500,
        ]);

        // Financement GCF (compte dans le budget_total existant, pas dans "autres financements")
        Financement::create([
            'project_id' => $project->id, 'type_financement' => 'gcf', 'mode_contribution' => 'numeraire',
            'source_financement' => 'GCF', 'budget_approuve' => 100000, 'devise' => 'AR',
            'date_approbation' => '2026-01-01',
        ]);

        // Cofinancement public + privé (hors GCF)
        $cofinPublic = Financement::create([
            'project_id' => $project->id, 'type_financement' => 'cofinancement_public', 'mode_contribution' => 'numeraire',
            'source_financement' => 'État malgache', 'budget_approuve' => 30000, 'devise' => 'AR',
            'date_approbation' => '2026-01-01',
        ]);
        Financement::create([
            'project_id' => $project->id, 'type_financement' => 'cofinancement_prive', 'mode_contribution' => 'numeraire',
            'source_financement' => 'Entreprise X', 'budget_approuve' => 20000, 'devise' => 'AR',
            'date_approbation' => '2026-01-01',
        ]);

        $bailleur = OrganismeContributeur::create(['designation' => 'PNUD']);
        FinancementContribution::create([
            'financement_id' => $cofinPublic->id, 'organisme_contributeur_id' => $bailleur->id,
            'mode_contribution' => 'nature', 'montant' => 5000, 'devise' => 'AR',
            'date_contribution' => '2026-02-01', 'description' => 'Appui matériel',
        ]);
        FinancementContribution::create([
            'financement_id' => $cofinPublic->id, 'organisme_contributeur_id' => $bailleur->id,
            'mode_contribution' => 'numeraire', 'montant' => 8000, 'devise' => 'AR',
            'date_contribution' => '2026-02-01',
        ]);

        $response = $this->getJson('/api/v1/public/stats');
        $response->assertOk();

        // Colonne 1 : GCF (comportement existant conservé + ajouts)
        $response->assertJsonPath('total_projets', 1);
        $response->assertJsonPath('nombre_beneficiaires', 1500);
        $response->assertJsonPath('nombre_regions', 1);

        // Colonne 2 : Autres financements
        $this->assertEquals(30000.0, $response->json('autres_financements.cofinancement_public'));
        $this->assertEquals(20000.0, $response->json('autres_financements.cofinancement_prive'));
        $this->assertEquals(5000.0, $response->json('autres_financements.contributions_nature'));
        $this->assertEquals(8000.0, $response->json('autres_financements.contributions_numeraire'));
        $this->assertEquals(13000.0, $response->json('autres_financements.partenaires_techniques_financiers'));
        $this->assertEquals(63000.0, $response->json('autres_financements.budget_total_hors_gcf')); // 30000+20000+13000
        $this->assertEquals(1, $response->json('autres_financements.nombre_bailleurs_partenaires'));

        // Dernière mise à jour : présente et récente
        $this->assertNotNull($response->json('derniere_mise_a_jour'));
    }

        public function test_last_updated_reflects_the_most_recent_change_across_tracked_tables(): void
    {
        $project = Project::create(['titre' => 'Ancien projet', 'statut' => 'En cours', 'is_published' => true]);
        $project->forceFill(['updated_at' => now()->subDays(10)])->save();

        $before = $this->getJson('/api/v1/public/stats')->json('derniere_mise_a_jour');

        sleep(1);
        $project->update(['description' => 'Mise à jour récente']);

        $after = $this->getJson('/api/v1/public/stats')->json('derniere_mise_a_jour');

        $this->assertNotEquals($before, $after);
    }

        public function test_it_manages_project_perspectives_and_exposes_them_publicly(): void
    {
        $project = Project::create(['titre' => 'Projet avec perspectives', 'statut' => 'En cours', 'is_published' => true]);
        $api = $this->actingAsAdmin();
        $typeExtensionId = \App\Models\PerspectiveType::where('designation', 'Extension')->firstOrFail()->id;

        $create = $api->postJson("/api/v1/projects/{$project->id}/perspectives", [
            'type_id' => $typeExtensionId,
            'titre' => 'Extension vers le Sud',
            'zone_extension_envisagee' => 'Région Anosy',
            'objectif_long_terme' => 'Couvrir 3 nouvelles communes',
            'impact_futur_attendu' => "Doubler le nombre de bénéficiaires d'ici 2030",
        ]);
        $create->assertCreated();
        $create->assertJsonPath('statut', 'a_l_etude');
        $create->assertJsonPath('type.designation', 'Extension');

        $list = $api->getJson("/api/v1/projects/{$project->id}/perspectives");
        $list->assertOk();
        $this->assertCount(1, $list->json());

        $public = $this->getJson('/api/v1/public/perspectives');
        $public->assertOk();
        $this->assertCount(1, $public->json('data'));

        $stats = $this->getJson('/api/v1/public/stats/perspectives');
        $stats->assertOk();
        $stats->assertJsonPath('projets_extension_envisagee', 1);
        $this->assertCount(1, $stats->json('apercu'));
    }

    public function test_it_can_add_a_custom_perspective_type_inline(): void
    {
        $project = Project::create(['titre' => 'Projet custom type', 'statut' => 'En cours', 'is_published' => true]);
        $api = $this->actingAsAdmin();

        $newType = $api->postJson('/api/v1/perspective-types', ['designation' => 'Réhabilitation']);
        $newType->assertCreated();

        $create = $api->postJson("/api/v1/projects/{$project->id}/perspectives", [
            'type_id' => $newType->json('id'),
            'titre'   => 'Réhabiliter les infrastructures existantes',
        ]);
        $create->assertCreated();
        $create->assertJsonPath('type.designation', 'Réhabilitation');
    }

        public function test_read_only_role_cannot_write_perspectives(): void
    {
        $project = Project::create(['titre' => 'Projet protégé', 'statut' => 'En cours', 'is_published' => true]);
        $readOnly = User::factory()->create(['role' => 'utilisateur']);
        $typeId = \App\Models\PerspectiveType::first()->id;

        $response = $this->actingAs($readOnly, 'sanctum')->postJson("/api/v1/projects/{$project->id}/perspectives", [
            'type_id' => $typeId, 'titre' => 'Non autorisé',
        ]);
        $response->assertForbidden();
    }
}
