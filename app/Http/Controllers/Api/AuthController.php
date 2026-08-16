<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use App\Services\CoaTemplateService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    protected CoaTemplateService $coaService;

    public function __construct(CoaTemplateService $coaService)
    {
        $this->coaService = $coaService;
    }

    /**
     * Register new company and owner user
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($validated) {
            // 1. Create Company
            $company = Company::create([
                'name' => $validated['company_name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'address' => $validated['address'] ?? null,
                'currency' => 'IDR',
                'standard' => 'PSAK EMKM',
            ]);

            // 2. Seed default COA Template for company
            $this->coaService->seedForCompany($company->id);

            // 3. Create User with Owner Role
            $user = User::create([
                'company_id' => $company->id,
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => 'owner',
            ]);

            // 4. Generate Sanctum token
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'message' => 'Registrasi berhasil! Akun dan template pembukuan UMKM telah disiapkan.',
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ],
                'company' => $company,
            ], 201);
        });
    }

    /**
     * Login user and issue token
     */
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::with('company')->where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email atau password yang Anda masukkan salah.'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login berhasil.',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
            'company' => $user->company,
        ]);
    }

    /**
     * Get authenticated user profile
     */
    public function me(Request $request)
    {
        $user = $request->user()->load('company');

        return response()->json([
            'user' => $user,
            'company' => $user->company,
        ]);
    }

    /**
     * Logout and revoke token
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout berhasil.',
        ]);
    }

    /**
     * Update Company Profile
     */
    public function updateCompany(Request $request)
    {
        $currentUser = $request->user();

        if ($currentUser->role !== 'owner' && $currentUser->role !== 'admin') {
            return response()->json(['message' => 'Hanya Owner atau Admin yang dapat memperbarui data usaha.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
        ]);

        $company = Company::findOrFail($currentUser->company_id);
        $company->update($validated);

        return response()->json([
            'message' => 'Profil usaha berhasil diperbarui.',
            'company' => $company,
        ]);
    }

    /**
     * Get Team Members in current company
     */
    public function getTeam(Request $request)
    {
        $currentUser = $request->user();

        $users = User::where('company_id', $currentUser->company_id)
            ->select('id', 'company_id', 'name', 'email', 'role', 'created_at')
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'data' => $users,
        ]);
    }

    /**
     * Invite staff user (Owner/Admin only)
     */
    public function invite(Request $request)
    {
        $currentUser = $request->user();

        if ($currentUser->role !== 'owner' && $currentUser->role !== 'admin') {
            return response()->json(['message' => 'Hanya Owner atau Admin yang dapat mengundang anggota.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:6',
            'role' => 'required|in:admin,staff',
        ]);

        $user = User::create([
            'company_id' => $currentUser->company_id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
        ]);

        return response()->json([
            'message' => 'Anggota tim berhasil ditambahkan.',
            'user' => $user,
        ], 201);
    }

    /**
     * Remove / Deactivate team member (Owner only)
     */
    public function deleteTeamMember(Request $request, $id)
    {
        $currentUser = $request->user();

        if ($currentUser->role !== 'owner') {
            return response()->json(['message' => 'Hanya Pemilik (Owner) yang dapat menghapus anggota tim.'], 403);
        }

        if ((int)$currentUser->id === (int)$id) {
            return response()->json(['message' => 'Anda tidak dapat menghapus akun Anda sendiri.'], 422);
        }

        $targetUser = User::where('company_id', $currentUser->company_id)->findOrFail($id);

        if ($targetUser->role === 'owner') {
            return response()->json(['message' => 'Tidak dapat menghapus sesama akun Owner.'], 422);
        }

        $targetUser->tokens()->delete();
        $targetUser->delete();

        return response()->json([
            'message' => 'Anggota tim berhasil dihapus.',
        ]);
    }

    /**
     * Update Current User Profile & Password
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'current_password' => 'nullable|required_with:new_password|string',
            'new_password' => 'nullable|string|min:6',
        ]);

        if (!empty($validated['new_password'])) {
            if (!Hash::check($validated['current_password'], $user->password)) {
                throw ValidationException::withMessages([
                    'current_password' => ['Kata sandi saat ini tidak cocok.'],
                ]);
            }
            $user->password = Hash::make($validated['new_password']);
        }

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->save();

        return response()->json([
            'message' => 'Profil Anda berhasil diperbarui.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ]);
    }
}
