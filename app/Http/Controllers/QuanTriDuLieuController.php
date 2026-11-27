<?php

namespace App\Http\Controllers;

use App\NghiepVuSpa;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class QuanTriDuLieuController extends Controller
{
    public function luu(Request $request, string $loai, ?int $id = null): RedirectResponse
    {
        $spec = config('bieu_mau_spa.'.$loai);
        abort_unless($spec, 404);
        abort_if($id && in_array($loai, ['lieu-trinh', 'lich-hen'], true), 405);
        $rules = [];
        foreach ($spec['fields'] as $field => [$label, $type]) {
            $rules[$field] = match ($type) {
                'boolean' => ['required', 'boolean'],
                'number' => ['required', 'numeric', 'min:0', 'max:9999999999'],
                'date' => ['required', 'date_format:Y-m-d'], 'time' => ['required', 'date_format:H:i'],
                'textarea' => ['nullable', 'string', 'max:2000'], 'email' => ['nullable', 'email', 'max:190', Rule::unique('nguoi_dung', 'dia_chi_thu_dien_tu')->ignore($id, 'ma_nguoi_dung')],
                'tel' => ['nullable', 'regex:/^\+?[0-9]{9,15}$/'], 'role' => ['required', 'in:admin,employee,customer'],
                'password' => ['nullable', 'string', 'min:8', 'max:100', 'confirmed'],
                'membership' => ['nullable', 'integer', 'exists:hang_thanh_vien,ma_hang_thanh_vien'],
                'category', 'service', 'room', 'employee', 'customer', 'package' => ['required', 'integer', 'min:1'],
                default => ['required', 'string', 'max:150'],
            };
        }
        if (isset($rules['tong_so_buoi'])) {
            $rules['tong_so_buoi'] = ['required', 'integer', 'min:1', 'max:10000'];
        }
        if (isset($rules['thoi_luong_phut'])) {
            $rules['thoi_luong_phut'] = ['required', 'integer', 'min:1', 'max:1440'];
        }
        if (isset($rules['ty_le_giam_gia'])) {
            $rules['ty_le_giam_gia'] = ['required', 'numeric', 'between:0,100'];
        }
        foreach (['ten_phong', 'ten_hang'] as $uniqueField) {
            if (isset($rules[$uniqueField])) {
                $rules[$uniqueField][] = Rule::unique($spec['table'], $uniqueField)->ignore($id, $spec['key']);
            }
        }
        if ($loai === 'phong-giuong') {
            $rules['ten_giuong'][] = Rule::unique('giuong', 'ten_giuong')->where('ma_phong', $request->input('ma_phong'))->ignore($id, 'ma_giuong');
        }
        if ($loai === 'danh-muc') {
            $rules['xac_nhan_ten_danh_muc'] = ['sometimes', 'accepted'];
        }
        if ($loai === 'nguoi-dung') {
            $rules['ly_do'] = ['required', 'string', 'max:2000'];
            if ($id) {
                $rules['phien_ban'] = ['required', 'integer', 'min:1'];
            }
        }
        if ($loai === 'lieu-trinh') {
            $rules['ma_de_nghi'] = ['nullable', 'integer', 'min:1'];
            foreach (['ma_khach_hang', 'ma_goi_lieu_trinh'] as $field) {
                $rules[$field][0] = 'required_without:ma_de_nghi';
            }
        }
        $data = $request->validate($rules);
        DB::transaction(function () use ($request, $loai, $spec, $id, $data): void {
            $actors = DB::table('nguoi_dung')->where(fn ($q) => $q->where('vai_tro', 'admin')->orWhere('ma_nguoi_dung', $request->user()->getKey())
                ->when($loai === 'nguoi-dung' && $id, fn ($q) => $q->orWhere('ma_nguoi_dung', $id)))
                ->orderBy('ma_nguoi_dung')->lockForUpdate()->get();
            $actor = $actors->firstWhere('ma_nguoi_dung', $request->user()->getKey());
            abort_unless($actor && $actor->dang_hoat_dong && ($actor->vai_tro === 'admin'
                || ($loai === 'lieu-trinh' && $actor->vai_tro === 'employee' && $actor->co_quyen_thu_tien)
                || ($loai === 'lich-hen' && $actor->vai_tro === 'employee' && $actor->co_quyen_tiep_nhan)), 403);
            $before = $id ? DB::table($spec['table'])->where($spec['key'], $id)->lockForUpdate()->first() : null;
            abort_if($id && ! $before, 404);
            if ($before && $loai === 'phong-giuong' && (int) $data['ma_phong'] !== $before->ma_phong
                && DB::table('lich_hen')->where('ma_giuong', $id)->exists()) {
                throw ValidationException::withMessages(['ma_phong' => 'Giường đã có lịch sử không được chuyển sang phòng khác.']);
            }
            if (isset($data['dang_hoat_dong']) && ! $data['dang_hoat_dong'] && $id && in_array($loai, ['phong', 'phong-giuong'], true)) {
                $assigned = DB::table('lich_hen as lh')->join('giuong as g', 'g.ma_giuong', '=', 'lh.ma_giuong')->where('lh.trang_thai', 'confirmed');
                $assigned->where($loai === 'phong' ? 'g.ma_phong' : 'g.ma_giuong', $id);
                if ($assigned->exists()) {
                    throw ValidationException::withMessages(['dang_hoat_dong' => 'Cần xử lý các lịch đã xác nhận trước khi ngừng phòng hoặc giường.']);
                }
            }
            $values = $data;
            if ($loai === 'danh-muc') {
                if ((! $before || $before->ten_danh_muc !== $data['ten_danh_muc']) && empty($data['xac_nhan_ten_danh_muc'])) {
                    $this->canhBaoTenDanhMuc($data['ten_danh_muc'], $id);
                }
                unset($values['xac_nhan_ten_danh_muc']);
            }
            if ($id && isset($data['dang_hoat_dong']) && ! $data['dang_hoat_dong'] && in_array($loai, ['dich-vu', 'danh-muc'], true)) {
                $services = $loai === 'dich-vu' ? [$id] : DB::table('dich_vu')->where('ma_danh_muc', $id)->pluck('ma_dich_vu')->all();
                $coLich = DB::table('lich_hen')->whereIn('ma_dich_vu', $services)->whereIn('trang_thai', ['pending', 'confirmed'])->exists();
                $coGoi = DB::table('goi_da_mua')->whereIn('ma_dich_vu', $services)->whereIn('trang_thai', ['active', 'pending_payment', 'transfer_locked'])->exists();
                if ($coLich || $coGoi) {
                    NghiepVuSpa::loi('Cần xử lý lịch hoặc quyền lợi gói còn hiệu lực trước khi ngừng dịch vụ/danh mục.');
                }
            }
            if ($loai === 'nguoi-dung') {
                if ($before && (int) $before->phien_ban !== (int) $data['phien_ban']) {
                    throw ValidationException::withMessages(['phien_ban' => 'Tài khoản đã được cập nhật. Vui lòng tải lại và kiểm tra thông tin trước khi lưu.']);
                }
                if ($data['vai_tro'] === 'customer') {
                    $values['co_quyen_tiep_nhan'] = false;
                    $values['co_quyen_thu_tien'] = false;
                }
                if ($before && $before->vai_tro === 'admin' && $before->dang_hoat_dong && ($data['vai_tro'] !== 'admin' || ! $data['dang_hoat_dong'])
                    && $actors->where('vai_tro', 'admin')->where('dang_hoat_dong', true)->count() <= 1) {
                    throw ValidationException::withMessages(['vai_tro' => 'Không thể khóa hoặc hạ quyền quản trị viên hoạt động cuối cùng.']);
                }
                if ($before && ($before->vai_tro !== $data['vai_tro'] || ! $data['dang_hoat_dong']) && DB::table('lich_hen')->where('ma_nhan_vien', $id)->whereIn('trang_thai', ['pending', 'confirmed'])->exists()) {
                    throw ValidationException::withMessages(['vai_tro' => 'Cần xử lý các lịch được phân công trước khi thay đổi vai trò hoặc khóa tài khoản.']);
                }
                unset($values['password'], $values['ly_do']);
                if (! empty($data['password'])) {
                    $values['mat_khau'] = Hash::make($data['password']);
                }
                if ($data['vai_tro'] !== 'customer' && (empty($data['dia_chi_thu_dien_tu']) || ((! $before || ! $before->mat_khau) && empty($data['password'])))) {
                    throw ValidationException::withMessages(['password' => 'Tài khoản nội bộ cần email và mật khẩu để đăng nhập.']);
                }
                $values['phien_ban'] = ($before->phien_ban ?? 0) + 1;
            }
            foreach (['ma_danh_muc' => ['danh_muc_dich_vu', 'ma_danh_muc'], 'ma_dich_vu' => ['dich_vu', 'ma_dich_vu'], 'ma_phong' => ['phong', 'ma_phong'], 'ma_goi_lieu_trinh' => ['goi_lieu_trinh', 'ma_goi_lieu_trinh']] as $field => [$table, $key]) {
                if (isset($data[$field]) && ! DB::table($table)->where($key, $data[$field])->exists()) {
                    throw ValidationException::withMessages([$field => 'Lựa chọn không còn tồn tại.']);
                }
            }
            if ($loai === 'lich-lam-viec') {
                $nv = DB::table('nguoi_dung')->where('ma_nguoi_dung', $data['ma_nhan_vien'])->where('vai_tro', 'employee')->where('dang_hoat_dong', true)->lockForUpdate()->first();
                $start = $data['gio_bat_dau'].':00';
                $end = $data['gio_ket_thuc'].':00';
                if (! $nv || $start >= $end || DB::table('lich_lam_viec')->where('ma_nhan_vien', $data['ma_nhan_vien'])->whereDate('ngay_lam_viec', $data['ngay_lam_viec'])
                    ->when($id, fn ($q) => $q->where('ma_lich_lam_viec', '<>', $id))->where('gio_bat_dau', '<', $end)->where('gio_ket_thuc', '>', $start)->exists()) {
                    throw ValidationException::withMessages(['gio_bat_dau' => 'Nhân viên không hợp lệ, giờ không hợp lệ hoặc ca bị giao nhau.']);
                }
                if ($before && DB::table('lich_hen')->where('ma_nhan_vien', $before->ma_nhan_vien)->whereDate('thoi_diem_bat_dau', $before->ngay_lam_viec)->where('trang_thai', 'confirmed')->exists()) {
                    throw ValidationException::withMessages(['ngay_lam_viec' => 'Ngày cũ có lịch đã xác nhận. Cần xử lý các lịch trước khi sửa ca.']);
                }
                $values['gio_bat_dau'] = $start;
                $values['gio_ket_thuc'] = $end;
            }
            if ($loai === 'lieu-trinh') {
                $deNghi = null;
                if (! empty($data['ma_de_nghi'])) {
                    // Dùng khách và mẫu gói trên đề nghị đã lưu. Khóa đề nghị để thao tác xác nhận lặp chỉ tạo một lần mua.
                    $deNghi = DB::table('de_nghi_mua_goi')->where('ma_de_nghi', $data['ma_de_nghi'])->lockForUpdate()->first();
                    abort_unless($deNghi, 404);
                    if ($deNghi->ma_goi_da_mua !== null) {
                        return;
                    }
                    $data['ma_khach_hang'] = $deNghi->ma_khach_hang;
                    $data['ma_goi_lieu_trinh'] = $deNghi->ma_goi_lieu_trinh;
                }
                $kh = DB::table('nguoi_dung')->where('ma_nguoi_dung', $data['ma_khach_hang'])->where('vai_tro', 'customer')->where('dang_hoat_dong', true)->lockForUpdate()->first();
                $g = TrangKhachController::goiDangBan()->where('g.ma_goi_lieu_trinh', $data['ma_goi_lieu_trinh'])->lockForUpdate()->first();
                if (! $kh || ! $g) {
                    throw ValidationException::withMessages(['ma_goi_lieu_trinh' => 'Khách hàng hoặc gói không còn đủ điều kiện.']);
                }
                // Chốt giá, ưu đãi và số buổi lúc bán để các lần sửa mẫu gói về sau không làm đổi dữ liệu lần mua này.
                $gia = NghiepVuSpa::gia($kh->ma_nguoi_dung, $g->gia);
                $newId = DB::table('goi_da_mua')->insertGetId(['ma_khach_hang' => $kh->ma_nguoi_dung, 'ma_goi_lieu_trinh' => $g->ma_goi_lieu_trinh,
                    'ma_nguoi_ban' => $actor->ma_nguoi_dung, 'thoi_diem_mua' => now(), 'ma_dich_vu' => $g->ma_dich_vu, 'tong_so_buoi' => $g->tong_so_buoi,
                    'gia_mua' => $gia['thanh_tien'], 'gia_tri_cap_von' => 0, 'ten_goi_luc_mua' => $g->ten_goi, 'ten_dich_vu_luc_mua' => $g->ten_dich_vu,
                    'trang_thai' => 'pending_payment', 'thoi_diem_tao' => now(), 'thoi_diem_cap_nhat' => now()]);
                DB::table('hoa_don')->insert($gia + ['so_hoa_don' => 'HD-'.Str::upper((string) Str::ulid()), 'ma_khach_hang' => $kh->ma_nguoi_dung,
                    'ma_goi_da_mua' => $newId, 'ma_nguoi_lap' => $actor->ma_nguoi_dung, 'tien_truoc_giam' => $g->gia,
                    'thanh_tien' => $g->gia, 'ten_noi_dung_luc_lap' => $g->ten_goi, 'tien_can_thu' => $gia['thanh_tien'],
                    'thoi_diem_tao' => now(), 'thoi_diem_cap_nhat' => now()]);
                if ($deNghi) {
                    DB::table('de_nghi_mua_goi')->where('ma_de_nghi', $deNghi->ma_de_nghi)->update(['ma_goi_da_mua' => $newId]);
                }

                return;
            }
            if ($loai === 'lich-hen') {
                $kh = DB::table('nguoi_dung')->where('ma_nguoi_dung', $data['ma_khach_hang'])->where('vai_tro', 'customer')->where('dang_hoat_dong', true)->lockForUpdate()->first();
                $dv = TrangKhachController::dichVuDangBan()->where('dv.ma_dich_vu', $data['ma_dich_vu'])->first();
                $start = Carbon::createFromFormat('Y-m-d H:i', $data['ngay'].' '.$data['gio'])->startOfMinute();
                $end = $start->copy()->addMinutes($dv->thoi_luong_phut ?? 0);
                if (! $kh || ! $dv || $start->isPast() || DB::table('lich_hen')->where('ma_khach_hang', $data['ma_khach_hang'])->whereIn('trang_thai', ['pending', 'confirmed'])
                    ->where('thoi_diem_bat_dau', '<', $end)->where('thoi_diem_ket_thuc', '>', $start)->exists()) {
                    throw ValidationException::withMessages(['gio' => 'Khách/dịch vụ không hợp lệ, giờ đã qua hoặc khách đã có lịch trùng.']);
                }
                $values = ['ma_khach_hang' => $kh->ma_nguoi_dung, 'ma_dich_vu' => $dv->ma_dich_vu, 'ma_nguoi_tao_lich' => $actor->ma_nguoi_dung,
                    'nguon_dat_lich' => 'walk_in', 'trang_thai' => 'pending', 'thoi_diem_bat_dau' => $start, 'thoi_diem_ket_thuc' => $end,
                    'ten_dich_vu_luc_dat' => $dv->ten_dich_vu, 'gia_dich_vu_luc_dat' => $dv->gia, 'ghi_chu_nhan_vien' => $data['ghi_chu'] ?? null];
            }
            $values['thoi_diem_cap_nhat'] = now();
            if ($id) {
                DB::table($spec['table'])->where($spec['key'], $id)->update($values);
            } else {
                $values['thoi_diem_tao'] = now();
                $id = DB::table($spec['table'])->insertGetId($values);
            }
            if ($loai === 'nguoi-dung') {
                $safeBefore = $before ? (array) $before : null;
                unset($safeBefore['mat_khau']);
                $safeAfter = $values;
                unset($safeAfter['mat_khau']);
                NghiepVuSpa::nhatKy($actor->ma_nguoi_dung, 'user', $id, $before ? 'update_user' : 'create_user', $safeBefore, $safeAfter, $data['ly_do']);
            }
        }, 3);

        return back()->with('thong_bao', 'Đã lưu '.$spec['title'].' vào cơ sở dữ liệu.');
    }

    private function canhBaoTenDanhMuc(string $ten, ?int $id): void
    {
        $chuanHoa = fn (string $ten): string => preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($ten)));
        $tenChuan = $chuanHoa($ten);
        $danhMucGanGiong = DB::table('danh_muc_dich_vu')
            ->when($id, fn ($query) => $query->where('ma_danh_muc', '<>', $id))
            ->orderBy('ma_danh_muc')->pluck('ten_danh_muc')->first(function (string $tenHienCo) use ($ten, $tenChuan, $chuanHoa): bool {
                $tenHienCoChuan = $chuanHoa($tenHienCo);
                if ($tenChuan === '' || $tenHienCoChuan === '') {
                    return Str::lower($ten) === Str::lower($tenHienCo);
                }

                $doDai = min(strlen($tenChuan), strlen($tenHienCoChuan));

                return levenshtein($tenChuan, $tenHienCoChuan) <= match (true) {
                    $doDai >= 10 => 2,
                    $doDai >= 5 => 1,
                    default => 0,
                };
            });
        if ($danhMucGanGiong !== null) {
            $truongCanhBao = $id ? 'ten_danh_muc_'.$id : 'ten_danh_muc';
            throw ValidationException::withMessages([$truongCanhBao => 'Tên gần giống danh mục “'.$danhMucGanGiong.'”. Kiểm tra lại tên hoặc xác nhận nếu vẫn muốn lưu.']);
        }
    }
}
