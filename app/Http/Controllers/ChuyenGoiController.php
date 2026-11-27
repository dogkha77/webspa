<?php

namespace App\Http\Controllers;

use App\NghiepVuSpa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ChuyenGoiController extends Controller
{
    private function actor(Request $request): object
    {
        $actor = DB::table('nguoi_dung')->where('ma_nguoi_dung', $request->user()->getKey())->lockForUpdate()->first();
        abort_unless($actor && $actor->dang_hoat_dong, 403);
        abort_unless(in_array($actor->vai_tro, ['admin', 'customer'], true) || ($actor->vai_tro === 'employee' && ($actor->co_quyen_tiep_nhan || $actor->co_quyen_thu_tien)), 403);

        return $actor;
    }

    public function hienThi(Request $request): View
    {
        $actor = $this->actor($request);
        $khach = $actor->vai_tro === 'customer';
        $cacGoi = DB::table('goi_da_mua as gm')->join('nguoi_dung as kh', 'kh.ma_nguoi_dung', '=', 'gm.ma_khach_hang')
            ->where('gm.trang_thai', 'active')->when($khach, fn ($q) => $q->where('gm.ma_khach_hang', $actor->ma_nguoi_dung))
            ->select('gm.*', 'kh.ten_nguoi_dung as khach_hang')->get();
        $yeuCau = DB::table('chuyen_goi as cg')->join('nguoi_dung as kh', 'kh.ma_nguoi_dung', '=', 'cg.ma_khach_hang')
            ->join('goi_lieu_trinh as g', 'g.ma_goi_lieu_trinh', '=', 'cg.ma_mau_goi_dich')
            ->when($khach, fn ($q) => $q->where('cg.ma_khach_hang', $actor->ma_nguoi_dung))
            ->select('cg.*', 'kh.ten_nguoi_dung as khach_hang', 'g.ten_goi as ten_mau_hien_tai')->orderByDesc('cg.ma_chuyen_goi')->paginate(10);
        $baoGia = [];
        foreach ($yeuCau as $cg) {
            if ($cg->trang_thai === 'pending') {
                $nguon = DB::table('goi_da_mua')->where('ma_goi_da_mua', $cg->ma_goi_nguon)->first();
                $dich = TrangKhachController::goiDangBan()->where('g.ma_goi_lieu_trinh', $cg->ma_mau_goi_dich)->first();
                if ($nguon && $dich) {
                    $gia = NghiepVuSpa::gia($cg->ma_khach_hang, $dich->gia);
                    $v = (int) floor((float) $nguon->gia_tri_cap_von * ($nguon->tong_so_buoi - $nguon->so_buoi_da_dung) / $nguon->tong_so_buoi);
                    $baoGia[$cg->ma_chuyen_goi] = ['V' => $v, 'P' => $gia['thanh_tien'], 'D' => $gia['thanh_tien'] - $v,
                        'phien_ban_nguon' => $nguon->phien_ban];
                }
            }
        }

        return view('pages.chuyen-goi', ['cacGoi' => $cacGoi, 'yeuCau' => $yeuCau, 'baoGia' => $baoGia,
            'nhatKy' => DB::table('nhat_ky_quan_tri')->where('loai_doi_tuong', 'package_transfer')->whereIn('ma_doi_tuong', $yeuCau->pluck('ma_chuyen_goi'))->orderByDesc('ma_nhat_ky')->get()->groupBy('ma_doi_tuong'),
            'mauGoi' => TrangKhachController::goiDangBan()->get(), 'actor' => $actor]);
    }

    public function deNghi(Request $request): RedirectResponse
    {
        $data = $request->validate(['ma_goi_nguon' => ['required', 'integer'], 'ma_mau_goi_dich' => ['required', 'integer'],
            'ly_do' => ['required', 'string', 'max:2000']]);
        DB::transaction(function () use ($request, $data): void {
            $actor = $this->actor($request);
            abort_unless($actor->vai_tro !== 'employee' || $actor->co_quyen_tiep_nhan, 403);
            $g = DB::table('goi_da_mua')->where('ma_goi_da_mua', $data['ma_goi_nguon'])->lockForUpdate()->first();
            abort_unless($g, 404);
            abort_if($actor->vai_tro === 'customer' && $g->ma_khach_hang !== $actor->ma_nguoi_dung, 403);
            $this->kiemTraNguon($g);
            if ($g->ma_goi_lieu_trinh === (int) $data['ma_mau_goi_dich'] || ! TrangKhachController::goiDangBan()->where('g.ma_goi_lieu_trinh', $data['ma_mau_goi_dich'])->exists()) {
                NghiepVuSpa::loi('Cần chọn mẫu gói khác đang bán.');
            }
            if (DB::table('chuyen_goi')->where('ma_goi_nguon', $g->ma_goi_da_mua)->whereIn('trang_thai', ['pending', 'awaiting_payment'])->exists()) {
                NghiepVuSpa::loi('Gói đã có một yêu cầu đang xử lý.');
            }
            $id = DB::table('chuyen_goi')->insertGetId(['khoa_chong_lap' => (string) Str::uuid(), 'ma_khach_hang' => $g->ma_khach_hang,
                'ma_goi_nguon' => $g->ma_goi_da_mua, 'ma_mau_goi_dich' => $data['ma_mau_goi_dich'], 'ma_nguoi_de_nghi' => $actor->ma_nguoi_dung,
                'ly_do_de_nghi' => $data['ly_do'], 'thoi_diem_de_nghi' => now()]);
            NghiepVuSpa::nhatKy($actor->ma_nguoi_dung, 'package_transfer', $id, 'request_transfer', null, $data, $data['ly_do']);
        }, 3);

        return back()->with('thong_bao', 'Đã ghi đề nghị. Giá trị sẽ được kiểm tra lại khi duyệt.');
    }

    private function kiemTraNguon(object $g): void
    {
        $paid = DB::table('hoa_don')->where('ma_goi_da_mua', $g->ma_goi_da_mua)->where('trang_thai_thanh_toan', 'paid')->where('trang_thai_xu_ly', '<>', 'error')->exists();
        $kh = DB::table('nguoi_dung')->where('ma_nguoi_dung', $g->ma_khach_hang)->where('vai_tro', 'customer')->where('dang_hoat_dong', true)->exists();
        if (! $kh || ! $paid || $g->trang_thai !== 'active' || $g->so_buoi_da_dung >= $g->tong_so_buoi) {
            NghiepVuSpa::loi('Gói nguồn phải còn buổi, đang hoạt động và đã thanh toán hợp lệ.');
        }
    }

    public function xuLy(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate(['hanh_dong' => ['required', 'in:approve,complete,reject,reconcile'], 'phien_ban' => ['required', 'integer'],
            'phien_ban_nguon' => ['nullable', 'integer'], 'gia_tri_chuyen' => ['nullable', 'integer'], 'gia_goi_dich' => ['nullable', 'integer'],
            'khach_dong_y' => ['sometimes', 'accepted'], 'chua_nhan_tien' => ['sometimes', 'accepted'], 'da_nhan_du' => ['sometimes', 'accepted'],
            'phuong_thuc' => ['nullable', 'in:cash,bank_transfer,no_charge'], 'ly_do' => ['nullable', 'string', 'max:2000']]);
        DB::transaction(function () use ($request, $id, $data): void {
            $actor = $this->actor($request);
            $pre = DB::table('chuyen_goi')->where('ma_chuyen_goi', $id)->first();
            abort_unless($pre, 404);
            $g = DB::table('goi_da_mua')->where('ma_goi_da_mua', $pre->ma_goi_nguon)->lockForUpdate()->first();
            $cg = DB::table('chuyen_goi')->where('ma_chuyen_goi', $id)->lockForUpdate()->first();
            $action = $data['hanh_dong'];
            if ($action === 'complete') {
                abort_unless($actor->vai_tro === 'admin' || ($actor->vai_tro === 'employee' && $actor->co_quyen_thu_tien), 403);
                if ($cg->trang_thai === 'completed') {
                    return;
                }
            } elseif (in_array($action, ['approve', 'reconcile'], true)) {
                abort_unless($actor->vai_tro === 'admin', 403);
            } else {
                abort_unless($actor->vai_tro === 'admin' || ($actor->vai_tro === 'customer' && $cg->ma_khach_hang === $actor->ma_nguoi_dung && $cg->trang_thai === 'pending'), 403);
            }
            if ($cg->phien_ban !== (int) $data['phien_ban']) {
                NghiepVuSpa::loi('Yêu cầu đã thay đổi. Hãy tải lại để kiểm tra.');
            }
            if ($action === 'approve') {
                if ($cg->trang_thai !== 'pending') {
                    NghiepVuSpa::loi('Chỉ duyệt đề nghị đang chờ.');
                }
                $this->kiemTraNguon($g);
                if (DB::table('lich_hen')->where('ma_goi_da_mua', $g->ma_goi_da_mua)->whereIn('trang_thai', ['pending', 'confirmed'])->exists()) {
                    NghiepVuSpa::loi('Cần đổi hoặc hủy hết lịch chưa kết thúc của gói nguồn trước khi duyệt.');
                }
                $dich = TrangKhachController::goiDangBan()->where('g.ma_goi_lieu_trinh', $cg->ma_mau_goi_dich)->lockForUpdate()->first();
                if (! $dich) {
                    NghiepVuSpa::loi('Gói đích không còn được bán.');
                }
                $gia = NghiepVuSpa::gia($cg->ma_khach_hang, $dich->gia);
                $v = (int) floor((float) $g->gia_tri_cap_von * ($g->tong_so_buoi - $g->so_buoi_da_dung) / $g->tong_so_buoi);
                if (empty($data['khach_dong_y']) || (int) ($data['phien_ban_nguon'] ?? 0) !== $g->phien_ban
                    || ! isset($data['gia_tri_chuyen'], $data['gia_goi_dich']) || (int) $data['gia_tri_chuyen'] !== $v || (int) $data['gia_goi_dich'] !== $gia['thanh_tien'] || $gia['thanh_tien'] < $v) {
                    NghiepVuSpa::loi('Báo giá đã đổi, chưa có khách đồng ý hoặc giá gói đích thấp hơn giá trị chuyển. Hãy tải lại và xác nhận phương án mới.');
                }
                $update = ['trang_thai' => 'awaiting_payment', 'trang_thai_nguon_truoc_khoa' => 'active', 'so_buoi_nguon' => $g->tong_so_buoi,
                    'so_buoi_da_dung_luc_duyet' => $g->so_buoi_da_dung, 'gia_tri_cap_von_nguon' => $g->gia_tri_cap_von, 'gia_tri_chuyen' => $v,
                    'ma_dich_vu_dich_luc_duyet' => $dich->ma_dich_vu, 'ten_goi_dich_luc_duyet' => $dich->ten_goi,
                    'ten_dich_vu_dich_luc_duyet' => $dich->ten_dich_vu, 'so_buoi_goi_dich' => $dich->tong_so_buoi,
                    'gia_truoc_giam_goi_dich' => $gia['tien_truoc_giam'], 'tien_giam_goi_dich' => $gia['tien_giam'],
                    'ten_hang_luc_duyet' => $gia['ten_hang_luc_lap'], 'ty_le_giam_luc_duyet' => $gia['ty_le_giam_luc_lap'],
                    'gia_goi_dich_sau_giam' => $gia['thanh_tien'], 'tien_thu_them' => $gia['thanh_tien'] - $v,
                    'phien_ban_chinh_sach' => '2026-11-v1', 'quy_tac_lam_tron' => 'floor_vnd', 'phien_ban_nguon_luc_duyet' => $g->phien_ban,
                    'thoi_diem_khach_dong_y' => now(), 'ma_nguoi_duyet' => $actor->ma_nguoi_dung, 'thoi_diem_duyet' => now(), 'phien_ban' => $cg->phien_ban + 1];
                DB::table('goi_da_mua')->where('ma_goi_da_mua', $g->ma_goi_da_mua)->update(['trang_thai' => 'transfer_locked', 'phien_ban' => $g->phien_ban + 1, 'thoi_diem_cap_nhat' => now()]);
                DB::table('chuyen_goi')->where('ma_chuyen_goi', $id)->update($update);
                $cg = (object) array_merge((array) $cg, $update);
                if ($update['tien_thu_them'] === 0) {
                    $this->hoanTat($cg, $actor, 'no_charge');
                }
            } elseif ($action === 'complete') {
                if ($cg->trang_thai !== 'awaiting_payment' || $g->trang_thai !== 'transfer_locked'
                    || $g->phien_ban !== $cg->phien_ban_nguon_luc_duyet + 1 || empty($data['da_nhan_du'])
                    || ! DB::table('hoa_don')->where('ma_goi_da_mua', $g->ma_goi_da_mua)->where('trang_thai_thanh_toan', 'paid')->where('trang_thai_xu_ly', '<>', 'error')->exists()
                    || ! in_array($data['phuong_thuc'] ?? '', ['cash', 'bank_transfer'], true)) {
                    NghiepVuSpa::loi('Cần yêu cầu đang chờ tiền, nguồn còn khóa và xác nhận đã nhận đủ đúng khoản chênh lệch.');
                }
                $this->hoanTat($cg, $actor, $data['phuong_thuc']);
            } elseif ($action === 'reconcile') {
                if ($cg->trang_thai !== 'awaiting_payment' || empty($data['ly_do'])) {
                    NghiepVuSpa::loi('Ghi căn cứ đối soát cho yêu cầu đang chờ hoàn tất và nhập đủ nội dung.');
                }
                DB::table('chuyen_goi')->where('ma_chuyen_goi', $id)->update(['phien_ban' => $cg->phien_ban + 1]);
            } else {
                if (! in_array($cg->trang_thai, ['pending', 'awaiting_payment'], true) || empty($data['ly_do'])
                    || ($cg->trang_thai === 'awaiting_payment' && empty($data['chua_nhan_tien']))) {
                    NghiepVuSpa::loi('Chỉ rút/từ chối yêu cầu chưa hoàn tất, có lý do và xác minh chưa nhận tiền.');
                }
                if ($cg->trang_thai === 'awaiting_payment') {
                    if ($g->trang_thai !== 'transfer_locked' || $g->phien_ban !== $cg->phien_ban_nguon_luc_duyet + 1) {
                        NghiepVuSpa::loi('Gói nguồn đã thay đổi, cần đối soát trước khi mở khóa.');
                    }
                    DB::table('goi_da_mua')->where('ma_goi_da_mua', $g->ma_goi_da_mua)->update(['trang_thai' => 'active', 'phien_ban' => $g->phien_ban + 1, 'thoi_diem_cap_nhat' => now()]);
                }
                DB::table('chuyen_goi')->where('ma_chuyen_goi', $id)->update(['trang_thai' => 'rejected', 'ly_do_ket_thuc' => $data['ly_do'],
                    'ma_nguoi_ket_thuc' => $actor->ma_nguoi_dung, 'thoi_diem_ket_thuc' => now(), 'phien_ban' => $cg->phien_ban + 1]);
            }
            NghiepVuSpa::nhatKy($actor->ma_nguoi_dung, 'package_transfer', $id, $action, $pre,
                DB::table('chuyen_goi')->where('ma_chuyen_goi', $id)->first(), $data['ly_do'] ?? 'Đã đối chiếu điều kiện và xác nhận khách hàng');
        }, 3);

        return back()->with('thong_bao', 'Đã lưu kết quả xử lý chuyển gói.');
    }

    private function hoanTat(object $cg, object $actor, string $phuongThuc): void
    {
        $new = DB::table('goi_da_mua')->insertGetId(['ma_khach_hang' => $cg->ma_khach_hang, 'ma_goi_lieu_trinh' => $cg->ma_mau_goi_dich,
            'ma_nguoi_ban' => $actor->ma_nguoi_dung, 'thoi_diem_mua' => now(), 'ma_dich_vu' => $cg->ma_dich_vu_dich_luc_duyet,
            'tong_so_buoi' => $cg->so_buoi_goi_dich, 'gia_mua' => $cg->gia_goi_dich_sau_giam, 'gia_tri_cap_von' => $cg->gia_goi_dich_sau_giam,
            'ten_goi_luc_mua' => $cg->ten_goi_dich_luc_duyet, 'ten_dich_vu_luc_mua' => $cg->ten_dich_vu_dich_luc_duyet,
            'trang_thai' => 'active', 'thoi_diem_tao' => now(), 'thoi_diem_cap_nhat' => now()]);
        $hd = DB::table('hoa_don')->insertGetId(['so_hoa_don' => 'HD-'.Str::upper((string) Str::ulid()), 'ma_khach_hang' => $cg->ma_khach_hang,
            'ma_goi_da_mua' => $new, 'ma_nguoi_lap' => $cg->ma_nguoi_duyet, 'tien_truoc_giam' => $cg->gia_truoc_giam_goi_dich,
            'tien_giam' => $cg->tien_giam_goi_dich, 'ten_hang_luc_lap' => $cg->ten_hang_luc_duyet, 'ty_le_giam_luc_lap' => $cg->ty_le_giam_luc_duyet,
            'thanh_tien' => $cg->gia_goi_dich_sau_giam, 'ten_noi_dung_luc_lap' => $cg->ten_goi_dich_luc_duyet,
            'credit_chuyen_goi' => $cg->gia_tri_chuyen, 'tien_can_thu' => $cg->tien_thu_them, 'so_tien_da_thu' => $cg->tien_thu_them,
            'ma_nguoi_thu' => $actor->ma_nguoi_dung, 'phuong_thuc_thanh_toan' => $phuongThuc, 'trang_thai_thanh_toan' => 'paid',
            'thoi_diem_thanh_toan' => now(), 'thoi_diem_tao' => now(), 'thoi_diem_cap_nhat' => now()]);
        DB::table('goi_da_mua')->where('ma_goi_da_mua', $cg->ma_goi_nguon)->update(['trang_thai' => 'transferred', 'phien_ban' => DB::raw('phien_ban + 1'), 'thoi_diem_cap_nhat' => now()]);
        DB::table('chuyen_goi')->where('ma_chuyen_goi', $cg->ma_chuyen_goi)->update(['trang_thai' => 'completed', 'ma_goi_dich' => $new,
            'ma_hoa_don' => $hd, 'ma_nguoi_ket_thuc' => $actor->ma_nguoi_dung, 'thoi_diem_ket_thuc' => now(), 'phien_ban' => $cg->phien_ban + 1]);
    }
}
