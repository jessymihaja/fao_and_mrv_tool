<?php

namespace App\Http\Controllers;

use App\Http\Resources\StakeholderResource;
use App\Models\Stakeholder;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class StakeholderController extends Controller
{
    public function __construct(
        private readonly ActivityLogService $logService
    ) {}

    private const RELATIONS = ['categorie', 'role', 'typeContribution'];
    private const DETAIL_RELATIONS = ['categorie', 'role', 'typeContribution', 'documents', 'creator'];

    /** 'ilike' n'existe que sous PostgreSQL — repli sur 'like' pour SQLite (tests). */
    private function likeOperator(): string
    {
        return DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $op = $this->likeOperator();

        $stakeholders = Stakeholder::with(self::RELATIONS)
            ->when($request->filled('search'), fn ($q) => $q->where(
                fn ($s) => $s->where('nom', $op, "%{$request->search}%")
                             ->orWhere('organisation', $op, "%{$request->search}%")
            ))
            ->when($request->filled('categorie_id'), fn ($q) => $q->where('categorie_id', $request->integer('categorie_id')))
            ->when($request->filled('role_id'), fn ($q) => $q->where('role_id', $request->integer('role_id')))
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->statut))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return StakeholderResource::collection($stakeholders);
    }

    /** Données non paginées (mêmes filtres), pour un futur export. */
    public function exportData(Request $request)
    {
        $op = $this->likeOperator();

        $stakeholders = Stakeholder::with(self::RELATIONS)
            ->when($request->filled('search'), fn ($q) => $q->where(
                fn ($s) => $s->where('nom', $op, "%{$request->search}%")
                             ->orWhere('organisation', $op, "%{$request->search}%")
            ))
            ->when($request->filled('categorie_id'), fn ($q) => $q->where('categorie_id', $request->integer('categorie_id')))
            ->when($request->filled('role_id'), fn ($q) => $q->where('role_id', $request->integer('role_id')))
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->statut))
            ->orderByDesc('created_at')
            ->limit(2000)
            ->get();

        return StakeholderResource::collection($stakeholders);
    }

    private function nullableFields(): array
    {
        return [
            'organisation', 'acronyme', 'role_id',
            'nom_representant', 'fonction', 'email', 'telephone', 'adresse',
            'type_contribution_id', 'description_contribution', 'montant_estimatif', 'devise',
            'date_debut', 'date_fin',
        ];
    }

    /** Chaînes vides '' -> null pour les champs optionnels (formulaire). */
    private function sanitize(Request $request): void
    {
        $data = $request->all();
        foreach ($this->nullableFields() as $field) {
            if (array_key_exists($field, $data) && $data[$field] === '') {
                $data[$field] = null;
            }
        }
        $request->replace($data);
    }

    private function rules(bool $isUpdate): array
    {
        $req = $isUpdate ? 'sometimes|required' : 'required';

        return [
            'nom'                       => "{$req}|string|max:255",
            'organisation'              => 'nullable|string|max:255',
            'acronyme'                  => 'nullable|string|max:50',

            'categorie_id'              => "{$req}|integer|exists:stakeholder_categories,id",
            'role_id'                   => 'nullable|integer|exists:stakeholder_roles,id',

            'nom_representant'          => 'nullable|string|max:255',
            'fonction'                  => 'nullable|string|max:255',
            'email'                     => 'nullable|email|max:255',
            // Chiffres, espaces, +, -, parenthèses — assez permissif pour des numéros locaux et internationaux.
            'telephone'                 => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{6,30}$/'],
            'adresse'                   => 'nullable|string',

            'type_contribution_id'      => 'nullable|integer|exists:stakeholder_contribution_types,id',
            'description_contribution'  => 'nullable|string',
            'montant_estimatif'         => 'nullable|numeric|min:0',
            'devise'                    => 'nullable|exists:currencies,code',

            'date_debut'                => 'nullable|date',
            'date_fin'                  => 'nullable|date|after_or_equal:date_debut',
            'statut'                    => 'sometimes|in:actif,en_attente,suspendu,termine',
        ];
    }

    private function messages(): array
    {
        return [
            'email.email'       => "Le format de l'adresse email n'est pas valide.",
            'telephone.regex'   => 'Le numéro de téléphone contient des caractères non valides.',
            'montant_estimatif.numeric' => 'Le montant doit être une valeur numérique.',
            'montant_estimatif.min'     => 'Le montant doit être positif.',
        ];
    }

    public function store(Request $request)
    {
        $this->sanitize($request);
        $validated = $request->validate($this->rules(false), $this->messages());

        $stakeholder = Stakeholder::create([
            ...$validated,
            'statut'     => $validated['statut'] ?? 'actif',
            'created_by' => $request->user()?->id,
        ]);

        $this->logService->log('create', 'stakeholder', "Partie prenante créée : {$stakeholder->nom}", null);

        return new StakeholderResource($stakeholder->load(self::DETAIL_RELATIONS));
    }

    public function show(int $id): StakeholderResource
    {
        return new StakeholderResource(Stakeholder::with(self::DETAIL_RELATIONS)->findOrFail($id));
    }

    public function update(Request $request, int $id): StakeholderResource
    {
        $stakeholder = Stakeholder::findOrFail($id);

        $this->sanitize($request);
        $validated = $request->validate($this->rules(true), $this->messages());

        $stakeholder->update($validated);

        $this->logService->log('update', 'stakeholder', "Partie prenante modifiée : {$stakeholder->nom}", null);

        return new StakeholderResource($stakeholder->fresh()->load(self::DETAIL_RELATIONS));
    }

    public function destroy(int $id)
    {
        $stakeholder = Stakeholder::findOrFail($id);
        $nom = $stakeholder->nom;
        $stakeholder->delete();

        $this->logService->log('delete', 'stakeholder', "Partie prenante supprimée : {$nom}", null);

        return response()->json(['message' => 'Partie prenante supprimée.']);
    }
}
