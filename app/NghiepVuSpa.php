<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class NghiepVuSpa
{
    /**
     * Gắn xác nhận của nhân viên với giá và thời lượng đang hiển thị trên đúng lịch.
     */
    public static function maRaSoatDichVu(object $lich, int|float|string $giaHienTai, int $thoiLuongHienTai): ?string
    {
        $thoiLuongLucDat = (int) Carbon::parse($lich->thoi_diem_bat_dau)->diffInMinutes(Carbon::parse($lich->thoi_diem_ket_thuc));
        $giaHienTai = number_format((float) $giaHienTai, 2, '.', '');
        $giaLucDat = number_format((float) $lich->gia_dich_vu_luc_dat, 2, '.', '');

        if (($lich->ma_goi_da_mua || $giaLucDat === $giaHienTai) && $thoiLuongLucDat === $thoiLuongHienTai) {
            return null;
        }

        return hash('sha256', implode('|', [$lich->ma_lich_hen, $lich->phien_ban, $giaHienTai, $thoiLuongHienTai]));
    }

    public static function gia(int $khach, int|float|string $gia): array
    {
        $hang = DB::table('nguoi_dung as nd')->join('hang_thanh_vien as h', 'h.ma_hang_thanh_vien', '=', 'nd.ma_hang_thanh_vien')
            ->where('nd.ma_nguoi_dung', $khach)->where('h.dang_hoat_dong', true)->select('h.*')->lockForUpdate()->first();
        $goc = (int) round((float) $gia);
        $tyLe = $hang ? (int) round((float) $hang->ty_le_giam_gia * 100) : 0;
        $giam = intdiv($goc * $tyLe, 10000);

        return ['tien_truoc_giam' => $goc, 'tien_giam' => $giam, 'thanh_tien' => $goc - $giam,
            'ten_hang_luc_lap' => $hang?->ten_hang, 'ty_le_giam_luc_lap' => $tyLe / 100];
    }

    public static function nhatKy(int $actor, string $loai, int $id, string $action, mixed $truoc, mixed $sau, string $lyDo): void
    {
        DB::table('nhat_ky_quan_tri')->insert(['ma_nguoi_thao_tac' => $actor, 'loai_doi_tuong' => $loai, 'ma_doi_tuong' => $id,
            'hanh_dong' => $action, 'du_lieu_truoc' => $truoc === null ? null : json_encode($truoc, JSON_UNESCAPED_UNICODE),
            'du_lieu_sau' => $sau === null ? null : json_encode($sau, JSON_UNESCAPED_UNICODE), 'ly_do' => $lyDo,
            'thoi_diem_thao_tac' => now(), 'khoa_yeu_cau' => (string) Str::uuid()]);
    }

    public static function loi(string $message): never
    {
        throw ValidationException::withMessages(['nghiep_vu' => $message]);
    }

    public static function nhacLich(object $lich): void
    {
        DB::table('nhac_hen')->updateOrInsert(['ma_lich_hen' => $lich->ma_lich_hen, 'loai_nhac' => 'before_24h'], [
            'thoi_diem_hen_luu_lai' => $lich->thoi_diem_bat_dau, 'trang_thai' => 'pending',
            'thoi_diem_du_kien_gui' => Carbon::parse($lich->thoi_diem_bat_dau)->subDay(),
            'thoi_diem_da_gui' => null, 'thoi_diem_bat_dau_xu_ly' => null, 'so_lan_thu_gui' => 0, 'loi_gan_nhat' => null,
            'thoi_diem_cap_nhat' => now(),
        ]);
    }
}
