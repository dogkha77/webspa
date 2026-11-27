<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class KiemTraTaiKhoanHoatDong
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->user()->dang_hoat_dong) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'dia_chi_thu_dien_tu' => 'Tài khoản đã ngừng hoạt động. Vui lòng liên hệ spa.',
            ]);
        }

        return $next($request);
    }
}
