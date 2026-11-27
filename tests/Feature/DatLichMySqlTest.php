<?php

namespace Tests\Feature;

use App\Models\NguoiDung;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DatLichMySqlTest extends TestCase
{
    public function test_customer_submission_and_admin_confirmation_on_mysql_are_rolled_back(): void
    {
        if (getenv('RUN_MYSQL_BOOKING_TESTS') !== '1') {
            $this->markTestSkipped('Chỉ chạy khi chủ động bật luồng đặt lịch MySQL trong giao dịch hoàn tác.');
        }
        $connection = config('database.connections.mysql');
        $connection['database'] = 'do_an_spa';
        config(['database.connections.booking_mysql' => $connection, 'database.default' => 'booking_mysql']);
        DB::purge('booking_mysql');
        $this->assertSame('do_an_spa', DB::connection()->getDatabaseName());
        $prefix = 'booking-test-'.Str::lower(Str::random(12));
        DB::beginTransaction();
        try {
            $people = [];
            foreach (['customer', 'employee', 'admin'] as $role) {
                $user = new NguoiDung;
                $user->ten_nguoi_dung = $prefix.'-'.$role;
                $user->dia_chi_thu_dien_tu = $prefix.'-'.$role.'@example.test';
                $user->mat_khau = 'KiemThuDatLich123!';
                $user->vai_tro = $role;
                $user->dang_hoat_dong = true;
                $user->save();
                $people[$role] = $user;
            }
            $category = DB::table('danh_muc_dich_vu')->insertGetId(['ten_danh_muc' => $prefix]);
            $service = DB::table('dich_vu')->insertGetId(['ma_danh_muc' => $category, 'ten_dich_vu' => $prefix, 'gia' => 500000, 'thoi_luong_phut' => 60]);
            $room = DB::table('phong')->insertGetId(['ten_phong' => $prefix]);
            $bed = DB::table('giuong')->insertGetId(['ma_phong' => $room, 'ten_giuong' => $prefix]);
            $date = now()->addDay()->toDateString();
            DB::table('lich_lam_viec')->insert(['ma_nhan_vien' => $people['employee']->getKey(), 'ngay_lam_viec' => $date, 'gio_bat_dau' => '09:00:00', 'gio_ket_thuc' => '18:00:00']);
            $this->actingAs($people['customer'])->getJson('/dat-lich/khung-gio?ma_dich_vu='.$service.'&ngay='.$date)->assertOk()->assertJsonFragment(['gio' => '10:00', 'ket_thuc' => '11:00']);
            $payload = ['ho_ten' => $people['customer']->ten_nguoi_dung, 'so_dien_thoai' => '0912345678', 'ma_dich_vu' => $service,
                'ngay' => $date, 'gio' => '10:00', 'gia_da_xem' => 500000, 'thoi_luong_da_xem' => 60];
            $this->post('/dat-lich', $payload)->assertRedirect('/dat-lich')->assertSessionHasNoErrors();
            $row = DB::table('lich_hen')->where('ma_khach_hang', $people['customer']->getKey())->sole();
            $this->assertSame('pending', $row->trang_thai);
            $this->assertNull($row->ma_nhan_vien);
            $this->post('/dat-lich', $payload)->assertRedirect('/dat-lich')->assertSessionHasNoErrors();
            $this->assertSame(1, DB::table('lich_hen')->where('ma_khach_hang', $people['customer']->getKey())->count());
            $this->actingAs($people['admin'])->post('/noi-bo/lich/'.$row->ma_lich_hen, ['hanh_dong' => 'confirmed', 'phien_ban' => 1,
                'ma_nhan_vien' => $people['employee']->getKey(), 'ma_giuong' => $bed])->assertRedirect()->assertSessionHasNoErrors();
            $this->assertSame('confirmed', DB::table('lich_hen')->where('ma_lich_hen', $row->ma_lich_hen)->value('trang_thai'));
            $this->actingAs($people['customer'])->get('/dat-lich')->assertOk()->assertSee('Đã xác nhận')->assertSee($prefix);
        } finally {
            DB::rollBack();
            Auth::forgetGuards();
        }
        $this->assertFalse(DB::table('nguoi_dung')->where('dia_chi_thu_dien_tu', 'like', $prefix.'%')->exists());
        $this->assertFalse(DB::table('dich_vu')->where('ten_dich_vu', $prefix)->exists());
        $this->assertFalse(DB::table('phong')->where('ten_phong', $prefix)->exists());
    }
}
