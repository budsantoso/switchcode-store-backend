<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class OwnerController extends Controller
{
    // List Admin & Owner Users
    public function indexAdmins(): JsonResponse
    {
        $users = User::whereIn('role', [User::ROLE_ADMIN, User::ROLE_OWNER])
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    // Owner Add New Admin
    public function storeAdmin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => ['required', 'string', 'min:8'],
            'role' => 'nullable|in:admin,owner',
        ]);

        $admin = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'] ?? User::ROLE_ADMIN,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Admin baru berhasil didaftarkan oleh Owner',
            'data' => $admin,
        ], 201);
    }

    // Owner Update Admin Status / Role / Password
    public function updateAdmin(Request $request, int $id): JsonResponse
    {
        $admin = User::whereIn('role', [User::ROLE_ADMIN, User::ROLE_OWNER])->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|max:255|unique:users,email,' . $id,
            'password' => ['nullable', 'string', 'min:8'],
            'role' => 'sometimes|required|in:admin,owner',
        ]);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $admin->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Data admin berhasil diperbarui',
            'data' => $admin,
        ]);
    }

    // Owner Delete Admin
    public function destroyAdmin(Request $request, int $id): JsonResponse
    {
        $currentUser = $request->user();
        if ($currentUser->id === $id) {
            return response()->json([
                'success' => false,
                'message' => 'Owner tidak dapat menghapus akunnya sendiri',
            ], 422);
        }

        $admin = User::whereIn('role', [User::ROLE_ADMIN, User::ROLE_OWNER])->findOrFail($id);
        $admin->delete();

        return response()->json([
            'success' => true,
            'message' => 'Admin berhasil dihapus',
        ]);
    }
}
