<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class GiaoDichController extends Controller
{
    public function hienThi(Request $request): View
    {
        $actor = $request->user();
        abort_unless($actor->vai_tro === 'admin' || ($actor->vai_tro === 'employee' && ($actor->co_quyen_thu_tien || $actor->co_quyen_tiep_nhan)), 403);
        $coTheThu = $actor->vai_tro === 'admin' || $actor->co_quyen_thu_tien;
        $deNghiMua = $coTheThu ? DB::table('de_nghi_mua_goi as dn')
            ->join('nguoi_dung as kh', 'kh.ma_nguoi_dung', '=', 'dn.ma_khach_hang')
            ->join('goi_lieu_trinh as g', 'g.ma_goi_lieu_trinh', '=', 'dn.ma_goi_lieu_trinh')
            ->whereNull('dn.ma_goi_da_mua')->select('dn.*', 'kh.ten_nguoi_dung', 'g.ten_goi', 'g.gia')
            ->orderBy('dn.ma_de_nghi')->paginate(10, pageName: 'de_nghi') : null;

        return view('pages.giao-dich', ['coTheThu' => $coTheThu, 'deNghiMua' => $deNghiMua,
            'coTheNhan' => $actor->vai_tro === 'admin' || $actor->co_quyen_tiep_nhan,
            'nhanVien' => DB::table('nguoi_dung')->where('vai_tro', 'employee')->where('dang_hoat_dong', true)->get(),
            'giuong' => DB::table('giuong as g')->join('phong as p', 'p.ma_phong', '=', 'g.ma_phong')->where('g.dang_hoat_dong', true)->where('p.dang_hoat_dong', true)->select('g.*', 'p.ten_phong')->get(),
            'goiDaMua' => DB::table('goi_da_mua')->where('trang_thai', 'active')->get(),
            'dichVu' => TrangKhachController::dichVuDangBan()->get(),
            'khach' => DB::table('nguoi_dung')->where('vai_tro', 'customer')->where('dang_hoat_dong', true)->get(),
            'goi' => TrangKhachController::goiDangBan()->get(),
            'hoaDon' => DB::table('hoa_don as hd')->join('nguoi_dung as kh', 'kh.ma_nguoi_dung', '=', 'hd.ma_khach_hang')
                ->where('hd.trang_thai_thanh_toan', 'unpaid')->when($actor->vai_tro !== 'admin' && ! $actor->co_quyen_thu_tien, fn ($q) => $q->whereRaw('1=0'))
                ->select('hd.*', 'kh.ten_nguoi_dung as khach_hang')->orderByDesc('hd.ma_hoa_don')->paginate(10),
            'lichLe' => DB::table('lich_hen as lh')->join('nguoi_dung as kh', 'kh.ma_nguoi_dung', '=', 'lh.ma_khach_hang')
                ->where('lh.trang_thai', 'completed')->whereNull('lh.ma_goi_da_mua')->when($actor->vai_tro !== 'admin' && ! $actor->co_quyen_thu_tien, fn ($q) => $q->whereRaw('1=0'))
                ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('hoa_don')->whereColumn('hoa_don.ma_lich_hen', 'lh.ma_lich_hen'))
                ->select('lh.*', 'kh.ten_nguoi_dung as khach_hang')->orderByDesc('lh.ma_lich_hen')->limit(50)->get()]);
    }
}
