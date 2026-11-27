<?php

use App\Http\Controllers\ChuyenGoiController;
use App\Http\Controllers\DatLichController;
use App\Http\Controllers\DoiLichController;
use App\Http\Controllers\GiaoDichController;
use App\Http\Controllers\HoaDonNoiBoController;
use App\Http\Controllers\HoSoController;
use App\Http\Controllers\LichNoiBoController;
use App\Http\Controllers\MuaGoiController;
use App\Http\Controllers\QuanTriDuLieuController;
use App\Http\Controllers\TiepNhanController;
use App\Http\Controllers\TrangKhachController;
use App\Http\Controllers\VanHanhController;
use App\Http\Controllers\XacThucController;
use Illuminate\Support\Facades\Route;

Route::middleware('khu-vuc-khach')->group(function (): void {
    Route::get('/', [TrangKhachController::class, 'trangChu'])->name('trang-chu');
    Route::get('/dich-vu', [TrangKhachController::class, 'dichVu'])->name('dich-vu');
    Route::get('/goi-lieu-trinh', [TrangKhachController::class, 'goi'])->name('goi-lieu-trinh');
    Route::get('/ve-spa', [TrangKhachController::class, 'veSpa'])->name('ve-spa');
    Route::get('/lien-he', [TrangKhachController::class, 'lienHe'])->name('lien-he');
});

Route::middleware('guest')->group(function (): void {
    Route::get('/dang-ky', [XacThucController::class, 'hienThiDangKy'])->name('register');
    Route::post('/dang-ky', [XacThucController::class, 'dangKy'])->middleware('throttle:10,1')->name('register.store');
    Route::get('/dang-nhap', [XacThucController::class, 'hienThiDangNhap'])->name('login');
    Route::post('/dang-nhap', [XacThucController::class, 'dangNhap'])->middleware('throttle:20,1')->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/tai-khoan', [HoSoController::class, 'hienThi'])->name('tai-khoan');
    Route::post('/tai-khoan', [HoSoController::class, 'luu'])->middleware('throttle:10,1')->name('tai-khoan.luu');
    Route::post('/dang-xuat', [XacThucController::class, 'dangXuat'])->name('logout');

    Route::get('/lich-hen-cua-ban', [HoSoController::class, 'lichHen'])->name('lich-hen-cua-ban');
    Route::get('/dat-lich', [DatLichController::class, 'hienThi'])->name('dat-lich');
    Route::get('/dat-lich/nhan-vien', [DatLichController::class, 'nhanVienTrong'])->middleware('throttle:60,1')->name('dat-lich.nhan-vien');
    Route::get('/dat-lich/khung-gio', [DatLichController::class, 'khungGio'])->middleware('throttle:60,1')->name('dat-lich.khung-gio');
    Route::post('/dat-lich', [DatLichController::class, 'luu'])->middleware('throttle:15,1')->name('dat-lich.luu');
    Route::post('/dat-lich/{lich}/rut', [DatLichController::class, 'huy'])->whereNumber('lich')->name('dat-lich.huy');
    Route::get('/lich-hen/{id}/lich-su', [DoiLichController::class, 'lichSu'])->whereNumber('id')->name('lich.lich-su');
    Route::post('/lich-hen/{id}/doi-lich', [DoiLichController::class, 'luu'])->whereNumber('id')->name('lich.doi');

    Route::get('/goi-cua-toi', [HoSoController::class, 'goi'])->name('goi-cua-toi');
    Route::post('/goi-lieu-trinh/{goi}/dang-ky', [MuaGoiController::class, 'deNghi'])->whereNumber('goi')->middleware('throttle:10,1')->name('goi-lieu-trinh.dang-ky');
    Route::get('/chuyen-goi', [ChuyenGoiController::class, 'hienThi'])->name('chuyen-goi');
    Route::post('/chuyen-goi', [ChuyenGoiController::class, 'deNghi'])->name('chuyen-goi.tao');
    Route::post('/chuyen-goi/{id}', [ChuyenGoiController::class, 'xuLy'])->whereNumber('id')->name('chuyen-goi.xu-ly');

    Route::post('/noi-bo/lich/{lich}', [LichNoiBoController::class, 'capNhat'])->whereNumber('lich')->name('noi-bo.lich.cap-nhat');
    Route::get('/giao-dich', [GiaoDichController::class, 'hienThi'])->name('giao-dich');
    Route::post('/giao-dich/tiep-nhan', [TiepNhanController::class, 'luu'])->name('giao-dich.tiep-nhan');
    Route::post('/giao-dich/mua-goi', [QuanTriDuLieuController::class, 'luu'])->defaults('loai', 'lieu-trinh')->name('giao-dich.mua-goi');
    Route::get('/quan-tri/hoa-don/{hoaDon}/xuat', [HoaDonNoiBoController::class, 'xuat'])->whereNumber('hoaDon')->name('admin.hoa-don.xuat');
    Route::post('/quan-tri/hoa-don/tu-lich/{lich}', [HoaDonNoiBoController::class, 'lap'])->whereNumber('lich')->name('admin.hoa-don.lap');
    Route::post('/quan-tri/hoa-don/{hoaDon}/thanh-toan', [HoaDonNoiBoController::class, 'thanhToan'])->whereNumber('hoaDon')->name('admin.hoa-don.thanh-toan');
});

Route::prefix('admin')->middleware(['auth', 'vai-tro:admin'])->group(function (): void {
    Route::get('/thong-bao/lich-moi', [VanHanhController::class, 'thongBaoLichMoi'])->name('admin.thong-bao.lich-moi');
    Route::get('/{muc?}', [VanHanhController::class, 'admin'])
        ->whereIn('muc', array_keys(config('menu_spa.admin')))
        ->name('admin.trang');
});

Route::prefix('nhan-vien')->middleware(['auth', 'vai-tro:employee'])->group(function (): void {
    Route::get('/{muc?}', [VanHanhController::class, 'nhanVien'])
        ->whereIn('muc', array_keys(config('menu_spa.employee')))
        ->name('nhan-vien.trang');
});

Route::middleware(['auth', 'vai-tro:admin'])->group(function (): void {
    Route::get('/quan-tri/bao-cao.csv', [VanHanhController::class, 'xuatBaoCao'])->name('admin.bao-cao.xuat');
    Route::post('/quan-tri/hoa-don/{hoaDon}/trang-thai', [HoaDonNoiBoController::class, 'trangThai'])->whereNumber('hoaDon')->name('admin.hoa-don.trang-thai');
    Route::post('/quan-tri/du-lieu/{loai}', [QuanTriDuLieuController::class, 'luu'])->whereIn('loai', array_keys(config('bieu_mau_spa')))->name('admin.du-lieu.them');
    Route::post('/quan-tri/du-lieu/{loai}/{id}', [QuanTriDuLieuController::class, 'luu'])->whereNumber('id')->whereIn('loai', array_keys(config('bieu_mau_spa')))->name('admin.du-lieu.sua');
});
