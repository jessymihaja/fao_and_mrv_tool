<?php

namespace App\Http\Controllers;

use App\Models\Status;
use App\Models\Classification;
use App\Models\DomaineIntervention;
use App\Models\EntiteAccreditee;
use App\Models\IndicateurReferentiel;
use App\Models\ContributionCategorie;
use App\Models\OrganismeContributeur;
use Illuminate\Http\Request;

class ReferenceController extends Controller
{
    // ── Statuts ──────────────────────────────────────────────────────────────

    public function indexStatuses()
    {
        return response()->json(Status::orderBy('designation')->get());
    }

    public function storeStatus(Request $request)
    {
        $data = $request->validate(['designation' => 'required|string|max:100|unique:statuses,designation']);
        $item = Status::create($data);
        return response()->json($item, 201);
    }

    public function updateStatus(Request $request, $id)
    {
        $item = Status::findOrFail($id);
        $data = $request->validate(['designation' => 'required|string|max:100|unique:statuses,designation,' . $id . ',id_status']);
        $item->update($data);
        return response()->json($item);
    }

    public function destroyStatus($id)
    {
        Status::findOrFail($id)->delete();
        return response()->json(null, 204);
    }

    // ── Classifications ───────────────────────────────────────────────────────

    public function indexClassifications()
    {
        return response()->json(Classification::orderBy('designation')->get());
    }

    public function storeClassification(Request $request)
    {
        $data = $request->validate(['designation' => 'required|string|max:100|unique:classifications,designation']);
        $item = Classification::create($data);
        return response()->json($item, 201);
    }

    public function updateClassification(Request $request, $id)
    {
        $item = Classification::findOrFail($id);
        $data = $request->validate(['designation' => 'required|string|max:100|unique:classifications,designation,' . $id . ',id_classification']);
        $item->update($data);
        return response()->json($item);
    }

    public function destroyClassification($id)
    {
        Classification::findOrFail($id)->delete();
        return response()->json(null, 204);
    }

    // ── Catégories de contribution en nature ────────────────────────────────

    public function indexContributionCategories()
    {
        return response()->json(ContributionCategorie::orderBy('designation')->get());
    }

    public function storeContributionCategorie(Request $request)
    {
        $data = $request->validate(['designation' => 'required|string|max:100|unique:contribution_categories,designation']);
        $item = ContributionCategorie::create($data);
        return response()->json($item, 201);
    }

    // ── Organismes contributeurs (co-financeurs) ────────────────────────────

    public function indexOrganismesContributeurs()
    {
        return response()->json(OrganismeContributeur::orderBy('designation')->get());
    }

    public function storeOrganismeContributeur(Request $request)
    {
        $data = $request->validate(['designation' => 'required|string|max:150|unique:organismes_contributeurs,designation']);
        $item = OrganismeContributeur::create($data);
        return response()->json($item, 201);
    }

    // ── Devises ───────────────────────────────────────────────────────────
    // Lecture seule : contrairement aux autres référentiels, l'ajout d'une
    // devise est une décision structurante (taux de change, cohérence des
    // rapports consolidés) qui ne passe pas par un simple bouton "+" côté
    // formulaire — cf. audit BDD §É-5.
    public function indexCurrencies()
    {
        return response()->json(\App\Models\Currency::where('actif', true)->orderBy('code')->get());
    }

    // ── Domaines d'intervention ───────────────────────────────────────────────

    public function indexDomaines()
    {
        return response()->json(DomaineIntervention::orderBy('designation')->get());
    }

    public function storeDomaine(Request $request)
    {
        $data = $request->validate(['designation' => 'required|string|max:150|unique:domaine_interventions,designation']);
        $item = DomaineIntervention::create($data);
        return response()->json($item, 201);
    }

    public function updateDomaine(Request $request, $id)
    {
        $item = DomaineIntervention::findOrFail($id);
        $data = $request->validate(['designation' => 'required|string|max:150|unique:domaine_interventions,designation,' . $id . ',id_domaine_intervention']);
        $item->update($data);
        return response()->json($item);
    }

    public function destroyDomaine($id)
    {
        DomaineIntervention::findOrFail($id)->delete();
        return response()->json(null, 204);
    }

    // ── Entités accréditées ───────────────────────────────────────────────────

    public function indexEntites()
    {
        return response()->json(EntiteAccreditee::orderBy('designation')->get());
    }

    public function storeEntite(Request $request)
    {
        $data = $request->validate([
            'designation' => 'required|string|max:200',
            'sigle'       => 'nullable|string|max:50',
        ]);
        $item = EntiteAccreditee::create($data);
        return response()->json($item, 201);
    }

    public function updateEntite(Request $request, $id)
    {
        $item = EntiteAccreditee::findOrFail($id);
        $data = $request->validate([
            'designation' => 'required|string|max:200',
            'sigle'       => 'nullable|string|max:50',
        ]);
        $item->update($data);
        return response()->json($item);
    }

    public function destroyEntite($id)
    {
        EntiteAccreditee::findOrFail($id)->delete();
        return response()->json(null, 204);
    }

    // ── Référentiel indicateurs ───────────────────────────────────────────────

    public function indexIndicateurReferentiels(Request $request)
    {
        return response()->json(
            IndicateurReferentiel::query()
                ->when($request->filled('dimension'), fn ($q) => $q->where('dimension', $request->dimension))
                ->orderBy('dimension')->orderBy('nom')
                ->get()
        );
    }

    public function storeIndicateurReferentiel(Request $request)
    {
        $data = $request->validate([
            'dimension' => 'required|in:financier,physique,adaptation,attenuation',
            'nom'       => 'required|string|max:200',
            'unite'     => 'required|string|max:50',
            'frequence' => 'nullable|string|max:50',
        ]);
        $item = IndicateurReferentiel::create($data);
        return response()->json($item, 201);
    }

    public function updateIndicateurReferentiel(Request $request, $id)
    {
        $item = IndicateurReferentiel::findOrFail($id);
        $data = $request->validate([
            'dimension' => 'required|in:financier,physique,adaptation,attenuation',
            'nom'       => 'required|string|max:200',
            'unite'     => 'required|string|max:50',
            'frequence' => 'nullable|string|max:50',
        ]);
        $item->update($data);
        return response()->json($item);
    }

    public function destroyIndicateurReferentiel($id)
    {
        IndicateurReferentiel::findOrFail($id)->delete();
        return response()->json(null, 204);
    }

    // ── Secteurs (module Idées de projet) ──────────────────────────────────

    public function indexSecteurs()
    {
        return response()->json(\App\Models\Secteur::orderBy('designation')->get());
    }

    public function storeSecteur(Request $request)
    {
        $data = $request->validate(['designation' => 'required|string|max:100|unique:secteurs,designation']);
        $item = \App\Models\Secteur::create($data);
        return response()->json($item, 201);
    }

    public function updateSecteur(Request $request, $id)
    {
        $item = \App\Models\Secteur::findOrFail($id);
        $data = $request->validate(['designation' => 'required|string|max:100|unique:secteurs,designation,' . $id]);
        $item->update($data);
        return response()->json($item);
    }

    public function destroySecteur($id)
    {
        \App\Models\Secteur::findOrFail($id)->delete();
        return response()->json(null, 204);
    }

    // ── Référentiels du module Parties prenantes ────────────────────────────

    public function indexStakeholderCategories()
    {
        return response()->json(\App\Models\StakeholderCategory::orderBy('designation')->get());
    }

    public function storeStakeholderCategory(Request $request)
    {
        $data = $request->validate(['designation' => 'required|string|max:100|unique:stakeholder_categories,designation']);
        $item = \App\Models\StakeholderCategory::create($data);
        return response()->json($item, 201);
    }

    public function updateStakeholderCategory(Request $request, $id)
    {
        $item = \App\Models\StakeholderCategory::findOrFail($id);
        $data = $request->validate(['designation' => 'required|string|max:100|unique:stakeholder_categories,designation,' . $id]);
        $item->update($data);
        return response()->json($item);
    }

    public function destroyStakeholderCategory($id)
    {
        \App\Models\StakeholderCategory::findOrFail($id)->delete();
        return response()->json(null, 204);
    }

    public function indexStakeholderRoles()
    {
        return response()->json(\App\Models\StakeholderRole::orderBy('designation')->get());
    }

    public function storeStakeholderRole(Request $request)
    {
        $data = $request->validate(['designation' => 'required|string|max:100|unique:stakeholder_roles,designation']);
        $item = \App\Models\StakeholderRole::create($data);
        return response()->json($item, 201);
    }

    public function updateStakeholderRole(Request $request, $id)
    {
        $item = \App\Models\StakeholderRole::findOrFail($id);
        $data = $request->validate(['designation' => 'required|string|max:100|unique:stakeholder_roles,designation,' . $id]);
        $item->update($data);
        return response()->json($item);
    }

    public function destroyStakeholderRole($id)
    {
        \App\Models\StakeholderRole::findOrFail($id)->delete();
        return response()->json(null, 204);
    }

    public function indexStakeholderContributionTypes()
    {
        return response()->json(\App\Models\StakeholderContributionType::orderBy('designation')->get());
    }

    public function storeStakeholderContributionType(Request $request)
    {
        $data = $request->validate(['designation' => 'required|string|max:100|unique:stakeholder_contribution_types,designation']);
        $item = \App\Models\StakeholderContributionType::create($data);
        return response()->json($item, 201);
    }

    public function updateStakeholderContributionType(Request $request, $id)
    {
        $item = \App\Models\StakeholderContributionType::findOrFail($id);
        $data = $request->validate(['designation' => 'required|string|max:100|unique:stakeholder_contribution_types,designation,' . $id]);
        $item->update($data);
        return response()->json($item);
    }

    public function destroyStakeholderContributionType($id)
    {
        \App\Models\StakeholderContributionType::findOrFail($id)->delete();
        return response()->json(null, 204);
    }

    // ── Référentiel du module Perspectives des projets ─────────────────────

    public function indexPerspectiveTypes()
    {
        return response()->json(\App\Models\PerspectiveType::orderBy('designation')->get());
    }

    public function storePerspectiveType(Request $request)
    {
        $data = $request->validate(['designation' => 'required|string|max:100|unique:perspective_types,designation']);
        $item = \App\Models\PerspectiveType::create($data);
        return response()->json($item, 201);
    }

    public function updatePerspectiveType(Request $request, $id)
    {
        $item = \App\Models\PerspectiveType::findOrFail($id);
        $data = $request->validate(['designation' => 'required|string|max:100|unique:perspective_types,designation,' . $id]);
        $item->update($data);
        return response()->json($item);
    }

    public function destroyPerspectiveType($id)
    {
        \App\Models\PerspectiveType::findOrFail($id)->delete();
        return response()->json(null, 204);
    }

    // ── Référentiels des modules Résultats / Bénéficiaires ──────────────────

    public function indexResultTypes()
    {
        return response()->json(\App\Models\ResultType::orderBy('designation')->get());
    }

    public function storeResultType(Request $request)
    {
        $data = $request->validate(['designation' => 'required|string|max:150|unique:result_types,designation']);
        $item = \App\Models\ResultType::create($data);
        return response()->json($item, 201);
    }

    public function updateResultType(Request $request, $id)
    {
        $item = \App\Models\ResultType::findOrFail($id);
        $data = $request->validate(['designation' => 'required|string|max:150|unique:result_types,designation,' . $id]);
        $item->update($data);
        return response()->json($item);
    }

    public function destroyResultType($id)
    {
        \App\Models\ResultType::findOrFail($id)->delete();
        return response()->json(null, 204);
    }

    public function indexBeneficiaryTypes()
    {
        return response()->json(\App\Models\BeneficiaryType::orderBy('designation')->get());
    }

    public function storeBeneficiaryType(Request $request)
    {
        $data = $request->validate(['designation' => 'required|string|max:150|unique:beneficiary_types,designation']);
        $item = \App\Models\BeneficiaryType::create($data);
        return response()->json($item, 201);
    }

    public function updateBeneficiaryType(Request $request, $id)
    {
        $item = \App\Models\BeneficiaryType::findOrFail($id);
        $data = $request->validate(['designation' => 'required|string|max:150|unique:beneficiary_types,designation,' . $id]);
        $item->update($data);
        return response()->json($item);
    }

    public function destroyBeneficiaryType($id)
    {
        \App\Models\BeneficiaryType::findOrFail($id)->delete();
        return response()->json(null, 204);
    }

    public function indexBeneficiaryCategories()
    {
        return response()->json(\App\Models\BeneficiaryCategory::orderBy('designation')->get());
    }

    public function storeBeneficiaryCategory(Request $request)
    {
        $data = $request->validate(['designation' => 'required|string|max:150|unique:beneficiary_categories,designation']);
        $item = \App\Models\BeneficiaryCategory::create($data);
        return response()->json($item, 201);
    }

    public function updateBeneficiaryCategory(Request $request, $id)
    {
        $item = \App\Models\BeneficiaryCategory::findOrFail($id);
        $data = $request->validate(['designation' => 'required|string|max:150|unique:beneficiary_categories,designation,' . $id]);
        $item->update($data);
        return response()->json($item);
    }

    public function destroyBeneficiaryCategory($id)
    {
        \App\Models\BeneficiaryCategory::findOrFail($id)->delete();
        return response()->json(null, 204);
    }
}
