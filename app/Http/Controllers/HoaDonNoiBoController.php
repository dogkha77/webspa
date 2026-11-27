<?php

namespace App\Http\Controllers;

use App\NghiepVuSpa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class HoaDonNoiBoController extends Controller
{
    public function lap(Request $request, int $lich): RedirectResponse
    {
        DB::transaction(function () use ($request, $lich): void {
            $actor = DB::table('nguoi_dung')->where('ma_nguoi_dung', $request->user()->getKey())->lockForUpdate()->first();
            abort_unless($actor && $actor->dang_hoat_dong && ($actor->vai_tro === 'admin' || ($actor->vai_tro === 'employee' && $actor->co_quyen_thu_tien)), 403);
            $row = DB::table('lich_hen')->where('ma_lich_hen', $lich)->lockForUpdate()->first();
            abort_unless($row, 404);
            if ($row->trang_thai !== 'completed' || $row->ma_goi_da_mua) {
                throw ValidationException::withMessages(['lich' => 'Chỉ lập hóa đơn dịch vụ lẻ đã hoàn thành; không thu lại buổi trong gói.']);
            }
            if (DB::table('hoa_don')->where('ma_lich_hen', $lich)->exists()) {
                return;
            }
            $gia = NghiepVuSpa::gia($row->ma_khach_hang, $row->gia_dich_vu_luc_dat);
            DB::table('hoa_don')->insert($gia + ['so_hoa_don' => 'HD-'.Str::upper((string) Str::ulid()), 'ma_khach_hang' => $row->ma_khach_hang,
                'ma_lich_hen' => $lich, 'ma_nguoi_lap' => $actor->ma_nguoi_dung, 'tien_truoc_giam' => $row->gia_dich_vu_luc_dat,
                'thanh_tien' => $row->gia_dich_vu_luc_dat, 'ten_noi_dung_luc_lap' => $row->ten_dich_vu_luc_dat,
                'tien_can_thu' => $gia['thanh_tien'], 'thoi_diem_tao' => now(), 'thoi_diem_cap_nhat' => now()]);
        });

        return redirect()->route('admin.trang', ['muc' => 'hoa-don'])->with('thong_bao', 'Đã mở danh sách hóa đơn. Mỗi lịch chỉ có một hóa đơn.');
    }

    /**
     * Ghi nhận khoản spa đã đối chiếu. Nếu là hóa đơn mua gói, kích hoạt gói trong cùng giao dịch.
     */
    public function thanhToan(Request $request, int $hoaDon): RedirectResponse
    {
        $data = $request->validate(['phuong_thuc' => ['required', 'in:cash,bank_transfer,no_charge'], 'xac_nhan' => ['accepted'], 'phien_ban' => ['required', 'integer']]);
        DB::transaction(function () use ($request, $hoaDon, $data): void {
            $actor = DB::table('nguoi_dung')->where('ma_nguoi_dung', $request->user()->getKey())->lockForUpdate()->first();
            abort_unless($actor && $actor->dang_hoat_dong && ($actor->vai_tro === 'admin' || ($actor->vai_tro === 'employee' && $actor->co_quyen_thu_tien)), 403);
            $pre = DB::table('hoa_don')->where('ma_hoa_don', $hoaDon)->first();
            abort_unless($pre, 404);
            $goi = $pre->ma_goi_da_mua ? DB::table('goi_da_mua')->where('ma_goi_da_mua', $pre->ma_goi_da_mua)->lockForUpdate()->first() : null;
            $row = DB::table('hoa_don')->where('ma_hoa_don', $hoaDon)->lockForUpdate()->first();
            if ($row->trang_thai_thanh_toan === 'paid') {
                return;
            }
            if ($row->trang_thai_xu_ly === 'error' || $row->phien_ban !== (int) $data['phien_ban'] || ($goi && $goi->trang_thai !== 'pending_payment')) {
                throw ValidationException::withMessages(['hoa_don' => 'Hóa đơn đang sai sót, đã thay đổi hoặc gói không còn chờ thanh toán.']);
            }
            if (((float) $row->tien_can_thu === 0.0) !== ($data['phuong_thuc'] === 'no_charge')) {
                throw ValidationException::withMessages(['phuong_thuc' => 'Phương thức phải phù hợp với khoản tiền cần thu.']);
            }
            DB::table('hoa_don')->where('ma_hoa_don', $hoaDon)->update(['trang_thai_thanh_toan' => 'paid', 'so_tien_da_thu' => $row->tien_can_thu,
                'ma_nguoi_thu' => $actor->ma_nguoi_dung, 'phuong_thuc_thanh_toan' => $data['phuong_thuc'], 'thoi_diem_thanh_toan' => now(),
                'phien_ban' => $row->phien_ban + 1, 'thoi_diem_cap_nhat' => now()]);
            if ($goi) {
                DB::table('goi_da_mua')->where('ma_goi_da_mua', $goi->ma_goi_da_mua)->update(['gia_tri_cap_von' => $row->thanh_tien,
                    'trang_thai' => 'active', 'phien_ban' => $goi->phien_ban + 1, 'thoi_diem_cap_nhat' => now()]);
            }
            DB::table('nhat_ky_quan_tri')->insert(['ma_nguoi_thao_tac' => $actor->ma_nguoi_dung, 'loai_doi_tuong' => 'invoice', 'ma_doi_tuong' => $hoaDon,
                'hanh_dong' => 'confirm_payment', 'du_lieu_sau' => json_encode(['so_tien_da_thu' => $row->tien_can_thu, 'phuong_thuc' => $data['phuong_thuc']]),
                'ly_do' => 'Đã đối chiếu và xác nhận đủ khoản phải thu', 'thoi_diem_thao_tac' => now(), 'khoa_yeu_cau' => (string) Str::uuid()]);
        });

        return back()->with('thong_bao', 'Đã xác nhận khoản thanh toán. Gửi lặp không ghi thêm khoản thu.');
    }

    public function xuat(Request $request, int $hoaDon): View
    {
        abort_unless($request->user()->vai_tro === 'admin' || ($request->user()->vai_tro === 'employee' && $request->user()->co_quyen_thu_tien), 403);
        $hd = DB::table('hoa_don as hd')->join('nguoi_dung as kh', 'kh.ma_nguoi_dung', '=', 'hd.ma_khach_hang')
            ->where('hd.ma_hoa_don', $hoaDon)->select('hd.*', 'kh.ten_nguoi_dung as khach_hang')->first();
        abort_unless($hd, 404);

        return view('noi-bo.hoa-don', compact('hd'));
    }

    public function trangThai(Request $request, int $hoaDon): RedirectResponse
    {
        $data = $request->validate(['trang_thai_xu_ly' => ['required', 'in:error,resolved'], 'ly_do' => ['required', 'string', 'max:2000'], 'phien_ban' => ['required', 'integer']]);
        DB::transaction(function () use ($request, $hoaDon, $data): void {
            $actor = DB::table('nguoi_dung')->where('ma_nguoi_dung', $request->user()->getKey())->lockForUpdate()->first();
            abort_unless($actor && $actor->dang_hoat_dong && ($actor->vai_tro === 'admin' || ($actor->vai_tro === 'employee' && $actor->co_quyen_thu_tien)), 403);
            $row = DB::table('hoa_don')->where('ma_hoa_don', $hoaDon)->lockForUpdate()->first();
            abort_unless($row, 404);
            abort_unless($actor->vai_tro === 'admin', 403);
            $next = $data['trang_thai_xu_ly'];
            if ($row->phien_ban !== (int) $data['phien_ban'] || ! in_array($row->trang_thai_xu_ly.'>'.$next, ['normal>error', 'resolved>error', 'error>resolved'], true)) {
                throw ValidationException::withMessages(['hoa_don' => 'Trạng thái đã thay đổi hoặc bước chuyển không hợp lệ.']);
            }
            DB::table('nhat_ky_hoa_don')->insert(['ma_hoa_don' => $hoaDon, 'ma_nguoi_thao_tac' => $actor->ma_nguoi_dung,
                'trang_thai_truoc' => $row->trang_thai_xu_ly, 'trang_thai_sau' => $next, 'ly_do' => $data['ly_do'],
                'noi_dung_doi_soat' => $data['ly_do'], 'thoi_diem_thao_tac' => now(), 'phien_ban_hoa_don' => $row->phien_ban + 1]);
            DB::table('hoa_don')->where('ma_hoa_don', $hoaDon)->update(['trang_thai_xu_ly' => $next, 'phien_ban' => $row->phien_ban + 1, 'thoi_diem_cap_nhat' => now()]);
        });

        return back()->with('thong_bao', 'Đã lưu trạng thái và lịch sử hóa đơn. Nội dung và tiền thanh toán được giữ nguyên.');
    }
}
