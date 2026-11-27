<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MuaGoiController extends Controller
{
    /**
     * Ghi nhu cầu mua gói của khách để spa xác nhận trước khi lập hóa đơn.
     */
    public function deNghi(Request $request, int $goi): RedirectResponse
    {
        DB::transaction(function () use ($request, $goi): void {
            $khach = DB::table('nguoi_dung')->where('ma_nguoi_dung', $request->user()->getKey())->lockForUpdate()->first();
            abort_unless($khach && $khach->vai_tro === 'customer' && $khach->dang_hoat_dong, 403);

            if (! TrangKhachController::goiDangBan()->where('g.ma_goi_lieu_trinh', $goi)->lockForUpdate()->first()) {
                throw ValidationException::withMessages(['goi' => 'Gói này hiện không còn nhận đăng ký.']);
            }

            // Khóa hồ sơ khách đến cuối giao dịch để hai lần gửi đồng thời chỉ tạo một đề nghị đang chờ.
            $deNghi = DB::table('de_nghi_mua_goi')->where('ma_khach_hang', $khach->ma_nguoi_dung)
                ->where('ma_goi_lieu_trinh', $goi)->whereNull('ma_goi_da_mua');
            if (! $deNghi->exists()) {
                DB::table('de_nghi_mua_goi')->insert([
                    'ma_khach_hang' => $khach->ma_nguoi_dung,
                    'ma_goi_lieu_trinh' => $goi,
                    'thoi_diem_gui' => now(),
                ]);
            }
        }, 3);

        return redirect()->route('goi-cua-toi')->with('thong_bao', 'Đã gửi đề nghị mua gói. Spa sẽ xác nhận thông tin và lập hóa đơn.');
    }
}
