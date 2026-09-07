<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\FinancementController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\RapportController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\PublicSettingsController;
use App\Http\Controllers\GeoController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\CmsController;
use App\Http\Controllers\DepenseController;
use App\Http\Controllers\SuiviFinancierController;
use App\Http\Controllers\IndicateurController;
use App\Http\Controllers\RapportNationalController;
use App\Http\Controllers\ReferenceController;
use App\Http\Controllers\ComposanteController;
use App\Http\Controllers\ActiviteController;
use App\Http\Controllers\ResultController;
use App\Http\Controllers\BeneficiaryController;
use App\Http\Controllers\BudgetPledgeController;
use App\Http\Controllers\BudgetApprobationController;
use App\Http\Controllers\BudgetMobilisationController;
use App\Http\Controllers\BudgetCycleController;
use App\Http\Controllers\ProjectIdeaController;
use App\Http\Controllers\ProjectIdeaFinancementController;
use App\Http\Controllers\ProjectIdeaDocumentController;
use App\Http\Controllers\ProjectIdeaStatusController;
use App\Http\Controllers\ProjectIdeaConversionController;
use App\Http\Controllers\ProjectIdeaDashboardController;
use App\Http\Controllers\StakeholderController;
use App\Http\Controllers\StakeholderDocumentController;
use App\Http\Controllers\ProjectPerspectiveController;
use App\Http\Controllers\ProjectGeographicZoneController;

/*
|--------------------------------------------------------------------------
| Matrice des permissions
|--------------------------------------------------------------------------
| super_admin      → tout (bypass automatique dans CheckRole)
| admin            → tout sauf super_admin
| gestionnaire     → saisie + modification données métier uniquement
| gestionnaire_cms → site vitrine uniquement (CMS, chatbot, paramètres
|                    publics) — aucun accès aux données métier ni aux
|                    utilisateurs
| utilisateur      → lecture seule : dashboard stats + projets
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // ── AUTH ─────────────────────────────────────────────────────────────────
    Route::post('/auth/login',  [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get ('/auth/me',     [AuthController::class, 'me'])->middleware('auth:sanctum');

    // ── PUBLIC ────────────────────────────────────────────────────────────────
    Route::get ('/public/settings',      [PublicSettingsController::class, 'index']);
    Route::get ('/public/stats',         [StatsController::class, 'public']);
    Route::get ('/public/stats/perspectives', [StatsController::class, 'perspectives']);
    Route::get ('/public/perspectives',  [ProjectPerspectiveController::class, 'publicIndex']);
    Route::get ('/public/projects',      [ProjectController::class, 'publicIndex']);
    Route::get ('/public/projects/map',  [ProjectController::class, 'mapData']);
    Route::get ('/public/projects/{id}', [ProjectController::class, 'publicShow']);
    Route::get ('/public/faq',           [CmsController::class, 'faq']);
    Route::get ('/public/partners',      [CmsController::class, 'partners']);
    Route::get ('/public/slider',        [CmsController::class, 'slider']);
    Route::post('/public/contact',       [CmsController::class, 'storeContact']);

    Route::middleware('throttle:30,1')->group(function () {
        Route::post('/chatbot/message',         [ChatbotController::class, 'message']);
        Route::get ('/chatbot/settings/public', [ChatbotController::class, 'publicSettings']);
    });

    Route::get('/geo/provinces',               [GeoController::class, 'provinces']);
    Route::get('/geo/regions/{province_id?}',  [GeoController::class, 'regions']);
    Route::get('/geo/districts/{region_id?}',  [GeoController::class, 'districts']);
    Route::get('/geo/communes/{district_id?}', [GeoController::class, 'communes']);
    Route::get('/geo/fokontany/{commune_id?}', [GeoController::class, 'fokontany']);

    Route::get('/documents/{id}/download', [DocumentController::class, 'download'])
        ->middleware('signed')->name('documents.download');
    
     Route::get('/rapports-nationaux/{rapportNational}/export/pdf',   [RapportNationalController::class, 'exportPdf']);
    Route::get('/rapports-nationaux/{rapportNational}/export/excel', [RapportNationalController::class, 'exportExcel']);


    // ══════════════════════════════════════════════════════════════════════════
    // TOUS LES RÔLES CONNECTÉS — lecture seule
    // ══════════════════════════════════════════════════════════════════════════
    Route::middleware(['auth:sanctum', 'role:utilisateur,gestionnaire,admin'])->group(function () {

        // Tables de référence — lecture (pour alimenter les menus déroulants)
        Route::get('/statuses',             [ReferenceController::class, 'indexStatuses']);
        Route::get('/classifications',      [ReferenceController::class, 'indexClassifications']);
        Route::get('/contribution-categories', [ReferenceController::class, 'indexContributionCategories']);
        Route::get('/organismes-contributeurs', [ReferenceController::class, 'indexOrganismesContributeurs']);
        Route::get('/currencies',               [ReferenceController::class, 'indexCurrencies']);
        Route::get('/indicateur-referentiels', [ReferenceController::class, 'indexIndicateurReferentiels']);
        Route::get('/domaine-interventions',[ReferenceController::class, 'indexDomaines']);
        Route::get('/entite-accreditees',   [ReferenceController::class, 'indexEntites']);
        Route::get('/secteurs',             [ReferenceController::class, 'indexSecteurs']);
        Route::get('/perspective-types',    [ReferenceController::class, 'indexPerspectiveTypes']);
        Route::get('/result-types',              [ReferenceController::class, 'indexResultTypes']);
        Route::get('/beneficiary-types',         [ReferenceController::class, 'indexBeneficiaryTypes']);
        Route::get('/beneficiary-categories',    [ReferenceController::class, 'indexBeneficiaryCategories']);
        Route::get('/stakeholder-categories',         [ReferenceController::class, 'indexStakeholderCategories']);
        Route::get('/stakeholder-roles',              [ReferenceController::class, 'indexStakeholderRoles']);
        Route::get('/stakeholder-contribution-types', [ReferenceController::class, 'indexStakeholderContributionTypes']);

        // Stats
        Route::get('/stats/global',             [StatsController::class, 'global']);
        Route::get('/stats/projects-by-status', [StatsController::class, 'projectsByStatus']);
        Route::get('/stats/budget-by-year',     [StatsController::class, 'budgetByYear']);
        Route::get('/stats/projects-by-region', [StatsController::class, 'projectsByRegion']);

        // Projets — lecture
        Route::get('/projects',      [ProjectController::class, 'index']);
        Route::get('/projects/{id}', [ProjectController::class, 'show']);
        Route::get('/projects/{id}/financements', [FinancementController::class, 'byProject']);
        Route::get('/projects/{id}/documents',    [DocumentController::class,   'byProject']);
        Route::get('/projects/{id}/depenses',     [SuiviFinancierController::class, 'projectDepenses']);
        Route::get('/projects/{id}/perspectives', [ProjectPerspectiveController::class, 'index']);
        Route::get('/projects/{id}/geographical-zones', [ProjectGeographicZoneController::class, 'index']);
        Route::get('/geo/zones/search', [ProjectGeographicZoneController::class, 'search']);

        // Financements — lecture
        Route::get('/financements/totaux', [FinancementController::class, 'totaux']);
        Route::get('/financements',        [FinancementController::class, 'index']);
        Route::get('/financements/{id}',   [FinancementController::class, 'show']);

        // Suivi financier — lecture
        Route::get('/financements/{id}/engagements',        [SuiviFinancierController::class, 'engagements']);
        Route::get('/financements/{id}/decaissement-plans', [SuiviFinancierController::class, 'decaissementPlans']);
        Route::get('/financements/{id}/decaissements',      [SuiviFinancierController::class, 'decaissements']);
        Route::get('/engagements/{id}/download',            [SuiviFinancierController::class, 'downloadEngagement']);
        Route::get('/decaissement-plans/{id}/download',     [SuiviFinancierController::class, 'downloadPlan']);
        Route::get('/decaissements/{id}/download',          [SuiviFinancierController::class, 'downloadDecaissement']);

        // ── MODULE BUDGETS — cycle de vie du financement (lecture) ─────────────
        Route::get('/financements/{id}/pledges',       [BudgetPledgeController::class, 'index']);
        Route::get('/pledges/{id}',                     [BudgetPledgeController::class, 'show']);
        Route::get('/pledges/{id}/download',            [BudgetPledgeController::class, 'download']);

        Route::get('/financements/{id}/mobilisations', [BudgetMobilisationController::class, 'index']);
        Route::get('/mobilisations/{id}',               [BudgetMobilisationController::class, 'show']);
        Route::get('/mobilisations/{id}/download',      [BudgetMobilisationController::class, 'download']);

        Route::get('/financements/{id}/approbations',  [BudgetApprobationController::class, 'index']);
        Route::get('/approbations/{id}',                [BudgetApprobationController::class, 'show']);
        Route::get('/approbations/{id}/download',       [BudgetApprobationController::class, 'download']);

        Route::get('/financements/{id}/budget-cycle',  [BudgetCycleController::class, 'forFinancement']);
        Route::get('/projects/{id}/budget-cycle',       [BudgetCycleController::class, 'forProject']);

        // Documents — lecture
        Route::get('/documents',                 [DocumentController::class, 'index']);
        Route::get('/documents/{id}',            [DocumentController::class, 'show']);
        Route::get('/documents/{id}/signed-url', [DocumentController::class, 'signedUrl']);

        // Dépenses — lecture
        Route::get('/depenses',      [DepenseController::class, 'index']);
        Route::get('/depenses/{id}', [DepenseController::class, 'show']);
        Route::get('/projects/{id}/depenses-summary', [DepenseController::class, 'summaryForProject']);

        // Activity logs — lecture
        Route::get('/activity-logs', [ActivityLogController::class, 'index']);

        // Rapports — lecture
        Route::get('/rapports/{id}',              [RapportController::class, 'show']);
        Route::get('/rapports/{id}/export/pdf',   [RapportController::class, 'exportPdf']);
        Route::get('/rapports/{id}/export/excel', [RapportController::class, 'exportExcel']);
        Route::get('/projects/{id}/rapports',     [RapportController::class, 'byProject']);

        // ── INDICATEURS — lecture ─────────────────────────────────────────────
        // KPIs en premier pour éviter la collision avec le wildcard {indicateur}
        Route::get('/indicateurs/kpis',                            [IndicateurController::class, 'kpis']);
        Route::get('/indicateurs',                                 [IndicateurController::class, 'index']);
        Route::get('/indicateurs/{indicateur}',                    [IndicateurController::class, 'show']);
        Route::get('/projects/{project}/indicateurs',              [IndicateurController::class, 'byProject']);
        Route::get('/projects/{project}/indicateurs-all',          [IndicateurController::class, 'allForProject']);
        Route::get('/composantes/{composante}/indicateurs',        [IndicateurController::class, 'byComposante']);
        Route::get('/activites/{activite}/indicateurs',            [IndicateurController::class, 'byActivite']);

        // ── COMPOSANTES — lecture ─────────────────────────────────────────────
        Route::get('/projects/{project}/composantes',        [ComposanteController::class, 'byProject']);
        Route::get('/composantes/{id}',                      [ComposanteController::class, 'show']);
        Route::get('/projects/{project}/activites',           [ActiviteController::class, 'byProject']);
        Route::get('/projects/{project}/activites-all',       [ActiviteController::class, 'allForProject']);
        Route::get('/composantes/{composante}/activites',    [ActiviteController::class, 'byComposante']);
        Route::get('/activites/{id}',                         [ActiviteController::class, 'show']);
        Route::get('/composantes/{composante}/documents',    [DocumentController::class, 'byComposante']);

        // ── RÉSULTATS DU PROJET — lecture ───────────────────────────────────
        Route::get('/projects/{project}/results', [ResultController::class, 'byProject']);
        Route::get('/results/{id}',               [ResultController::class, 'show']);

        // ── BÉNÉFICIAIRES DU PROJET — lecture ───────────────────────────────
        Route::get('/projects/{project}/beneficiaries', [BeneficiaryController::class, 'byProject']);
        Route::get('/beneficiaries/{id}',               [BeneficiaryController::class, 'show']);

        // ── RAPPORTS NATIONAUX — lecture ──────────────────────────────────────
        Route::get('/rapports-nationaux',                          [RapportNationalController::class, 'index']);
        Route::get('/rapports-nationaux/{rapportNational}',        [RapportNationalController::class, 'show']);
       
        // ── MODULE IDÉES DE PROJET — lecture ────────────────────────────────────
        Route::get('/project-ideas/dashboard',        [ProjectIdeaDashboardController::class, 'index']);
        Route::get('/project-ideas/export-data',      [ProjectIdeaController::class, 'exportData']);
        Route::get('/project-ideas',                  [ProjectIdeaController::class, 'index']);
        Route::get('/project-ideas/{id}',              [ProjectIdeaController::class, 'show']);
        Route::get('/project-ideas/{id}/financements', [ProjectIdeaFinancementController::class, 'index']);
        Route::get('/project-ideas/{id}/documents',    [ProjectIdeaDocumentController::class, 'index']);
        Route::get('/project-idea-documents/{id}/download', [ProjectIdeaDocumentController::class, 'download']);
    });

    // ── MODULE PARTIES PRENANTES — lecture ──────────────────────────────────
    Route::middleware(['auth:sanctum', 'role:utilisateur,gestionnaire,admin'])->group(function () {
        Route::get('/stakeholders/export-data',   [StakeholderController::class, 'exportData']);
        Route::get('/stakeholders',               [StakeholderController::class, 'index']);
        Route::get('/stakeholders/{id}',          [StakeholderController::class, 'show']);
        Route::get('/stakeholders/{id}/documents', [StakeholderDocumentController::class, 'index']);
        Route::get('/stakeholder-documents/{id}/download', [StakeholderDocumentController::class, 'download']);
    });

    // ══════════════════════════════════════════════════════════════════════════
    // GESTIONNAIRE + ADMIN — saisie et modification données métier
    // ══════════════════════════════════════════════════════════════════════════
    Route::middleware(['auth:sanctum', 'role:gestionnaire,admin'])->group(function () {

        // Tables de référence — écriture (ajout depuis les menus déroulants)
        Route::post  ('/statuses',                [ReferenceController::class, 'storeStatus']);
        Route::put   ('/statuses/{id}',           [ReferenceController::class, 'updateStatus']);
        Route::delete('/statuses/{id}',           [ReferenceController::class, 'destroyStatus']);

        Route::post  ('/classifications',         [ReferenceController::class, 'storeClassification']);
        Route::put   ('/classifications/{id}',    [ReferenceController::class, 'updateClassification']);
        Route::delete('/classifications/{id}',    [ReferenceController::class, 'destroyClassification']);

        Route::post  ('/contribution-categories', [ReferenceController::class, 'storeContributionCategorie']);
        Route::post  ('/organismes-contributeurs', [ReferenceController::class, 'storeOrganismeContributeur']);

        Route::post  ('/indicateur-referentiels',         [ReferenceController::class, 'storeIndicateurReferentiel']);
        Route::put   ('/indicateur-referentiels/{id}',    [ReferenceController::class, 'updateIndicateurReferentiel']);
        Route::delete('/indicateur-referentiels/{id}',    [ReferenceController::class, 'destroyIndicateurReferentiel']);

        Route::post  ('/domaine-interventions',       [ReferenceController::class, 'storeDomaine']);
        Route::put   ('/domaine-interventions/{id}',  [ReferenceController::class, 'updateDomaine']);
        Route::delete('/domaine-interventions/{id}',  [ReferenceController::class, 'destroyDomaine']);

        Route::post  ('/entite-accreditees',          [ReferenceController::class, 'storeEntite']);
        Route::put   ('/entite-accreditees/{id}',     [ReferenceController::class, 'updateEntite']);
        Route::delete('/entite-accreditees/{id}',     [ReferenceController::class, 'destroyEntite']);

        Route::post  ('/secteurs',         [ReferenceController::class, 'storeSecteur']);
        Route::put   ('/secteurs/{id}',    [ReferenceController::class, 'updateSecteur']);
        Route::delete('/secteurs/{id}',    [ReferenceController::class, 'destroySecteur']);

        Route::post  ('/perspective-types',      [ReferenceController::class, 'storePerspectiveType']);
        Route::put   ('/perspective-types/{id}', [ReferenceController::class, 'updatePerspectiveType']);
        Route::delete('/perspective-types/{id}', [ReferenceController::class, 'destroyPerspectiveType']);

        Route::post  ('/result-types',           [ReferenceController::class, 'storeResultType']);
        Route::put   ('/result-types/{id}',      [ReferenceController::class, 'updateResultType']);
        Route::delete('/result-types/{id}',      [ReferenceController::class, 'destroyResultType']);

        Route::post  ('/beneficiary-types',      [ReferenceController::class, 'storeBeneficiaryType']);
        Route::put   ('/beneficiary-types/{id}', [ReferenceController::class, 'updateBeneficiaryType']);
        Route::delete('/beneficiary-types/{id}', [ReferenceController::class, 'destroyBeneficiaryType']);

        Route::post  ('/beneficiary-categories',      [ReferenceController::class, 'storeBeneficiaryCategory']);
        Route::put   ('/beneficiary-categories/{id}', [ReferenceController::class, 'updateBeneficiaryCategory']);
        Route::delete('/beneficiary-categories/{id}', [ReferenceController::class, 'destroyBeneficiaryCategory']);

        Route::post  ('/stakeholder-categories',         [ReferenceController::class, 'storeStakeholderCategory']);
        Route::put   ('/stakeholder-categories/{id}',    [ReferenceController::class, 'updateStakeholderCategory']);
        Route::delete('/stakeholder-categories/{id}',    [ReferenceController::class, 'destroyStakeholderCategory']);

        Route::post  ('/stakeholder-roles',       [ReferenceController::class, 'storeStakeholderRole']);
        Route::put   ('/stakeholder-roles/{id}',  [ReferenceController::class, 'updateStakeholderRole']);
        Route::delete('/stakeholder-roles/{id}',  [ReferenceController::class, 'destroyStakeholderRole']);

        Route::post  ('/stakeholder-contribution-types',      [ReferenceController::class, 'storeStakeholderContributionType']);
        Route::put   ('/stakeholder-contribution-types/{id}', [ReferenceController::class, 'updateStakeholderContributionType']);
        Route::delete('/stakeholder-contribution-types/{id}', [ReferenceController::class, 'destroyStakeholderContributionType']);


        // Projets — écriture
        Route::post  ('/projects',      [ProjectController::class, 'store']);
        Route::put   ('/projects/{id}', [ProjectController::class, 'update']);
        Route::delete('/projects/{id}', [ProjectController::class, 'destroy']);
        Route::post  ('/projects/{id}/wizard-step', [ProjectController::class, 'advanceWizardStep']);

        // ── ZONES GÉOGRAPHIQUES MULTIPLES — écriture ────────────────────────
        Route::post  ('/projects/{id}/geographical-zones',           [ProjectGeographicZoneController::class, 'store']);
        Route::delete('/projects/{id}/geographical-zones/{zoneId}',  [ProjectGeographicZoneController::class, 'destroy']);

        // ── COMPOSANTES — écriture ─────────────────────────────────────────────
        Route::post  ('/projects/{project}/composantes', [ComposanteController::class, 'store']);
        Route::put   ('/composantes/{id}',                [ComposanteController::class, 'update']);
        Route::delete('/composantes/{id}',                [ComposanteController::class, 'destroy']);

        // ── ACTIVITÉS — écriture ───────────────────────────────────────────────
        Route::post  ('/composantes/{composante}/activites', [ActiviteController::class, 'store']);
        Route::post  ('/projects/{project}/activites',        [ActiviteController::class, 'storeForProject']);
        Route::put   ('/activites/{id}',                       [ActiviteController::class, 'update']);
        Route::post  ('/activites/{id}',                       [ActiviteController::class, 'update']); // multipart fallback (pièces jointes)
        Route::delete('/activites/{id}',                       [ActiviteController::class, 'destroy']);
        Route::delete('/activites/{activite}/pieces-jointes/{piece}', [ActiviteController::class, 'destroyPieceJointe']);

        // ── RÉSULTATS DU PROJET — écriture ──────────────────────────────────
        Route::post  ('/projects/{project}/results', [ResultController::class, 'store']);
        Route::put   ('/results/{id}',                [ResultController::class, 'update']);
        Route::post  ('/results/{id}',                [ResultController::class, 'update']); // multipart fallback (pièces jointes)
        Route::delete('/results/{id}',                [ResultController::class, 'destroy']);
        Route::delete('/results/{result}/pieces-jointes/{piece}', [ResultController::class, 'destroyPieceJointe']);

        // ── BÉNÉFICIAIRES DU PROJET — écriture ──────────────────────────────
        Route::post  ('/projects/{project}/beneficiaries', [BeneficiaryController::class, 'store']);
        Route::put   ('/beneficiaries/{id}',                [BeneficiaryController::class, 'update']);
        Route::delete('/beneficiaries/{id}',                [BeneficiaryController::class, 'destroy']);

        // Financements — écriture
        Route::post  ('/financements',      [FinancementController::class, 'store']);
        Route::put   ('/financements/{id}', [FinancementController::class, 'update']);
        Route::delete('/financements/{id}', [FinancementController::class, 'destroy']);

        // Dépenses — écriture
        Route::post  ('/depenses',                   [DepenseController::class, 'store']);
        Route::put   ('/depenses/{id}',              [DepenseController::class, 'update']);
        Route::post  ('/depenses/{id}',              [DepenseController::class, 'update']); // multipart fallback (justificatif)
        Route::delete('/depenses/{id}',              [DepenseController::class, 'destroy']);
        Route::get   ('/depenses/{id}/download',     [DepenseController::class, 'downloadJustification']);
        Route::put   ('/depenses/{id}/audit',            [DepenseController::class, 'audit']);
        Route::post  ('/depenses/{id}/audit',            [DepenseController::class, 'audit']); // multipart fallback (rapport)
        Route::get   ('/depenses/{id}/rapport-audit/download', [DepenseController::class, 'downloadRapportAudit']);

        // Suivi financier — écriture
        Route::post  ('/financements/{id}/engagements',        [SuiviFinancierController::class, 'storeEngagement']);
        Route::put   ('/engagements/{id}',                     [SuiviFinancierController::class, 'updateEngagement']);
        Route::post  ('/engagements/{id}',                     [SuiviFinancierController::class, 'updateEngagement']); // multipart fallback
        Route::delete('/engagements/{id}',                     [SuiviFinancierController::class, 'destroyEngagement']);

        Route::post  ('/financements/{id}/decaissement-plans', [SuiviFinancierController::class, 'storePlan']);
        Route::put   ('/decaissement-plans/{id}',              [SuiviFinancierController::class, 'updatePlan']);
        Route::post  ('/decaissement-plans/{id}',              [SuiviFinancierController::class, 'updatePlan']); // multipart fallback
        Route::delete('/decaissement-plans/{id}',              [SuiviFinancierController::class, 'destroyPlan']);

        Route::post  ('/financements/{id}/decaissements',      [SuiviFinancierController::class, 'storeDecaissement']);
        Route::put   ('/decaissements/{id}',                   [SuiviFinancierController::class, 'updateDecaissement']);
        Route::post  ('/decaissements/{id}',                   [SuiviFinancierController::class, 'updateDecaissement']); // multipart fallback
        Route::delete('/decaissements/{id}',                   [SuiviFinancierController::class, 'destroyDecaissement']);

        // ── MODULE BUDGETS — cycle de vie du financement (écriture) ────────────
        Route::post  ('/financements/{id}/pledges', [BudgetPledgeController::class, 'store']);
        Route::put   ('/pledges/{id}',               [BudgetPledgeController::class, 'update']);
        Route::post  ('/pledges/{id}',               [BudgetPledgeController::class, 'update']); // multipart fallback
        Route::delete('/pledges/{id}',               [BudgetPledgeController::class, 'destroy']);

        Route::post  ('/financements/{id}/mobilisations', [BudgetMobilisationController::class, 'store']);
        Route::put   ('/mobilisations/{id}',               [BudgetMobilisationController::class, 'update']);
        Route::post  ('/mobilisations/{id}',               [BudgetMobilisationController::class, 'update']); // multipart fallback
        Route::delete('/mobilisations/{id}',               [BudgetMobilisationController::class, 'destroy']);

        Route::post  ('/financements/{id}/approbations', [BudgetApprobationController::class, 'store']);
        Route::put   ('/approbations/{id}',               [BudgetApprobationController::class, 'update']);
        Route::post  ('/approbations/{id}',               [BudgetApprobationController::class, 'update']); // multipart fallback
        Route::delete('/approbations/{id}',               [BudgetApprobationController::class, 'destroy']);

        // Documents — écriture
        Route::post  ('/documents',      [DocumentController::class, 'store']);
        Route::delete('/documents/{id}', [DocumentController::class, 'destroy']);

        // ── INDICATEURS — écriture (gestionnaire + admin) ─────────────────────
        Route::post  ('/indicateurs',                                          [IndicateurController::class, 'store']);
        Route::put   ('/indicateurs/{indicateur}',                             [IndicateurController::class, 'update']);
        Route::post  ('/indicateurs/{indicateur}',                             [IndicateurController::class, 'update']); // multipart fallback
        Route::delete('/indicateurs/{indicateur}',                             [IndicateurController::class, 'destroy']);
        Route::delete('/indicateurs/{indicateur}/justificatifs/{justificatif}',[IndicateurController::class, 'destroyJustificatif']);

        // ── RAPPORTS NATIONAUX — écriture (gestionnaire + admin) ─────────────
        Route::post('/rapports-nationaux',                          [RapportNationalController::class, 'store']);
        Route::post('/rapports-nationaux/{rapportNational}/generate',[RapportNationalController::class, 'generate']);

        // ── MODULE IDÉES DE PROJET — écriture ───────────────────────────────────
        Route::post  ('/project-ideas',      [ProjectIdeaController::class, 'store']);
        Route::put   ('/project-ideas/{id}', [ProjectIdeaController::class, 'update']);
        Route::delete('/project-ideas/{id}', [ProjectIdeaController::class, 'destroy']);
        Route::put   ('/project-ideas/{id}/status', [ProjectIdeaStatusController::class, 'update']);
        Route::post  ('/project-ideas/{id}/convert', [ProjectIdeaConversionController::class, 'convert']);

        Route::post  ('/project-ideas/{id}/financements', [ProjectIdeaFinancementController::class, 'store']);
        Route::put   ('/project-idea-financements/{id}',  [ProjectIdeaFinancementController::class, 'update']);
        Route::delete('/project-idea-financements/{id}',  [ProjectIdeaFinancementController::class, 'destroy']);

        Route::post  ('/project-ideas/{id}/documents',      [ProjectIdeaDocumentController::class, 'store']);
        Route::delete('/project-idea-documents/{id}',       [ProjectIdeaDocumentController::class, 'destroy']);
    });

    // ── MODULE PARTIES PRENANTES — écriture ─────────────────────────────────
    Route::middleware(['auth:sanctum', 'role:gestionnaire,admin'])->group(function () {
        Route::post  ('/stakeholders',      [StakeholderController::class, 'store']);
        Route::put   ('/stakeholders/{id}', [StakeholderController::class, 'update']);
        Route::delete('/stakeholders/{id}', [StakeholderController::class, 'destroy']);

        Route::post  ('/stakeholders/{id}/documents', [StakeholderDocumentController::class, 'store']);
        Route::delete('/stakeholder-documents/{id}',  [StakeholderDocumentController::class, 'destroy']);
    });

    // ── PERSPECTIVES DES PROJETS — écriture ─────────────────────────────────
    Route::middleware(['auth:sanctum', 'role:gestionnaire,admin'])->group(function () {
        Route::post  ('/projects/{id}/perspectives', [ProjectPerspectiveController::class, 'store']);
        Route::put   ('/project-perspectives/{id}',  [ProjectPerspectiveController::class, 'update']);
        Route::delete('/project-perspectives/{id}',  [ProjectPerspectiveController::class, 'destroy']);
    });

    // ══════════════════════════════════════════════════════════════════════════
    // SITE VITRINE (CMS) — admin ET gestionnaire_cms (rôle dédié, limité à ce
    // périmètre : aucun accès aux données métier ni à la gestion des
    // utilisateurs, voir plus bas)
    // ══════════════════════════════════════════════════════════════════════════
    Route::middleware(['auth:sanctum', 'role:admin,gestionnaire_cms'])->group(function () {

        // CMS
        Route::prefix('cms')->group(function () {
            Route::get   ('/faq',                [CmsController::class, 'adminFaq']);
            Route::post  ('/faq',                [CmsController::class, 'storeFaq']);
            Route::put   ('/faq/{id}',           [CmsController::class, 'updateFaq']);
            Route::delete('/faq/{id}',           [CmsController::class, 'destroyFaq']);

            Route::get   ('/partners',           [CmsController::class, 'adminPartners']);
            Route::post  ('/partners',           [CmsController::class, 'storePartner']);
            Route::post  ('/partners/{id}',      [CmsController::class, 'updatePartner']);
            Route::put   ('/partners/{id}',      [CmsController::class, 'updatePartner']);
            Route::delete('/partners/{id}',      [CmsController::class, 'destroyPartner']);

            Route::get   ('/contacts',           [CmsController::class, 'contacts']);
            Route::delete('/contacts/{id}',      [CmsController::class, 'destroyContact']);
            Route::put   ('/contacts/{id}/read', [CmsController::class, 'markAsRead']);

            Route::get   ('/slider',             [CmsController::class, 'adminSlider']);
            Route::post  ('/slider',             [CmsController::class, 'storeSlider']);
            Route::post  ('/slider/{id}',        [CmsController::class, 'updateSlider']);
            Route::put   ('/slider/{id}',        [CmsController::class, 'updateSlider']);
            Route::delete('/slider/{id}',        [CmsController::class, 'destroySlider']);
        });

        // Chatbot (widget public du site vitrine)
        Route::get   ('/chatbot/settings',        [ChatbotController::class, 'settings']);
        Route::put   ('/chatbot/settings',        [ChatbotController::class, 'updateSettings']);
        Route::get   ('/chatbot/knowledge',       [ChatbotController::class, 'knowledge']);
        Route::post  ('/chatbot/knowledge',       [ChatbotController::class, 'storeKnowledge']);
        Route::put   ('/chatbot/knowledge/{id}',  [ChatbotController::class, 'updateKnowledge']);
        Route::delete('/chatbot/knowledge/{id}',  [ChatbotController::class, 'destroyKnowledge']);

        // Paramètres publics (contenu affiché sur le site vitrine)
        Route::get ('/settings',        [PublicSettingsController::class, 'adminIndex']);
        Route::put ('/settings/{key}',  [PublicSettingsController::class, 'update']);
        Route::post('/settings/stats',  [StatsController::class, 'updateManual']);
    });

    // ══════════════════════════════════════════════════════════════════════════
    // ADMIN UNIQUEMENT — gestion des utilisateurs, données métier sensibles
    // ══════════════════════════════════════════════════════════════════════════
    Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {

        // Users
        Route::get   ('/users',             [UserController::class, 'index']);
        Route::post  ('/users',             [UserController::class, 'store']);
        Route::get   ('/users/{id}',        [UserController::class, 'show']);
        Route::put   ('/users/{id}',        [UserController::class, 'update']);
        Route::delete('/users/{id}',        [UserController::class, 'destroy']);
        Route::put   ('/users/{id}/role',   [UserController::class, 'updateRole']);
        Route::put   ('/users/{id}/toggle', [UserController::class, 'toggle']);

        // ── RAPPORTS NATIONAUX — suppression (admin uniquement) ───────────────
        Route::delete('/rapports-nationaux/{rapportNational}', [RapportNationalController::class, 'destroy']);
    });
});