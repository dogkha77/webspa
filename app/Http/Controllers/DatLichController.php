<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DatLichController extends Controller
{
    public function hienThi(Request $request): View|RedirectResponse
    {
        abort_unless($request->user()->vai_tro === 'customer', 403, 'Trang đặt lịch dành cho khách hàng.');
        $filters = $request->validate(['trang_thai' => ['nullable', 'in:pending,confirmed,completed,cancelled,rejected'],
            'goi' => ['nullable', 'integer', 'min:1']]);
        $cacGoi = self::goiCoTheDat($request->user()->getKey());
        $goiDaChon = isset($filters['goi']) ? $cacGoi->firstWhere('ma_goi_da_mua', $filters['goi']) : null;
        if (isset($filters['goi']) && ! $goiDaChon) {
            return redirect()->route('goi-cua-toi')->withErrors(['goi' => 'Gói này không thể dùng để đặt lịch.']);
        }
        $lichCuaToi = DB::table('lich_hen as lh')->leftJoin('nguoi_dung as nv', 'nv.ma_nguoi_dung', '=', 'lh.ma_nhan_vien')
            ->where('lh.ma_khach_hang', $request->user()->getKey())
            ->when(! empty($filters['trang_thai']), fn ($q) => $q->where('lh.trang_thai', $filters['trang_thai']))
            ->select('lh.*', 'nv.ten_nguoi_dung as nhan_vien_phuc_vu')->orderByDesc('lh.ma_lich_hen')->paginate(5)->withQueryString();

        return view('pages.dat-lich', ['dichVu' => TrangKhachController::dichVuDangBan()->orderBy('dv.ten_dich_vu')->get(),
            'cacGoi' => $cacGoi, 'lichCuaToi' => $lichCuaToi, 'goiDaChon' => $goiDaChon]);
    }

    /**
     * Lấy gói đã thanh toán của khách có thể đặt lịch, sau khi tính số buổi đã dùng
     * và số buổi giữ cho các lịch đã xác nhận.
     */
    public static function goiCoTheDat(int $khachId): Collection
    {
        return DB::table('goi_da_mua as gm')->join('hoa_don as hd', 'hd.ma_goi_da_mua', '=', 'gm.ma_goi_da_mua')
            ->where('gm.ma_khach_hang', $khachId)->where('gm.trang_thai', 'active')
            ->whereIn('gm.ma_dich_vu', TrangKhachController::dichVuDangBan()->select('dv.ma_dich_vu'))
            ->whereColumn('gm.so_buoi_da_dung', '<', 'gm.tong_so_buoi')->where('hd.trang_thai_thanh_toan', 'paid')
            ->where('hd.trang_thai_xu_ly', '<>', 'error')->select('gm.*')
            ->selectRaw("(SELECT COUNT(*) FROM lich_hen WHERE lich_hen.ma_goi_da_mua = gm.ma_goi_da_mua AND lich_hen.trang_thai = 'confirmed') AS so_buoi_da_giu")
            ->get()->filter(fn ($g): bool => $g->so_buoi_da_dung + $g->so_buoi_da_giu < $g->tong_so_buoi);
    }

    public function khungGio(Request $request): JsonResponse
    {
        abort_unless($request->user()->vai_tro === 'customer' && $request->user()->dang_hoat_dong, 403);
        $data = $request->validate(['ma_dich_vu' => ['required', 'integer', 'min:1'],
            'ngay' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'], 'ma_goi_da_mua' => ['nullable', 'integer', 'min:1']]);
        $dv = TrangKhachController::dichVuDangBan()->where('dv.ma_dich_vu', $data['ma_dich_vu'])->first();
        if (! $dv) {
            throw ValidationException::withMessages(['ma_dich_vu' => 'Dịch vụ hiện không còn nhận lịch.']);
        }
        if (! empty($data['ma_goi_da_mua'])) {
            $this->kiemTraGoi($request->user()->getKey(), $dv->ma_dich_vu, (int) $data['ma_goi_da_mua']);
        }
        $nguon = $this->nguonLuc($data['ngay'], $request->user()->getKey());
        $candidates = [];
        foreach ($nguon['ca'] as $ca) {
            $cursor = Carbon::parse($data['ngay'].' '.$ca->gio_bat_dau)->startOfMinute();
            if ($cursor->format('H:i:s') < $ca->gio_bat_dau) {
                $cursor->addMinute();
            }
            if ($cursor->minute % 15 !== 0) {
                $cursor->addMinutes(15 - $cursor->minute % 15);
            }
            $limit = Carbon::parse($data['ngay'].' '.$ca->gio_ket_thuc);
            while ($cursor->copy()->addMinutes($dv->thoi_luong_phut)->lte($limit)) {
                if ($cursor->isFuture()) {
                    $candidates[$cursor->format('H:i')] = $cursor->copy();
                }
                $cursor->addMinutes(15);
            }
        }
        ksort($candidates);
        $slots = [];
        foreach ($candidates as $time => $start) {
            $end = $start->copy()->addMinutes($dv->thoi_luong_phut);
            if ($this->lyDoKhongNhan($start, $end, $nguon) === null) {
                $slots[] = ['gio' => $time, 'ket_thuc' => $end->format('H:i')];
            }
        }

        return response()->json(['khung_gio' => $slots, 'gia' => $dv->gia, 'thoi_luong_phut' => $dv->thoi_luong_phut,
            'thong_bao' => $slots ? 'Chọn một giờ gợi ý hoặc nhập giờ phù hợp với ca làm. Spa xác nhận lịch sau khi kiểm tra lại.'
                : 'Chưa có giờ gợi ý phù hợp. Bạn có thể chọn ngày khác hoặc nhập giờ để hệ thống kiểm tra lại.',
        ])->header('Cache-Control', 'private, no-store');
    }

    public function nhanVienTrong(Request $request): JsonResponse
    {
        abort_unless($request->user()->vai_tro === 'customer', 403);
        $d = $request->validate(['ngay' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'], 'gio' => ['required', 'date_format:H:i'], 'ma_dich_vu' => ['required', 'integer']]);
        $dv = TrangKhachController::dichVuDangBan()->where('dv.ma_dich_vu', $d['ma_dich_vu'])->first();
        abort_unless($dv, 404);
        $start = Carbon::parse($d['ngay'].' '.$d['gio']);
        $end = $start->copy()->addMinutes($dv->thoi_luong_phut);
        if ($start->isPast() || ! $start->isSameDay($end)) {
            return response()->json([]);
        }
        $nguon = $this->nguonLuc($d['ngay'], $request->user()->getKey());
        if ($this->lyDoKhongNhan($start, $end, $nguon) !== null) {
            return response()->json([]);
        }
        $ids = $nguon['ca']->filter(fn ($ca) => $ca->gio_bat_dau <= $start->format('H:i:s') && $ca->gio_ket_thuc >= $end->format('H:i:s'))->pluck('ma_nhan_vien');
        $ban = $nguon['lich']->filter(fn ($l) => $l->thoi_diem_bat_dau < $end->toDateTimeString() && $l->thoi_diem_ket_thuc > $start->toDateTimeString())->pluck('ma_nhan_vien');

        return response()->json(DB::table('nguoi_dung')->whereIn('ma_nguoi_dung', $ids)->whereNotIn('ma_nguoi_dung', $ban)->get(['ma_nguoi_dung', 'ten_nguoi_dung']))->header('Cache-Control', 'private, no-store');
    }

    /**
     * Kiểm tra lại dịch vụ, gói và khung giờ khi gửi, rồi tạo yêu cầu chờ spa duyệt.
     */
    public function luu(Request $request): RedirectResponse
    {
        abort_unless($request->user()->vai_tro === 'customer', 403);
        $data = $request->validate([
            'ho_ten' => ['required', 'string', 'max:150'], 'so_dien_thoai' => ['required', 'regex:/^\+?[0-9]{9,15}$/'],
            'ma_dich_vu' => ['required', 'integer', 'min:1'], 'ma_goi_da_mua' => ['nullable', 'integer', 'min:1'],
            'ngay' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'], 'gio' => ['required', 'date_format:H:i'],
            'gia_da_xem' => ['required', 'numeric', 'min:0'], 'thoi_luong_da_xem' => ['required', 'integer', 'min:1'],
            'ghi_chu' => ['nullable', 'string', 'max:2000'], 'ma_nhan_vien' => ['nullable', 'integer', 'min:1'],
        ], ['so_dien_thoai.regex' => 'Số điện thoại gồm 9 đến 15 chữ số, có thể bắt đầu bằng dấu +.',
            'ngay.after_or_equal' => 'Vui lòng chọn hôm nay hoặc một ngày trong tương lai.',
            'gia_da_xem.required' => 'Vui lòng chọn dịch vụ để xem giá trước khi gửi.',
            'thoi_luong_da_xem.required' => 'Vui lòng chọn dịch vụ để xem thời lượng trước khi gửi.',
        ]);
        $ketQua = DB::transaction(function () use ($request, $data): array {
            $khach = DB::table('nguoi_dung')->where('ma_nguoi_dung', $request->user()->getKey())->lockForUpdate()->first();
            abort_unless($khach && $khach->dang_hoat_dong && $khach->vai_tro === 'customer', 403);
            $dv = TrangKhachController::dichVuDangBan()->where('dv.ma_dich_vu', $data['ma_dich_vu'])->first();
            if (! $dv) {
                throw ValidationException::withMessages(['ma_dich_vu' => 'Dịch vụ hiện không còn nhận lịch.']);
            }
            $batDau = Carbon::createFromFormat('Y-m-d H:i', $data['ngay'].' '.$data['gio'])->startOfMinute();
            if ($batDau->isPast()) {
                throw ValidationException::withMessages(['gio' => 'Vui lòng chọn thời gian trong tương lai.']);
            }
            $ketThuc = $batDau->copy()->addMinutes($dv->thoi_luong_phut);
            $goiId = empty($data['ma_goi_da_mua']) ? null : (int) $data['ma_goi_da_mua'];
            $trung = DB::table('lich_hen')->where('ma_khach_hang', $khach->ma_nguoi_dung)->whereIn('trang_thai', ['pending', 'confirmed'])
                ->where('thoi_diem_bat_dau', '<', $ketThuc)->where('thoi_diem_ket_thuc', '>', $batDau)->first();
            if ($trung) {
                if ($trung->trang_thai === 'pending' && $trung->ma_dich_vu === $dv->ma_dich_vu
                    && (int) $trung->ma_goi_da_mua === (int) $goiId && $trung->thoi_diem_bat_dau === $batDau->format('Y-m-d H:i:s')
                    && (int) $trung->ma_nhan_vien === (int) ($data['ma_nhan_vien'] ?? 0) && ($trung->ghi_chu_khach_hang ?? '') === ($data['ghi_chu'] ?? '')) {
                    return ['id' => $trung->ma_lich_hen, 'da_co' => true];
                }
                throw ValidationException::withMessages(['gio' => 'Bạn đã có lịch trong khoảng thời gian này. Vui lòng chọn giờ khác.']);
            }
            if ((int) $data['thoi_luong_da_xem'] !== $dv->thoi_luong_phut || (! $goiId && round((float) $data['gia_da_xem'], 2) !== round((float) $dv->gia, 2))) {
                throw ValidationException::withMessages(['ma_dich_vu' => 'Giá hoặc thời lượng dịch vụ đã thay đổi. Vui lòng xem lại thông tin mới rồi gửi yêu cầu.']);
            }
            if ($goiId) {
                $this->kiemTraGoi($khach->ma_nguoi_dung, $dv->ma_dich_vu, $goiId, true);
            }
            $lyDo = $this->lyDoKhongNhan($batDau, $ketThuc, $this->nguonLuc($data['ngay'], $khach->ma_nguoi_dung));
            if ($lyDo !== null) {
                throw ValidationException::withMessages(['gio' => $lyDo]);
            }
            DB::table('nguoi_dung')->where('ma_nguoi_dung', $khach->ma_nguoi_dung)->update([
                'ten_nguoi_dung' => $data['ho_ten'], 'so_dien_thoai' => $data['so_dien_thoai'],
                'phien_ban' => $khach->phien_ban + 1, 'thoi_diem_cap_nhat' => now(),
            ]);
            $nhanVien = null;
            if (! empty($data['ma_nhan_vien'])) {
                $nhanVien = DB::table('nguoi_dung')->where('ma_nguoi_dung', $data['ma_nhan_vien'])->where('vai_tro', 'employee')->where('dang_hoat_dong', true)->lockForUpdate()->first();
                $coCa = $nhanVien && $batDau->isSameDay($ketThuc) && DB::table('lich_lam_viec')->where('ma_nhan_vien', $nhanVien->ma_nguoi_dung)->whereDate('ngay_lam_viec', $data['ngay'])->where('co_the_nhan_lich', true)->where('gio_bat_dau', '<=', $batDau->format('H:i:s'))->where('gio_ket_thuc', '>=', $ketThuc->format('H:i:s'))->exists();
                $ban = $nhanVien && DB::table('lich_hen')->where('ma_nhan_vien', $nhanVien->ma_nguoi_dung)->where('trang_thai', 'confirmed')->where('thoi_diem_bat_dau', '<', $ketThuc)->where('thoi_diem_ket_thuc', '>', $batDau)->exists();
                if (! $coCa || $ban) {
                    throw ValidationException::withMessages(['ma_nhan_vien' => 'Nhân viên không còn phù hợp với giờ đã chọn. Vui lòng chọn lại.']);
                }
            }
            $id = DB::table('lich_hen')->insertGetId(['ma_nhan_vien' => $nhanVien?->ma_nguoi_dung,
                'ma_khach_hang' => $khach->ma_nguoi_dung, 'ma_dich_vu' => $dv->ma_dich_vu, 'ma_goi_da_mua' => $goiId,
                'ma_nguoi_tao_lich' => $khach->ma_nguoi_dung, 'nguon_dat_lich' => 'online', 'trang_thai' => 'pending',
                'thoi_diem_bat_dau' => $batDau, 'thoi_diem_ket_thuc' => $ketThuc,
                'ten_dich_vu_luc_dat' => $dv->ten_dich_vu, 'gia_dich_vu_luc_dat' => $dv->gia,
                'ghi_chu_khach_hang' => $data['ghi_chu'] ?? null, 'phien_ban' => 1, 'thoi_diem_tao' => now(), 'thoi_diem_cap_nhat' => now(),
            ]);

            return ['id' => $id, 'da_co' => false];
        }, 3);

        return redirect()->route('dat-lich')->with('dat_lich_ma', $ketQua['id'])
            ->with('thong_bao', ($ketQua['da_co'] ? 'Yêu cầu này đã được ghi nhận' : 'Đã gửi yêu cầu đặt lịch').' #'.$ketQua['id'].'. Spa sẽ kiểm tra và xác nhận; gói chưa bị trừ buổi.');
    }

    /**
     * Kiểm tra gói đúng khách, đúng dịch vụ và còn buổi có thể đặt.
     * Khi lưu lịch, khóa gói đến cuối giao dịch để phối hợp với luồng xác nhận và hoàn thành.
     */
    private function kiemTraGoi(int $khachId, int $dichVuId, int $goiId, bool $khoa = false): void
    {
        $query = DB::table('goi_da_mua')->where('ma_goi_da_mua', $goiId);
        if ($khoa) {
            $query->lockForUpdate();
        }
        $goi = $query->first();
        $daTra = DB::table('hoa_don')->where('ma_goi_da_mua', $goiId)->where('trang_thai_thanh_toan', 'paid')->where('trang_thai_xu_ly', '<>', 'error')->exists();
        $daGiu = DB::table('lich_hen')->where('ma_goi_da_mua', $goiId)->where('trang_thai', 'confirmed')->count();
        if (! $goi || $goi->ma_khach_hang !== $khachId || $goi->ma_dich_vu !== $dichVuId
            || $goi->trang_thai !== 'active' || ! $daTra || $goi->so_buoi_da_dung + $daGiu >= $goi->tong_so_buoi) {
            throw ValidationException::withMessages(['ma_goi_da_mua' => 'Gói không đủ điều kiện hoặc số buổi còn lại đã được giữ cho lịch khác.']);
        }
    }

    private function nguonLuc(string $ngay, int $khachId): array
    {
        $dau = Carbon::parse($ngay)->startOfDay();
        $cuoi = $dau->copy()->addDay();

        return [
            'ca' => DB::table('lich_lam_viec as ca')->join('nguoi_dung as nv', 'nv.ma_nguoi_dung', '=', 'ca.ma_nhan_vien')
                ->whereDate('ca.ngay_lam_viec', $ngay)->where('ca.co_the_nhan_lich', true)->where('nv.vai_tro', 'employee')
                ->where('nv.dang_hoat_dong', true)->select('ca.ma_nhan_vien', 'ca.gio_bat_dau', 'ca.gio_ket_thuc')->get(),
            'giuong' => DB::table('giuong as g')->join('phong as p', 'p.ma_phong', '=', 'g.ma_phong')
                ->where('g.dang_hoat_dong', true)->where('p.dang_hoat_dong', true)->pluck('g.ma_giuong')->all(),
            'lich' => DB::table('lich_hen')->where('trang_thai', 'confirmed')->where('thoi_diem_bat_dau', '<', $cuoi)
                ->where('thoi_diem_ket_thuc', '>', $dau)->get(['ma_nhan_vien', 'ma_giuong', 'thoi_diem_bat_dau', 'thoi_diem_ket_thuc']),
            'lich_khach' => DB::table('lich_hen')->where('ma_khach_hang', $khachId)->whereIn('trang_thai', ['pending', 'confirmed'])
                ->where('thoi_diem_bat_dau', '<', $cuoi)->where('thoi_diem_ket_thuc', '>', $dau)->get(['thoi_diem_bat_dau', 'thoi_diem_ket_thuc']),
        ];
    }

    private function lyDoKhongNhan(Carbon $batDau, Carbon $ketThuc, array $nguon): ?string
    {
        $start = $batDau->format('Y-m-d H:i:s');
        $end = $ketThuc->format('Y-m-d H:i:s');
        $giaoNhau = fn ($lh): bool => $lh->thoi_diem_bat_dau < $end && $lh->thoi_diem_ket_thuc > $start;
        if ($nguon['lich_khach']->contains($giaoNhau)) {
            return 'Bạn đã có lịch trong khoảng thời gian này. Vui lòng chọn giờ khác.';
        }
        $nhanVien = $nguon['ca']->filter(fn ($ca): bool => $batDau->isSameDay($ketThuc)
            && $ca->gio_bat_dau <= $batDau->format('H:i:s') && $ca->gio_ket_thuc >= $ketThuc->format('H:i:s'))
            ->pluck('ma_nhan_vien')->unique()->all();
        $ban = $nguon['lich']->filter($giaoNhau);
        $nhanVienTrong = array_diff($nhanVien, $ban->pluck('ma_nhan_vien')->all());
        $giuongTrong = array_diff($nguon['giuong'], $ban->pluck('ma_giuong')->all());
        if (! $nhanVienTrong || ! $giuongTrong) {
            return 'Khung giờ này chưa có đủ nhân viên trong ca hoặc giường trống. Vui lòng chọn giờ/ngày khác.';
        }

        return null;
    }

    public function huy(Request $request, int $lich): RedirectResponse
    {
        $data = $request->validate(['ly_do' => ['required', 'string', 'max:500']]);
        DB::transaction(function () use ($request, $lich, $data): void {
            $khach = DB::table('nguoi_dung')->where('ma_nguoi_dung', $request->user()->getKey())->lockForUpdate()->first();
            abort_unless($khach && $khach->dang_hoat_dong && $khach->vai_tro === 'customer', 403);
            $row = DB::table('lich_hen')->where('ma_lich_hen', $lich)->where('ma_khach_hang', $khach->ma_nguoi_dung)->lockForUpdate()->first();
            abort_unless($row, 404);
            if ($row->trang_thai !== 'pending') {
                throw ValidationException::withMessages(['lich' => 'Chỉ rút trực tuyến yêu cầu còn chờ. Với lịch đã xác nhận, vui lòng liên hệ spa.']);
            }
            DB::table('lich_hen')->where('ma_lich_hen', $lich)->update(['trang_thai' => 'cancelled',
                'ghi_chu_khach_hang' => trim(($row->ghi_chu_khach_hang ?? '')."\nLý do rút yêu cầu: ".$data['ly_do']),
                'phien_ban' => $row->phien_ban + 1, 'thoi_diem_cap_nhat' => now()]);
            DB::table('nhac_hen')->where('ma_lich_hen', $lich)->whereIn('trang_thai', ['pending', 'failed'])->update(['trang_thai' => 'cancelled']);
        }, 3);

        return redirect()->route('dat-lich')->with('thong_bao', 'Đã rút yêu cầu đặt lịch, không trừ buổi gói.');
    }
}

