<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class GuiNhacHenSpa extends Command
{
    protected $signature = 'spa:gui-nhac-hen {--thu-lai-da-doi-soat : Chỉ dùng sau khi xác minh email lỗi chưa được gửi}';

    protected $description = 'Gửi email nhắc lịch đã xác nhận trước 24 giờ, không gửi lại lịch cũ';

    public function handle(): int
    {
        if (in_array(config('mail.mailers.'.config('mail.default').'.transport'), ['log', 'array'], true)) {
            $this->error('Chưa cấu hình dịch vụ gửi email thật. Không đánh dấu email là đã gửi.');

            return self::FAILURE;
        }
        $statuses = $this->option('thu-lai-da-doi-soat') ? ['pending', 'failed'] : ['pending'];
        $ids = DB::table('nhac_hen')->whereIn('trang_thai', $statuses)->where('thoi_diem_du_kien_gui', '<=', now())->orderBy('ma_nhac_hen')->limit(100)->pluck('ma_nhac_hen');
        $sent = 0;
        foreach ($ids as $id) {
            $claimed = DB::table('nhac_hen')->where('ma_nhac_hen', $id)->whereIn('trang_thai', $statuses)->update([
                'trang_thai' => 'processing', 'thoi_diem_bat_dau_xu_ly' => now(), 'so_lan_thu_gui' => DB::raw('so_lan_thu_gui + 1'),
            ]);
            if (! $claimed) {
                continue;
            }
            $ok = DB::transaction(function () use ($id): bool {
                $pre = DB::table('nhac_hen')->where('ma_nhac_hen', $id)->first();
                $lh = DB::table('lich_hen')->where('ma_lich_hen', $pre->ma_lich_hen)->lockForUpdate()->first();
                $nhac = DB::table('nhac_hen')->where('ma_nhac_hen', $id)->lockForUpdate()->first();
                if ($nhac->trang_thai !== 'processing') {
                    return false;
                }
                $kh = $lh ? DB::table('nguoi_dung')->where('ma_nguoi_dung', $lh->ma_khach_hang)->first() : null;
                if (! $lh || $lh->trang_thai !== 'confirmed' || $lh->thoi_diem_bat_dau !== $nhac->thoi_diem_hen_luu_lai || Carbon::parse($lh->thoi_diem_bat_dau)->isPast() || ! $kh?->dang_hoat_dong) {
                    DB::table('nhac_hen')->where('ma_nhac_hen', $id)->update(['trang_thai' => 'cancelled', 'thoi_diem_cap_nhat' => now()]);

                    return false;
                }
                if (! filter_var($kh->dia_chi_thu_dien_tu, FILTER_VALIDATE_EMAIL)) {
                    DB::table('nhac_hen')->where('ma_nhac_hen', $id)->update(['trang_thai' => 'failed', 'loi_gan_nhat' => 'Khách chưa có email hợp lệ', 'thoi_diem_cap_nhat' => now()]);

                    return false;
                }
                try {
                    Mail::raw('An Nhiên Spa nhắc lịch: '.$lh->ten_dich_vu_luc_dat.' lúc '.Carbon::parse($lh->thoi_diem_bat_dau)->format('H:i d/m/Y').'. Mã lịch #'.$lh->ma_lich_hen.'. Vui lòng liên hệ spa nếu cần thay đổi.', function ($mail) use ($kh): void {
                        $mail->to($kh->dia_chi_thu_dien_tu)->subject('An Nhiên Spa nhắc lịch hẹn');
                    });
                    DB::table('nhac_hen')->where('ma_nhac_hen', $id)->update(['trang_thai' => 'sent', 'thoi_diem_da_gui' => now(), 'loi_gan_nhat' => null, 'thoi_diem_cap_nhat' => now()]);

                    return true;
                } catch (\Throwable $e) {
                    DB::table('nhac_hen')->where('ma_nhac_hen', $id)->update(['trang_thai' => 'failed', 'loi_gan_nhat' => 'Gửi chưa được xác nhận. Đối soát nhà cung cấp trước khi thử lại.', 'thoi_diem_cap_nhat' => now()]);

                    return false;
                }
            });
            $sent += (int) $ok;
        }
        $this->info('Đã ghi nhận gửi '.$sent.' email. Hàng lỗi hoặc đang xử lý cần được đối soát trước khi gửi lại.');

        return self::SUCCESS;
    }
}
