<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Email atau password tidak valid.'], 422);
        }

        if (!$user->is_active) {
            return response()->json(['message' => 'Akun pengguna tidak aktif.'], 403);
        }

        if ($user->skpd_id !== null && !$user->skpd?->is_active) {
            return response()->json(['message' => 'SKPD pengguna tidak aktif.'], 403);
        }

        $token = $user->createToken('rekon-akuntansi')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user->load('skpd'),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->is_active || ($user->skpd_id !== null && !$user->skpd?->is_active)) {
            $user->currentAccessToken()?->delete();
            return response()->json(['message' => 'Akun atau SKPD pengguna tidak aktif.'], 403);
        }

        return response()->json($user->load('skpd'));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logout berhasil.']);
    }
}
