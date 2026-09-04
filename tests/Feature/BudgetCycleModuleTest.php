<?php

namespace Tests\Feature;

use App\Models\Activite;
use App\Models\Composante;
use App\Models\Financement;
use App\Models\OrganismeContributeur;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class BudgetCycleModuleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $readOnly;
    private Project $project;
    private Financement $financement;
    private Composante $composante;
    private Activite $activite;
    private OrganismeContributeur $bailleur;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin    = User::factory()->create(['role' => 'admin']);
        $this->readOnly = User::factory()->create(['role' => 'utilisateur']);

        $this->project = Project::create(['titre' => 'Projet Test Budgets']);

        $this->financement = Financement::create([
            'project_id'         => $this->project->id,
            'type_financement'   => 'gcf',
            'mode_contribution'  => 'numeraire',
            'source_financement' => 'GCF',
            'budget_approuve'    => 100000,
            'devise'             => 'AR',
            'date_approbation'   => '2026-01-01',
        ]);

        $this->composante = Composante::create([
            'project_id' => $this->project->id,
            'code'       => 'C1',
            'nom'        => 'Composante test',
        ]);

        $this->activite = Activite::create([
            'project_id'    => $this->project->id,
            'composante_id' => $this->composante->id,
            'code'          => 'A1',
            'nom'           => 'Activité test',
        ]);

        $this->bailleur = OrganismeContributeur::create(['designation' => 'Green Climate Fund']);
    }

    private function actingAsAdmin()
    {
        return $this->actingAs($this->admin, 'sanctum');
    }

        public function test_it_creates_a_pledge_with_a_justificatif_and_downloads_it(): void
    {
        $file = UploadedFile::fake()->create('annonce.pdf', 100, 'application/pdf');

        $response = $this->actingAsAdmin()->post("/api/v1/financements/{$this->financement->id}/pledges", [
            'date_annonce'  => '2026-01-15',
            'bailleur_id'   => $this->bailleur->id,
            'montant'       => 50000,
            'devise'        => 'AR',
            'source'        => 'COP30',
            'composante_id' => $this->composante->id,
            'justificatif'  => $file,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('montant', '50000.00');
        $this->assertNotNull($response->json('justificatif_path'));

        $pledgeId = $response->json('id');

        $download = $this->actingAsAdmin()->get("/api/v1/pledges/{$pledgeId}/download");
        $download->assertOk();
    }

        public function test_read_only_role_cannot_create_a_pledge(): void
    {
        $response = $this->actingAs($this->readOnly, 'sanctum')
            ->post("/api/v1/financements/{$this->financement->id}/pledges", [
                'date_annonce' => '2026-01-15',
                'montant'      => 1000,
                'devise'       => 'AR',
            ]);

        $response->assertForbidden();
    }

        public function test_it_validates_required_fields_on_pledge_creation(): void
    {
        $response = $this->actingAsAdmin()->postJson("/api/v1/financements/{$this->financement->id}/pledges", []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['date_annonce', 'montant', 'devise']);
    }

        public function test_it_runs_the_full_cycle_and_computes_correct_totals_and_rates(): void
    {
        $api = $this->actingAsAdmin();

        // 1. Pledge : 100 000
        $api->postJson("/api/v1/financements/{$this->financement->id}/pledges", [
            'date_annonce' => '2026-01-01', 'montant' => 100000, 'devise' => 'AR',
        ])->assertCreated();

        // 2. Mobilisé : 80 000
        $api->postJson("/api/v1/financements/{$this->financement->id}/mobilisations", [
            'organisme_contributeur_id' => $this->bailleur->id,
            'mode_contribution'         => 'numeraire',
            'type_mobilisation'         => 'public',
            'montant'                   => 80000,
            'devise'                    => 'AR',
            'date_contribution'         => '2026-02-01',
        ])->assertCreated();

        // 3. Engagé : 60 000
        $api->postJson("/api/v1/financements/{$this->financement->id}/engagements", [
            'date' => '2026-03-01', 'montant' => 60000, 'devise' => 'AR',
            'reference_accord' => 'ACC-001',
        ])->assertCreated();

        // 4. Approuvé : 60 000
        $api->postJson("/api/v1/financements/{$this->financement->id}/approbations", [
            'date_approbation' => '2026-03-15', 'montant_approuve' => 60000, 'devise' => 'AR',
        ])->assertCreated();

        // 5. Programmé : 60 000
        $api->postJson("/api/v1/financements/{$this->financement->id}/decaissement-plans", [
            'date_prevue' => '2026-04-01', 'montant_prevu' => 60000, 'devise' => 'AR',
            'exercice_budgetaire' => '2026-2027',
        ])->assertCreated();

        // 6. Décaissé : 40 000
        $api->postJson("/api/v1/financements/{$this->financement->id}/decaissements", [
            'date' => '2026-05-01', 'montant' => 40000, 'devise' => 'AR',
            'beneficiaire' => 'Agence exécutrice',
        ])->assertCreated();

        // 7. Dépensé puis audité : 30 000 dépensés, 28 000 audités
        $depenseResp = $api->postJson('/api/v1/depenses', [
            'project_id' => $this->project->id, 'financement_id' => $this->financement->id,
            'designation' => 'Achat matériel', 'note' => 'Note', 'montant' => 30000, 'devise' => 'AR', 'date' => '2026-06-01', 'beneficiaire' => 'Fournisseur X',
        ]);
        $depenseResp->assertCreated();
        $depenseId = $depenseResp->json('id');

        $auditResp = $api->postJson("/api/v1/depenses/{$depenseId}/audit", [
            'montant_audite' => 28000, 'organisme_audit' => 'Cour des comptes', 'date_audit' => '2026-07-01',
        ]);
        $auditResp->assertOk();
        $auditResp->assertJsonPath('statut', 'audite');

        // ── Vérification de l'agrégation ────────────────────────────────────
        $cycle = $api->getJson("/api/v1/financements/{$this->financement->id}/budget-cycle");
        $cycle->assertOk();

        $cycle->assertJsonPath('stages.pledge.total', 100000);
        $cycle->assertJsonPath('stages.mobilise.total', 80000);
        $cycle->assertJsonPath('stages.engage.total', 60000);
        $cycle->assertJsonPath('stages.approuve.total', 60000);
        $cycle->assertJsonPath('stages.programme.total', 60000);
        $cycle->assertJsonPath('stages.decaisse.total', 40000);
        $cycle->assertJsonPath('stages.audite.total', 30000);
        $cycle->assertJsonPath('stages.audite.total_audite', 28000);

        // taux_mobilisation = 80000/100000*100 = 80
        $this->assertEqualsWithDelta(80.0, $cycle->json('rates.taux_mobilisation'), 0.01);
        // taux_engagement = 60000/80000*100 = 75
        $this->assertEqualsWithDelta(75.0, $cycle->json('rates.taux_engagement'), 0.01);
        // taux_decaissement = 40000/60000*100 = 66.7
        $this->assertEqualsWithDelta(66.7, $cycle->json('rates.taux_decaissement'), 0.01);
        // taux_execution = 30000/40000*100 = 75
        $this->assertEqualsWithDelta(75.0, $cycle->json('rates.taux_execution'), 0.01);

        // Vue projet (sans filtre) doit donner les mêmes totaux (un seul financement)
        $projectCycle = $api->getJson("/api/v1/projects/{$this->project->id}/budget-cycle");
        $projectCycle->assertOk();
        $projectCycle->assertJsonPath('stages.pledge.total', 100000);
        $this->assertEqualsWithDelta(80.0, $projectCycle->json('rates.taux_mobilisation'), 0.01);

        // Filtre par composante : rien n'a été rattaché à une composante → tout vide
        $filtered = $api->getJson("/api/v1/projects/{$this->project->id}/budget-cycle?composante_id={$this->composante->id}");
        $filtered->assertOk();
        $filtered->assertJsonPath('stages.pledge.total', 0);
    }

        public function test_it_updates_and_deletes_an_engagement_with_multipart_fallback(): void
    {
        $api = $this->actingAsAdmin();

        $create = $api->postJson("/api/v1/financements/{$this->financement->id}/engagements", [
            'date' => '2026-03-01', 'montant' => 1000, 'devise' => 'AR',
        ]);
        $create->assertCreated();
        $id = $create->json('id');

        // Update via POST fallback (multipart) avec un fichier
        $file = UploadedFile::fake()->create('accord.pdf', 50, 'application/pdf');
        $update = $api->post("/api/v1/engagements/{$id}", [
            '_method'          => 'PUT',
            'date'             => '2026-03-02',
            'montant'          => 1500,
            'devise'           => 'AR',
            'reference_accord' => 'ACC-XYZ',
            'justificatif'     => $file,
        ]);
        $update->assertOk();
        $update->assertJsonPath('montant', '1500.00');
        $this->assertNotNull($update->json('justificatif_path'));

        $api->delete("/api/v1/engagements/{$id}")->assertOk();
    }

        public function test_nature_mobilisation_requires_a_category(): void
    {
        $response = $this->actingAsAdmin()->postJson("/api/v1/financements/{$this->financement->id}/mobilisations", [
            'organisme_contributeur_id' => $this->bailleur->id,
            'mode_contribution'         => 'nature',
            'montant'                   => 5000,
            'devise'                    => 'AR',
            'date_contribution'         => '2026-02-01',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['categorie_contribution_id', 'description']);
    }

    public function test_creating_an_engagement_without_devise_still_works_for_backward_compatibility(): void
    {
        // Simule l'ancien formulaire "Financements" qui n'envoie pas encore
        // de devise : ne doit pas régresser après le module Budgets.
        $response = $this->actingAsAdmin()->postJson("/api/v1/financements/{$this->financement->id}/engagements", [
            'date'        => '2026-03-01',
            'montant'     => 2000,
            'description' => 'Accord simple sans devise',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('devise', 'AR');
        $response->assertJsonPath('montant', '2000.00');
    }

    public function test_creating_a_depense_without_devise_still_works_for_backward_compatibility(): void
    {
        $response = $this->actingAsAdmin()->postJson('/api/v1/depenses', [
            'project_id'   => $this->project->id,
            'designation'  => 'Achat simple',
            'note'         => 'Note',
            'montant'      => 3000,
            'date'         => '2026-06-01',
            'beneficiaire' => 'Fournisseur Y',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('devise', 'AR');
        $response->assertJsonPath('montant', '3000.00');
    }
}
