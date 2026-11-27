<?php

namespace App\Http\Controllers;

use App\Http\Requests\DangKyRequest;
use App\Http\Requests\DangNhapRequest;
use App\Models\NguoiDung;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class XacThucController extends Controller
{
    public function hienThiDangKy(): View
    {
        return view('auth.dang-ky');
    }

    public function dangKy(DangKyRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $nguoiDung = new NguoiDung;
        $nguoiDung->ten_nguoi_dung = $data['ten_nguoi_dung'];
        $nguoiDung->dia_chi_thu_dien_tu = $data['dia_chi_thu_dien_tu'];
        $nguoiDung->so_dien_thoai = $data['so_dien_thoai'] ?? null;
        $nguoiDung->mat_khau = $data['password'];
        $nguoiDung->vai_tro = 'customer';
        $nguoiDung->dang_hoat_dong = true;

        try {
            $nguoiDung->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'dia_chi_thu_dien_tu' => 'Email đã được sử dụng. Vui lòng dùng email khác.',
            ]);
        }

        return redirect()->route('login')
            ->withInput(['dia_chi_thu_dien_tu' => $nguoiDung->dia_chi_thu_dien_tu])
            ->with('thong_bao', 'Đăng ký thành công. Bạn hãy đăng nhập để sử dụng tài khoản.');
    }

    public function hienThiDangNhap(Request $request): View
    {
        if (! $this->trangDichHopLe($request, $request->session()->get('url.intended'))) {
            $trangTruoc = $this->trangDichHopLe($request, url()->previous());

            if ($trangTruoc !== null) {
                $request->session()->put('url.intended', $trangTruoc);
            } else {
                $request->session()->forget('url.intended');
            }
        }

        return view('auth.dang-nhap');
    }

    public function dangNhap(DangNhapRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $key = 'dang-nhap:'.hash('sha256', $data['dia_chi_thu_dien_tu'].'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'dia_chi_thu_dien_tu' => "Bạn đã thử quá nhiều lần. Vui lòng thử lại sau {$seconds} giây.",
            ]);
        }

        if (! Auth::attempt([
            'dia_chi_thu_dien_tu' => $data['dia_chi_thu_dien_tu'],
            'password' => $data['password'],
            'dang_hoat_dong' => true,
        ])) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages([
                'dia_chi_thu_dien_tu' => 'Email hoặc mật khẩu không đúng, hoặc tài khoản đã ngừng hoạt động.',
            ]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        $trangDich = $this->trangDichHopLe($request, $request->session()->pull('url.intended'));
        $khuVuc = match ($request->user()->vai_tro) {
            'admin' => 'admin',
            'employee' => 'nhan-vien',
            default => null,
        };
        if ($khuVuc !== null) {
            $dungKhuVuc = $trangDich !== null
                && Route::getRoutes()->match(Request::create($trangDich, 'GET'))->named($khuVuc.'.*');
            if (! $dungKhuVuc) {
                $trangDich = route($khuVuc.'.trang');
            }
        }

        return redirect()->to($trangDich ?? route('trang-chu'))
            ->with('thong_bao', 'Đăng nhập thành công.');
    }

    public function dangXuat(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('trang-chu')->with('thong_bao', 'Bạn đã đăng xuất.');
    }

    private function trangDichHopLe(Request $request, mixed $url): ?string
    {
        if (! is_string($url) || $url === '' || preg_match('/[\x00-\x20\x7f\\\\]/', $url)) {
            return null;
        }

        $origin = $request->getSchemeAndHttpHost();

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            $url = $origin.$url;
        }

        if ($url !== $origin
            && ! str_starts_with($url, $origin.'/')
            && ! str_starts_with($url, $origin.'?')
            && ! str_starts_with($url, $origin.'#')) {
            return null;
        }

        try {
            $route = Route::getRoutes()->match(Request::create($url, 'GET'));
        } catch (NotFoundHttpException|MethodNotAllowedHttpException) {
            return null;
        }

        if (! in_array('web', $route->gatherMiddleware(), true)
            || $route->named('login', 'login.store', 'register', 'register.store', 'logout')) {
            return null;
        }

        if ($request->user()) {
            foreach ($route->gatherMiddleware() as $middleware) {
                if (str_starts_with($middleware, 'vai-tro:')
                    && ! in_array($request->user()->vai_tro, explode(',', substr($middleware, 8)), true)) {
                    return null;
                }
            }
        }

        return $url;
    }
}
