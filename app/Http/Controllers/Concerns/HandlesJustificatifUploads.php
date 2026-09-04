<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Logique commune aux contrôleurs du module "Budgets" (cycle de vie des
 * financements climatiques) : stockage/suppression/téléchargement des
 * pièces justificatives, et normalisation des champs FormData.
 *
 * Mêmes conventions que Depense/ActivitePieceJointe déjà existants dans le
 * projet : fichiers privés sur le disque 'local', téléchargement via une
 * route authentifiée (pas d'URL publique directe).
 */
trait HandlesJustificatifUploads
{
    /**
     * Enregistre le fichier envoyé sous le champ $field (s'il est présent)
     * et retourne [chemin_stocké, nom_original]. Retourne [null, null] si
     * aucun fichier n'a été envoyé dans la requête (cas d'une mise à jour
     * qui ne touche pas au justificatif).
     */
    /**
     * Sur la création, 'devise' est désormais optionnelle (rétrocompatibilité
     * avec les formulaires existants qui ne l'envoient pas encore) : on
     * applique Ariary par défaut, comme pour les enregistrements déjà en
     * base. Ne s'applique volontairement qu'à la création — sur une mise à
     * jour, l'absence de ce champ signifie "ne pas y toucher", pas "le
     * réinitialiser".
     */
    protected function applyCurrencyDefaults(array $validated, string $montantField): array
    {
        $validated['devise'] ??= 'AR';

        return $validated;
    }

    protected function storeJustificatif(Request $request, string $field, string $folder): array
    {
        if (! $request->hasFile($field)) {
            return [null, null];
        }

        $file = $request->file($field);
        $path = $file->store($folder, 'local');

        return [$path, $file->getClientOriginalName()];
    }

    protected function deleteJustificatifFile(?string $path): void
    {
        if ($path && Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }
    }

    /**
     * Réponse de téléchargement pour une pièce justificative, ou 404 JSON
     * si aucun fichier n'est attaché / introuvable sur le disque.
     */
    protected function downloadJustificatifResponse(?string $path, ?string $name)
    {
        if (! $path) {
            return response()->json(['message' => 'Aucun justificatif attaché.'], 404);
        }
        if (! Storage::disk('local')->exists($path)) {
            return response()->json(['message' => 'Fichier introuvable.'], 404);
        }

        $fullPath = Storage::disk('local')->path($path);
        $mimeType = Storage::disk('local')->mimeType($path);

        return response()->download($fullPath, $name ?: basename($path), ['Content-Type' => $mimeType]);
    }

    /**
     * Les champs optionnels arrivent en chaîne vide '' depuis le FormData du
     * frontend (multipart, requis pour l'upload de fichier). Sans cette
     * normalisation, 'nullable|numeric' ou 'nullable|date' laissent passer
     * '' telle quelle, qui casse ensuite les casts decimal:2 / date du
     * modèle à la relecture. Même correctif que celui déjà appliqué dans
     * ActiviteController.
     */
    protected function normalizeEmptyStrings(Request $request, array $fields): void
    {
        $merge = [];
        foreach ($fields as $field) {
            if ($request->has($field) && $request->input($field) === '') {
                $merge[$field] = null;
            }
        }
        if ($merge) {
            $request->merge($merge);
        }
    }
}
