<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class KiemTraVaiTro
{
    public function handle(Request $request, Closure $next, string ...$vaiTro): Response
    {
        abort_unless($request->user() && in_array($request->user()->vai_tro, $vaiTro, true), 403);

        return $next($request);
    }
}
