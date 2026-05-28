<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class IsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // ambil data user yang sedang login dari JWT token
        $user = Auth::user();

        // kalau role bukan admin, tolak request nya
        if ($user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => "akses ditolak hanya admin yang boleh akses",
            ], 403);
        }

        // return $next : memperbolehkan untuk melanjutkan akses ke halaman
        return $next($request);
    }
}
