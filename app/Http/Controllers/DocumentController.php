<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class DocumentController extends Controller
{
    public function __construct(
        private readonly ActivityLogService $logService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $documents = Document::with(['project', 'financement'])
            ->when($request->filled('project_id'), fn ($q) => $q->where('project_id', $request->integer('project_id')))
            ->when($request->filled('composante_id'), fn ($q) => $q->where('composante_id', $request->integer('composante_id')))
            ->when($request->filled('type'),        fn ($q) => $q->where('type', $request->type))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return DocumentResource::collection($documents);
    }

    public function byProject(int $projectId): AnonymousResourceCollection
    {
        $documents = Document::with('financement')
            ->where('project_id', $projectId)
            ->orderByDesc('created_at')
            ->get();

        return DocumentResource::collection($documents);
    }

    public function byComposante(int $composanteId): AnonymousResourceCollection
    {
        $documents = Document::with('financement')
            ->where('composante_id', $composanteId)
            ->orderByDesc('created_at')
            ->get();

        return DocumentResource::collection($documents);
    }

    public function store(Request $request): DocumentResource
    {
        $request->validate([
            'titre'          => ['nullable', 'string', 'max:255'],
            'type'           => ['required', 'in:rapport,contrat,accord,plan,etude,photo,autre'],
            'fichier'        => ['required', 'file', 'max:51200', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,zip'],
            'project_id'     => ['required_without:composante_id', 'nullable', 'exists:projects,id'],
            'composante_id'  => ['required_without:project_id', 'nullable', 'exists:composantes,id'],
            'financement_id' => ['nullable', 'exists:financements,id'],
            'description'    => ['nullable', 'string'],
        ]);

        $file     = $request->file('fichier');
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path     = $file->storeAs('documents', $filename, 'public');

        $composanteId = $request->integer('composante_id') ?: null;
        $projectId    = $request->integer('project_id') ?: null;

        // Si rattaché à une composante, en déduire le projet parent
        if ($composanteId && ! $projectId) {
            $projectId = \App\Models\Composante::findOrFail($composanteId)->project_id;
        }

        $document = Document::create([
            'titre'            => $request->filled('titre') ? $request->titre : $file->getClientOriginalName(),
            'type'             => $request->type,
            'fichier'          => $path,
            'fichier_original' => $file->getClientOriginalName(),
            'taille'           => $file->getSize(),
            'mime_type'        => $file->getMimeType(),
            'project_id'       => $projectId,
            'composante_id'    => $composanteId,
            'financement_id'   => $request->integer('financement_id') ?: null,
            'description'      => $request->description,
            'uploaded_by'      => auth()->id(),
        ]);

        $this->logService->log('upload', 'document', "Document uploadé : {$document->titre}", $projectId);

        return new DocumentResource($document->load(['project', 'financement']));
    }

    public function show(int $id): DocumentResource
    {
        return new DocumentResource(
            Document::with(['project', 'financement'])->findOrFail($id)
        );
    }

    /**
     * Suppression douce (soft delete) : le fichier physique est CONSERVÉ
     * sur le disque. Un document est potentiellement une pièce
     * légale/contractuelle — le supprimer physiquement en même temps que la
     * ligne rendrait la restauration (deleted_at) inutile, puisque le
     * fichier associé serait déjà irrécupérable. Le nettoyage physique
     * définitif des fichiers de documents soft-deletés depuis longtemps
     * relève d'une politique de purge séparée (ex. commande artisan
     * planifiée), pas de cette action utilisateur.
     */
    public function destroy(int $id): JsonResponse
    {
        $document = Document::findOrFail($id);

        $this->logService->log('delete', 'document', "Document supprimé : {$document->titre}");

        $document->delete();

        return response()->json(['message' => 'Document supprimé.']);
    }

    /**
     * Téléchargement via URL signée temporaire (15 min).
     * Accessible sans header Authorization — le token est dans la signature URL.
     * Appelé via GET /documents/{id}/download?signature=...&expires=...
     */
    public function download(int $id)
    {
        $document = Document::findOrFail($id);

        if (! Storage::disk('public')->exists($document->fichier)) {
            return response()->json(['message' => 'Fichier introuvable.'], 404);
        }

        return Storage::disk('public')->download(
            $document->fichier,
            $document->fichier_original ?? basename($document->fichier)
        );
    }

    /**
     * Génère une URL signée temporaire (15 min) pour le téléchargement.
     * Le frontend appelle d'abord cet endpoint (avec Bearer token),
     * puis redirige/ouvre l'URL signée retournée.
     *
     * GET /documents/{id}/signed-url
     */
    public function signedUrl(int $id): JsonResponse
    {
        // Vérifie que le document existe
        Document::findOrFail($id);

        $url = URL::temporarySignedRoute(
            'documents.download',
            now()->addMinutes(15),
            ['id' => $id]
        );

        return response()->json(['url' => $url]);
    }
}
