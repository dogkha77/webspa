<?php

namespace App\Http\Controllers;

use App\NghiepVuSpa;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LichNoiBoController extends Controller
{
    /**
     * Xử lý lịch theo quyền của người thao tác. Khi hoàn thành lịch dùng gói,
     * ghi lượt sử dụng và cập nhật số buổi trong cùng giao dịch với trạng thái lịch.
     */
    public function capNhat(Request $request, int $lich): RedirectResponse
    {
        $data = $request->validate(['hanh_dong' => ['required', 'in:confirmed,rejected,cancelled,completed,note'],
            'ma_nhan_vien' => ['nullable', 'integer'], 'ma_giuong' => ['nullable', 'integer'],
            'ghi_chu' => ['nullable', 'string', 'max:2000'], 'phien_ban' => ['required', 'integer', 'min:1'],
            'xac_nhan_dich_vu' => ['nullable', 'string', 'size:64']]);
        DB::transaction(function () use ($request, $lich, $data): void {
            $before = DB::table('lich_hen')->where('ma_lich_hen', $lich)->first();
            abort_unless($before, 404);
            $ids = array_filter([$request->user()->getKey(), $before->ma_khach_hang, $before->ma_nhan_vien, $data['ma_nhan_vien'] ?? null]);
            $users = DB::table('nguoi_dung')->whereIn('ma_nguoi_dung', $ids)->orderBy('ma_nguoi_dung')->lockForUpdate()->get()->keyBy('ma_nguoi_dung');
            $actor = $users[$request->user()->getKey()];
            abort_unless($actor->dang_hoat_dong && in_array($actor->vai_tro, ['admin', 'employee'], true), 403);
            $goi = $before->ma_goi_da_mua ? DB::table('goi_da_mua')->where('ma_goi_da_mua', $before->ma_goi_da_mua)->lockForUpdate()->first() : null;
            $row = DB::table('lich_hen')->where('ma_lich_hen', $lich)->lockForUpdate()->first();
            // Sau khi khóa lịch, so phiên bản và liên kết để phát hiện thay đổi kể từ lúc biểu mẫu hoặc giao dịch đọc dữ liệu.
            if ($row->phien_ban !== (int) $data['phien_ban'] || $row->ma_goi_da_mua !== $before->ma_goi_da_mua || $row->ma_nhan_vien !== $before->ma_nhan_vien) {
                throw ValidationException::withMessages(['lich' => 'Lịch đã thay đổi. Vui lòng tải lại trước khi thao tác.']);
            }
            $action = $data['hanh_dong'];
            $admin = $actor->vai_tro === 'admin';
            if (in_array($action, ['note', 'completed'], true)) {
                abort_unless($admin || $row->ma_nhan_vien === $actor->ma_nguoi_dung, 403);
            } else {
                abort_unless($admin || ($actor->vai_tro === 'employee' && $actor->co_quyen_tiep_nhan), 403);
            }
            $update = ['ghi_chu_nhan_vien' => $data['ghi_chu'] ?? $row->ghi_chu_nhan_vien,
                'phien_ban' => $row->phien_ban + 1, 'thoi_diem_cap_nhat' => now()];
            if ($action === 'confirmed') {
                if ($row->trang_thai !== 'pending' || Carbon::parse($row->thoi_diem_bat_dau)->isPast()) {
                    throw ValidationException::withMessages(['lich' => 'Chỉ xác nhận yêu cầu còn chờ và chưa đến giờ.']);
                }
                $khach = $users[$row->ma_khach_hang] ?? null;
                $dichVu = TrangKhachController::dichVuDangBan()->where('dv.ma_dich_vu', $row->ma_dich_vu)->lockForUpdate()->first();
                if (! $khach || ! $khach->dang_hoat_dong || $khach->vai_tro !== 'customer' || ! $dichVu) {
                    throw ValidationException::withMessages(['lich' => 'Khách hàng hoặc dịch vụ không còn đủ điều kiện để xác nhận lịch.']);
                }
                $maRaSoat = NghiepVuSpa::maRaSoatDichVu($row, $dichVu->gia, $dichVu->thoi_luong_phut);
                if ($maRaSoat !== null && $maRaSoat !== ($data['xac_nhan_dich_vu'] ?? null)) {
                    throw ValidationException::withMessages(['xac_nhan_dich_vu' => 'Giá hoặc thời lượng dịch vụ đã thay đổi. Tải lại lịch, đối chiếu thông tin và trao đổi với khách trước khi duyệt.']);
                }
                if ($row->ma_nhan_vien && $row->nguon_dat_lich === 'online' && $row->ma_nhan_vien !== (int) ($data['ma_nhan_vien'] ?? 0)) {
                    throw ValidationException::withMessages(['ma_nhan_vien' => 'Cần giữ nhân viên khách đã chọn; nếu không thể phục vụ hãy trao đổi và xử lý lại lịch.']);
                }
                $nv = $users[$data['ma_nhan_vien'] ?? 0] ?? null;
                $giuong = DB::table('giuong')->where('ma_giuong', $data['ma_giuong'] ?? 0)->lockForUpdate()->first();
                $phong = $giuong ? DB::table('phong')->where('ma_phong', $giuong->ma_phong)->lockForUpdate()->first() : null;
                if (! $nv || $nv->vai_tro !== 'employee' || ! $nv->dang_hoat_dong || ! $giuong || ! $giuong->dang_hoat_dong || ! $phong?->dang_hoat_dong) {
                    throw ValidationException::withMessages(['lich' => 'Cần chọn nhân viên, phòng và giường đang hoạt động.']);
                }
                $start = Carbon::parse($row->thoi_diem_bat_dau);
                $end = Carbon::parse($row->thoi_diem_ket_thuc);
                $coCa = $start->isSameDay($end) && DB::table('lich_lam_viec')->where('ma_nhan_vien', $nv->ma_nguoi_dung)
                    ->whereDate('ngay_lam_viec', $start)->where('co_the_nhan_lich', true)->where('gio_bat_dau', '<=', $start->format('H:i:s'))
                    ->where('gio_ket_thuc', '>=', $end->format('H:i:s'))->exists();
                $trung = DB::table('lich_hen')->where('ma_lich_hen', '<>', $lich)->where('trang_thai', 'confirmed')
                    ->where('thoi_diem_bat_dau', '<', $end)->where('thoi_diem_ket_thuc', '>', $start)
                    ->where(fn ($q) => $q->where('ma_nhan_vien', $nv->ma_nguoi_dung)->orWhere('ma_giuong', $giuong->ma_giuong)->orWhere('ma_khach_hang', $row->ma_khach_hang))
                    ->lockForUpdate()->first(['ma_lich_hen']);
                if (! $coCa || $trung) {
                    throw ValidationException::withMessages(['lich' => 'Không đủ ca làm hoặc trùng nhân viên, giường hay khách.']);
                }
                $update += ['trang_thai' => 'confirmed', 'ma_nhan_vien' => $nv->ma_nguoi_dung, 'ma_giuong' => $giuong->ma_giuong];
            } elseif ($action === 'completed') {
                if ($row->trang_thai !== 'confirmed' || Carbon::parse($row->thoi_diem_bat_dau)->isFuture()) {
                    throw ValidationException::withMessages(['lich' => 'Chỉ hoàn thành lịch đã xác nhận và đã bắt đầu phục vụ.']);
                }
                $update += ['trang_thai' => 'completed', 'ma_nguoi_hoan_thanh' => $actor->ma_nguoi_dung, 'thoi_diem_hoan_thanh' => now()];
            } elseif (in_array($action, ['cancelled', 'rejected'], true)) {
                if (! in_array($row->trang_thai, ['pending', 'confirmed'], true) || ($action === 'rejected' && $row->trang_thai !== 'pending') || empty($data['ghi_chu'])) {
                    throw ValidationException::withMessages(['lich' => 'Trạng thái không phù hợp hoặc chưa nhập lý do xử lý.']);
                }
                $update['trang_thai'] = $action;
            }
            if ($row->ma_goi_da_mua && in_array($action, ['confirmed', 'completed'], true)) {
                $paid = DB::table('hoa_don')->where('ma_goi_da_mua', $row->ma_goi_da_mua)->where('trang_thai_thanh_toan', 'paid')
                    ->where('trang_thai_xu_ly', '<>', 'error')->lockForUpdate()->first(['ma_hoa_don']);
                // Chỉ đếm buổi giữ cho lịch khác. Nếu đang hoàn thành, lịch hiện tại sẽ chuyển từ giữ buổi sang đã dùng.
                $reserved = DB::table('lich_hen')->where('ma_goi_da_mua', $row->ma_goi_da_mua)->where('trang_thai', 'confirmed')
                    ->where('ma_lich_hen', '<>', $lich)->lockForUpdate()->get(['ma_lich_hen'])->count();
                if (! $goi || ! $paid || $goi->trang_thai !== 'active' || $goi->ma_khach_hang !== $row->ma_khach_hang || $goi->ma_dich_vu !== $row->ma_dich_vu
                    || $goi->so_buoi_da_dung + $reserved >= $goi->tong_so_buoi) {
                    throw ValidationException::withMessages(['lich' => 'Gói không đủ điều kiện hoặc số buổi còn lại đã được giữ cho lịch khác.']);
                }
                if ($action === 'completed') {
                    DB::table('luot_su_dung_goi')->insert(['ma_goi_da_mua' => $goi->ma_goi_da_mua, 'ma_lich_hen' => $lich,
                        'ma_nguoi_ghi_nhan' => $actor->ma_nguoi_dung, 'thoi_diem_su_dung' => now(), 'thoi_diem_tao' => now(), 'thoi_diem_cap_nhat' => now()]);
                    DB::table('goi_da_mua')->where('ma_goi_da_mua', $goi->ma_goi_da_mua)->update(['so_buoi_da_dung' => $goi->so_buoi_da_dung + 1,
                        'trang_thai' => $goi->so_buoi_da_dung + 1 === $goi->tong_so_buoi ? 'completed' : 'active',
                        'phien_ban' => $goi->phien_ban + 1, 'thoi_diem_cap_nhat' => now()]);
                }
            }
            DB::table('lich_hen')->where('ma_lich_hen', $lich)->update($update);
            if ($action === 'confirmed') {
                NghiepVuSpa::nhacLich((object) array_merge((array) $row, $update));
            }
            if (in_array($action, ['cancelled', 'rejected', 'completed'], true)) {
                DB::table('nhac_hen')->where('ma_lich_hen', $lich)->whereIn('trang_thai', ['pending', 'processing', 'failed'])->update(['trang_thai' => 'cancelled']);
            }
        }, 3);

        return back()->with('thong_bao', 'Đã cập nhật lịch hẹn và lưu dữ liệu. Hoàn thành dịch vụ không tự xác nhận thanh toán.');
    }
}
