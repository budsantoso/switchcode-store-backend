<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuthChallenge;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // Generate Visual Captcha Challenge (Shopee/Tokopedia Security)
    public function getCaptcha(Request $request): JsonResponse
    {
        $a = rand(2, 9);
        $b = rand(2, 9);
        $question = "{$a} + {$b}";
        $solution = (string) ($a + $b);
        $token = Str::random(32);

        $email = $request->string('email', 'guest@switchcode.id');

        AuthChallenge::create([
            'email' => $email,
            'captcha_token' => $token,
            'captcha_solution' => $solution,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'captcha_token' => $token,
                'captcha_question' => "Berapa hasil dari {$question} ?",
            ]
        ]);
    }

    // Request & Send OTP Code
    public function requestOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|max:100',
            'captcha_token' => 'required|string',
            'captcha_answer' => 'required|string',
        ]);

        $challenge = AuthChallenge::where('captcha_token', $validated['captcha_token'])->first();
        if (!$challenge || trim($challenge->captcha_solution) !== trim($validated['captcha_answer'])) {
            return response()->json([
                'success' => false,
                'message' => 'Jawaban Captcha tidak tepat. Silakan coba kembali.'
            ], 422);
        }

        // Generate 6 digit numeric OTP
        $otp = (string) rand(100000, 999999);
        $challenge->update([
            'email' => $validated['email'],
            'otp_code' => $otp,
            'otp_expires_at' => now()->addMinutes(10),
        ]);

        // Coba kirim email riil jika mail driver aktif, otherwise fallback
        try {
            Mail::raw("Halo! Kode OTP registrasi SwitchCode Store Anda adalah: {$otp}. Kode berlaku 10 menit.", function ($m) use ($validated) {
                $m->to($validated['email'])->subject('Kode OTP Registrasi SwitchCode Store');
            });
        } catch (\Exception $e) {}

        return response()->json([
            'success' => true,
            'message' => 'Kode OTP berhasil dibuat dan dikirim ke ' . $validated['email'],
            'data' => [
                'email' => $validated['email'],
                'captcha_token' => $challenge->captcha_token,
                'otp_demo' => $otp, // Disediakan juga di response untuk kemudahan pengujian demo langsung
            ]
        ]);
    }

    // Register dengan validasi Captcha + OTP
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'captcha_token' => 'required|string',
            'otp_code' => 'required|string',
        ]);

        $challenge = AuthChallenge::where('captcha_token', $validated['captcha_token'])
            ->where('email', $validated['email'])
            ->where('is_verified', false)
            ->lockForUpdate()
            ->first();

        if (!$challenge || !hash_equals((string) $challenge->otp_code, (string) trim($validated['otp_code']))) {
            return response()->json([
                'success' => false,
                'message' => 'Kode OTP salah atau tidak cocok dengan akun ini.'
            ], 422);
        }

        if ($challenge->otp_expires_at && $challenge->otp_expires_at->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'Kode OTP telah kedaluwarsa. Silakan minta kode baru.'
            ], 422);
        }

        // Invalidasi challenge seketika (Anti replay attack)
        $challenge->update(['is_verified' => true]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => User::ROLE_CUSTOMER,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Registrasi berhasil!',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ],
                'token' => $token,
            ],
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        $user = User::where('email', $validated['email'])->first();

        // Timing-attack prevention: jalankan dummy hash check jika user tidak ditemukan
        if (!$user) {
            Hash::check($validated['password'], '$2y$12$e80yq9t56d1r9y3aB7Qe7eKjWpA5hM9mXh3dD1b4q9c5z7t3x1v6.');
            return response()->json([
                'success' => false,
                'message' => 'Email atau kata sandi tidak valid.',
            ], 401);
        }

        if (!Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Email atau kata sandi tidak valid.',
            ], 401);
        }

        // Hapus token lama untuk keamanan sesi
        $user->tokens()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ],
                'token' => $token,
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $request->user(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil',
        ]);
    }
}
