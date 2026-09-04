<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Faq;
use App\Models\Partner;
use App\Models\Slider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CmsController extends Controller
{
    // ─── FAQ ──────────────────────────────────────────────────
    public function faq(): JsonResponse
    {
        return response()->json(
            Faq::where('is_active', true)->orderBy('ordre')->get()
        );
    }

    public function storeFaq(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'question'  => ['required', 'string', 'max:500'],
            'reponse'   => ['required', 'string'],
            'categorie' => ['nullable', 'string', 'max:100'],
            'ordre'     => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        return response()->json(Faq::create($validated), 201);
    }

    public function updateFaq(Request $request, int $id): JsonResponse
    {
        $faq = Faq::findOrFail($id);
        $faq->update($request->validate([
            'question'  => ['sometimes', 'string', 'max:500'],
            'reponse'   => ['sometimes', 'string'],
            'categorie' => ['nullable', 'string', 'max:100'],
            'ordre'     => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]));
        return response()->json($faq);
    }

    public function destroyFaq(int $id): JsonResponse
    {
        Faq::findOrFail($id)->delete();
        return response()->json(['message' => 'FAQ supprimée.']);
    }

    public function adminFaq(): JsonResponse
    {
        return response()->json(
            Faq::orderBy('ordre')->get() 
        );
    }

    // ─── PARTENAIRES ──────────────────────────────────────────

    /**
     * Logo resolution rules (same as frontend getLogoUrl):
     *
     *   '/partenair/Saina.png'  → stored as-is, returned as-is (chemin public frontend)
     *   'partners/uuid.jpg'     → uploaded file, served via /storage/partners/uuid.jpg
     *   'https://...'           → URL externe, stored and returned as-is
     *
     * The frontend getLogoUrl() handles all three cases transparently.
     */
    public function partners(): JsonResponse
    {
        return response()->json(
            Partner::where('is_active', true)->orderBy('ordre')->get()
        );
    }

    public function storePartner(Request $request): JsonResponse
    {
        $request->validate([
            'nom'         => ['required', 'string', 'max:255'],
            'abbr'        => ['nullable', 'string', 'max:10'],
            'color'       => ['nullable', 'string', 'max:20'],
            // logo peut être : un fichier uploadé OU une chaîne (chemin local ou URL)
            'logo'        => ['nullable'],
            'logo_file'   => ['nullable', 'file', 'image', 'max:2048'],
            'url'         => ['nullable', 'url'],
            'description' => ['nullable', 'string', 'max:500'],
            'ordre'       => ['nullable', 'integer', 'min:0'],
            'is_active'   => ['nullable'],
        ]);

        $data = $request->only(['nom', 'abbr', 'color', 'url', 'description', 'ordre']);
        $data['is_active'] = filter_var($request->input('is_active', true), FILTER_VALIDATE_BOOLEAN);

        // Priorité 1 : fichier uploadé (champ 'logo' comme file)
        if ($request->hasFile('logo')) {
            $file         = $request->file('logo');
            $data['logo'] = $file->storeAs('partners', Str::uuid() . '.' . $file->extension(), 'public');
        }
        // Priorité 2 : chemin texte fourni (ex: '/partenair/Saina.png' ou 'https://...')
        elseif ($request->filled('logo') && is_string($request->input('logo'))) {
            $data['logo'] = $request->input('logo');
        }

        return response()->json(Partner::create($data), 201);
    }

    public function updatePartner(Request $request, int $id): JsonResponse
    {
        $partner = Partner::findOrFail($id);

        $request->validate([
            'nom'         => ['sometimes', 'string', 'max:255'],
            'abbr'        => ['nullable', 'string', 'max:10'],
            'color'       => ['nullable', 'string', 'max:20'],
            'logo'        => ['nullable'],
            'url'         => ['nullable', 'url'],
            'description' => ['nullable', 'string', 'max:500'],
            'ordre'       => ['nullable', 'integer', 'min:0'],
            'is_active'   => ['nullable'],
        ]);

        $data = $request->only(['nom', 'abbr', 'color', 'url', 'description', 'ordre']);

        if ($request->has('is_active')) {
            $data['is_active'] = filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN);
        }

        // Cas 1 : nouveau fichier uploadé
        if ($request->hasFile('logo')) {
            // Supprimer l'ancien fichier uploadé (mais pas les chemins locaux/URLs)
            $this->deleteOldLogo($partner->logo);
            $file         = $request->file('logo');
            $data['logo'] = $file->storeAs('partners', Str::uuid() . '.' . $file->extension(), 'public');
        }
        // Cas 2 : chemin texte fourni (chemin local ou URL externe)
        elseif ($request->filled('logo') && is_string($request->input('logo'))) {
            $logoValue = $request->input('logo');
            // Si on remplace un fichier uploadé par un chemin local, supprimer l'ancien
            if ($partner->logo && $this->isUploadedFile($partner->logo)) {
                $this->deleteOldLogo($partner->logo);
            }
            $data['logo'] = $logoValue;
        }

        $partner->update($data);
        return response()->json($partner);
    }

    public function destroyPartner(int $id): JsonResponse
    {
        $partner = Partner::findOrFail($id);
        // Ne supprimer que les fichiers uploadés (pas les chemins locaux frontend ni URLs)
        if ($partner->logo && $this->isUploadedFile($partner->logo)) {
            $this->deleteOldLogo($partner->logo);
        }
        $partner->delete();
        return response()->json(['message' => 'Partenaire supprimé.']);
    }

    public function adminPartners(): JsonResponse
    {
        return response()->json(
            Partner::orderBy('ordre')->get()
        );
    }
    // ─── CONTACTS ─────────────────────────────────────────────
    public function storeContact(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nom'     => ['required', 'string', 'max:255'],
            'email'   => ['required', 'email'],
            'sujet'   => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $contact = Contact::create($validated);
        return response()->json(['message' => 'Message envoyé avec succès.', 'id' => $contact->id], 201);
    }

    public function contacts(Request $request): JsonResponse
    {
        return response()->json(Contact::orderByDesc('created_at')->paginate(20));
    }

    public function destroyContact(int $id): JsonResponse
    {
        Contact::findOrFail($id)->delete();
        return response()->json(['message' => 'Message supprimé.']);
    }

    public function markAsRead(int $id): JsonResponse
    {
        $contact = Contact::findOrFail($id);
        $contact->update(['is_read' => true]);
        return response()->json($contact);
    }

    // ─── SLIDER ───────────────────────────────────────────────
    public function slider(): JsonResponse
    {
        return response()->json(
            Slider::where('is_active', true)->orderBy('ordre')->get()
        );
    }

    public function storeSlider(Request $request): JsonResponse
    {
        $request->validate([
            'titre'      => ['required', 'string', 'max:255'],
            'sous_titre' => ['nullable', 'string'],
            'image'      => ['nullable'],
            'cta_text'   => ['nullable', 'string', 'max:100'],
            'cta_url'    => ['nullable', 'string', 'max:255'],
            'ordre'      => ['nullable', 'integer', 'min:0'],
            'is_active'  => ['nullable'],
        ]);

        $data = $request->only(['titre', 'sous_titre', 'cta_text', 'cta_url', 'ordre']);
        $data['is_active'] = filter_var($request->input('is_active', true), FILTER_VALIDATE_BOOLEAN);

        if ($request->hasFile('image')) {
            $file          = $request->file('image');
            $data['image'] = $file->storeAs('sliders', Str::uuid() . '.' . $file->extension(), 'public');
        } elseif ($request->filled('image') && is_string($request->input('image'))) {
            $data['image'] = $request->input('image');
        }

        return response()->json(Slider::create($data), 201);
    }

    public function updateSlider(Request $request, int $id): JsonResponse
    {
        $slider = Slider::findOrFail($id);
        $data   = $request->only(['titre', 'sous_titre', 'cta_text', 'cta_url', 'ordre']);

        if ($request->has('is_active')) {
            $data['is_active'] = filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN);
        }

        if ($request->hasFile('image')) {
            if ($slider->image && $this->isUploadedFile($slider->image)) {
                Storage::disk('public')->delete($slider->image);
            }
            $file          = $request->file('image');
            $data['image'] = $file->storeAs('sliders', Str::uuid() . '.' . $file->extension(), 'public');
        } elseif ($request->filled('image') && is_string($request->input('image'))) {
            $data['image'] = $request->input('image');
        }

        $slider->update($data);
        return response()->json($slider);
    }

    public function destroySlider(int $id): JsonResponse
    {
        $slider = Slider::findOrFail($id);
        if ($slider->image && $this->isUploadedFile($slider->image)) {
            Storage::disk('public')->delete($slider->image);
        }
        $slider->delete();
        return response()->json(['message' => 'Slide supprimée.']);
    }

    public function adminSlider(): JsonResponse
    {
        return response()->json(
            Slider::orderBy('ordre')->get()
        );
    }

    // ─── Helpers ──────────────────────────────────────────────

    /**
     * Un fichier est "uploadé" si c'est un path storage relatif
     * (ni une URL complète, ni un chemin absolu /partenair/...)
     */
    private function isUploadedFile(?string $path): bool
    {
        if (! $path) return false;
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) return false;
        if (str_starts_with($path, '/')) return false;  // chemin local frontend
        return true; // ex: 'partners/uuid.jpg'
    }

    private function deleteOldLogo(?string $path): void
    {
        if ($this->isUploadedFile($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}