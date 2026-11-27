<?php

namespace Tests\Feature;

use App\Models\NguoiDung;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class XacThucMySqlTest extends TestCase
{
    public function test_registration_login_disabled_account_and_logout_on_mysql(): void
    {
        if (getenv('RUN_MYSQL_AUTH_TESTS') !== '1') {
            $this->markTestSkipped('Chỉ chạy khi chủ động bật kiểm thử MySQL trên WAMP.');
        }

        config(['database.connections.mysql.database' => 'do_an_spa', 'database.default' => 'mysql', 'auth.providers.users.model' => NguoiDung::class]);
        DB::purge('mysql');
        $data = [
            'ten_nguoi_dung' => 'Kiểm thử xác thực',
            'dia_chi_thu_dien_tu' => 'kiemthu-'.Str::lower(Str::random(20)).'@example.test',
            'password' => 'KiemThu123!',
            'password_confirmation' => 'KiemThu123!',
            'vai_tro' => 'admin',
            'ma_hang_thanh_vien' => 999,
        ];

        DB::beginTransaction();

        try {
            $this->post('/dang-ky', $data)->assertRedirect('/dang-nhap')->assertSessionHasNoErrors();
            $this->assertGuest();
            $user = NguoiDung::where('dia_chi_thu_dien_tu', $data['dia_chi_thu_dien_tu'])->sole();
            $this->assertSame('customer', $user->vai_tro);
            $this->assertNull($user->ma_hang_thanh_vien);
            $this->assertTrue(Hash::check($data['password'], $user->mat_khau));
            $this->assertNotNull($user->thoi_diem_tao);
            $this->post('/dang-ky', $data)->assertSessionHasErrors('dia_chi_thu_dien_tu');
            $this->post('/dang-nhap', $data)->assertRedirect('/')->assertSessionHasNoErrors();
            Auth::forgetGuards();
            $this->get('/tai-khoan')->assertOk()->assertSee('Kiểm thử xác thực');
            $this->post('/dang-xuat')->assertRedirect('/');
            $this->assertGuest();
            $this->get('/tai-khoan')->assertRedirect('/dang-nhap');
            $user->dang_hoat_dong = false;
            $user->save();
            $this->post('/dang-nhap', $data)->assertSessionHasErrors('dia_chi_thu_dien_tu');
            $this->assertGuest();
        } finally {
            DB::rollBack();
        }

        $this->assertFalse(NguoiDung::where('dia_chi_thu_dien_tu', $data['dia_chi_thu_dien_tu'])->exists());
    }
}

