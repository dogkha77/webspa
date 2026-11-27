<?php

namespace App\Http\Controllers;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TrangKhachController extends Controller
{
    public static function dichVuDangBan(): Builder
    {
        return DB::table('dich_vu as dv')->join('danh_muc_dich_vu as dm', 'dm.ma_danh_muc', '=', 'dv.ma_danh_muc')
            ->where('dv.dang_hoat_dong', true)->where('dm.dang_hoat_dong', true)->select('dv.*', 'dm.ten_danh_muc');
    }

    public static function goiDangBan(): Builder
    {
        return DB::table('goi_lieu_trinh as g')->join('dich_vu as dv', 'dv.ma_dich_vu', '=', 'g.ma_dich_vu')
            ->join('danh_muc_dich_vu as dm', 'dm.ma_danh_muc', '=', 'dv.ma_danh_muc')
            ->where('g.dang_hoat_dong', true)->where('dv.dang_hoat_dong', true)->where('dm.dang_hoat_dong', true)
            ->select('g.*', 'dv.ten_dich_vu', 'dv.thoi_luong_phut');
    }

    public function trangChu(): View
    {
        $services = self::dichVuDangBan()->orderBy('dv.ma_dich_vu')->limit(4)->get()->map(fn ($dv): array => [
            'id' => $dv->ma_dich_vu, 'name' => $dv->ten_dich_vu, 'description' => $dv->mo_ta,
            'duration' => $dv->thoi_luong_phut, 'price' => number_format((float) $dv->gia, 0, ',', '.'),
        ]);
        $packages = self::goiDangBan()->orderBy('g.ma_goi_lieu_trinh')->limit(3)->get()->map(fn ($g): array => [
            'tag' => $g->tong_so_buoi.' buổi', 'name' => $g->ten_goi,
            'subtitle' => $g->ten_dich_vu, 'price' => number_format((float) $g->gia, 0, ',', '.'),
            'features' => [$g->tong_so_buoi.' buổi '.$g->ten_dich_vu, $g->thoi_luong_phut.' phút / buổi'],
        ]);

        return view('welcome', compact('services', 'packages'));
    }

    public function dichVu(): View
    {
        return view('pages.dich-vu', ['dichVu' => self::dichVuDangBan()->orderBy('dv.ten_dich_vu')->get(),
            'danhMuc' => DB::table('danh_muc_dich_vu')->where('dang_hoat_dong', true)->orderBy('ten_danh_muc')->get()]);
    }

    public function goi(Request $request): View
    {
        $deNghiMua = collect();
        if ($request->user()?->vai_tro === 'customer') {
            $deNghiMua = DB::table('de_nghi_mua_goi as dn')->join('goi_lieu_trinh as g', 'g.ma_goi_lieu_trinh', '=', 'dn.ma_goi_lieu_trinh')
                ->leftJoin('goi_da_mua as gm', 'gm.ma_goi_da_mua', '=', 'dn.ma_goi_da_mua')
                ->leftJoin('hoa_don as hd', 'hd.ma_goi_da_mua', '=', 'gm.ma_goi_da_mua')
                ->where('dn.ma_khach_hang', $request->user()->getKey())
                ->select('dn.ma_de_nghi', 'dn.ma_goi_da_mua', 'dn.thoi_diem_gui', 'g.ten_goi', 'hd.so_hoa_don',
                    'hd.trang_thai_thanh_toan', 'hd.trang_thai_xu_ly', 'hd.tien_can_thu', 'hd.so_tien_da_thu')
                ->orderByDesc('dn.ma_de_nghi')->limit(5)->get();
        }

        return view('pages.goi-lieu-trinh', ['cacGoi' => self::goiDangBan()->orderBy('g.gia')->get(), 'deNghiMua' => $deNghiMua]);
    }

    public function lienHe(): View
    {
        return view('pages.lien-he', ['dichVu' => self::dichVuDangBan()->orderBy('dv.ten_dich_vu')->get()]);
    }

    public function veSpa(): View
    {
        return view('pages.ve-spa', ['soDichVu' => self::dichVuDangBan()->count(),
            'soNhanVien' => DB::table('nguoi_dung')->where('vai_tro', 'employee')->where('dang_hoat_dong', true)->count(),
            'soPhong' => DB::table('phong')->where('dang_hoat_dong', true)->count()]);
    }
}
