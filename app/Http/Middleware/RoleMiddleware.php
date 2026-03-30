<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class RoleMiddleware
{
    public function handle($request, Closure $next, ...$roles)
    {
        $user = Auth::user();

        if (!$user) {
            return redirect()->route('login');
        }

        // Super admin bisa akses semua
        if ($user->role === 'super_admin') {
            return $next($request);
        }

        // Cek apakah user role termasuk dalam roles yang diizinkan
        if (in_array($user->role, $roles)) {
            return $next($request);
        }

        return redirect()->route('barang_master.index')->with('error', 'Anda tidak memiliki akses');
    }
}
