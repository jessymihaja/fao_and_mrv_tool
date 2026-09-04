<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Indicateur;
use App\Models\IndicateurJustificatif;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class IndicateurController extends Controller
{
    // ── GET /indicateurs ──────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $q = Indicateur::with(['project:id,titre,secteur_climatique', 'justificatifs'])
            ->when($request->categorie,         fn($q, $v) => $q->where('categorie', $v))
            ->when($request->project_id,        fn($q, $v) => $q->where('project_id', $v))
            ->when($request->composante_id,     fn($q, $v) => $q->where('composante_id', $v))
            ->when($request->activite_id,       fn($q, $v) => $q->where('activite_id', $v))
            ->when($request->niveau_performance, fn($q, $v) => $q->where('niveau_performance', $v))
            ->when($request->annee, fn($q, $v) =>
                $q->whereYear('date_reference', $v)
            )
            ->orderByDesc('date_reference');

        return response()->json($q->paginate($request->per_page ?? 15));
    }

    // ── GET /projects/{project}/indicateurs ───────────────────────
    // Uniquement les indicateurs strictement niveau "projet" (ni composante, ni activité)
    public function byProject(int $projectId): JsonResponse
    {
        $items = Indicateur::with('justificatifs')
            ->where('project_id', $projectId)
            ->whereNull('composante_id')
            ->whereNull('activite_id')
            ->orderByDesc('date_reference')
            ->get();

        return response()->json($items);
    }

    // ── GET /projects/{project}/indicateurs-all ────────────────────
    // TOUS les indicateurs du projet, quel que soit leur niveau de
    // rattachement (projet / composante / activité). "project_id" est
    // toujours renseigné à la création (cf. store(), résolution
    // automatique activité > composante > projet), donc un simple filtre
    // sur project_id suffit ici — contrairement à byProject() ci-dessus qui
    // sert un usage volontairement restreint (gestion des indicateurs de
    // niveau projet uniquement).
    public function allForProject(int $projectId): JsonResponse
    {
        $items = Indicateur::with('justificatifs')
            ->where('project_id', $projectId)
            ->orderByDesc('date_reference')
            ->get();

        return response()->json($items);
    }

    // ── GET /composantes/{composante}/indicateurs ─────────────────
    public function byComposante(int $composanteId): JsonResponse
    {
        $items = Indicateur::with('justificatifs')
            ->where('composante_id', $composanteId)
            ->whereNull('activite_id')
            ->orderByDesc('date_reference')
            ->get();

        return response()->json($items);
    }

    // ── GET /activites/{activite}/indicateurs ──────────────────────
    public function byActivite(int $activiteId): JsonResponse
    {
        $items = Indicateur::with('justificatifs')
            ->where('activite_id', $activiteId)
            ->orderByDesc('date_reference')
            ->get();

        return response()->json($items);
    }

    // ── GET /indicateurs/kpis ─────────────────────────────────────
    public function kpis(Request $request): JsonResponse
    {
        $q = Indicateur::query()
            ->when($request->project_id,        fn($q, $v) => $q->where('project_id', $v))
            ->when($request->categorie,         fn($q, $v) => $q->where('categorie', $v))
            ->when($request->niveau_performance, fn($q, $v) => $q->where('niveau_performance', $v))
            ->when($request->annee,             fn($q, $v) => $q->whereYear('date_reference', $v));

        $all = $q->get();

        return response()->json([
            'total'        => $all->count(),
            'taux_moyen'   => round($all->avg('taux_atteinte') ?? 0, 1),
            'nb_excellent' => $all->where('niveau_performance', 'Excellent')->count(),
            'nb_bon'       => $all->where('niveau_performance', 'Bon')->count(),
            'nb_moyen'     => $all->where('niveau_performance', 'Moyen')->count(),
            'nb_faible'    => $all->where('niveau_performance', 'Faible')->count(),
            'par_categorie' => $all->groupBy('categorie')->map(fn($g) => [
                'count'      => $g->count(),
                'taux_moyen' => round($g->avg('taux_atteinte'), 1),
            ]),
        ]);
    }

    // ── POST /indicateurs ─────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id'      => 'required_without_all:composante_id,activite_id|nullable|exists:projects,id',
            'composante_id'   => 'required_without_all:project_id,activite_id|nullable|exists:composantes,id',
            'activite_id'     => 'nullable|exists:activites,id',
            'categorie'       => 'required|in:financier,physique,adaptation,attenuation',
            'nom'             => 'required|string|max:255',
            'unite'           => 'required|string|max:50',
            'valeur_cible'    => 'required|numeric|min:0',
            'valeur_realisee' => 'required|numeric|min:0',
            'date_reference'  => 'required|date',
            'commentaire'     => 'nullable|string',
            'justificatifs'   => 'nullable|array',
            'justificatifs.*' => 'file|max:20480|mimes:pdf,jpg,jpeg,png,xlsx,docx',
        ]);

        // Déduction automatique du projet (et éventuellement de la composante)
        // parent selon le niveau de rattachement le plus précis fourni :
        // activité > composante > projet.
        if (!empty($validated['activite_id'])) {
            $activite = \App\Models\Activite::findOrFail($validated['activite_id']);
            $validated['composante_id'] = $validated['composante_id'] ?? $activite->composante_id;
            $validated['project_id']    = $validated['project_id'] ?? $activite->resolveProjectId();
        } elseif (!empty($validated['composante_id']) && empty($validated['project_id'])) {
            $validated['project_id'] = \App\Models\Composante::findOrFail($validated['composante_id'])->project_id;
        }

        $indicateur = Indicateur::create(
            array_merge($validated, ['created_by' => $request->user()->id])
        );

        // Stocker les fichiers
        if ($request->hasFile('justificatifs')) {
            foreach ($request->file('justificatifs') as $file) {
                $path = $file->store("indicateurs/{$indicateur->id}", 'local');
                $indicateur->justificatifs()->create([
                    'fichier'      => $path,
                    'nom_original' => $file->getClientOriginalName(),
                    'taille'       => $file->getSize(),
                    'mime_type'    => $file->getMimeType(),
                ]);
            }
        }

        return response()->json(
            $indicateur->load(['project:id,titre', 'justificatifs']),
            201
        );
    }

    // ── GET /indicateurs/{id} ─────────────────────────────────────
    public function show(Indicateur $indicateur): JsonResponse
    {
        return response()->json(
            $indicateur->load(['project:id,titre,secteur_climatique', 'justificatifs'])
        );
    }

    // ── POST /indicateurs/{id}  (multipart update) ────────────────
    public function update(Request $request, Indicateur $indicateur): JsonResponse
    {
        $validated = $request->validate([
            'project_id'      => 'sometimes|exists:projects,id',
            'composante_id'   => 'sometimes|nullable|exists:composantes,id',
            'activite_id'     => 'sometimes|nullable|exists:activites,id',
            'categorie'       => 'sometimes|in:financier,physique,adaptation,attenuation',
            'nom'             => 'sometimes|string|max:255',
            'unite'           => 'sometimes|string|max:50',
            'valeur_cible'    => 'sometimes|numeric|min:0',
            'valeur_realisee' => 'sometimes|numeric|min:0',
            'date_reference'  => 'sometimes|date',
            'commentaire'     => 'nullable|string',
            'justificatifs'   => 'nullable|array',
            'justificatifs.*' => 'file|max:20480|mimes:pdf,jpg,jpeg,png,xlsx,docx',
        ]);

        $indicateur->update($validated);

        if ($request->hasFile('justificatifs')) {
            foreach ($request->file('justificatifs') as $file) {
                $path = $file->store("indicateurs/{$indicateur->id}", 'local');
                $indicateur->justificatifs()->create([
                    'fichier'      => $path,
                    'nom_original' => $file->getClientOriginalName(),
                    'taille'       => $file->getSize(),
                    'mime_type'    => $file->getMimeType(),
                ]);
            }
        }

        return response()->json(
            $indicateur->fresh()->load(['project:id,titre', 'justificatifs'])
        );
    }

    // ── DELETE /indicateurs/{id} ──────────────────────────────────
    public function destroy(Indicateur $indicateur): JsonResponse
    {
        // Supprimer les fichiers physiques
        foreach ($indicateur->justificatifs as $j) {
            Storage::disk('local')->delete($j->fichier);
        }
        $indicateur->delete();

        return response()->json(null, 204);
    }

    // ── DELETE /indicateurs/{id}/justificatifs/{justificatif} ─────
    public function destroyJustificatif(Indicateur $indicateur, IndicateurJustificatif $justificatif): JsonResponse
    {
        abort_if($justificatif->indicateur_id !== $indicateur->id, 404);
        Storage::disk('local')->delete($justificatif->fichier);
        $justificatif->delete();

        return response()->json(null, 204);
    }
}