<?php

namespace Tests\Feature;

use App\Models\NguoiDung;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\SchemaSpa;
use Tests\TestCase;

class DatLichTest extends TestCase
{
    use SchemaSpa;

    private NguoiDung $customer;

    private NguoiDung $employee;

    private NguoiDung $admin;

    private int $service;

    private int $room;

    private int $bed;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-11 08:00:00', 'Asia/Ho_Chi_Minh'));
        $this->taoCauTrucSpa();
        $this->customer = $this->nguoiDung('customer', 'Khách đặt lịch');
        $this->employee = $this->nguoiDung('employee', 'Nhân viên phục vụ');
        $this->admin = $this->nguoiDung('admin', 'Quản trị lịch');
        $category = DB::table('danh_muc_dich_vu')->insertGetId(['ten_danh_muc' => 'Chăm sóc thử']);
        $this->service = DB::table('dich_vu')->insertGetId(['ma_danh_muc' => $category, 'ten_dich_vu' => 'Dịch vụ đặt lịch thử', 'gia' => 500000, 'thoi_luong_phut' => 60]);
        $this->room = DB::table('phong')->insertGetId(['ten_phong' => 'Phòng thử']);
        $this->bed = DB::table('giuong')->insertGetId(['ma_phong' => $this->room, 'ten_giuong' => 'Giường thử']);
        $this->taoCa($this->employee->getKey());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function nguoiDung(string $role, string $name): NguoiDung
    {
        $user = new NguoiDung;
        $user->ten_nguoi_dung = $name;
        $user->dia_chi_thu_dien_tu = uniqid('booking').'@example.test';
        $user->so_dien_thoai = '0912345678';
        $user->mat_khau = 'DatLich123!';
        $user->vai_tro = $role;
        $user->dang_hoat_dong = true;
        $user->save();

        return $user;
    }

    private function taoCa(int $employee): void
    {
        DB::table('lich_lam_viec')->insert(['ma_nhan_vien' => $employee, 'ngay_lam_viec' => '2026-11-12', 'gio_bat_dau' => '09:00:00', 'gio_ket_thuc' => '18:00:00']);
    }

    private function duLieu(array $overrides = []): array
    {
        return array_replace(['ho_ten' => $this->customer->ten_nguoi_dung, 'so_dien_thoai' => '0912345678', 'ma_dich_vu' => $this->service,
            'ngay' => '2026-11-12', 'gio' => '10:00', 'gia_da_xem' => 500000, 'thoi_luong_da_xem' => 60], $overrides);
    }

    private function lich(array $overrides = []): int
    {
        return DB::table('lich_hen')->insertGetId(array_replace(['ma_khach_hang' => $this->customer->getKey(), 'ma_dich_vu' => $this->service,
            'ma_nhan_vien' => $this->employee->getKey(), 'ma_giuong' => $this->bed, 'trang_thai' => 'confirmed',
            'thoi_diem_bat_dau' => '2026-11-12 10:00:00', 'thoi_diem_ket_thuc' => '2026-11-12 11:00:00',
            'ten_dich_vu_luc_dat' => 'Dịch vụ đặt lịch thử', 'gia_dich_vu_luc_dat' => 500000], $overrides));
    }

    private function gioGoiY(array $overrides = []): array
    {
        $response = $this->actingAs($this->customer)->getJson('/dat-lich/khung-gio?'.http_build_query(array_replace(['ma_dich_vu' => $this->service, 'ngay' => '2026-11-12'], $overrides)))->assertOk();

        return array_column($response->json('khung_gio'), 'gio');
    }

    public function test_suggested_times_cover_full_duration_and_do_not_reveal_customer_or_employee_details(): void
    {
        $times = $this->gioGoiY();
        $this->assertContains('09:00', $times);
        $this->assertContains('17:00', $times);
        $this->assertNotContains('17:15', $times);
        $response = $this->getJson('/dat-lich/khung-gio?ma_dich_vu='.$this->service.'&ngay=2026-11-12')->assertOk()->assertJsonPath('thoi_luong_phut', 60);
        $response->assertDontSee($this->employee->ten_nguoi_dung)->assertDontSee($this->customer->dia_chi_thu_dien_tu);
        if ($dir = getenv('SPA_BOOKING_CAPTURE_DIR')) {
            file_put_contents($dir.'/availability.json', $response->getContent());
            file_put_contents($dir.'/booking.html', $this->get('/dat-lich?dich_vu='.$this->service)->getContent());
        }
    }

    public function test_availability_endpoint_requires_customer_login_and_valid_date(): void
    {
        $this->getJson('/dat-lich/khung-gio?ma_dich_vu='.$this->service.'&ngay=2026-11-12')->assertUnauthorized();
        $this->actingAs($this->employee)->getJson('/dat-lich/khung-gio?ma_dich_vu='.$this->service.'&ngay=2026-11-12')->assertForbidden();
        $this->actingAs($this->customer)->getJson('/dat-lich/khung-gio?ma_dich_vu='.$this->service.'&ngay=2026-11-10')->assertUnprocessable()->assertJsonValidationErrors('ngay');
    }

    #[DataProvider('nguonLucKhongHopLe')]
    public function test_inactive_resources_do_not_offer_times_or_accept_bookings(string $table, string $column): void
    {
        DB::table($table)->update([$column => false]);
        $this->assertSame([], $this->gioGoiY());
        $this->post('/dat-lich', $this->duLieu())->assertSessionHasErrors('gio');
        $this->assertDatabaseCount('lich_hen', 0);
    }

    public static function nguonLucKhongHopLe(): array
    {
        return [['phong', 'dang_hoat_dong'], ['giuong', 'dang_hoat_dong'], ['lich_lam_viec', 'co_the_nhan_lich']];
    }

    public function test_no_active_employee_or_no_shift_prevents_booking(): void
    {
        $this->employee->dang_hoat_dong = false;
        $this->employee->save();
        $this->assertSame([], $this->gioGoiY());
        $this->post('/dat-lich', $this->duLieu())->assertSessionHasErrors('gio');
        $this->employee->dang_hoat_dong = true;
        $this->employee->save();
        DB::table('lich_lam_viec')->delete();
        $this->assertSame([], $this->gioGoiY());
    }

    public function test_confirmed_booking_blocks_overlaps_but_not_adjacent_time(): void
    {
        $other = $this->nguoiDung('customer', 'Khách khác');
        $this->lich(['ma_khach_hang' => $other->getKey()]);
        $times = $this->gioGoiY();
        $this->assertContains('09:00', $times);
        $this->assertNotContains('09:15', $times);
        $this->assertNotContains('10:00', $times);
        $this->assertContains('11:00', $times);
        $this->post('/dat-lich', $this->duLieu())->assertSessionHasErrors('gio');
    }

    public function test_both_employee_and_bed_must_be_free_for_the_full_interval(): void
    {
        $other = $this->nguoiDung('customer', 'Khách đang phục vụ');
        $this->lich(['ma_khach_hang' => $other->getKey()]);
        $second = $this->nguoiDung('employee', 'Nhân viên thứ hai');
        $this->taoCa($second->getKey());
        $this->assertNotContains('10:00', $this->gioGoiY());
        DB::table('giuong')->insert(['ma_phong' => $this->room, 'ten_giuong' => 'Giường thứ hai']);
        $this->assertContains('10:00', $this->gioGoiY());
        $this->post('/dat-lich', $this->duLieu())->assertRedirect('/dat-lich')->assertSessionHasNoErrors();
    }

    public function test_pending_request_does_not_reserve_resources_for_other_customers(): void
    {
        $other = $this->nguoiDung('customer', 'Khách chờ khác');
        $this->lich(['ma_khach_hang' => $other->getKey(), 'trang_thai' => 'pending', 'ma_nhan_vien' => null, 'ma_giuong' => null]);
        $this->assertContains('10:00', $this->gioGoiY());
        $this->post('/dat-lich', $this->duLieu())->assertRedirect('/dat-lich')->assertSessionHasNoErrors();
        $this->assertDatabaseCount('lich_hen', 2);
    }

    public function test_same_pending_submission_is_reused_but_a_different_overlapping_request_is_rejected(): void
    {
        $this->actingAs($this->customer)->post('/dat-lich', $this->duLieu())->assertRedirect('/dat-lich')->assertSessionHas('dat_lich_ma');
        $id = DB::table('lich_hen')->value('ma_lich_hen');
        $this->post('/dat-lich', $this->duLieu())->assertRedirect('/dat-lich')->assertSessionHas('dat_lich_ma', $id)->assertSessionHasNoErrors();
        $this->post('/dat-lich', $this->duLieu(['gio' => '10:15']))->assertSessionHasErrors('gio');
        $this->assertDatabaseCount('lich_hen', 1);
        $this->assertDatabaseCount('hoa_don', 0);
    }

    public function test_changed_price_or_duration_requires_customer_to_review_again(): void
    {
        $this->actingAs($this->customer);
        DB::table('dich_vu')->where('ma_dich_vu', $this->service)->update(['gia' => 600000]);
        $this->post('/dat-lich', $this->duLieu())->assertSessionHasErrors('ma_dich_vu');
        $this->post('/dat-lich', $this->duLieu(['gia_da_xem' => 600000, 'thoi_luong_da_xem' => 90]))->assertSessionHasErrors('ma_dich_vu');
        $this->assertDatabaseCount('lich_hen', 0);
        $this->post('/dat-lich', $this->duLieu(['gia_da_xem' => 600000]))->assertRedirect('/dat-lich');
        $this->assertDatabaseHas('lich_hen', ['gia_dich_vu_luc_dat' => 600000, 'trang_thai' => 'pending']);
    }

    public function test_history_is_private_and_never_shows_internal_notes(): void
    {
        $this->lich(['trang_thai' => 'pending', 'ghi_chu_nhan_vien' => 'Ghi chú nội bộ không công khai']);
        $this->lich(['ma_khach_hang' => $this->nguoiDung('customer', 'Người khác')->getKey(), 'ten_dich_vu_luc_dat' => 'Dịch vụ riêng của khách khác']);
        $this->actingAs($this->customer)->get('/dat-lich?trang_thai=pending')->assertOk()->assertSee('Chờ xác nhận')
            ->assertDontSee('Ghi chú nội bộ không công khai')->assertDontSee('Dịch vụ riêng của khách khác');
        $this->get('/dat-lich?trang_thai=completed')->assertOk()->assertDontSee('id="lich-1"', false);
    }

    private function goi(int $owner): int
    {
        $template = DB::table('goi_lieu_trinh')->insertGetId(['ma_dich_vu' => $this->service, 'ten_goi' => 'Gói năm buổi', 'tong_so_buoi' => 5, 'gia' => 2000000]);
        $id = DB::table('goi_da_mua')->insertGetId(['ma_khach_hang' => $owner, 'ma_goi_lieu_trinh' => $template, 'ma_dich_vu' => $this->service,
            'thoi_diem_mua' => now(), 'tong_so_buoi' => 5, 'so_buoi_da_dung' => 2, 'gia_mua' => 2000000, 'gia_tri_cap_von' => 2000000,
            'ten_goi_luc_mua' => 'Gói năm buổi', 'ten_dich_vu_luc_mua' => 'Dịch vụ đặt lịch thử', 'trang_thai' => 'active']);
        DB::table('hoa_don')->insert(['so_hoa_don' => uniqid('HD-'), 'ma_khach_hang' => $owner, 'ma_goi_da_mua' => $id,
            'tien_truoc_giam' => 2000000, 'thanh_tien' => 2000000, 'ten_noi_dung_luc_lap' => 'Gói năm buổi', 'tien_can_thu' => 2000000,
            'so_tien_da_thu' => 2000000, 'trang_thai_thanh_toan' => 'paid', 'ma_nguoi_thu' => $this->admin->getKey(), 'phuong_thuc_thanh_toan' => 'cash', 'thoi_diem_thanh_toan' => now()]);

        return $id;
    }

    public function test_package_must_belong_to_customer_and_have_unreserved_paid_sessions(): void
    {
        $otherPackage = $this->goi($this->nguoiDung('customer', 'Chủ gói khác')->getKey());
        $this->actingAs($this->customer)->post('/dat-lich', $this->duLieu(['ma_goi_da_mua' => $otherPackage]))->assertSessionHasErrors('ma_goi_da_mua');
        $package = $this->goi($this->customer->getKey());
        foreach (['13:00:00', '14:00:00', '15:00:00'] as $time) {
            $start = Carbon::parse('2026-11-12 '.$time);
            $this->lich(['ma_goi_da_mua' => $package, 'thoi_diem_bat_dau' => $start, 'thoi_diem_ket_thuc' => $start->copy()->addHour()]);
        }
        $this->post('/dat-lich', $this->duLieu(['ma_goi_da_mua' => $package]))->assertSessionHasErrors('ma_goi_da_mua');
        $this->assertDatabaseCount('lich_hen', 3);
    }

    public function test_paid_package_booking_does_not_charge_or_consume_a_session_until_completion(): void
    {
        $package = $this->goi($this->customer->getKey());
        $this->actingAs($this->customer)->post('/dat-lich', $this->duLieu(['ma_goi_da_mua' => $package]))->assertRedirect('/dat-lich');
        $this->assertDatabaseHas('goi_da_mua', ['ma_goi_da_mua' => $package, 'so_buoi_da_dung' => 2]);
        $this->assertDatabaseCount('hoa_don', 1);
        $this->assertDatabaseCount('luot_su_dung_goi', 0);
    }

    public function test_employee_receiving_permission_controls_pending_queue_and_confirmation(): void
    {
        $id = $this->lich(['trang_thai' => 'pending', 'ma_nhan_vien' => null, 'ma_giuong' => null]);
        $this->actingAs($this->employee)->get('/nhan-vien/lich-hen')->assertOk()->assertDontSee('Khách đặt lịch');
        $this->employee->co_quyen_tiep_nhan = true;
        $this->employee->save();
        $this->actingAs($this->employee->fresh())->get('/nhan-vien/lich-hen')->assertOk()->assertSee('Khách đặt lịch')->assertSee('Duyệt & phân công', false);
        $this->employee->co_quyen_tiep_nhan = false;
        $this->employee->save();
        $payload = ['hanh_dong' => 'confirmed', 'phien_ban' => 1, 'ma_nhan_vien' => $this->employee->getKey(), 'ma_giuong' => $this->bed];
        $this->post('/noi-bo/lich/'.$id, $payload)->assertForbidden();
        $this->employee->co_quyen_tiep_nhan = true;
        $this->employee->save();
        $this->actingAs($this->employee->fresh())->post('/noi-bo/lich/'.$id, $payload)->assertRedirect();
        $this->assertDatabaseHas('lich_hen', ['ma_lich_hen' => $id, 'trang_thai' => 'confirmed']);
    }

    public function test_admin_setup_guide_uses_real_missing_resources(): void
    {
        DB::table('lich_lam_viec')->delete();
        $this->actingAs($this->admin)->get('/admin/lich-hen')->assertOk()->assertSee('Chuẩn bị nhận lịch')->assertSee('Ca nhận lịch từ hôm nay');
    }
}
