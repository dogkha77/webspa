<?php

namespace App\Http\Controllers;

use App\NghiepVuSpa;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DoiLichController extends Controller
{
    public function luu(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate(['ngay' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'], 'gio' => ['required', 'date_format:H:i'],
            'ly_do' => ['required', 'string', 'max:1000'], 'phien_ban' => ['required', 'integer']]);
        DB::transaction(function () use ($request, $id, $data): void {
            $pre = DB::table('lich_hen')->where('ma_lich_hen', $id)->first();
            abort_unless($pre, 404);
            $users = DB::table('nguoi_dung')->whereIn('ma_nguoi_dung', array_filter([$request->user()->getKey(), $pre->ma_khach_hang, $pre->ma_nhan_vien]))
                ->orderBy('ma_nguoi_dung')->lockForUpdate()->get()->keyBy('ma_nguoi_dung');
            $actor = $users[$request->user()->getKey()];
            $khach = $actor->vai_tro === 'customer' && $pre->ma_khach_hang === $actor->ma_nguoi_dung;
            abort_unless($actor->dang_hoat_dong && ($khach || $actor->vai_tro === 'admin' || ($actor->vai_tro === 'employee' && $actor->co_quyen_tiep_nhan)), 403);
            $goi = $pre->ma_goi_da_mua ? DB::table('goi_da_mua')->where('ma_goi_da_mua', $pre->ma_goi_da_mua)->lockForUpdate()->first() : null;
            $lh = DB::table('lich_hen')->where('ma_lich_hen', $id)->lockForUpdate()->first();
            if ($lh->phien_ban !== (int) $data['phien_ban'] || $lh->ma_goi_da_mua !== $pre->ma_goi_da_mua || $lh->ma_nhan_vien !== $pre->ma_nhan_vien
                || ! in_array($lh->trang_thai, ['pending', 'confirmed'], true) || Carbon::parse($lh->thoi_diem_bat_dau)->isPast()) {
                NghiepVuSpa::loi('Chỉ đổi lịch chưa bắt đầu, còn chờ hoặc đã xác nhận và chưa bị thay đổi.');
            }
            if ($goi && $goi->trang_thai !== 'active') {
                NghiepVuSpa::loi('Gói đang khóa hoặc không còn hoạt động.');
            }
            $start = Carbon::createFromFormat('Y-m-d H:i', $data['ngay'].' '.$data['gio'])->startOfMinute();
            $end = $start->copy()->addMinutes(Carbon::parse($lh->thoi_diem_bat_dau)->diffInMinutes(Carbon::parse($lh->thoi_diem_ket_thuc)));
            if ($start->isPast() || ! $start->isSameDay($end)) {
                NghiepVuSpa::loi('Giờ mới phải ở tương lai và kết thúc trong cùng ngày.');
            }
            $trungKhach = DB::table('lich_hen')->where('ma_lich_hen', '<>', $id)->where('ma_khach_hang', $lh->ma_khach_hang)
                ->whereIn('trang_thai', ['pending', 'confirmed'])->where('thoi_diem_bat_dau', '<', $end)->where('thoi_diem_ket_thuc', '>', $start)->exists();
            if ($trungKhach) {
                NghiepVuSpa::loi('Khách đã có lịch trùng khoảng giờ mới.');
            }
            $values = ['thoi_diem_bat_dau' => $start, 'thoi_diem_ket_thuc' => $end, 'phien_ban' => $lh->phien_ban + 1, 'thoi_diem_cap_nhat' => now()];
            if ($khach) {
                $values += ['trang_thai' => 'pending', 'ma_nhan_vien' => null, 'ma_giuong' => null];
            } elseif ($lh->trang_thai === 'pending') {
                $values += ['ma_nhan_vien' => null, 'ma_giuong' => null];
            } elseif ($lh->trang_thai === 'confirmed') {
                $nv = $users[$lh->ma_nhan_vien] ?? null;
                $bed = DB::table('giuong')->where('ma_giuong', $lh->ma_giuong)->lockForUpdate()->first();
                $room = $bed ? DB::table('phong')->where('ma_phong', $bed->ma_phong)->lockForUpdate()->first() : null;
                $ca = DB::table('lich_lam_viec')->where('ma_nhan_vien', $lh->ma_nhan_vien)->whereDate('ngay_lam_viec', $data['ngay'])
                    ->where('co_the_nhan_lich', true)->where('gio_bat_dau', '<=', $start->format('H:i:s'))->where('gio_ket_thuc', '>=', $end->format('H:i:s'))->exists();
                $trung = DB::table('lich_hen')->where('ma_lich_hen', '<>', $id)->where('trang_thai', 'confirmed')
                    ->where('thoi_diem_bat_dau', '<', $end)->where('thoi_diem_ket_thuc', '>', $start)
                    ->where(fn ($q) => $q->where('ma_nhan_vien', $lh->ma_nhan_vien)->orWhere('ma_giuong', $lh->ma_giuong))->exists();
                if (! $nv || ! $nv->dang_hoat_dong || $nv->vai_tro !== 'employee' || ! $bed?->dang_hoat_dong || ! $room?->dang_hoat_dong || ! $ca || $trung) {
                    NghiepVuSpa::loi('Giờ mới không đủ ca hoặc trùng nhân viên/giường. Hãy chọn giờ khác.');
                }
            }
            DB::table('lich_hen')->where('ma_lich_hen', $id)->update($values);
            DB::table('nhat_ky_lich_hen')->insert(['ma_lich_hen' => $id, 'ma_nguoi_thao_tac' => $actor->ma_nguoi_dung,
                'du_lieu_truoc' => json_encode(['bat_dau' => $lh->thoi_diem_bat_dau, 'ket_thuc' => $lh->thoi_diem_ket_thuc,
                    'trang_thai' => $lh->trang_thai, 'nhan_vien' => $lh->ma_nhan_vien, 'giuong' => $lh->ma_giuong]),
                'du_lieu_sau' => json_encode(['bat_dau' => $start->toDateTimeString(), 'ket_thuc' => $end->toDateTimeString(),
                    'trang_thai' => $values['trang_thai'] ?? $lh->trang_thai, 'nhan_vien' => ($khach || $lh->trang_thai === 'pending') ? null : $lh->ma_nhan_vien,
                    'giuong' => ($khach || $lh->trang_thai === 'pending') ? null : $lh->ma_giuong]),
                'ly_do' => $data['ly_do'], 'thoi_diem_thao_tac' => now(), 'phien_ban_lich' => $lh->phien_ban + 1]);
            DB::table('nhac_hen')->where('ma_lich_hen', $id)->update(['trang_thai' => 'cancelled', 'thoi_diem_cap_nhat' => now()]);
            if (! $khach && $lh->trang_thai === 'confirmed') {
                NghiepVuSpa::nhacLich((object) array_merge((array) $lh, $values));
            }
        }, 3);

        return back()->with('thong_bao', 'Đã đổi lịch và lưu giờ cũ cùng lý do. Yêu cầu khách tự đổi sẽ chờ spa xác nhận lại.');
    }

    public function lichSu(Request $request, int $id): View
    {
        $lh = DB::table('lich_hen')->where('ma_lich_hen', $id)->first();
        abort_unless($lh, 404);
        $actor = $request->user();
        abort_unless($actor->vai_tro === 'admin' || ($actor->vai_tro === 'customer' && $lh->ma_khach_hang === $actor->getKey())
            || ($actor->vai_tro === 'employee' && ($actor->co_quyen_tiep_nhan || $lh->ma_nhan_vien === $actor->getKey())), 403);

        return view('pages.lich-su-lich', ['lich' => $lh, 'nhatKy' => DB::table('nhat_ky_lich_hen')->where('ma_lich_hen', $id)->orderByDesc('ma_nhat_ky')->get()]);
    }
}
