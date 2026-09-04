<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    /**
     * Rôles visibles et gérables selon le rôle de l'utilisateur connecté.
     * super_admin n'apparaît jamais dans l'interface de gestion.
     *
     * gestionnaire_cms : rôle limité au site vitrine (CMS, chatbot,
     * paramètres publics) — aucun accès aux données métier.
     */
    private function allowedRoles(): array
    {
        return ['admin', 'gestionnaire', 'gestionnaire_cms', 'utilisateur'];
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $users = User::query()
            // Exclure les super_admin de la liste
            ->whereNotIn('role', ['super_admin'])
            ->when($request->filled('search'), fn ($q) => $q->where(
                fn ($s) => $s->where('name',  'ilike', "%{$request->search}%")
                             ->orWhere('email','ilike', "%{$request->search}%")
            ))
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->role))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return UserResource::collection($users);
    }

    public function store(Request $request): UserResource
    {
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
            'role'     => ['required', Rule::in($this->allowedRoles())],
        ], [
            'role.in' => 'Le rôle sélectionné est invalide.',
        ]);

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role'     => $validated['role'],
        ]);

        return new UserResource($user);
    }

    public function show(int $id): UserResource
    {
        $user = User::whereNotIn('role', ['super_admin'])->findOrFail($id);
        return new UserResource($user);
    }

    public function update(Request $request, int $id): UserResource
    {
        $user = User::whereNotIn('role', ['super_admin'])->findOrFail($id);

        $validated = $request->validate([
            'name'     => ['sometimes', 'required', 'string', 'max:255'],
            'email'    => ['sometimes', 'required', 'email', 'unique:users,email,' . $id],
            'password' => ['nullable', 'confirmed', Password::min(8)->mixedCase()->numbers()],
            'role'     => ['sometimes', 'required', Rule::in($this->allowedRoles())],
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        return new UserResource($user);
    }

    public function updateRole(Request $request, int $id): UserResource
    {
        $user = User::whereNotIn('role', ['super_admin'])->findOrFail($id);
        $request->validate([
            'role' => ['required', Rule::in($this->allowedRoles())],
        ]);
        $user->update(['role' => $request->role]);
        return new UserResource($user);
    }

    public function toggle(int $id): JsonResponse
    {
        $user = User::whereNotIn('role', ['super_admin'])->findOrFail($id);

        if ($user->id === auth()->id()) {
            return response()->json(['message' => 'Impossible de désactiver votre propre compte.'], 403);
        }

        $user->update(['is_active' => ! $user->is_active]);

        return response()->json(new UserResource($user));
    }

    public function destroy(int $id): JsonResponse
    {
        $user = User::whereNotIn('role', ['super_admin'])->findOrFail($id);

        if ($user->id === auth()->id()) {
            return response()->json(['message' => 'Impossible de supprimer votre propre compte.'], 403);
        }

        $user->delete();

        return response()->json(['message' => 'Utilisateur supprimé.']);
    }
}