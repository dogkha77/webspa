<?php

namespace App\Http\Controllers;

use App\NghiepVuSpa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HoSoController extends Controller
{
    public function hienThi(Request $request): View
    {
        $hangThanhVien = DB::table('hang_thanh_vien')->where('ma_hang_thanh_vien', $request->user()->ma_hang_thanh_vien)->first();

        return view('auth.tai-khoan', compact('hangThanhVien'));
    }

    public function lichHen(Request $request): View
    {
        abort_unless($request->user()->vai_tro === 'customer', 403);
        $filters = $request->validate(['trang_thai' => ['nullable', 'in:pending,confirmed,completed,cancelled,rejected']]);
        $lichCuaToi = DB::table('lich_hen as lh')
            ->leftJoin('nguoi_dung as nv', 'nv.ma_nguoi_dung', '=', 'lh.ma_nhan_vien')
            ->where('lh.ma_khach_hang', $request->user()->getKey())
            ->when(! empty($filters['trang_thai']), fn ($q) => $q->where('lh.trang_thai', $filters['trang_thai']))
            ->select('lh.*', 'nv.ten_nguoi_dung as nhan_vien_phuc_vu')
            ->orderByDesc('lh.ma_lich_hen')->paginate(5)->withQueryString();

        return view('pages.lich-hen-cua-ban', compact('lichCuaToi'));
    }

    public function goi(Request $request): View
    {
        abort_unless($request->user()->vai_tro === 'customer', 403);
        $cacGoi = DB::table('goi_da_mua as gm')->leftJoin('hoa_don as hd', 'hd.ma_goi_da_mua', '=', 'gm.ma_goi_da_mua')
            ->where('gm.ma_khach_hang', $request->user()->getKey())
            ->select('gm.*', 'hd.so_hoa_don', 'hd.tien_can_thu', 'hd.so_tien_da_thu', 'hd.trang_thai_thanh_toan',
                'hd.phuong_thuc_thanh_toan', 'hd.thoi_diem_thanh_toan', 'hd.trang_thai_xu_ly')
            ->orderByDesc('gm.ma_goi_da_mua')->paginate(10);
        $luotDung = DB::table('luot_su_dung_goi')->whereIn('ma_goi_da_mua', $cacGoi->pluck('ma_goi_da_mua'))->orderByDesc('thoi_diem_su_dung')->get()->groupBy('ma_goi_da_mua');
        $goiCoTheDat = DatLichController::goiCoTheDat($request->user()->getKey())->keyBy('ma_goi_da_mua');
        $deNghiMua = DB::table('de_nghi_mua_goi as dn')->join('goi_lieu_trinh as g', 'g.ma_goi_lieu_trinh', '=', 'dn.ma_goi_lieu_trinh')
            ->leftJoin('goi_da_mua as gm', 'gm.ma_goi_da_mua', '=', 'dn.ma_goi_da_mua')
            ->leftJoin('hoa_don as hd', 'hd.ma_goi_da_mua', '=', 'gm.ma_goi_da_mua')
            ->where('dn.ma_khach_hang', $request->user()->getKey())
            ->select('dn.ma_de_nghi', 'dn.ma_goi_da_mua', 'dn.thoi_diem_gui', 'g.ten_goi', 'hd.so_hoa_don',
                'hd.trang_thai_thanh_toan', 'hd.trang_thai_xu_ly', 'hd.tien_can_thu', 'hd.so_tien_da_thu')
            ->orderByDesc('dn.ma_de_nghi')->paginate(10, pageName: 'de_nghi');

        return view('pages.goi-cua-toi', compact('cacGoi', 'luotDung', 'goiCoTheDat', 'deNghiMua'));
    }

    public function luu(Request $request): RedirectResponse
    {
        $data = $request->validate(['ten_nguoi_dung' => ['required', 'string', 'max:100'],
            'so_dien_thoai' => ['nullable', 'regex:/^\+?[0-9]{9,15}$/'],
            'dia_chi_thu_dien_tu' => ['required', 'email', 'max:190', Rule::unique('nguoi_dung')->ignore($request->user()->getKey(), 'ma_nguoi_dung')],
            'mat_khau_hien_tai' => ['required', 'string'], 'password' => ['nullable', 'string', 'min:8', 'max:100', 'confirmed']]);
        DB::transaction(function () use ($request, $data): void {
            $actor = DB::table('nguoi_dung')->where('ma_nguoi_dung', $request->user()->getKey())->lockForUpdate()->first();
            abort_unless($actor && $actor->dang_hoat_dong, 403);
            if (! Hash::check($data['mat_khau_hien_tai'], $actor->mat_khau)) {
                NghiepVuSpa::loi('Mật khẩu hiện tại không đúng.');
            }
            $values = ['ten_nguoi_dung' => $data['ten_nguoi_dung'], 'so_dien_thoai' => $data['so_dien_thoai'] ?? null,
                'dia_chi_thu_dien_tu' => $data['dia_chi_thu_dien_tu'], 'phien_ban' => $actor->phien_ban + 1, 'thoi_diem_cap_nhat' => now()];
            NghiepVuSpa::nhatKy($actor->ma_nguoi_dung, 'user', $actor->ma_nguoi_dung, 'update_profile',
                ['ten_nguoi_dung' => $actor->ten_nguoi_dung, 'so_dien_thoai' => $actor->so_dien_thoai, 'dia_chi_thu_dien_tu' => $actor->dia_chi_thu_dien_tu], $values, 'Chủ tài khoản cập nhật thông tin');
            if (! empty($data['password'])) {
                $values['mat_khau'] = Hash::make($data['password']);
            }
            DB::table('nguoi_dung')->where('ma_nguoi_dung', $actor->ma_nguoi_dung)->update($values);
        });
        $request->session()->regenerate();

        return back()->with('thong_bao', 'Đã cập nhật thông tin cá nhân.');
    }
}
