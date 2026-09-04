<?php

namespace App\Http\Controllers;

use App\Models\Stakeholder;
use App\Models\StakeholderDocument;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StakeholderDocumentController extends Controller
{
    public function __construct(
        private readonly ActivityLogService $logService
    ) {}

    public function index(int $stakeholderId): JsonResponse
    {
        Stakeholder::findOrFail($stakeholderId);

        return response()->json(StakeholderDocument::where('stakeholder_id', $stakeholderId)->orderByDesc('created_at')->get());
    }

    public function store(Request $request, int $stakeholderId): JsonResponse
    {
        $stakeholder = Stakeholder::findOrFail($stakeholderId);

        $validated = $request->validate([
            'libelle' => 'nullable|string|max:255',
            'file'    => 'required|file|max:20480|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,gif,webp',
        ]);

        $file = $request->file('file');
        $path = $file->store('stakeholders/documents', 'local');

        $document = StakeholderDocument::create([
            'stakeholder_id' => $stakeholder->id,
            'libelle'        => $validated['libelle'] ?? null,
            'file_path'      => $path,
            'file_name'      => $file->getClientOriginalName(),
            'mime_type'      => $file->getClientMimeType(),
            'size'           => $file->getSize(),
            'uploaded_by'    => $request->user()?->id,
        ]);

        $this->logService->log('create', 'stakeholder_document', "Document ajouté pour : {$stakeholder->nom}", null);

        return response()->json($document, 201);
    }

    public function destroy(int $id): JsonResponse
    {
        $document = StakeholderDocument::findOrFail($id);

        if (Storage::disk('local')->exists($document->file_path)) {
            Storage::disk('local')->delete($document->file_path);
        }
        $document->delete();

        $this->logService->log('delete', 'stakeholder_document', "Document supprimé #{$id}", null);

        return response()->json(['message' => 'Document supprimé.']);
    }

    public function download(int $id)
    {
        $document = StakeholderDocument::findOrFail($id);

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
