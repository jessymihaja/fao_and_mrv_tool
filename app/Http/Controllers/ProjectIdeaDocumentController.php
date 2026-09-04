<?php

namespace App\Http\Controllers;

use App\Models\ProjectIdea;
use App\Models\ProjectIdeaDocument;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProjectIdeaDocumentController extends Controller
{
    public function __construct(
        private readonly ActivityLogService $logService
    ) {}

    public function index(int $ideaId): JsonResponse
    {
        ProjectIdea::findOrFail($ideaId);

        return response()->json(ProjectIdeaDocument::where('project_idea_id', $ideaId)->orderByDesc('created_at')->get());
    }

    public function store(Request $request, int $ideaId): JsonResponse
    {
        $idea = ProjectIdea::findOrFail($ideaId);

        $validated = $request->validate([
            'type'    => 'required|in:concept_note,etude_faisabilite,budget,carte,images,autre',
            'libelle' => 'nullable|string|max:255',
            'file'    => 'required|file|max:20480|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,gif,webp',
        ]);

        $file = $request->file('file');
        $path = $file->store('project-ideas/documents', 'local');

        $document = ProjectIdeaDocument::create([
            'project_idea_id' => $idea->id,
            'type'            => $validated['type'],
            'libelle'         => $validated['libelle'] ?? null,
            'file_path'       => $path,
            'file_name'       => $file->getClientOriginalName(),
            'mime_type'       => $file->getClientMimeType(),
            'size'            => $file->getSize(),
            'uploaded_by'     => $request->user()?->id,
        ]);

        $this->logService->log('create', 'project_idea_document', "Document ajouté pour : {$idea->titre}", null);

        return response()->json($document, 201);
    }

    public function destroy(int $id): JsonResponse
    {
        $document = ProjectIdeaDocument::findOrFail($id);

        if (Storage::disk('local')->exists($document->file_path)) {
            Storage::disk('local')->delete($document->file_path);
        }
        $document->delete();

        $this->logService->log('delete', 'project_idea_document', "Document supprimé #{$id}", null);

        return response()->json(['message' => 'Document supprimé.']);
    }

    public function download(int $id)
    {
        $document = ProjectIdeaDocument::findOrFail($id);

        if (! Storage::disk('local')->exists($document->file_path)) {
            return response()->json(['message' => 'Fichier introuvable.'], 404);
        }

        return response()->download(
            Storage::disk('local')->path($document->file_path),
            $document->file_name,
            ['Content-Type' => $document->mime_type]
        );
    }
}
