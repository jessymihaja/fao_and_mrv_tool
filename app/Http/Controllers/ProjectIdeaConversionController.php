<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProjectResource;
use App\Models\DomaineIntervention;
use App\Models\Document;
use App\Models\Project;
use App\Models\ProjectIdea;
use App\Models\ProjectIdeaStatusHistory;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Conversion d'une idée de projet en Projet officiel.
 *
 * Champs copiés automatiquement : informations générales, localisation,
 * secteurs, objectifs, documents. Le budget prévisionnel et les
 * financements envisagés de l'idée ne sont PAS transformés en
 * enregistrements Financement : ce sont des intentions ("envisagé"), pas
 * des financements réels/signés, et le modèle Financement applique des
 * règles métier GCF strictes (type, mode de contribution, approbation...)
 * qui n'ont pas de sens pour de simples intentions budgétaires. Cette
 * information reste néanmoins entièrement accessible : l'idée d'origine
 * est conservée (statut "Converti en Projet") et reste consultable depuis
 * le projet via project_idea_id — rien n'est perdu, c'est au gestionnaire
 * de créer les Financements réels une fois les discussions officiellement
 * engagées.
 */
class ProjectIdeaConversionController extends Controller
{
    public function __construct(
        private readonly ActivityLogService $logService
    ) {}

    public function convert(Request $request, int $id): JsonResponse
    {
        $idea = ProjectIdea::with(['secteurs', 'documents'])->findOrFail($id);

        if ($idea->statut !== 'approuve') {
            return response()->json([
                'message' => "Seule une idée au statut « Approuvé » peut être convertie en projet (statut actuel : {$idea->statut}).",
            ], 422);
        }
        if ($idea->converted_project_id) {
            return response()->json(['message' => 'Cette idée a déjà été convertie en projet.'], 422);
        }

        $project = DB::transaction(function () use ($idea, $request) {
            $objectifsParts = array_filter([$idea->objectif_general, $idea->objectifs_specifiques, $idea->resultats_attendus]);

            $project = Project::create([
                'id_projet'                => Project::generateIdProjet(),
                'titre'                    => $idea->titre,
                'description'              => $idea->description,
                'objectifs'                => implode("\n\n", $objectifsParts) ?: null,
                'date_debut'               => $idea->date_debut_estimee,
                'date_fin'                 => $idea->date_fin_estimee,

                'latitude'                 => $idea->latitude,
                'longitude'                => $idea->longitude,
                'province_id'              => $idea->province_id,
                'region_id'                => $idea->region_id,
                'district_id'              => $idea->district_id,
                'commune_id'               => $idea->commune_id,
                'fokontany_id'             => $idea->fokontany_id,
                'zone_description'         => $idea->zone_description,
                'geo_address'              => $idea->geo_address,

                'is_published'             => false,
                'project_idea_id'          => $idea->id,
            ]);

            // Secteurs (Secteur -> DomaineIntervention, find-or-create par désignation
            // pour préserver l'intitulé même si le référentiel diffère)
            $domaineIds = $idea->secteurs->map(function ($secteur) {
                $domaine = DomaineIntervention::firstOrCreate(['designation' => $secteur->designation]);
                return $domaine->id_domaine_intervention;
            });
            if ($domaineIds->isNotEmpty()) {
                $project->domainesIntervention()->sync($domaineIds);
            }

            // Documents (copie physique du fichier, disque 'local' idée -> disque 'public' projet)
            foreach ($idea->documents as $doc) {
                if (! Storage::disk('local')->exists($doc->file_path)) {
                    continue;
                }
                $newPath = 'documents/' . uniqid('idea_') . '_' . $doc->file_name;
                Storage::disk('public')->put($newPath, Storage::disk('local')->get($doc->file_path));

                Document::create([
                    'titre'            => $doc->libelle ?: $doc->file_name,
                    'type'             => $doc->type,
                    'fichier'          => $newPath,
                    'fichier_original' => $doc->file_name,
                    'taille'           => $doc->size,
                    'mime_type'        => $doc->mime_type,
                    'project_id'       => $project->id,
                    'description'      => "Repris de l'idée de projet #{$idea->id} lors de la conversion.",
                    'uploaded_by'      => $request->user()?->id,
                ]);
            }

            $ancienStatut = $idea->statut;
            $idea->update([
                'statut'               => 'converti',
                'converted_project_id' => $project->id,
                'converted_at'         => now(),
            ]);

            ProjectIdeaStatusHistory::create([
                'project_idea_id' => $idea->id,
                'ancien_statut'   => $ancienStatut,
                'nouveau_statut'  => 'converti',
                'commentaire'     => "Convertie en projet #{$project->id} ({$project->titre})",
                'changed_by'      => $request->user()?->id,
            ]);

            return $project;
        });

        $this->logService->log(
            'convert', 'project_idea',
            "Idée de projet « {$idea->titre} » convertie en projet #{$project->id}",
            $project->id
        );

        return response()->json([
            'message' => 'Idée convertie en projet avec succès.',
            'project' => new ProjectResource($project->load(['province', 'region', 'district', 'domainesIntervention'])),
            'idea'    => $idea->fresh(),
        ], 201);
    }
}
