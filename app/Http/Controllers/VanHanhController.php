<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VanHanhController extends Controller
{
    public static function lich(Request $request, bool $tiepNhan = false): Builder
    {
        $q = DB::table('lich_hen as lh')->join('nguoi_dung as kh', 'kh.ma_nguoi_dung', '=', 'lh.ma_khach_hang')
            ->join('dich_vu as dv', 'dv.ma_dich_vu', '=', 'lh.ma_dich_vu')
            ->leftJoin('nguoi_dung as nv', 'nv.ma_nguoi_dung', '=', 'lh.ma_nhan_vien')
            ->leftJoin('giuong as g', 'g.ma_giuong', '=', 'lh.ma_giuong')->leftJoin('phong as p', 'p.ma_phong', '=', 'g.ma_phong')
            ->select('lh.*', 'kh.ten_nguoi_dung as khach_hang', 'kh.so_dien_thoai', 'nv.ten_nguoi_dung as nhan_vien', 'g.ten_giuong', 'p.ten_phong',
                'dv.gia as gia_dich_vu_hien_tai', 'dv.thoi_luong_phut as thoi_luong_hien_tai');
        if ($request->user()->vai_tro !== 'admin') {
            $q->where(function (Builder $scope) use ($request, $tiepNhan): void {
                $scope->where('lh.ma_nhan_vien', $request->user()->getKey());
                if ($tiepNhan && $request->user()->co_quyen_tiep_nhan) {
                    $scope->orWhere('lh.trang_thai', 'pending');
                }
            });
        }

        return $q;
    }

    public function admin(Request $request, string $muc = 'tong-quan'): View
    {
        return $this->hienThi($request, 'admin', $muc);
    }

    public function thongBaoLichMoi(): JsonResponse
    {
        $lichMoi = DB::table('lich_hen')->where('nguon_dat_lich', 'online')
            ->orderByDesc('ma_lich_hen')->limit(10)
            ->get(['ma_lich_hen', 'ten_dich_vu_luc_dat', 'thoi_diem_bat_dau', 'trang_thai']);

        return response()->json($lichMoi->map(fn (object $lich): array => [
            'ma_lich_hen' => $lich->ma_lich_hen,
            'dich_vu' => $lich->ten_dich_vu_luc_dat,
            'gio_hen' => Carbon::parse($lich->thoi_diem_bat_dau)->format('H:i d/m/Y'),
            'trang_thai' => $lich->trang_thai,
            'url' => route('admin.trang', ['muc' => 'lich-hen', 'lich' => $lich->ma_lich_hen]),
        ]))->header('Cache-Control', 'private, no-store');
    }

    public function nhanVien(Request $request, string $muc = 'tong-quan'): View
    {
        return $this->hienThi($request, 'employee', $muc);
    }

    private function hienThi(Request $request, string $vaiTro, string $muc): View
    {
        $menu = config('menu_spa.'.$vaiTro);
        abort_unless(isset($menu[$muc]), 404);
        $filters = $request->validate(['ma_nhan_vien' => ['nullable', 'integer', 'min:1'], 'lich' => ['nullable', 'integer', 'min:1'],
            'q' => ['nullable', 'string', 'max:100'], 'ngay' => ['nullable', 'date_format:Y-m-d'],
            'trang_thai' => ['nullable', 'in:pending,confirmed,completed,cancelled,rejected'], 'tu' => ['nullable', 'date_format:Y-m-d'],
            'den' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:tu']]);
        $laAdmin = $vaiTro === 'admin';
        $coTheTiepNhan = $laAdmin || (bool) $request->user()->co_quyen_tiep_nhan;
        $ngay = $filters['ngay'] ?? now()->toDateString();
        $lichNgay = self::lich($request)->whereDate('lh.thoi_diem_bat_dau', $ngay)
            ->when($laAdmin, fn (Builder $q) => $q->orderByDesc('lh.ma_lich_hen'), fn (Builder $q) => $q->orderBy('lh.thoi_diem_bat_dau'))->get();
        $tu = $filters['tu'] ?? now()->startOfMonth()->toDateString();
        $den = $filters['den'] ?? now()->toDateString();
        $tien = $laAdmin ? DB::table('hoa_don')->where('trang_thai_thanh_toan', 'paid')
            ->where('thoi_diem_thanh_toan', '>=', $tu)->where('thoi_diem_thanh_toan', '<', Carbon::parse($den)->addDay()) : null;
        $tongThu = $tien ? (clone $tien)->sum('so_tien_da_thu') : null;
        $doiSoat = $tien ? (clone $tien)->where('trang_thai_xu_ly', 'error')->sum('so_tien_da_thu') : null;
        $chiTieu = [];
        if ($tien) {
            $chuyen = fn (Builder $q) => $q->selectRaw('1')->from('chuyen_goi as cg')->whereColumn('cg.ma_hoa_don', 'hoa_don.ma_hoa_don')->where('cg.trang_thai', 'completed');
            $chiTieu = ['Giá trị giao dịch sau ưu đãi' => (clone $tien)->sum('thanh_tien'), 'Ưu đãi đã áp dụng' => (clone $tien)->sum('tien_giam'),
                'Giá trị chuyển nội bộ' => (clone $tien)->sum('credit_chuyen_goi'), 'Thu thêm chuyển gói' => (clone $tien)->whereExists($chuyen)->sum('so_tien_da_thu'),
                'Thu dịch vụ lẻ' => (clone $tien)->whereNotNull('ma_lich_hen')->sum('so_tien_da_thu'),
                'Thu mua gói thông thường' => (clone $tien)->whereNotNull('ma_goi_da_mua')->whereNotExists($chuyen)->sum('so_tien_da_thu')];
        }
        $ngayThu = $tien ? (clone $tien)->selectRaw('DATE(thoi_diem_thanh_toan) as ngay, SUM(so_tien_da_thu) as tien')->groupByRaw('DATE(thoi_diem_thanh_toan)')->orderBy('ngay')->get() : collect();
        $phong = $coTheTiepNhan ? DB::table('phong')->where('dang_hoat_dong', true)->orderBy('ten_phong')->get() : collect();
        $giuong = $coTheTiepNhan ? DB::table('giuong')->where('dang_hoat_dong', true)->get() : collect();
        $dangDung = self::lich($request)->where('lh.trang_thai', 'confirmed')->where('lh.thoi_diem_bat_dau', '<=', now())
            ->where('lh.thoi_diem_ket_thuc', '>', now())->get();
        $caLam = DB::table('lich_lam_viec as ca')->join('nguoi_dung as nv', 'nv.ma_nguoi_dung', '=', 'ca.ma_nhan_vien')
            ->whereDate('ca.ngay_lam_viec', $ngay)->where('ca.co_the_nhan_lich', true)
            ->when(! $laAdmin, fn (Builder $q) => $q->where('ca.ma_nhan_vien', $request->user()->getKey()))
            ->select('ca.*', 'nv.ten_nguoi_dung')->orderBy('ca.gio_bat_dau')->get();
        $nhanVien = $coTheTiepNhan ? DB::table('nguoi_dung')->where('vai_tro', 'employee')->where('dang_hoat_dong', true)
            ->select('ma_nguoi_dung', 'ten_nguoi_dung')->orderBy('ten_nguoi_dung')->get() : collect();
        [$query, $columns] = $this->bang($request, $muc);
        if ($query && ! empty($filters['q'])) {
            $needle = '%'.addcslashes($filters['q'], '%_\\').'%';
            $query = DB::query()->fromSub($query, 'ket_qua')->where(function (Builder $q) use ($columns, $needle): void {
                foreach (array_keys($columns) as $field) {
                    $q->orWhere($field, 'like', $needle);
                }
            });
        }
        if ($query && $muc === 'lich-hen') {
            $query->when(! empty($filters['ngay']), fn (Builder $q) => $q->whereDate('thoi_diem_bat_dau', $filters['ngay']))
                ->when(! empty($filters['trang_thai']), fn (Builder $q) => $q->where('trang_thai', $filters['trang_thai']))
                ->when(isset($filters['lich']), fn (Builder $q) => $q->where('ma_lich_hen', $filters['lich']))
                ->when($laAdmin, fn (Builder $q) => $q->orderByDesc('ma_lich_hen'), fn (Builder $q) => $q->orderByDesc('thoi_diem_bat_dau'));
        }
        $rows = $query ? $query->paginate(15)->withQueryString() : null;

        $formTypes = $laAdmin ? match ($muc) {
            'nhan-vien', 'khach-hang', 'nguoi-dung' => ['nguoi-dung'],
            'phong-giuong' => ['phong', 'phong-giuong'],
            default => config('bieu_mau_spa.'.$muc) ? [$muc] : [],
        } : [];
        $luaChon = ['boolean' => [1 => 'Có', 0 => 'Không'], 'role' => ['customer' => 'Khách hàng', 'employee' => 'Nhân viên', 'admin' => 'Quản trị viên']];
        if ($formTypes) {
            $luaChon += [
                'membership' => ['' => 'Chưa xếp hạng'] + DB::table('hang_thanh_vien')->where('dang_hoat_dong', true)->pluck('ten_hang', 'ma_hang_thanh_vien')->all(),
                'category' => DB::table('danh_muc_dich_vu')->pluck('ten_danh_muc', 'ma_danh_muc')->all(),
                'service' => TrangKhachController::dichVuDangBan()->pluck('dv.ten_dich_vu', 'dv.ma_dich_vu')->all(),
                'room' => DB::table('phong')->where('dang_hoat_dong', true)->pluck('ten_phong', 'ma_phong')->all(),
                'employee' => $nhanVien->pluck('ten_nguoi_dung', 'ma_nguoi_dung')->all(),
                'customer' => DB::table('nguoi_dung')->where('vai_tro', 'customer')->where('dang_hoat_dong', true)->pluck('ten_nguoi_dung', 'ma_nguoi_dung')->all(),
                'package' => TrangKhachController::goiDangBan()->pluck('g.ten_goi', 'g.ma_goi_lieu_trinh')->all(),
            ];
        }

        $cauHinhDatLich = [];
        if ($laAdmin && in_array($muc, ['tong-quan', 'lich-hen'], true)) {
            $cauHinhDatLich = [
                ['ten' => 'Danh mục đang mở', 'muc' => 'danh-muc', 'so_luong' => DB::table('danh_muc_dich_vu')->where('dang_hoat_dong', true)->count()],
                ['ten' => 'Dịch vụ nhận lịch', 'muc' => 'dich-vu', 'so_luong' => TrangKhachController::dichVuDangBan()->count()],
                ['ten' => 'Nhân viên hoạt động', 'muc' => 'nhan-vien', 'so_luong' => DB::table('nguoi_dung')->where('vai_tro', 'employee')->where('dang_hoat_dong', true)->count()],
                ['ten' => 'Giường trong phòng hoạt động', 'muc' => 'phong-giuong', 'so_luong' => DB::table('giuong as g')->join('phong as p', 'p.ma_phong', '=', 'g.ma_phong')->where('g.dang_hoat_dong', true)->where('p.dang_hoat_dong', true)->count()],
                ['ten' => 'Ca nhận lịch từ hôm nay', 'muc' => 'lich-lam-viec', 'so_luong' => DB::table('lich_lam_viec as ca')->join('nguoi_dung as nv', 'nv.ma_nguoi_dung', '=', 'ca.ma_nhan_vien')->whereDate('ca.ngay_lam_viec', '>=', now()->toDateString())->where('ca.co_the_nhan_lich', true)->where('nv.vai_tro', 'employee')->where('nv.dang_hoat_dong', true)->count()],
            ];
        }

        return view('noi-bo.trang', compact('menu', 'muc', 'laAdmin', 'ngay', 'lichNgay', 'tongThu', 'doiSoat', 'ngayThu', 'tu', 'den', 'phong', 'giuong', 'dangDung', 'caLam', 'nhanVien', 'rows', 'columns') + [
            'chiTieu' => $chiTieu, 'coTheTiepNhan' => $coTheTiepNhan, 'cauHinhDatLich' => $cauHinhDatLich, 'formTypes' => $formTypes, 'luaChon' => $luaChon, 'tieuDe' => $menu[$muc]['title'], 'tienToRoute' => $laAdmin ? 'admin.trang' : 'nhan-vien.trang',
        ]);
    }

    private function bang(Request $request, string $muc): array
    {
        $admin = $request->user()->vai_tro === 'admin';

        return match ($muc) {
            'nhac-hen' => [DB::table('nhac_hen')->orderByDesc('ma_nhac_hen'), ['ma_nhac_hen' => 'Mã nhắc', 'ma_lich_hen' => 'Mã lịch', 'thoi_diem_hen_luu_lai' => 'Giờ hẹn', 'thoi_diem_du_kien_gui' => 'Dự kiến gửi', 'trang_thai' => 'Trạng thái', 'thoi_diem_da_gui' => 'Đã gửi lúc', 'so_lan_thu_gui' => 'Số lần thử', 'loi_gan_nhat' => 'Ghi chú lỗi']],
            'lich-hen' => [self::lich($request, true), ['ma_lich_hen' => 'Mã hẹn', 'khach_hang' => 'Khách hàng', 'so_dien_thoai' => 'Điện thoại', 'ten_dich_vu_luc_dat' => 'Dịch vụ', 'nhan_vien' => 'Nhân viên', 'ten_phong' => 'Phòng', 'ten_giuong' => 'Giường', 'thoi_diem_bat_dau' => 'Bắt đầu', 'trang_thai' => 'Trạng thái']],
            'khach-hang', 'nhan-vien', 'nguoi-dung' => [DB::table('nguoi_dung')->select('ma_nguoi_dung', 'ten_nguoi_dung', 'dia_chi_thu_dien_tu', 'so_dien_thoai', 'vai_tro', 'ma_hang_thanh_vien', 'dang_hoat_dong', 'co_quyen_tiep_nhan', 'co_quyen_thu_tien', 'phien_ban')
                ->when($muc !== 'nguoi-dung', fn (Builder $q) => $q->where('vai_tro', $muc === 'khach-hang' ? 'customer' : 'employee'))->orderBy('ten_nguoi_dung'),
                ['ma_nguoi_dung' => 'Mã', 'ten_nguoi_dung' => 'Họ tên', 'dia_chi_thu_dien_tu' => 'Email', 'so_dien_thoai' => 'Điện thoại', 'vai_tro' => 'Vai trò', 'co_quyen_tiep_nhan' => 'Tiếp nhận', 'co_quyen_thu_tien' => 'Thu tiền', 'dang_hoat_dong' => 'Hoạt động']],
            'dich-vu' => [DB::table('dich_vu as dv')->join('danh_muc_dich_vu as dm', 'dm.ma_danh_muc', '=', 'dv.ma_danh_muc')
                ->select('dv.*', 'dm.ten_danh_muc')->when(! $admin, fn (Builder $q) => $q->where('dv.dang_hoat_dong', true)->where('dm.dang_hoat_dong', true))->orderBy('dv.ten_dich_vu'),
                ['ma_dich_vu' => 'Mã', 'ten_dich_vu' => 'Dịch vụ', 'ten_danh_muc' => 'Danh mục', 'gia' => 'Giá', 'thoi_luong_phut' => 'Phút', 'dang_hoat_dong' => 'Hoạt động']],
            'goi-lieu-trinh' => [DB::table('goi_lieu_trinh as g')->join('dich_vu as dv', 'dv.ma_dich_vu', '=', 'g.ma_dich_vu')->select('g.*', 'dv.ten_dich_vu')->orderBy('g.ten_goi'),
                ['ma_goi_lieu_trinh' => 'Mã', 'ten_goi' => 'Gói', 'ten_dich_vu' => 'Dịch vụ', 'tong_so_buoi' => 'Tổng buổi', 'gia' => 'Giá gói', 'dang_hoat_dong' => 'Đang bán']],
            'danh-muc' => [DB::table('danh_muc_dich_vu')->orderBy('ten_danh_muc'), ['ma_danh_muc' => 'Mã', 'ten_danh_muc' => 'Danh mục', 'mo_ta' => 'Mô tả', 'dang_hoat_dong' => 'Hoạt động']],
            'hang-thanh-vien' => [DB::table('hang_thanh_vien')->orderBy('ty_le_giam_gia'), ['ma_hang_thanh_vien' => 'Mã', 'ten_hang' => 'Hạng', 'ty_le_giam_gia' => 'Giảm %', 'dang_hoat_dong' => 'Hoạt động']],
            'phong-giuong' => [DB::table('giuong as g')->join('phong as p', 'p.ma_phong', '=', 'g.ma_phong')->select('g.*', 'p.ten_phong')->orderBy('p.ten_phong')->orderBy('g.ten_giuong'), ['ma_giuong' => 'Mã', 'ten_phong' => 'Phòng', 'ten_giuong' => 'Giường', 'dang_hoat_dong' => 'Hoạt động']],
            'lich-lam-viec' => [DB::table('lich_lam_viec as ca')->join('nguoi_dung as nv', 'nv.ma_nguoi_dung', '=', 'ca.ma_nhan_vien')->select('ca.*', 'nv.ten_nguoi_dung')
                ->when($admin && $request->filled('ma_nhan_vien'), fn (Builder $q) => $q->where('ca.ma_nhan_vien', (int) $request->input('ma_nhan_vien')))->when(! $admin, fn (Builder $q) => $q->where('ca.ma_nhan_vien', $request->user()->getKey()))->orderByDesc('ca.ngay_lam_viec'),
                ['ten_nguoi_dung' => 'Nhân viên', 'ngay_lam_viec' => 'Ngày', 'gio_bat_dau' => 'Bắt đầu', 'gio_ket_thuc' => 'Kết thúc', 'co_the_nhan_lich' => 'Nhận lịch']],
            'lieu-trinh' => [DB::table('goi_da_mua as gm')->join('nguoi_dung as kh', 'kh.ma_nguoi_dung', '=', 'gm.ma_khach_hang')->select('gm.*', 'kh.ten_nguoi_dung as khach_hang')
                ->when(! $admin, fn (Builder $q) => $q->whereExists(fn (Builder $a) => $a->selectRaw('1')->from('lich_hen as a')->whereColumn('a.ma_goi_da_mua', 'gm.ma_goi_da_mua')->where('a.ma_nhan_vien', $request->user()->getKey())))->orderByDesc('gm.thoi_diem_mua'),
                ['ma_goi_da_mua' => 'Mã gói', 'khach_hang' => 'Khách hàng', 'ten_goi_luc_mua' => 'Gói đã mua', 'tong_so_buoi' => 'Tổng buổi', 'so_buoi_da_dung' => 'Đã dùng', 'trang_thai' => 'Trạng thái']],
            'hoa-don' => [DB::table('hoa_don as hd')->join('nguoi_dung as kh', 'kh.ma_nguoi_dung', '=', 'hd.ma_khach_hang')->select('hd.*', 'kh.ten_nguoi_dung as khach_hang')->orderByDesc('hd.ma_hoa_don'),
                ['so_hoa_don' => 'Số hóa đơn', 'khach_hang' => 'Khách hàng', 'ten_noi_dung_luc_lap' => 'Nội dung', 'thanh_tien' => 'Giá trị', 'credit_chuyen_goi' => 'Chuyển từ gói', 'tien_can_thu' => 'Cần thu', 'so_tien_da_thu' => 'Đã ghi nhận', 'trang_thai_thanh_toan' => 'Thanh toán', 'trang_thai_xu_ly' => 'Xử lý']],
            'chuyen-goi' => [DB::table('chuyen_goi as cg')->join('nguoi_dung as kh', 'kh.ma_nguoi_dung', '=', 'cg.ma_khach_hang')->select('cg.*', 'kh.ten_nguoi_dung as khach_hang')->orderByDesc('cg.ma_chuyen_goi'),
                ['ma_chuyen_goi' => 'Mã', 'khach_hang' => 'Khách', 'ma_goi_nguon' => 'Gói nguồn', 'ten_goi_dich_luc_duyet' => 'Gói đích', 'gia_tri_chuyen' => 'Giá trị chuyển', 'tien_thu_them' => 'Thu thêm', 'trang_thai' => 'Trạng thái']],
            'de-nghi-goi' => [DB::table('de_nghi_mua_goi as dn')->join('nguoi_dung as kh', 'kh.ma_nguoi_dung', '=', 'dn.ma_khach_hang')
                ->join('goi_lieu_trinh as g', 'g.ma_goi_lieu_trinh', '=', 'dn.ma_goi_lieu_trinh')
                ->leftJoin('hoa_don as hd', 'hd.ma_goi_da_mua', '=', 'dn.ma_goi_da_mua')
                ->select('dn.ma_de_nghi', 'dn.ma_goi_da_mua', 'kh.ten_nguoi_dung as khach_hang', 'g.ten_goi', 'g.gia', 'dn.thoi_diem_gui',
                    'hd.ma_hoa_don', 'hd.so_hoa_don', 'hd.tien_can_thu')
                ->selectRaw("COALESCE(hd.trang_thai_xu_ly, 'none') AS trang_thai_xu_ly")
                ->selectRaw("CASE WHEN dn.ma_goi_da_mua IS NULL THEN 'waiting_invoice' ELSE COALESCE(hd.trang_thai_thanh_toan, 'unpaid') END AS trang_thai_thanh_toan")
                ->orderByDesc('dn.ma_de_nghi'),
                ['ma_de_nghi' => 'Mã đơn', 'khach_hang' => 'Khách hàng', 'ten_goi' => 'Gói đã chọn', 'gia' => 'Giá niêm yết', 'thoi_diem_gui' => 'Thời điểm đặt', 'so_hoa_don' => 'Hóa đơn', 'tien_can_thu' => 'Cần thu', 'trang_thai_thanh_toan' => 'Thanh toán', 'trang_thai_xu_ly' => 'Đối soát']],
            default => [null, []],
        };
    }

    public function xuatBaoCao(Request $request): StreamedResponse
    {
        abort_unless($request->user()->vai_tro === 'admin', 403);
        $data = $request->validate(['tu' => ['required', 'date_format:Y-m-d'], 'den' => ['required', 'date_format:Y-m-d', 'after_or_equal:tu']]);
        $rows = DB::table('hoa_don')->where('trang_thai_thanh_toan', 'paid')->where('thoi_diem_thanh_toan', '>=', $data['tu'])
            ->where('thoi_diem_thanh_toan', '<', Carbon::parse($data['den'])->addDay())->orderBy('ma_hoa_don')
            ->select(['so_hoa_don', 'thoi_diem_thanh_toan', 'tien_truoc_giam', 'tien_giam', 'thanh_tien', 'credit_chuyen_goi', 'so_tien_da_thu', 'trang_thai_xu_ly'])
            ->selectRaw("CASE WHEN EXISTS (SELECT 1 FROM chuyen_goi cg WHERE cg.ma_hoa_don=hoa_don.ma_hoa_don AND cg.trang_thai='completed') THEN 'Chuyển gói' WHEN ma_lich_hen IS NOT NULL THEN 'Dịch vụ lẻ' ELSE 'Mua gói' END AS loai_giao_dich")->get();

        return response()->streamDownload(function () use ($rows): void {
            $f = fopen('php://output', 'w');
            fwrite($f, "\xEF\xBB\xBF");
            fputcsv($f, ['Hóa đơn', 'Thời điểm', 'Trước giảm', 'Ưu đãi', 'Giá trị sau giảm', 'Giá trị chuyển nội bộ', 'Tiền đã ghi nhận', 'Trạng thái xử lý', 'Loại giao dịch'], ',', '"', '');
            foreach ($rows as $row) {
                $values = array_map(fn ($v) => preg_match('/^[=+@\-\t\r]/u', (string) $v) ? "'".$v : $v, (array) $row);
                fputcsv($f, $values, ',', '"', '');
            }
            fclose($f);
        }, 'bao-cao-'.$data['tu'].'-'.$data['den'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
