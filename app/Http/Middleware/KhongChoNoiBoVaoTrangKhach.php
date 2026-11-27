<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class KhongChoNoiBoVaoTrangKhach
{
    public function handle(Request $request, Closure $next): Response
    {
        $trangNoiBo = match ($request->user()?->vai_tro) {
            'admin' => 'admin.trang',
            'employee' => 'nhan-vien.trang',
            default => null,
        };

        if ($trangNoiBo !== null) {
            return redirect()->route($trangNoiBo);
        }

        return $next($request);
    }
}
