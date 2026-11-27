<?php

use App\Http\Middleware\KhongLuuBoNhoDem;
use App\Http\Middleware\KiemTraTaiKhoanHoatDong;
use App\Http\Middleware\KiemTraVaiTro;
use App\Http\Middleware\KhongChoNoiBoVaoTrangKhach;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['vai-tro' => KiemTraVaiTro::class, 'khu-vuc-khach' => KhongChoNoiBoVaoTrangKhach::class]);
        $middleware->web(prepend: [KhongLuuBoNhoDem::class], append: [KiemTraTaiKhoanHoatDong::class]);
        $middleware->prependToPriorityList(AuthenticatesRequests::class, KiemTraTaiKhoanHoatDong::class);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => match ($request->user()?->vai_tro) {
            'admin' => route('admin.trang'),
            'employee' => route('nhan-vien.trang'),
            default => route('trang-chu'),
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['mat_khau_hien_tai']);
    })->create();
