<?php

namespace Tests\Feature;

use App\Models\StakeholderCategory;
use App\Models\StakeholderContributionType;
use App\Models\StakeholderRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class StakeholderModuleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $readOnly;
    private StakeholderCategory $categorieOng;
    private StakeholderRole $rolePartenaire;
    private StakeholderContributionType $typeFinanciere;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin    = User::factory()->create(['role' => 'admin']);
        $this->readOnly = User::factory()->create(['role' => 'utilisateur']);

        // Référentiels seedés par la migration
        $this->categorieOng   = StakeholderCategory::where('designation', 'ONG')->firstOrFail();
        $this->rolePartenaire = StakeholderRole::where('designation', 'Partenaire technique')->firstOrFail();
        $this->typeFinanciere = StakeholderContributionType::where('designation', 'Financière')->firstOrFail();
    }

    private function actingAsAdmin()
    {
        return $this->actingAs($this->admin, 'sanctum');
    }

    private function createStakeholder(array $overrides = [])
    {
        $response = $this->actingAsAdmin()->postJson('/api/v1/stakeholders', array_merge([
            'nom'                  => 'ONG Test Environnement',
            'organisation'         => 'ONG Test',
            'categorie_id'         => $this->categorieOng->id,
            'role_id'              => $this->rolePartenaire->id,
            'email'                => 'contact@ong-test.mg',
            'telephone'            => '+261 34 12 345 67',
            'type_contribution_id' => $this->typeFinanciere->id,
            'montant_estimatif'    => 50000,
            'devise'               => 'AR',
            'date_debut'           => '2026-01-01',
        ], $overrides));

        return $response;
    }

    public function test_it_creates_a_stakeholder_with_defaults(): void
    {
        $response = $this->createStakeholder();
        $response->assertCreated();
        $response->assertJsonPath('statut', 'actif');
        $response->assertJsonPath('nom', 'ONG Test Environnement');
        $response->assertJsonPath('categorie.designation', 'ONG');
    }

    public function test_read_only_role_cannot_create_a_stakeholder(): void
    {
        $response = $this->actingAs($this->readOnly, 'sanctum')->postJson('/api/v1/stakeholders', [
            'nom' => 'Non autorisé', 'categorie_id' => $this->categorieOng->id,
        ]);
        $response->assertForbidden();
    }

    public function test_it_validates_required_fields(): void
    {
        $response = $this->actingAsAdmin()->postJson('/api/v1/stakeholders', []);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['nom', 'categorie_id']);
    }

    public function test_it_validates_email_format(): void
    {
        $response = $this->createStakeholder(['email' => 'pas-un-email']);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_it_validates_phone_format(): void
    {
        $response = $this->createStakeholder(['telephone' => 'abc***']);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['telephone']);
    }

    public function test_it_rejects_negative_montant(): void
    {
        $response = $this->createStakeholder(['montant_estimatif' => -100]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['montant_estimatif']);
    }

    public function test_empty_optional_fields_are_treated_as_absent_not_invalid(): void
    {
        // Un formulaire qui envoie des chaînes vides pour les champs optionnels
        // ne doit pas être rejeté (email '', telephone '' ...).
        $response = $this->actingAsAdmin()->postJson('/api/v1/stakeholders', [
            'nom' => 'Sans coordonnées', 'categorie_id' => $this->categorieOng->id,
            'email' => '', 'telephone' => '', 'montant_estimatif' => '',
        ]);
        $response->assertCreated();
        $response->assertJsonPath('email', null);
        $response->assertJsonPath('telephone', null);
    }

    public function test_list_supports_search_and_filters(): void
    {
        $this->createStakeholder(['nom' => 'Ministère Environnement']);
        $this->createStakeholder(['nom' => 'Banque Mondiale', 'organisation' => 'Banque Mondiale', 'categorie_id' => $this->categorieOng->id]);

        $bySearch = $this->actingAsAdmin()->getJson('/api/v1/stakeholders?search=Ministère');
        $bySearch->assertOk();
        $this->assertCount(1, $bySearch->json('data'));

        $byCategorie = $this->actingAsAdmin()->getJson("/api/v1/stakeholders?categorie_id={$this->categorieOng->id}");
        $byCategorie->assertOk();
        $this->assertCount(2, $byCategorie->json('data'));
    }

    public function test_it_updates_a_stakeholder(): void
    {
        $create = $this->createStakeholder();
        $id = $create->json('id');

        $update = $this->actingAsAdmin()->putJson("/api/v1/stakeholders/{$id}", [
            'statut' => 'suspendu',
        ]);
        $update->assertOk();
        $update->assertJsonPath('statut', 'suspendu');
        // Les champs non envoyés restent inchangés
        $update->assertJsonPath('nom', 'ONG Test Environnement');
    }

    public function test_it_deletes_a_stakeholder(): void
    {
        $create = $this->createStakeholder();
        $id = $create->json('id');

        $this->actingAsAdmin()->deleteJson("/api/v1/stakeholders/{$id}")->assertOk();
        $this->actingAsAdmin()->getJson("/api/v1/stakeholders/{$id}")->assertNotFound();
    }

    public function test_it_uploads_lists_and_downloads_a_document(): void
    {
        $id = $this->createStakeholder()->json('id');
        $file = UploadedFile::fake()->create('convention.pdf', 100, 'application/pdf');

        $upload = $this->actingAsAdmin()->post("/api/v1/stakeholders/{$id}/documents", ['file' => $file]);
        $upload->assertCreated();

        $list = $this->actingAsAdmin()->getJson("/api/v1/stakeholders/{$id}/documents");
        $list->assertOk();
        $this->assertCount(1, $list->json());

        $download = $this->actingAsAdmin()->get("/api/v1/stakeholder-documents/{$upload->json('id')}/download");
        $download->assertOk();

        $this->actingAsAdmin()->deleteJson("/api/v1/stakeholder-documents/{$upload->json('id')}")->assertOk();
    }

    public function test_referential_inline_add_works_for_all_three_lists(): void
    {
        $api = $this->actingAsAdmin();

        $cat = $api->postJson('/api/v1/stakeholder-categories', ['designation' => 'Fondation privée']);
        $cat->assertCreated();

        $role = $api->postJson('/api/v1/stakeholder-roles', ['designation' => 'Observateur']);
        $role->assertCreated();

        $type = $api->postJson('/api/v1/stakeholder-contribution-types', ['designation' => 'Logistique']);
        $type->assertCreated();

        $api->getJson('/api/v1/stakeholder-categories')->assertJsonFragment(['designation' => 'Fondation privée']);
        $api->getJson('/api/v1/stakeholder-roles')->assertJsonFragment(['designation' => 'Observateur']);
        $api->getJson('/api/v1/stakeholder-contribution-types')->assertJsonFragment(['designation' => 'Logistique']);
    }
}
