<?php

namespace Tests\Feature;

use App\Models\NguoiDung;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\SchemaSpa;
use Tests\TestCase;

class GiaoDienNoiBoTest extends TestCase
{
    use SchemaSpa;

    private NguoiDung $admin;

    private NguoiDung $employee;

    private NguoiDung $customer;

    private int $service;

    private int $bed;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-11 08:00:00', 'Asia/Ho_Chi_Minh'));
        $this->taoCauTrucSpa();
        $this->admin = $this->user('admin', 'Quản trị thử');
        $this->employee = $this->user('employee', 'Nhân viên được phân công');
        $this->customer = $this->user('customer', 'Khách dữ liệu kiểm tra');
        $category = DB::table('danh_muc_dich_vu')->insertGetId(['ten_danh_muc' => 'Danh mục thật trong kiểm thử']);
        $this->service = DB::table('dich_vu')->insertGetId(['ma_danh_muc' => $category, 'ten_dich_vu' => 'Dịch vụ từ cơ sở dữ liệu', 'gia' => 500000, 'thoi_luong_phut' => 60]);
        $room = DB::table('phong')->insertGetId(['ten_phong' => 'Phòng kiểm tra']);
        $this->bed = DB::table('giuong')->insertGetId(['ma_phong' => $room, 'ten_giuong' => 'Giường kiểm tra']);
        DB::table('lich_lam_viec')->insert(['ma_nhan_vien' => $this->employee->getKey(), 'ngay_lam_viec' => '2026-11-12', 'gio_bat_dau' => '09:00:00', 'gio_ket_thuc' => '18:00:00']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function user(string $role, string $name): NguoiDung
    {
        $user = new NguoiDung;
        $user->ten_nguoi_dung = $name;
        $user->dia_chi_thu_dien_tu = uniqid('test').'@example.test';
        $user->so_dien_thoai = '0912345678';
        $user->vai_tro = $role;
        $user->mat_khau = 'Spa12345!';
        $user->dang_hoat_dong = true;
        $user->save();

        return $user;
    }

    private function booking(array $overrides = []): int
    {
        return DB::table('lich_hen')->insertGetId(array_merge([
            'ma_khach_hang' => $this->customer->getKey(), 'ma_dich_vu' => $this->service,
            'ma_nhan_vien' => $this->employee->getKey(), 'ma_giuong' => $this->bed,
            'thoi_diem_bat_dau' => '2026-11-12 10:00:00', 'thoi_diem_ket_thuc' => '2026-11-12 11:00:00',
            'ten_dich_vu_luc_dat' => 'Dịch vụ từ cơ sở dữ liệu', 'gia_dich_vu_luc_dat' => 500000,
            'trang_thai' => 'pending',
        ], $overrides));
    }

    public function test_customer_sees_only_the_rejection_reason_for_their_own_booking(): void
    {
        $booking = $this->booking();
        $this->booking(['ghi_chu_nhan_vien' => 'Ghi chú xử lý nội bộ']);
        $reason = 'Spa không thể phục vụ vào khung giờ đã chọn.';

        $this->actingAs($this->admin)->post(route('noi-bo.lich.cap-nhat', $booking), [
            'hanh_dong' => 'rejected',
            'ghi_chu' => $reason,
            'phien_ban' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($this->customer)->get(route('lich-hen-cua-ban'))
            ->assertOk()->assertSee('Lý do spa từ chối:')->assertSee($reason)->assertDontSee('Ghi chú xử lý nội bộ');

        $this->actingAs($this->user('customer', 'Khách khác'))->get(route('lich-hen-cua-ban'))
            ->assertOk()->assertDontSee($reason);
    }

    public function test_catalogue_reads_database_and_hides_inactive_services(): void
    {
        $this->get('/')->assertOk()->assertSee('Dịch vụ từ cơ sở dữ liệu')->assertDontSee('350.000');
        $this->get('/dich-vu')->assertOk()->assertSee('500.000');
        DB::table('dich_vu')->where('ma_dich_vu', $this->service)->update(['dang_hoat_dong' => false]);
        $this->get('/dich-vu')->assertOk()->assertDontSee('Dịch vụ từ cơ sở dữ liệu')->assertSee('Chưa có dịch vụ');
    }

    public function test_admin_booking_notifications_and_filtered_appointments_show_newest_first(): void
    {
        $older = $this->booking(['nguon_dat_lich' => 'online', 'thoi_diem_bat_dau' => '2026-11-12 16:00:00',
            'thoi_diem_ket_thuc' => '2026-11-12 17:00:00']);
        $newer = $this->booking(['nguon_dat_lich' => 'online']);
        $walkIn = $this->booking(['nguon_dat_lich' => 'walk_in']);
        $feed = route('admin.thong-bao.lich-moi');
        $this->getJson($feed)->assertUnauthorized();
        $this->actingAs($this->employee)->getJson($feed)->assertForbidden();
        $this->get('/nhan-vien')->assertOk()->assertDontSee('data-admin-bookings');
        $this->actingAs($this->admin)->get('/admin')->assertOk()->assertSee('data-admin-bookings');
        $response = $this->getJson($feed)->assertOk()->assertJsonCount(2)
            ->assertJsonPath('0.ma_lich_hen', $newer)->assertJsonPath('1.ma_lich_hen', $older);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->get('/admin/lich-hen?trang_thai=pending&ngay=2026-11-12&q=Dịch vụ')
            ->assertOk()->assertSeeInOrder(['Lịch hẹn #'.$walkIn, 'Lịch hẹn #'.$newer, 'Lịch hẹn #'.$older]);
        $this->get($response->json('0.url'))->assertOk()->assertSee('Lịch hẹn #'.$newer)
            ->assertDontSee('Lịch hẹn #'.$older)->assertDontSee('Lịch hẹn #'.$walkIn);
    }

    public function test_all_admin_pages_render_live_or_empty_records_without_mock_data(): void
    {
        $this->booking(['trang_thai' => 'confirmed']);
        $this->actingAs($this->admin);
        if ($dir = getenv('SPA_UI_CAPTURE_DIR')) {
            file_put_contents($dir.'/admin.html', $this->get('/admin?ngay=2026-11-12')->getContent());
            file_put_contents($dir.'/admin-services.html', $this->get('/admin/dich-vu')->getContent());
        }
        foreach (config('menu_spa.admin') as $key => $item) {
            $this->get('/admin/'.$key)->assertOk()->assertSee($item['title'])->assertDontSee('Dữ liệu minh họa')->assertDontSee('Mai Anh');
        }
    }

    public function test_employee_only_reads_own_assignments_and_cannot_access_admin(): void
    {
        $this->booking(['trang_thai' => 'confirmed']);
        $other = $this->user('customer', 'Khách không được xem');
        $anotherEmployee = $this->user('employee', 'Nhân viên khác');
        $this->booking(['ma_khach_hang' => $other->getKey(), 'ma_nhan_vien' => $anotherEmployee->getKey()]);
        $this->actingAs($this->employee);
        foreach (config('menu_spa.employee') as $key => $item) {
            $this->get('/nhan-vien/'.$key)->assertOk()->assertDontSee('Khách không được xem')->assertDontSee('Tiền đã ghi nhận');
        }
        if ($dir = getenv('SPA_UI_CAPTURE_DIR')) {
            file_put_contents($dir.'/employee.html', $this->get('/nhan-vien?ngay=2026-11-12')->getContent());
        }
        $this->get('/nhan-vien/lich-hen')->assertSee('Khách dữ liệu kiểm tra');
        $this->get('/admin')->assertForbidden();
        $this->get('/quan-tri/bao-cao.csv?tu=2026-11-01&den=2026-11-30')->assertForbidden();
    }

    public function test_booking_requires_login_and_rejects_staff(): void
    {
        $this->get('/dat-lich')->assertRedirect('/dang-nhap');
        $this->actingAs($this->employee)->get('/dat-lich')->assertForbidden();
        $this->actingAs($this->customer)->get('/dat-lich')->assertOk();
        if ($dir = getenv('SPA_UI_CAPTURE_DIR')) {
            file_put_contents($dir.'/booking.html', $this->get('/dat-lich')->getContent());
        }
        $this->actingAs($this->customer)->get('/admin')->assertForbidden();
    }

    public function test_booking_uses_server_price_owner_duration_and_pending_state(): void
    {
        $this->actingAs($this->customer)->post('/dat-lich', [
            'ho_ten' => 'Tên được khách cập nhật', 'so_dien_thoai' => '0912345678', 'ma_dich_vu' => $this->service,
            'ngay' => '2026-11-12', 'gio' => '10:00', 'gia_dich_vu_luc_dat' => 1, 'gia_da_xem' => 500000, 'thoi_luong_da_xem' => 60, 'ma_khach_hang' => $this->admin->getKey(),
            'trang_thai' => 'confirmed', 'ma_nhan_vien' => $this->employee->getKey(),
        ])->assertRedirect('/dat-lich');
        $this->assertDatabaseHas('lich_hen', ['ma_khach_hang' => $this->customer->getKey(), 'gia_dich_vu_luc_dat' => 500000,
            'trang_thai' => 'pending', 'ma_nhan_vien' => $this->employee->getKey(), 'thoi_diem_ket_thuc' => '2026-11-12 11:00:00']);
        $this->assertDatabaseCount('hoa_don', 0);
    }

    public function test_duplicate_booking_is_reused_and_invalid_package_is_rejected(): void
    {
        $payload = ['ho_ten' => $this->customer->ten_nguoi_dung, 'so_dien_thoai' => '0912345678', 'ma_dich_vu' => $this->service, 'ngay' => '2026-11-12', 'gio' => '10:00', 'gia_da_xem' => 500000, 'thoi_luong_da_xem' => 60];
        $this->actingAs($this->customer)->post('/dat-lich', $payload)->assertRedirect('/dat-lich');
        $this->post('/dat-lich', $payload)->assertRedirect('/dat-lich')->assertSessionHasNoErrors();
        $this->assertDatabaseCount('lich_hen', 1);
        $payload['gio'] = '13:00';
        $payload['ma_goi_da_mua'] = 999;
        $this->post('/dat-lich', $payload)->assertSessionHasErrors('ma_goi_da_mua');
    }

    public function test_customer_can_only_withdraw_own_pending_booking(): void
    {
        $id = $this->booking();
        $this->actingAs($this->user('customer', 'Khách khác'))->post('/dat-lich/'.$id.'/rut', ['ly_do' => 'Không thể tới'])->assertNotFound();
        $this->actingAs($this->customer)->post('/dat-lich/'.$id.'/rut', ['ly_do' => 'Đổi kế hoạch'])->assertRedirect();
        $this->assertDatabaseHas('lich_hen', ['ma_lich_hen' => $id, 'trang_thai' => 'cancelled']);
    }

    public function test_confirm_checks_shift_and_prevents_resource_conflict(): void
    {
        $id = $this->booking();
        $this->actingAs($this->admin)->post('/noi-bo/lich/'.$id, ['hanh_dong' => 'confirmed', 'phien_ban' => 1,
            'ma_nhan_vien' => $this->employee->getKey(), 'ma_giuong' => $this->bed])->assertRedirect();
        $this->assertDatabaseHas('lich_hen', ['ma_lich_hen' => $id, 'trang_thai' => 'confirmed']);
        $other = $this->booking(['ma_khach_hang' => $this->user('customer', 'Khách trùng giờ')->getKey()]);
        $this->post('/noi-bo/lich/'.$other, ['hanh_dong' => 'confirmed', 'phien_ban' => 1,
            'ma_nhan_vien' => $this->employee->getKey(), 'ma_giuong' => $this->bed])->assertSessionHasErrors('lich');
        $this->assertDatabaseHas('lich_hen', ['ma_lich_hen' => $other, 'trang_thai' => 'pending']);
    }

    public function test_staff_cannot_complete_another_employees_booking_or_early_booking(): void
    {
        $id = $this->booking(['trang_thai' => 'confirmed']);
        $this->actingAs($this->user('employee', 'Không được phân công'))->post('/noi-bo/lich/'.$id,
            ['hanh_dong' => 'completed', 'phien_ban' => 1])->assertForbidden();
        $this->actingAs($this->employee)->post('/noi-bo/lich/'.$id,
            ['hanh_dong' => 'completed', 'phien_ban' => 1])->assertSessionHasErrors('lich');
        Carbon::setTestNow(Carbon::parse('2026-11-12 11:00:00'));
        $this->post('/noi-bo/lich/'.$id, ['hanh_dong' => 'completed', 'phien_ban' => 1, 'ghi_chu' => 'Đã phục vụ'])->assertRedirect();
        $this->post('/noi-bo/lich/'.$id, ['hanh_dong' => 'completed', 'phien_ban' => 1])->assertSessionHasErrors('lich');
        $this->assertDatabaseHas('lich_hen', ['ma_lich_hen' => $id, 'trang_thai' => 'completed', 'ma_nguoi_hoan_thanh' => $this->employee->getKey()]);
        $this->assertDatabaseCount('hoa_don', 0);
    }

    public function test_invoice_error_is_logged_without_changing_paid_amount_and_export_uses_real_values(): void
    {
        $id = $this->booking(['trang_thai' => 'completed']);
        $invoice = DB::table('hoa_don')->insertGetId(['so_hoa_don' => 'HD-TEST', 'ma_khach_hang' => $this->customer->getKey(),
            'ma_lich_hen' => $id, 'tien_truoc_giam' => 500000, 'thanh_tien' => 500000, 'ten_noi_dung_luc_lap' => 'Dịch vụ từ cơ sở dữ liệu',
            'tien_can_thu' => 500000, 'so_tien_da_thu' => 500000, 'trang_thai_thanh_toan' => 'paid', 'ma_nguoi_thu' => $this->admin->getKey(),
            'phuong_thuc_thanh_toan' => 'cash', 'thoi_diem_thanh_toan' => now()]);
        $this->actingAs($this->employee)->post('/quan-tri/hoa-don/'.$invoice.'/trang-thai', ['trang_thai_xu_ly' => 'error', 'ly_do' => 'Kiểm tra', 'phien_ban' => 1])->assertForbidden();
        $this->actingAs($this->admin)->post('/quan-tri/hoa-don/'.$invoice.'/trang-thai', ['trang_thai_xu_ly' => 'error', 'ly_do' => 'Đang đối soát', 'phien_ban' => 1])->assertRedirect();
        $this->assertDatabaseHas('hoa_don', ['ma_hoa_don' => $invoice, 'trang_thai_xu_ly' => 'error', 'so_tien_da_thu' => 500000, 'trang_thai_thanh_toan' => 'paid']);
        $this->assertDatabaseCount('nhat_ky_hoa_don', 1);
        $this->get('/quan-tri/hoa-don/'.$invoice.'/xuat')->assertOk()->assertSee('500.000')->assertSee('đang cần đối soát');
        $this->get('/quan-tri/bao-cao.csv?tu=2026-11-01&den=2026-11-30')->assertOk()->assertDownload();
        $this->get('/admin/bao-cao?tu=2026-11-01&den=2026-11-30')->assertOk()->assertSee('500.000');
    }

    public function test_admin_enters_real_catalogue_data_and_employee_cannot_change_it(): void
    {
        $payload = ['ten_danh_muc' => 'Danh mục mới từ biểu mẫu', 'mo_ta' => 'Chăm sóc', 'dang_hoat_dong' => 1];
        $this->actingAs($this->employee)->post('/quan-tri/du-lieu/danh-muc', $payload)->assertForbidden();
        $this->actingAs($this->admin)->post('/quan-tri/du-lieu/danh-muc', $payload)->assertRedirect();
        $category = DB::table('danh_muc_dich_vu')->where('ten_danh_muc', $payload['ten_danh_muc'])->value('ma_danh_muc');
        $this->post('/quan-tri/du-lieu/dich-vu', ['ma_danh_muc' => $category, 'ten_dich_vu' => 'Trị liệu vừa nhập',
            'mo_ta' => 'Mô tả được lưu thật', 'gia' => 650000, 'thoi_luong_phut' => 75, 'dang_hoat_dong' => 1])->assertRedirect();
        $this->get('/dich-vu')->assertOk()->assertSee('Trị liệu vừa nhập')->assertSee('650.000');
    }

    public function test_similar_category_name_requires_explicit_confirmation(): void
    {
        $url = '/quan-tri/du-lieu/danh-muc';
        $payload = ['ten_danh_muc' => 'Danh muc that trong kiem thu', 'mo_ta' => 'Mô tả mới', 'dang_hoat_dong' => 1];

        $this->actingAs($this->admin)->from('/admin/danh-muc')->post($url, $payload)->assertSessionHasErrors('ten_danh_muc');
        $this->assertDatabaseCount('danh_muc_dich_vu', 1);
        $this->withCookie(config('session.cookie'), session()->getId())
            ->get('/admin/danh-muc')->assertOk()->assertSee($payload['ten_danh_muc'])->assertSee('xac_nhan_ten_danh_muc');
        $this->post($url, $payload + ['xac_nhan_ten_danh_muc' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('danh_muc_dich_vu', 2);
    }

    public function test_service_changes_require_staff_review_before_confirming_original_booking_terms(): void
    {
        $id = $this->booking();
        $url = '/noi-bo/lich/'.$id;
        $payload = ['hanh_dong' => 'confirmed', 'phien_ban' => 1, 'ma_nhan_vien' => $this->employee->getKey(), 'ma_giuong' => $this->bed];
        DB::table('dich_vu')->where('ma_dich_vu', $this->service)->update(['gia' => 650000, 'thoi_luong_phut' => 75]);

        $this->actingAs($this->admin)->post($url, $payload)->assertSessionHasErrors('xac_nhan_dich_vu');
        $form = $this->get('/admin/lich-hen')->assertOk()->assertSee('Thông tin dịch vụ đã thay đổi')->assertSee('650.000')->assertSee('75 phút');
        $matches = [];
        $this->assertSame(1, preg_match('/name="xac_nhan_dich_vu" value="([a-f0-9]{64})"/', $form->getContent(), $matches));
        DB::table('dich_vu')->where('ma_dich_vu', $this->service)->update(['gia' => 700000]);
        $this->post($url, $payload + ['xac_nhan_dich_vu' => $matches[1]])->assertSessionHasErrors('xac_nhan_dich_vu');
        $this->assertDatabaseHas('lich_hen', ['ma_lich_hen' => $id, 'trang_thai' => 'pending']);
        $form = $this->get('/admin/lich-hen')->assertOk()->assertSee('700.000');
        $this->assertSame(1, preg_match('/name="xac_nhan_dich_vu" value="([a-f0-9]{64})"/', $form->getContent(), $matches));
        $this->post($url, $payload + ['xac_nhan_dich_vu' => $matches[1]])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('lich_hen', ['ma_lich_hen' => $id, 'trang_thai' => 'confirmed',
            'gia_dich_vu_luc_dat' => 500000, 'thoi_diem_ket_thuc' => '2026-11-12 11:00:00']);
    }

    public function test_last_admin_is_protected_and_privilege_changes_are_logged_without_password(): void
    {
        $payload = ['ten_nguoi_dung' => $this->admin->ten_nguoi_dung, 'dia_chi_thu_dien_tu' => $this->admin->dia_chi_thu_dien_tu,
            'so_dien_thoai' => '0912345678', 'vai_tro' => 'customer', 'co_quyen_tiep_nhan' => 0, 'co_quyen_thu_tien' => 0, 'dang_hoat_dong' => 1,
            'phien_ban' => 1, 'ly_do' => 'Thay đổi nhiệm vụ'];
        $this->actingAs($this->admin)->post('/quan-tri/du-lieu/nguoi-dung/'.$this->admin->getKey(), $payload)->assertSessionHasErrors('vai_tro');
        $this->assertDatabaseHas('nguoi_dung', ['ma_nguoi_dung' => $this->admin->getKey(), 'vai_tro' => 'admin']);
        $payload['vai_tro'] = 'employee';
        $payload['dia_chi_thu_dien_tu'] = 'new.employee@example.test';
        $payload['password'] = 'A-New-Password-2026';
        $payload['password_confirmation'] = $payload['password'];
        $this->post('/quan-tri/du-lieu/nguoi-dung', $payload)->assertRedirect();
        $this->assertDatabaseCount('nhat_ky_quan_tri', 1);
        $log = DB::table('nhat_ky_quan_tri')->first();
        $this->assertStringNotContainsString('mat_khau', $log->du_lieu_sau);
        $this->assertStringNotContainsString($payload['password'], $log->du_lieu_sau);
    }

    public function test_user_update_rejects_stale_version_and_records_the_admin_reason(): void
    {
        $id = $this->employee->getKey();
        $url = '/quan-tri/du-lieu/nguoi-dung/'.$id;
        $payload = ['ten_nguoi_dung' => 'Nhân viên tiếp nhận', 'dia_chi_thu_dien_tu' => $this->employee->dia_chi_thu_dien_tu,
            'vai_tro' => 'employee', 'co_quyen_tiep_nhan' => 1, 'co_quyen_thu_tien' => 0, 'dang_hoat_dong' => 1, 'phien_ban' => 1];

        $this->actingAs($this->admin)->post($url, $payload)->assertSessionHasErrors('ly_do');
        $this->post($url, $payload + ['ly_do' => 'Phân công trực quầy'])->assertSessionHasNoErrors();
        $this->post($url, array_replace($payload, ['ly_do' => 'Lưu từ biểu mẫu cũ', 'co_quyen_tiep_nhan' => 0]))->assertSessionHasErrors('phien_ban');
        $this->assertDatabaseHas('nguoi_dung', ['ma_nguoi_dung' => $id, 'phien_ban' => 2, 'co_quyen_tiep_nhan' => 1]);
        $this->assertDatabaseCount('nhat_ky_quan_tri', 1);
        $this->assertDatabaseHas('nhat_ky_quan_tri', ['ma_doi_tuong' => $id, 'hanh_dong' => 'update_user', 'ly_do' => 'Phân công trực quầy']);
    }

    public function test_purchase_payment_and_package_usage_are_saved_once(): void
    {
        $template = DB::table('goi_lieu_trinh')->insertGetId(['ma_dich_vu' => $this->service, 'ten_goi' => 'Gói kiểm tra năm buổi', 'tong_so_buoi' => 5, 'gia' => 2000000]);
        $this->actingAs($this->admin)->post('/quan-tri/du-lieu/lieu-trinh', ['ma_khach_hang' => $this->customer->getKey(), 'ma_goi_lieu_trinh' => $template])->assertRedirect();
        $package = DB::table('goi_da_mua')->first();
        $invoice = DB::table('hoa_don')->first();
        $this->assertSame('pending_payment', $package->trang_thai);
        $this->post('/quan-tri/hoa-don/'.$invoice->ma_hoa_don.'/thanh-toan', ['phuong_thuc' => 'bank_transfer', 'xac_nhan' => 1, 'phien_ban' => 1])->assertRedirect();
        $this->post('/quan-tri/hoa-don/'.$invoice->ma_hoa_don.'/thanh-toan', ['phuong_thuc' => 'bank_transfer', 'xac_nhan' => 1, 'phien_ban' => 1])->assertRedirect();
        $this->assertDatabaseHas('goi_da_mua', ['ma_goi_da_mua' => $package->ma_goi_da_mua, 'trang_thai' => 'active', 'gia_tri_cap_von' => 2000000]);
        $this->assertDatabaseCount('nhat_ky_quan_tri', 1);
        $id = $this->booking(['ma_goi_da_mua' => $package->ma_goi_da_mua, 'trang_thai' => 'confirmed']);
        Carbon::setTestNow(Carbon::parse('2026-11-12 11:00:00'));
        $this->actingAs($this->employee)->post('/noi-bo/lich/'.$id, ['hanh_dong' => 'completed', 'phien_ban' => 1])->assertRedirect();
        $this->post('/noi-bo/lich/'.$id, ['hanh_dong' => 'completed', 'phien_ban' => 1])->assertSessionHasErrors('lich');
        $this->assertDatabaseHas('goi_da_mua', ['ma_goi_da_mua' => $package->ma_goi_da_mua, 'so_buoi_da_dung' => 1]);
        $this->assertDatabaseCount('luot_su_dung_goi', 1);
        $this->assertDatabaseCount('hoa_don', 1);
    }
}
