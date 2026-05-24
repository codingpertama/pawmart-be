<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    // register (membuat akun baru sebagai user biasa)
    public function register(Request $request)
    {
        // validasi input kiriman dari frontend
        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'phone'    => 'nullable|string|max:15',
        ]);

        // kalau validasi gagal, kirim error 422(data tidak valid)
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' =>  'validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        // simpan user baru ke database
        // role user by default
        $user = User::create([
            'name' =>  $request->name,
            'email' => $request->email,
            'password' => $request->password,
            'phone'    => $request->phone,
            'role'     => 'user',
        ]);

        // buatin JWT token setelah register berhasil
        $token =  Auth::login($user);

        return response()->json([
            'success' => true,
            'message' => 'register berhasil',
            'data' => [
                'user' => $user,
                'token' => $token,
            ]
        ], 201);
    }

    // login (autentikasi user dan kembalikan JWT Token)
    public function login(Request $request)
    {
        // validasi input
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        // kalaau validasi gagal, kirim error 422
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Coba cocokkan email dan password
        // Kalau cocok, Auth::attempt() akan return JWT token
        // Kalau tidak cocok, return false
        $token = Auth::attempt([
            'email' => $request->email,
            'password' => $request->password,
        ]);

        // kalau token false berarti email/password salah
        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'email/password salah',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'message' => 'login berhasil',
            'data' => [
                'user' => Auth::user(),
                'token' => $token,
            ],
        ]);
    }

    // logout (membersihkan JWT token / logout user)
    public function logout()
    {
        // hapus token supaya tidak bisa dipakai lagi
        Auth::logout();

        return response()->json([
            'success' => true,
            'message' => 'logout berhasil',
        ]);
    }

    // profile (ngasih data user yang lagi login)
    public function profile()
    {
        return response()->json([
            'success' => true,
            'message' => 'data profile berhasil diambil',
            'data' => Auth::user(),
        ]);
    }
}
