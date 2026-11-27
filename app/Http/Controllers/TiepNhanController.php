<?php

namespace App\Http\Controllers;

use App\NghiepVuSpa;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TiepNhanController extends Controller
{
    public function luu(Request $request): RedirectResponse
    {
        $data = $request->validate(['ma_khach_hang' => ['nullable', 'integer', 'min:1'], 'ten_khach_moi' => ['required_without:ma_khach_hang', 'nullable', 'string', 'max:150'], 'dien_thoai_moi' => ['required_without:ma_khach_hang', 'nullable', 'regex:/^\+?[0-9]{9,15}$/'], 'email_moi' => ['nullable', 'email', 'max:190'], 'ma_dich_vu' => ['required', 'integer'], 'ma_goi_da_mua' => ['nullable', 'integer'], 'ma_nhan_vien' => ['nullable', 'integer'], 'ma_giuong' => ['nullable', 'integer'], 'ngay' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'], 'gio' => ['required', 'date_format:H:i'], 'ghi_chu' => ['nullable', 'string', 'max:2000'], 'xac_nhan_ngay' => ['sometimes', 'boolean'], 'khoa_yeu_cau' => ['sometimes', 'uuid']]);
        DB::transaction(function () use ($request, $data): void {
            $actor = DB::table('nguoi_dung')->where('ma_nguoi_dung', $request->user()->getKey())->lockForUpdate()->first();
            abort_unless($actor && $actor->dang_hoat_dong && ($actor->vai_tro === 'admin' || ($actor->vai_tro === 'employee' && $actor->co_quyen_tiep_nhan)), 403);
            $key = $data['khoa_yeu_cau'] ?? (string) Str::uuid();
            $hash = hash('sha256', json_encode($data));
            $old = DB::table('nhat_ky_quan_tri')->where('khoa_yeu_cau', $key)->first();
            if ($old) {
                if ($old->ma_nguoi_thao_tac !== $actor->ma_nguoi_dung || (json_decode($old->du_lieu_sau, true)['request_hash'] ?? null) !== $hash) {
                    NghiepVuSpa::loi('Mã yêu cầu đã được dùng cho giao dịch khác.');
                }

                return;
            }
            if (! empty($data['ma_khach_hang'])) {
                $kh = DB::table('nguoi_dung')->where('ma_nguoi_dung', $data['ma_khach_hang'])->where('vai_tro', 'customer')->where('dang_hoat_dong', true)->lockForUpdate()->first();
                if (! $kh) {
                    NghiepVuSpa::loi('Khách hàng không hợp lệ.');
                }
                $khach = $kh->ma_nguoi_dung;
            } else {
                if (! empty($data['email_moi']) && DB::table('nguoi_dung')->where('dia_chi_thu_dien_tu', $data['email_moi'])->exists()) {
                    NghiepVuSpa::loi('Email đã thuộc một hồ sơ khác. Hãy chọn đúng khách hàng.');
                }
                $khach = DB::table('nguoi_dung')->insertGetId(['ten_nguoi_dung' => $data['ten_khach_moi'], 'so_dien_thoai' => $data['dien_thoai_moi'], 'dia_chi_thu_dien_tu' => $data['email_moi'] ?? null, 'vai_tro' => 'customer', 'mat_khau' => null, 'dang_hoat_dong' => true, 'thoi_diem_tao' => now(), 'thoi_diem_cap_nhat' => now()]);
            }
            $dv = TrangKhachController::dichVuDangBan()->where('dv.ma_dich_vu', $data['ma_dich_vu'])->first();
            if (! $dv) {
                NghiepVuSpa::loi('Dịch vụ không còn nhận lịch.');
            }
            $start = Carbon::parse($data['ngay'].' '.$data['gio']);
            $end = $start->copy()->addMinutes($dv->thoi_luong_phut);
            if ($start->isPast() || ! $start->isSameDay($end) || DB::table('lich_hen')->where('ma_khach_hang', $khach)->whereIn('trang_thai', ['pending', 'confirmed'])->where('thoi_diem_bat_dau', '<', $end)->where('thoi_diem_ket_thuc', '>', $start)->exists()) {
                NghiepVuSpa::loi('Giờ không hợp lệ hoặc khách đã có lịch trùng.');
            }
            $goi = null;
            if (! empty($data['ma_goi_da_mua'])) {
                $goi = DB::table('goi_da_mua')->where('ma_goi_da_mua', $data['ma_goi_da_mua'])->lockForUpdate()->first();
                $paid = $goi && DB::table('hoa_don')->where('ma_goi_da_mua', $goi->ma_goi_da_mua)->where('trang_thai_thanh_toan', 'paid')->where('trang_thai_xu_ly', '<>', 'error')->exists();
                $reserved = $goi ? DB::table('lich_hen')->where('ma_goi_da_mua', $goi->ma_goi_da_mua)->where('trang_thai', 'confirmed')->count() : 0;
                if (! $goi || ! $paid || $goi->ma_khach_hang !== $khach || $goi->ma_dich_vu !== $dv->ma_dich_vu || $goi->trang_thai !== 'active' || $goi->so_buoi_da_dung + $reserved >= $goi->tong_so_buoi) {
                    NghiepVuSpa::loi('Gói không đúng khách/dịch vụ, chưa thanh toán hoặc không đủ buổi.');
                }
            }
            $nv = null;
            $bed = null;
            if (! empty($data['ma_nhan_vien'])) {
                $nv = DB::table('nguoi_dung')->where('ma_nguoi_dung', $data['ma_nhan_vien'])->lockForUpdate()->first();
                $ca = $nv && DB::table('lich_lam_viec')->where('ma_nhan_vien', $nv->ma_nguoi_dung)->whereDate('ngay_lam_viec', $data['ngay'])->where('co_the_nhan_lich', true)->where('gio_bat_dau', '<=', $start->format('H:i:s'))->where('gio_ket_thuc', '>=', $end->format('H:i:s'))->exists();
                if (! $nv || $nv->vai_tro !== 'employee' || ! $nv->dang_hoat_dong || ! $ca || DB::table('lich_hen')->where('ma_nhan_vien', $nv->ma_nguoi_dung)->where('trang_thai', 'confirmed')->where('thoi_diem_bat_dau', '<', $end)->where('thoi_diem_ket_thuc', '>', $start)->exists()) {
                    NghiepVuSpa::loi('Nhân viên không đủ ca hoặc đã bận.');
                }
            }
            if (! empty($data['ma_giuong'])) {
                $bed = DB::table('giuong')->where('ma_giuong', $data['ma_giuong'])->lockForUpdate()->first();
                $room = $bed ? DB::table('phong')->where('ma_phong', $bed->ma_phong)->lockForUpdate()->first() : null;
                if (! $bed?->dang_hoat_dong || ! $room?->dang_hoat_dong || DB::table('lich_hen')->where('ma_giuong', $bed->ma_giuong)->where('trang_thai', 'confirmed')->where('thoi_diem_bat_dau', '<', $end)->where('thoi_diem_ket_thuc', '>', $start)->exists()) {
                    NghiepVuSpa::loi('Phòng/giường không hoạt động hoặc đã bận.');
                }
            }
            if (! empty($data['xac_nhan_ngay']) && (! $nv || ! $bed)) {
                NghiepVuSpa::loi('Xác nhận cần chọn đủ nhân viên và giường.');
            }
            $id = DB::table('lich_hen')->insertGetId(['ma_khach_hang' => $khach, 'ma_dich_vu' => $dv->ma_dich_vu, 'ma_goi_da_mua' => $goi?->ma_goi_da_mua, 'ma_nhan_vien' => $nv?->ma_nguoi_dung, 'ma_giuong' => $bed?->ma_giuong, 'ma_nguoi_tao_lich' => $actor->ma_nguoi_dung, 'nguon_dat_lich' => 'walk_in', 'trang_thai' => ! empty($data['xac_nhan_ngay']) ? 'confirmed' : 'pending', 'thoi_diem_bat_dau' => $start, 'thoi_diem_ket_thuc' => $end, 'ten_dich_vu_luc_dat' => $dv->ten_dich_vu, 'gia_dich_vu_luc_dat' => $dv->gia, 'ghi_chu_nhan_vien' => $data['ghi_chu'] ?? null, 'thoi_diem_tao' => now(), 'thoi_diem_cap_nhat' => now()]);
            if (! empty($data['xac_nhan_ngay'])) {
                NghiepVuSpa::nhacLich(DB::table('lich_hen')->where('ma_lich_hen', $id)->first());
            }
            DB::table('nhat_ky_quan_tri')->insert(['ma_nguoi_thao_tac' => $actor->ma_nguoi_dung, 'loai_doi_tuong' => 'user', 'ma_doi_tuong' => $khach, 'hanh_dong' => 'book_for_customer', 'du_lieu_sau' => json_encode(['ma_lich_hen' => $id, 'request_hash' => $hash]), 'ly_do' => 'Tiếp nhận lịch tại quầy', 'thoi_diem_thao_tac' => now(), 'khoa_yeu_cau' => $key]);
        }, 3);

        return back()->with('thong_bao', 'Đã tiếp nhận lịch hộ khách và kiểm tra điều kiện. Chưa thu tiền hoặc trừ buổi gói.');
    }
}
