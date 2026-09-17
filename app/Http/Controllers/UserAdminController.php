<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserAdminController extends Controller
{
    private function authorizeAdmin(): void
    {
        abort_unless(request()->user()?->isAdmin(), 403);
    }

    public function index(): JsonResponse
    {
        $this->authorizeAdmin();

        $users = User::query()
            ->with('skpd:id,code,name')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'skpd_id', 'is_active', 'created_at', 'updated_at']);

        return response()->json($users);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in(['admin', 'skpkd', 'skpd'])],
            'skpd_id' => ['nullable', 'integer', 'exists:skpds,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $this->validateRoleScope($data['role'], $data['skpd_id'] ?? null);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $data['role'],
            'skpd_id' => $data['skpd_id'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json($user->load('skpd:id,code,name'), 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['sometimes', 'nullable', 'string', 'min:8'],
            'role' => ['sometimes', 'required', Rule::in(['admin', 'skpkd', 'skpd'])],
            'skpd_id' => ['sometimes', 'nullable', 'integer', 'exists:skpds,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $role = $data['role'] ?? $user->role;
        $skpdId = array_key_exists('skpd_id', $data) ? $data['skpd_id'] : $user->skpd_id;
        $this->validateRoleScope($role, $skpdId);

        if ($user->id === $request->user()->id && ($data['is_active'] ?? true) === false) {
            return response()->json(['message' => 'Pengguna yang sedang login tidak dapat dinonaktifkan.'], 422);
        }

        if ($user->id === $request->user()->id && $role === 'skpd') {
            return response()->json(['message' => 'Akun admin yang sedang login tidak dapat diubah menjadi pengguna SKPD.'], 422);
        }

        $passwordChanged = array_key_exists('password', $data) && $data['password'] !== null;
        if (!$passwordChanged) {
            unset($data['password']);
        }

        $user->fill($data);
        $user->save();

        if (!$user->is_active || $passwordChanged) {
            $user->tokens()->delete();
        }

        return response()->json($user->fresh()->load('skpd:id,code,name'));
    }

    private function validateRoleScope(string $role, ?int $skpdId): void
    {
        if ($role === 'skpd' && $skpdId === null) {
            abort(422, 'Pengguna dengan role skpd wajib memiliki SKPD.');
        }

        if ($role !== 'skpd' && $skpdId !== null) {
            abort(422, 'Role admin/skpkd tidak boleh memiliki SKPD.');
        }
    }
}
