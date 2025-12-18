<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    // --- 1. REGISTER MANUAL ---
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'phone_number' => 'required|string|unique:users', // Wajib untuk WA Fonnte
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone_number' => $request->phone_number,
            'role' => 'customer', // Default
        ]);

        // Langsung login setelah register
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Registrasi berhasil',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user
        ], 201);
    }

    // --- 2. LOGIN MANUAL ---
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json(['message' => 'Email atau password salah'], 401);
        }

        $user = User::where('email', $request->email)->firstOrFail();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login berhasil',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user
        ]);
    }

    // --- 3. GOOGLE LOGIN (Yg sudah ada) ---
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();

            $user = User::updateOrCreate(
                ['email' => $googleUser->getEmail()],
                [
                    'name' => $googleUser->getName(),
                    'google_id' => $googleUser->getId(),
                    'avatar' => $googleUser->getAvatar(),
                    // Password null karena Google
                ]
            );

            $token = $user->createToken('auth_token')->plainTextToken;

            // Redirect ke Frontend
            $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');
            return redirect("{$frontendUrl}/auth/callback?token={$token}&role={$user->role}&name=" . urlencode($user->name));

        } catch (\Exception $e) {
            return response()->json(['error' => 'Login Google Gagal'], 500);
        }
    }

    // --- 4. LOGOUT & ME ---
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logout berhasil']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }
    // --- 5. UPDATE PROFIL LENGKAP (FOTO + DATA) ---
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$user->id,
            'phone_number' => 'required|string|unique:users,phone_number,'.$user->id,
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg|max:10240',
        ]);

        // Update data teks
        $user->name = $request->name;
        $user->email = $request->email;
        $user->phone_number = $request->phone_number;

        // Cek apakah ada upload foto baru
        if ($request->hasFile('avatar')) {
            // Hapus foto lama jika bukan dari Google (bukan URL http)
            if ($user->avatar && !str_starts_with($user->avatar, 'http')) {
                // Hapus logika file lama (opsional, perlu parse path)
            }

            // Simpan foto baru ke folder 'public/avatars'
            $path = $request->file('avatar')->store('avatars', 'public');

            // Simpan URL lengkap ke database agar mudah dipanggil frontend
            $user->avatar = url('storage/' . $path);
        }

        $user->save();

        return response()->json([
            'message' => 'Profil berhasil diperbarui',
            'user' => $user
        ]);
    }
}
