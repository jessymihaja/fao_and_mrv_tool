<?php

namespace Tests\Feature;

use App\Models\OrganismeContributeur;
use App\Models\Project;
use App\Models\ProjectIdea;
use App\Models\Province;
use App\Models\Region;
use App\Models\Secteur;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ProjectIdeaModuleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $readOnly;
    private Region $region;
    private Secteur $secteurAgriculture;
    private Secteur $secteurEau;
    private OrganismeContributeur $gcf;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin    = User::factory()->create(['role' => 'admin']);
        $this->readOnly = User::factory()->create(['role' => 'utilisateur']);

        $province = Province::create(['nom' => 'Analamanga', 'code' => 'ANA']);
        $this->region = Region::create(['nom' => 'Analamanga', 'code' => 'ANA', 'province_id' => $province->id]);

        // Secteurs seedés par la migration (Agriculture, Eau, ...)
        $this->secteurAgriculture = Secteur::where('designation', 'Agriculture')->firstOrFail();
        $this->secteurEau         = Secteur::where('designation', 'Eau')->firstOrFail();

        $this->gcf = OrganismeContributeur::create(['designation' => 'Green Climate Fund']);
    }

    private function actingAsAdmin()
    {
        return $this->actingAs($this->admin, 'sanctum');
    }

    private function createIdea(array $overrides = []): ProjectIdea
    {
        $response = $this->actingAsAdmin()->postJson('/api/v1/project-ideas', array_merge([
            'titre'                 => 'Idée de projet test',
            'lien'                  => 'https://example.org/concept-note',
            'porteur_projet'        => 'Ministère de l\'Environnement',
            'region_id'             => $this->region->id,
            'secteur_ids'           => [$this->secteurAgriculture->id, $this->secteurEau->id],
            'budget_total_estime'   => 500000,
            'devise'                => 'AR',
            'contribution_nationale'=> 100000,
        ], $overrides));

        $response->assertCreated();

        return ProjectIdea::findOrFail($response->json('id'));
    }

    public function test_it_creates_an_idea_with_secteurs_and_logs_initial_status(): void
    {
        $idea = $this->createIdea();

        $this->assertEquals('brouillon', $idea->statut);
        $this->assertEquals('https://example.org/concept-note', $idea->lien);
        $this->assertCount(2, $idea->secteurs);
        $this->assertCount(1, $idea->statusHistory);
        $this->assertEquals('brouillon', $idea->statusHistory->first()->nouveau_statut);
    }

    public function test_lien_field_is_returned_by_the_api_and_must_be_a_valid_url(): void
    {
        $idea = $this->createIdea();

        $show = $this->actingAsAdmin()->getJson("/api/v1/project-ideas/{$idea->id}");
        $show->assertOk();
        $show->assertJsonPath('lien', 'https://example.org/concept-note');

        $invalid = $this->actingAsAdmin()->postJson('/api/v1/project-ideas', [
            'titre' => 'Idée avec lien invalide',
            'lien'  => 'pas-une-url',
        ]);
        $invalid->assertStatus(422);
        $invalid->assertJsonValidationErrors(['lien']);

        // Une chaîne vide (formulaire laissé vide) ne doit pas être rejetée
        // comme une URL invalide — elle doit être traitée comme "absent".
        $empty = $this->actingAsAdmin()->postJson('/api/v1/project-ideas', [
            'titre' => 'Idée sans lien',
            'lien'  => '',
        ]);
        $empty->assertCreated();
        $empty->assertJsonPath('lien', null);
    }

    public function test_read_only_role_cannot_create_an_idea(): void
    {
        $response = $this->actingAs($this->readOnly, 'sanctum')->postJson('/api/v1/project-ideas', [
            'titre' => 'Idée non autorisée',
        ]);

        $response->assertForbidden();
    }

    public function test_it_validates_required_fields(): void
    {
        $response = $this->actingAsAdmin()->postJson('/api/v1/project-ideas', []);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['titre']);
    }

    public function test_list_supports_search_and_status_filter(): void
    {
        $this->createIdea(['titre' => 'Reboisement Analamanga']);
        $this->createIdea(['titre' => 'Adduction eau potable Sofia']);

        $bySearch = $this->actingAsAdmin()->getJson('/api/v1/project-ideas?search=Reboisement');
        $bySearch->assertOk();
        $this->assertCount(1, $bySearch->json('data'));

        $byStatut = $this->actingAsAdmin()->getJson('/api/v1/project-ideas?statut=brouillon');
        $byStatut->assertOk();
        $this->assertCount(2, $byStatut->json('data'));
    }

    public function test_it_updates_an_idea_and_resyncs_secteurs(): void
    {
        $idea = $this->createIdea();

        $response = $this->actingAsAdmin()->putJson("/api/v1/project-ideas/{$idea->id}", [
            'titre'       => 'Titre modifié',
            'secteur_ids' => [$this->secteurEau->id],
        ]);

        $response->assertOk();
        $response->assertJsonPath('titre', 'Titre modifié');
        $this->assertCount(1, $idea->fresh()->secteurs);
    }

    public function test_it_manages_financements_envisages(): void
    {
        $idea = $this->createIdea();

        $create = $this->actingAsAdmin()->postJson("/api/v1/project-ideas/{$idea->id}/financements", [
            'organisme_contributeur_id' => $this->gcf->id,
            'montant_demande'           => 200000,
            'devise'                    => 'AR',
            'type_financement'          => 'don',
            'statut'                    => 'en_preparation',
        ]);
        $create->assertCreated();

        $withAutre = $this->actingAsAdmin()->postJson("/api/v1/project-ideas/{$idea->id}/financements", [
            'bailleur_autre'   => 'Fondation locale',
            'montant_demande'  => 50000,
            'devise'           => 'AR',
            'type_financement' => 'cofinancement',
            'statut'           => 'soumis',
        ]);
        $withAutre->assertCreated();

        $missingBoth = $this->actingAsAdmin()->postJson("/api/v1/project-ideas/{$idea->id}/financements", [
            'devise' => 'AR', 'type_financement' => 'don', 'statut' => 'soumis',
        ]);
        $missingBoth->assertStatus(422);

        $this->assertCount(2, $idea->fresh()->financements);
    }

    public function test_it_uploads_and_downloads_a_document(): void
    {
        $idea = $this->createIdea();
        $file = UploadedFile::fake()->create('concept-note.pdf', 200, 'application/pdf');

        $upload = $this->actingAsAdmin()->post("/api/v1/project-ideas/{$idea->id}/documents", [
            'type' => 'concept_note',
            'file' => $file,
        ]);
        $upload->assertCreated();

        $download = $this->actingAsAdmin()->get("/api/v1/project-idea-documents/{$upload->json('id')}/download");
        $download->assertOk();
    }

    public function test_status_workflow_only_allows_the_next_step(): void
    {
        $idea = $this->createIdea();
        $api  = $this->actingAsAdmin();

        // brouillon -> en_etude directement : refusé (saute soumis)
        $skip = $api->putJson("/api/v1/project-ideas/{$idea->id}/status", ['nouveau_statut' => 'en_etude']);
        $skip->assertStatus(422);

        // brouillon -> soumis : autorisé
        $ok1 = $api->putJson("/api/v1/project-ideas/{$idea->id}/status", ['nouveau_statut' => 'soumis']);
        $ok1->assertOk();
        $ok1->assertJsonPath('statut', 'soumis');

        // -> converti directement via cette route : toujours refusé
        $direct = $api->putJson("/api/v1/project-ideas/{$idea->id}/status", ['nouveau_statut' => 'converti']);
        $direct->assertStatus(422);

        $ok2 = $api->putJson("/api/v1/project-ideas/{$idea->id}/status", ['nouveau_statut' => 'en_etude']);
        $ok2->assertOk();
        $ok3 = $api->putJson("/api/v1/project-ideas/{$idea->id}/status", ['nouveau_statut' => 'approuve']);
        $ok3->assertOk();

        $this->assertCount(4, $idea->fresh()->statusHistory); // création + 3 transitions
    }

    public function test_conversion_requires_approved_status_and_copies_fields(): void
    {
        $idea = $this->createIdea([
            'titre'               => 'Projet de reboisement Nord',
            'description'         => 'Description complète',
            'objectif_general'    => 'Reboiser 500ha',
            'date_debut_estimee'  => '2027-01-01',
            'date_fin_estimee'    => '2029-01-01',
        ]);
        $api = $this->actingAsAdmin();

        // Pas encore approuvé -> refusé
        $tooEarly = $api->postJson("/api/v1/project-ideas/{$idea->id}/convert");
        $tooEarly->assertStatus(422);

        foreach (['soumis', 'en_etude', 'approuve'] as $statut) {
            $api->putJson("/api/v1/project-ideas/{$idea->id}/status", ['nouveau_statut' => $statut])->assertOk();
        }

        $convert = $api->postJson("/api/v1/project-ideas/{$idea->id}/convert");
        $convert->assertCreated();

        $projectId = $convert->json('project.id');
        $this->assertNotNull($projectId);

        $project = Project::findOrFail($projectId);
        $this->assertEquals('Projet de reboisement Nord', $project->titre);
        $this->assertEquals($idea->id, $project->project_idea_id);
        $this->assertNotNull($project->id_projet);
        $this->assertStringStartsWith('GCF-', $project->id_projet);

        $idea->refresh();
        $this->assertEquals('converti', $idea->statut);
        $this->assertEquals($project->id, $idea->converted_project_id);
        $this->assertNotNull($idea->converted_at);

        // Double conversion refusée
        $again = $api->postJson("/api/v1/project-ideas/{$idea->id}/convert");
        $again->assertStatus(422);

        // Une idée convertie ne peut plus être supprimée
        $delete = $api->deleteJson("/api/v1/project-ideas/{$idea->id}");
        $delete->assertStatus(422);
    }

    public function test_dashboard_aggregates_are_correct(): void
    {
        $idea1 = $this->createIdea(['budget_total_estime' => 300000, 'secteur_ids' => [$this->secteurAgriculture->id]]);
        $idea2 = $this->createIdea(['budget_total_estime' => 200000, 'secteur_ids' => [$this->secteurAgriculture->id, $this->secteurEau->id]]);

        $api = $this->actingAsAdmin();
        $api->putJson("/api/v1/project-ideas/{$idea2->id}/status", ['nouveau_statut' => 'soumis'])->assertOk();

        $dashboard = $api->getJson('/api/v1/project-ideas/dashboard');
        $dashboard->assertOk();

        $this->assertEquals(2, $dashboard->json('total'));
        $this->assertEquals(1, $dashboard->json('par_statut.brouillon'));
        $this->assertEquals(1, $dashboard->json('par_statut.soumis'));
        $this->assertEquals(500000, $dashboard->json('budget_total_estime'));

        $parSecteur = collect($dashboard->json('budget_par_secteur'))->keyBy('secteur');
        $this->assertEquals(500000, $parSecteur['Agriculture']['montant']); // idea1 + idea2
        $this->assertEquals(200000, $parSecteur['Eau']['montant']);        // idea2 seulement
    }
}
