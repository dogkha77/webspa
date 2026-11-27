<?php

namespace Tests\Feature;

use App\Models\NguoiDung;
use App\NghiepVuSpa;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\SchemaSpa;
use Tests\TestCase;

class HoanThienNghiepVuTest extends TestCase
{
    use SchemaSpa;

    private array $people = [];

    private int $service;

    private int $targetService;

    private int $source;

    private int $target;

    private int $bed;

    private int $booking;

    private bool $mysql = false;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-12 08:00:00', 'Asia/Ho_Chi_Minh'));
        $this->mysql = getenv('RUN_MYSQL_COMPLETION_TESTS') === '1';
        if ($this->mysql) {
            $connection = config('database.connections.mysql');
            $connection['database'] = 'do_an_spa';
            config(['database.connections.completion_mysql' => $connection, 'database.default' => 'completion_mysql']);
            DB::purge('completion_mysql');
            DB::beginTransaction();
        } else {
            $this->taoCauTrucSpa();
        }
        foreach (['admin', 'employee', 'customer', 'other'] as $role) {
            $u = new NguoiDung;
            $u->ten_nguoi_dung = 'Kiểm thử '.Str::random(12);
            $u->dia_chi_thu_dien_tu = Str::uuid().'@example.test';
            $u->mat_khau = 'KiemThu123!';
            $u->vai_tro = $role === 'other' ? 'customer' : $role;
            $u->dang_hoat_dong = true;
            $u->save();
            $this->people[$role] = $u;
        }
        $cat = DB::table('danh_muc_dich_vu')->insertGetId(['ten_danh_muc' => 'Danh mục kiểm thử']);
        $this->service = DB::table('dich_vu')->insertGetId(['ma_danh_muc' => $cat, 'ten_dich_vu' => 'Dịch vụ gốc', 'gia' => 500000, 'thoi_luong_phut' => 60]);
        $this->targetService = DB::table('dich_vu')->insertGetId(['ma_danh_muc' => $cat, 'ten_dich_vu' => 'Dịch vụ đích', 'gia' => 600000, 'thoi_luong_phut' => 60]);
        $old = DB::table('goi_lieu_trinh')->insertGetId(['ten_goi' => 'Gói gốc', 'ma_dich_vu' => $this->service, 'tong_so_buoi' => 5, 'gia' => 2500000]);
        $this->target = DB::table('goi_lieu_trinh')->insertGetId(['ten_goi' => 'Gói đích', 'ma_dich_vu' => $this->targetService, 'tong_so_buoi' => 6, 'gia' => 3000000]);
        $this->source = DB::table('goi_da_mua')->insertGetId(['ma_khach_hang' => $this->people['customer']->getKey(), 'ma_goi_lieu_trinh' => $old,
            'thoi_diem_mua' => now(), 'ma_dich_vu' => $this->service, 'tong_so_buoi' => 5, 'so_buoi_da_dung' => 2, 'gia_mua' => 2500000,
            'gia_tri_cap_von' => 2500000, 'ten_goi_luc_mua' => 'Gói gốc', 'ten_dich_vu_luc_mua' => 'Dịch vụ gốc', 'trang_thai' => 'active']);
        DB::table('hoa_don')->insert(['so_hoa_don' => 'TEST-'.Str::ulid(), 'ma_khach_hang' => $this->people['customer']->getKey(), 'ma_goi_da_mua' => $this->source,
            'tien_truoc_giam' => 2500000, 'thanh_tien' => 2500000, 'ten_noi_dung_luc_lap' => 'Gói gốc', 'tien_can_thu' => 2500000,
            'so_tien_da_thu' => 2500000, 'ma_nguoi_thu' => $this->people['admin']->getKey(), 'trang_thai_thanh_toan' => 'paid', 'phuong_thuc_thanh_toan' => 'cash', 'thoi_diem_thanh_toan' => now()]);
        $room = DB::table('phong')->insertGetId(['ten_phong' => 'Thử '.Str::uuid()]);
        $this->bed = DB::table('giuong')->insertGetId(['ma_phong' => $room, 'ten_giuong' => 'Giường thử']);
        DB::table('lich_lam_viec')->insert(['ma_nhan_vien' => $this->people['employee']->getKey(), 'ngay_lam_viec' => '2026-11-13', 'gio_bat_dau' => '09:00', 'gio_ket_thuc' => '18:00']);
        $this->booking = $this->themLich();
    }

    protected function tearDown(): void
    {
        if ($this->mysql) {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            Auth::forgetGuards();
        }
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function themLich(array $values = []): int
    {
        return DB::table('lich_hen')->insertGetId(array_replace(['ma_khach_hang' => $this->people['customer']->getKey(), 'ma_dich_vu' => $this->service,
            'ma_nhan_vien' => $this->people['employee']->getKey(), 'ma_giuong' => $this->bed, 'trang_thai' => 'confirmed',
            'thoi_diem_bat_dau' => '2026-11-13 10:00:00', 'thoi_diem_ket_thuc' => '2026-11-13 11:00:00',
            'ten_dich_vu_luc_dat' => 'Dịch vụ gốc', 'gia_dich_vu_luc_dat' => 500000], $values));
    }

    private function deNghi(): int
    {
        $this->actingAs($this->people['customer'])->post('/chuyen-goi', ['ma_goi_nguon' => $this->source, 'ma_mau_goi_dich' => $this->target, 'ly_do' => 'Đổi liệu trình'])->assertRedirect()->assertSessionHasNoErrors();

        return DB::table('chuyen_goi')->where('ma_goi_nguon', $this->source)->value('ma_chuyen_goi');
    }

    private function duyet(int $id, array $values = []): TestResponse
    {
        return $this->actingAs($this->people['admin'])->post('/chuyen-goi/'.$id, array_replace(['hanh_dong' => 'approve', 'phien_ban' => 1,
            'phien_ban_nguon' => 1, 'gia_tri_chuyen' => 1500000, 'gia_goi_dich' => 3000000, 'khach_dong_y' => 1], $values));
    }

    private function chupTrang(string $name, string $url): void
    {
        if ($dir = getenv('SPA_COMPLETION_CAPTURE_DIR')) {
            file_put_contents($dir.'/'.$name.'.html', $this->get($url)->assertOk()->getContent());
        }
    }

    public function test_transfer_completes_once_keeps_history_and_uses_approved_service_snapshot(): void
    {
        $id = $this->deNghi();
        $this->chupTrang('transfer-pending', '/chuyen-goi');
        $this->duyet($id)->assertSessionHasNoErrors();
        $this->chupTrang('transfer-approve', '/chuyen-goi');
        $this->assertDatabaseHas('goi_da_mua', ['ma_goi_da_mua' => $this->source, 'trang_thai' => 'transfer_locked']);
        DB::table('goi_lieu_trinh')->where('ma_goi_lieu_trinh', $this->target)->update(['ma_dich_vu' => $this->service, 'gia' => 9000000, 'tong_so_buoi' => 30]);
        $payload = ['hanh_dong' => 'complete', 'phien_ban' => 2, 'da_nhan_du' => 1, 'phuong_thuc' => 'bank_transfer'];
        $this->post('/chuyen-goi/'.$id, $payload)->assertSessionHasNoErrors();
        $done = DB::table('chuyen_goi')->where('ma_chuyen_goi', $id)->first();
        $this->assertSame('completed', $done->trang_thai);
        $this->assertDatabaseHas('goi_da_mua', ['ma_goi_da_mua' => $done->ma_goi_dich, 'ma_dich_vu' => $this->targetService, 'tong_so_buoi' => 6, 'gia_tri_cap_von' => 3000000]);
        $this->assertDatabaseHas('goi_da_mua', ['ma_goi_da_mua' => $this->source, 'so_buoi_da_dung' => 2, 'trang_thai' => 'transferred']);
        $this->assertDatabaseHas('hoa_don', ['ma_hoa_don' => $done->ma_hoa_don, 'thanh_tien' => 3000000, 'credit_chuyen_goi' => 1500000, 'so_tien_da_thu' => 1500000]);
        $before = DB::table('hoa_don')->count();
        $this->post('/chuyen-goi/'.$id, $payload)->assertSessionHasNoErrors();
        $this->assertSame($before, DB::table('hoa_don')->count());
        $this->get('/chuyen-goi')->assertOk()->assertSee('Đã hoàn tất');
        $this->chupTrang('transfer-done', '/chuyen-goi');
    }

    public function test_transfer_cannot_steal_package_or_create_duplicate_open_request(): void
    {
        $data = ['ma_goi_nguon' => $this->source, 'ma_mau_goi_dich' => $this->target, 'ly_do' => 'Thử'];
        $this->actingAs($this->people['other'])->post('/chuyen-goi', $data)->assertForbidden();
        $this->deNghi();
        $this->post('/chuyen-goi', $data)->assertSessionHasErrors();
        $this->assertSame(1, DB::table('chuyen_goi')->where('ma_goi_nguon', $this->source)->count());
    }

    public function test_transfer_rejects_stale_quote_lower_price_and_unfinished_booking(): void
    {
        $id = $this->deNghi();
        $this->duyet($id, ['gia_goi_dich' => 1])->assertSessionHasErrors();
        DB::table('goi_lieu_trinh')->where('ma_goi_lieu_trinh', $this->target)->update(['gia' => 1000000]);
        $this->duyet($id, ['gia_goi_dich' => 1000000])->assertSessionHasErrors();
        DB::table('goi_lieu_trinh')->where('ma_goi_lieu_trinh', $this->target)->update(['gia' => 3000000]);
        DB::table('lich_hen')->where('ma_lich_hen', $this->booking)->update(['ma_goi_da_mua' => $this->source]);
        $this->duyet($id)->assertSessionHasErrors();
        $this->assertDatabaseHas('goi_da_mua', ['ma_goi_da_mua' => $this->source, 'trang_thai' => 'active']);
    }

    public function test_zero_topup_finishes_on_approval_with_no_fake_cash(): void
    {
        DB::table('goi_lieu_trinh')->where('ma_goi_lieu_trinh', $this->target)->update(['gia' => 1500000]);
        $id = $this->deNghi();
        $this->duyet($id, ['gia_goi_dich' => 1500000])->assertSessionHasNoErrors();
        $done = DB::table('chuyen_goi')->where('ma_chuyen_goi', $id)->first();
        $this->assertSame('completed', $done->trang_thai);
        $this->assertDatabaseHas('hoa_don', ['ma_hoa_don' => $done->ma_hoa_don, 'so_tien_da_thu' => 0, 'phuong_thuc_thanh_toan' => 'no_charge']);
    }

    public function test_reject_locked_transfer_requires_no_money_confirmation_and_unlocks_source(): void
    {
        $id = $this->deNghi();
        $this->duyet($id)->assertSessionHasNoErrors();
        $data = ['hanh_dong' => 'reject', 'phien_ban' => 2, 'ly_do' => 'Khách rút'];
        $this->post('/chuyen-goi/'.$id, $data)->assertSessionHasErrors();
        $this->post('/chuyen-goi/'.$id, $data + ['chua_nhan_tien' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('goi_da_mua', ['ma_goi_da_mua' => $this->source, 'trang_thai' => 'active']);
    }

    public function test_employee_may_collect_only_when_assigned_and_never_approve(): void
    {
        $id = $this->deNghi();
        $this->duyet($id)->assertSessionHasNoErrors();
        $data = ['hanh_dong' => 'complete', 'phien_ban' => 2, 'da_nhan_du' => 1, 'phuong_thuc' => 'cash'];
        $this->actingAs($this->people['employee'])->post('/chuyen-goi/'.$id, $data)->assertForbidden();
        DB::table('nguoi_dung')->where('ma_nguoi_dung', $this->people['employee']->getKey())->update(['co_quyen_thu_tien' => true]);
        $this->people['employee']->refresh();
        $this->post('/chuyen-goi/'.$id, ['hanh_dong' => 'approve', 'phien_ban' => 2])->assertForbidden();
        $this->post('/chuyen-goi/'.$id, $data)->assertSessionHasNoErrors();
    }

    public function test_customer_reschedule_releases_resources_preserves_audit_and_needs_reconfirmation(): void
    {
        $this->actingAs($this->people['customer'])->post('/lich-hen/'.$this->booking.'/doi-lich', ['ngay' => '2026-11-13', 'gio' => '12:00', 'ly_do' => 'Bận việc', 'phien_ban' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('lich_hen', ['ma_lich_hen' => $this->booking, 'trang_thai' => 'pending', 'ma_nhan_vien' => null, 'ma_giuong' => null, 'thoi_diem_bat_dau' => '2026-11-13 12:00:00']);
        $audit = DB::table('nhat_ky_lich_hen')->where('ma_lich_hen', $this->booking)->sole();
        $this->assertSame('2026-11-13 10:00:00', json_decode($audit->du_lieu_truoc, true)['bat_dau']);
        $this->get('/lich-hen/'.$this->booking.'/lich-su')->assertOk()->assertSee('Bận việc');
        $this->actingAs($this->people['other'])->get('/lich-hen/'.$this->booking.'/lich-su')->assertForbidden();
    }

    public function test_admin_reschedule_rejects_conflicts_and_outside_shift_then_succeeds(): void
    {
        $this->themLich(['ma_khach_hang' => $this->people['other']->getKey(), 'thoi_diem_bat_dau' => '2026-11-13 12:00:00', 'thoi_diem_ket_thuc' => '2026-11-13 13:00:00']);
        $url = '/lich-hen/'.$this->booking.'/doi-lich';
        $data = ['ngay' => '2026-11-13', 'gio' => '12:00', 'ly_do' => 'Khách đồng ý đổi', 'phien_ban' => 1];
        $this->actingAs($this->people['admin'])->post($url, $data)->assertSessionHasErrors();
        $data['gio'] = '18:00';
        $this->post($url, $data)->assertSessionHasErrors();
        $data['gio'] = '14:00';
        $this->post($url, $data)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('lich_hen', ['ma_lich_hen' => $this->booking, 'trang_thai' => 'confirmed', 'thoi_diem_bat_dau' => '2026-11-13 14:00:00']);
        $this->assertDatabaseHas('nhac_hen', ['ma_lich_hen' => $this->booking, 'trang_thai' => 'pending', 'thoi_diem_hen_luu_lai' => '2026-11-13 14:00:00']);
        $this->post($url, $data)->assertSessionHasErrors();
    }

    public function test_membership_discount_is_snapshotted_for_invoice_and_package(): void
    {
        $tier = DB::table('hang_thanh_vien')->insertGetId(['ten_hang' => 'Thử '.Str::uuid(), 'ty_le_giam_gia' => 10]);
        DB::table('nguoi_dung')->where('ma_nguoi_dung', $this->people['customer']->getKey())->update(['ma_hang_thanh_vien' => $tier]);
        DB::table('lich_hen')->where('ma_lich_hen', $this->booking)->update(['trang_thai' => 'completed']);
        $this->actingAs($this->people['admin'])->post('/quan-tri/hoa-don/tu-lich/'.$this->booking)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('hoa_don', ['ma_lich_hen' => $this->booking, 'tien_giam' => 50000, 'tien_can_thu' => 450000, 'ty_le_giam_luc_lap' => 10]);
        $this->post('/giao-dich/mua-goi', ['ma_khach_hang' => $this->people['customer']->getKey(), 'ma_goi_lieu_trinh' => $this->target])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('goi_da_mua', ['ma_goi_lieu_trinh' => $this->target, 'gia_mua' => 2700000]);
        DB::table('hang_thanh_vien')->where('ma_hang_thanh_vien', $tier)->update(['ty_le_giam_gia' => 20]);
        $this->post('/quan-tri/hoa-don/tu-lich/'.$this->booking)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('hoa_don', ['ma_lich_hen' => $this->booking, 'tien_can_thu' => 450000]);
    }

    public function test_customer_package_request_becomes_bookable_only_after_sale_and_payment(): void
    {
        $customer = $this->people['customer'];
        $url = '/goi-lieu-trinh/'.$this->target.'/dang-ky';
        $this->post($url)->assertRedirect(route('login'));
        $this->actingAs($this->people['employee'])->post($url)->assertForbidden();
        $this->actingAs($customer)->get('/goi-lieu-trinh')->assertOk()->assertSee($url);
        $this->post($url)->assertRedirect(route('goi-cua-toi'));
        $this->post($url)->assertRedirect(route('goi-cua-toi'));
        $requests = DB::table('de_nghi_mua_goi')->where('ma_khach_hang', $customer->getKey());
        $this->assertSame(1, $requests->count());
        $this->assertDatabaseMissing('goi_da_mua', ['ma_goi_lieu_trinh' => $this->target]);
        $this->get('/goi-cua-toi')->assertOk()->assertSee('Đề nghị chờ spa xác nhận');
        $this->actingAs($this->people['other'])->get('/goi-cua-toi')->assertOk()->assertDontSee('Gói đích');

        $requestId = $requests->value('ma_de_nghi');
        $sale = ['ma_de_nghi' => $requestId];
        $this->actingAs($this->people['employee'])->post('/giao-dich/mua-goi', $sale)->assertForbidden();
        $this->actingAs($this->people['admin'])->get('/giao-dich')->assertOk()->assertSee('Gói đích');
        $this->post('/giao-dich/mua-goi', $sale + ['ma_khach_hang' => $this->people['other']->getKey()])->assertSessionHasNoErrors();
        $this->post('/giao-dich/mua-goi', $sale)->assertSessionHasNoErrors();
        $package = $requests->value('ma_goi_da_mua');
        $this->assertDatabaseHas('goi_da_mua', ['ma_goi_da_mua' => $package, 'ma_khach_hang' => $customer->getKey(), 'trang_thai' => 'pending_payment']);
        $invoices = DB::table('hoa_don')->where('ma_goi_da_mua', $package);
        $this->assertSame(1, $invoices->count());
        $invoice = $invoices->first();
        $this->assertSame('unpaid', $invoice->trang_thai_thanh_toan);
        $bookingUrl = route('dat-lich', ['goi' => $package]);
        $this->actingAs($customer)->get($bookingUrl)->assertRedirect(route('goi-cua-toi'));

        $this->actingAs($this->people['admin'])->post('/quan-tri/hoa-don/'.$invoice->ma_hoa_don.'/thanh-toan', [
            'phuong_thuc' => 'cash', 'xac_nhan' => 1, 'phien_ban' => $invoice->phien_ban,
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->people['other'])->get($bookingUrl)->assertRedirect(route('goi-cua-toi'));
        $this->actingAs($customer)->get('/goi-cua-toi')->assertOk()->assertSee($bookingUrl);
        $this->get($bookingUrl)->assertOk()
            ->assertSeeHtml('data-service="'.$this->targetService.'" selected');
        $this->post('/dat-lich', ['ho_ten' => 'Khách đặt theo gói', 'so_dien_thoai' => '0912345678', 'ma_dich_vu' => $this->targetService,
            'ma_goi_da_mua' => $package, 'ngay' => '2026-11-13', 'gio' => '14:00', 'gia_da_xem' => 600000, 'thoi_luong_da_xem' => 60])
            ->assertSessionHasNoErrors();
        $booking = DB::table('lich_hen')->where('ma_goi_da_mua', $package)->first();
        $this->assertSame('pending', $booking->trang_thai);
        $this->assertDatabaseHas('goi_da_mua', ['ma_goi_da_mua' => $package, 'so_buoi_da_dung' => 0]);

        $this->actingAs($this->people['admin'])->post('/noi-bo/lich/'.$booking->ma_lich_hen, [
            'hanh_dong' => 'confirmed', 'phien_ban' => $booking->phien_ban,
            'ma_nhan_vien' => $this->people['employee']->getKey(), 'ma_giuong' => $this->bed,
        ])->assertSessionHasNoErrors();
        Carbon::setTestNow(Carbon::parse('2026-11-13 15:00:00', 'Asia/Ho_Chi_Minh'));
        $complete = ['hanh_dong' => 'completed', 'phien_ban' => $booking->phien_ban + 1];
        $this->post('/noi-bo/lich/'.$booking->ma_lich_hen, $complete)->assertSessionHasNoErrors();
        $this->post('/noi-bo/lich/'.$booking->ma_lich_hen, $complete)->assertSessionHasErrors();
        $this->assertDatabaseHas('goi_da_mua', ['ma_goi_da_mua' => $package, 'so_buoi_da_dung' => 1]);
        $this->assertSame(1, DB::table('luot_su_dung_goi')->where('ma_lich_hen', $booking->ma_lich_hen)->count());
        $this->post('/quan-tri/hoa-don/tu-lich/'.$booking->ma_lich_hen)->assertSessionHasErrors();
        $this->assertDatabaseMissing('hoa_don', ['ma_lich_hen' => $booking->ma_lich_hen]);
    }

    public function test_profile_requires_current_password_and_cannot_change_role_or_log_password(): void
    {
        $u = $this->people['customer'];
        $data = ['ten_nguoi_dung' => 'Tên cập nhật', 'dia_chi_thu_dien_tu' => $u->dia_chi_thu_dien_tu,
            'mat_khau_hien_tai' => 'sai', 'vai_tro' => 'admin', 'co_quyen_thu_tien' => 1, 'password' => 'MatKhauMoi123!', 'password_confirmation' => 'MatKhauMoi123!'];
        $this->actingAs($u)->post('/tai-khoan', $data)->assertSessionHasErrors();
        $data['mat_khau_hien_tai'] = 'KiemThu123!';
        $this->post('/tai-khoan', $data)->assertSessionHasNoErrors();
        $u->refresh();
        $this->assertSame('customer', $u->vai_tro);
        $this->assertFalse($u->co_quyen_thu_tien);
        $this->assertTrue(Hash::check('MatKhauMoi123!', $u->mat_khau));
        $log = DB::table('nhat_ky_quan_tri')->where('ma_doi_tuong', $u->getKey())->where('hanh_dong', 'update_profile')->sole();
        $this->assertStringNotContainsString('MatKhauMoi', $log->du_lieu_sau);
    }

    public function test_reminders_refuse_log_transport_and_do_not_send_twice(): void
    {
        NghiepVuSpa::nhacLich(DB::table('lich_hen')->where('ma_lich_hen', $this->booking)->first());
        Carbon::setTestNow(Carbon::parse('2026-11-12 11:00:00', 'Asia/Ho_Chi_Minh'));
        config(['mail.default' => 'log']);
        $this->artisan('spa:gui-nhac-hen')->assertFailed();
        config(['mail.default' => 'smtp']);
        Mail::shouldReceive('raw')->once()->andReturnNull();
        $this->artisan('spa:gui-nhac-hen')->assertSuccessful();
        $this->artisan('spa:gui-nhac-hen')->assertSuccessful();
        $this->assertDatabaseHas('nhac_hen', ['ma_lich_hen' => $this->booking, 'trang_thai' => 'sent', 'so_lan_thu_gui' => 1]);
    }

    public function test_work_pages_render_and_do_not_allow_customer_financial_operations(): void
    {
        $this->actingAs($this->people['admin'])->get('/giao-dich')->assertOk();
        $this->chupTrang('transactions', '/giao-dich');
        $this->chupTrang('admin-booking', '/admin/lich-hen?ngay=2026-11-13');
        $this->get('/chuyen-goi')->assertOk();
        $this->chupTrang('profile', '/tai-khoan');
        $this->actingAs($this->people['customer'])->get('/giao-dich')->assertForbidden();
        $this->chupTrang('customer-booking', '/dat-lich');
        $this->post('/giao-dich/mua-goi', ['ma_khach_hang' => $this->people['customer']->getKey(), 'ma_goi_lieu_trinh' => $this->target])->assertForbidden();
    }

    public function test_transfer_rolls_back_all_writes_if_invoice_storage_fails(): void
    {
        $id = $this->deNghi();
        $this->duyet($id)->assertSessionHasNoErrors();
        $packages = DB::table('goi_da_mua')->count();
        $invoices = DB::table('hoa_don')->count();
        DB::listen(function ($query): void {
            if (str_starts_with(strtolower($query->sql), 'insert into') && str_contains($query->sql, 'hoa_don')) {
                throw new \RuntimeException('Lỗi lưu hóa đơn mô phỏng');
            }
        });
        $this->withoutExceptionHandling();
        try {
            $this->post('/chuyen-goi/'.$id, ['hanh_dong' => 'complete', 'phien_ban' => 2, 'da_nhan_du' => 1, 'phuong_thuc' => 'cash']);
            $this->fail('Expected a simulated storage failure');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('Lỗi lưu hóa đơn', $error->getMessage());
        }
        $this->assertSame($packages, DB::table('goi_da_mua')->count());
        $this->assertSame($invoices, DB::table('hoa_don')->count());
        $this->assertDatabaseHas('chuyen_goi', ['ma_chuyen_goi' => $id, 'trang_thai' => 'awaiting_payment', 'ma_goi_dich' => null]);
        $this->assertDatabaseHas('goi_da_mua', ['ma_goi_da_mua' => $this->source, 'trang_thai' => 'transfer_locked']);
    }

    public function test_transfer_chain_uses_all_capital_including_credit(): void
    {
        $id = $this->deNghi();
        $this->duyet($id)->assertSessionHasNoErrors();
        $this->post('/chuyen-goi/'.$id, ['hanh_dong' => 'complete', 'phien_ban' => 2, 'da_nhan_du' => 1, 'phuong_thuc' => 'cash'])->assertSessionHasNoErrors();
        $new = DB::table('chuyen_goi')->where('ma_chuyen_goi', $id)->value('ma_goi_dich');
        $oldTemplate = DB::table('goi_da_mua')->where('ma_goi_da_mua', $this->source)->value('ma_goi_lieu_trinh');
        DB::table('goi_da_mua')->where('ma_goi_da_mua', $new)->update(['so_buoi_da_dung' => 1, 'phien_ban' => 2]);
        $this->source = $new;
        $this->target = $oldTemplate;
        $id2 = $this->deNghi();
        $this->duyet($id2, ['phien_ban_nguon' => 2, 'gia_tri_chuyen' => 2500000, 'gia_goi_dich' => 2500000])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('chuyen_goi', ['ma_chuyen_goi' => $id2, 'trang_thai' => 'completed', 'gia_tri_chuyen' => 2500000, 'tien_thu_them' => 0]);
    }

    public function test_reception_assignment_can_create_walk_in_but_cannot_collect_money(): void
    {
        $u = $this->people['employee'];
        DB::table('nguoi_dung')->where('ma_nguoi_dung', $u->getKey())->update(['co_quyen_tiep_nhan' => true]);
        $u->refresh();
        $this->actingAs($u)->get('/giao-dich')->assertOk()->assertSee('Tiếp nhận lịch tại quầy')->assertDontSee('Đăng ký gói cho khách');
        $this->post('/giao-dich/tiep-nhan', ['ma_khach_hang' => $this->people['other']->getKey(), 'ma_dich_vu' => $this->service,
            'ngay' => '2026-11-13', 'gio' => '15:00'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('lich_hen', ['ma_khach_hang' => $this->people['other']->getKey(), 'nguon_dat_lich' => 'walk_in', 'trang_thai' => 'pending']);
        $this->post('/giao-dich/mua-goi', ['ma_khach_hang' => $this->people['customer']->getKey(), 'ma_goi_lieu_trinh' => $this->target])->assertForbidden();
    }

    public function test_failed_email_is_not_retried_automatically_and_old_time_is_cancelled(): void
    {
        NghiepVuSpa::nhacLich(DB::table('lich_hen')->where('ma_lich_hen', $this->booking)->first());
        Carbon::setTestNow(Carbon::parse('2026-11-12 11:00:00', 'Asia/Ho_Chi_Minh'));
        config(['mail.default' => 'smtp']);
        Mail::shouldReceive('raw')->once()->andThrow(new \RuntimeException('SMTP unavailable'));
        $this->artisan('spa:gui-nhac-hen')->assertSuccessful();
        $this->artisan('spa:gui-nhac-hen')->assertSuccessful();
        $this->assertDatabaseHas('nhac_hen', ['ma_lich_hen' => $this->booking, 'trang_thai' => 'failed', 'so_lan_thu_gui' => 1]);
        DB::table('nhac_hen')->where('ma_lich_hen', $this->booking)->update(['trang_thai' => 'pending']);
        DB::table('lich_hen')->where('ma_lich_hen', $this->booking)->update(['thoi_diem_bat_dau' => '2026-11-13 14:00:00', 'thoi_diem_ket_thuc' => '2026-11-13 15:00:00']);
        $this->artisan('spa:gui-nhac-hen')->assertSuccessful();
        $this->assertDatabaseHas('nhac_hen', ['ma_lich_hen' => $this->booking, 'trang_thai' => 'cancelled']);
    }

    public function test_reconciliation_before_invoice_preserves_source_lock_and_logs_reference(): void
    {
        $id = $this->deNghi();
        $this->duyet($id)->assertSessionHasNoErrors();
        $this->post('/chuyen-goi/'.$id, ['hanh_dong' => 'reconcile', 'phien_ban' => 2, 'ly_do' => 'Tham chiếu đối soát TEST-REF-01'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('chuyen_goi', ['ma_chuyen_goi' => $id, 'trang_thai' => 'awaiting_payment', 'ma_hoa_don' => null, 'phien_ban' => 3]);
        $this->assertDatabaseHas('goi_da_mua', ['ma_goi_da_mua' => $this->source, 'trang_thai' => 'transfer_locked']);
        $this->assertDatabaseHas('nhat_ky_quan_tri', ['loai_doi_tuong' => 'package_transfer', 'ma_doi_tuong' => $id, 'hanh_dong' => 'reconcile']);
        $this->get('/chuyen-goi')->assertOk()->assertSee('TEST-REF-01');
    }

    public function test_reports_classify_transfer_even_when_source_credit_is_zero(): void
    {
        DB::table('goi_da_mua')->where('ma_goi_da_mua', $this->source)->update(['gia_mua' => 0, 'gia_tri_cap_von' => 0]);
        DB::table('hoa_don')->where('ma_goi_da_mua', $this->source)->update(['tien_truoc_giam' => 0, 'thanh_tien' => 0, 'tien_can_thu' => 0, 'so_tien_da_thu' => 0, 'phuong_thuc_thanh_toan' => 'no_charge']);
        $id = $this->deNghi();
        $this->duyet($id, ['gia_tri_chuyen' => 0])->assertSessionHasNoErrors();
        $this->post('/chuyen-goi/'.$id, ['hanh_dong' => 'complete', 'phien_ban' => 2, 'da_nhan_du' => 1, 'phuong_thuc' => 'cash'])->assertSessionHasNoErrors();
        $response = $this->get('/admin/bao-cao?tu=2026-11-01&den=2026-11-30')->assertOk();
        $this->assertEquals(3000000, $response->viewData('chiTieu')['Thu thêm chuyển gói']);
        $this->assertEquals(0, $response->viewData('chiTieu')['Giá trị chuyển nội bộ']);
        $this->assertEquals(0, $response->viewData('chiTieu')['Thu mua gói thông thường']);
    }

    public function test_customer_package_history_is_scoped_to_owner_and_keeps_transferred_packages(): void
    {
        $this->actingAs($this->people['customer'])->get('/goi-cua-toi')->assertOk()->assertSee('Gói gốc');
        $this->chupTrang('packages', '/goi-cua-toi');
        DB::table('goi_da_mua')->where('ma_goi_da_mua', $this->source)->update(['trang_thai' => 'transferred']);
        $this->get('/goi-cua-toi')->assertOk()->assertSee('Đã chuyển sang gói khác');
        $this->actingAs($this->people['other'])->get('/goi-cua-toi')->assertOk()->assertDontSee('Gói gốc');
        $this->actingAs($this->people['employee'])->get('/goi-cua-toi')->assertForbidden();
    }

    public function test_selected_employee_is_available_and_saved_only_for_valid_slot(): void
    {
        $this->actingAs($this->people['customer']);
        $this->getJson('/dat-lich/nhan-vien?ma_dich_vu='.$this->service.'&ngay=2026-11-13&gio=12:00')->assertOk()->assertJsonFragment(['ma_nguoi_dung' => $this->people['employee']->getKey()]);
        $this->getJson('/dat-lich/nhan-vien?ma_dich_vu='.$this->service.'&ngay=2026-11-13&gio=10:00')->assertOk()->assertExactJson([]);
        $data = ['ho_ten' => 'Khách thử', 'so_dien_thoai' => '0912345678', 'ma_dich_vu' => $this->service, 'ngay' => '2026-11-13', 'gio' => '12:00', 'gia_da_xem' => 500000, 'thoi_luong_da_xem' => 60, 'ma_nhan_vien' => $this->people['other']->getKey()];
        $this->post('/dat-lich', $data)->assertSessionHasErrors('ma_nhan_vien');
        $data['ma_nhan_vien'] = $this->people['employee']->getKey();
        $this->post('/dat-lich', $data)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('lich_hen', ['ma_khach_hang' => $this->people['customer']->getKey(), 'thoi_diem_bat_dau' => '2026-11-13 12:00:00', 'ma_nhan_vien' => $this->people['employee']->getKey(), 'trang_thai' => 'pending']);
    }

    public function test_customer_hub_does_not_reveal_another_customers_purchases(): void
    {
        $this->actingAs($this->people['customer'])->get('/lich-hen-cua-ban')->assertOk()->assertSee('Lịch hẹn của tôi')->assertSee('Dịch vụ gốc')->assertSee('Lọc lịch');
        $this->chupTrang('customer-hub', '/lich-hen-cua-ban');
        $this->actingAs($this->people['other'])->get('/lich-hen-cua-ban')->assertOk()->assertDontSee('Gói gốc')->assertDontSee('Dịch vụ gốc');
        $this->actingAs($this->people['employee'])->get('/lich-hen-cua-ban')->assertForbidden();
    }

    public function test_history_database_has_real_foreign_keys_and_transactional_engine(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            $engine = DB::selectOne("SELECT ENGINE AS e FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='nhat_ky_lich_hen'")->e;
            $this->assertSame('InnoDB', $engine);
            $this->assertEquals(2, DB::selectOne("SELECT COUNT(*) AS n FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='nhat_ky_lich_hen' AND CONSTRAINT_TYPE='FOREIGN KEY'")->n);
        }
        $before = DB::table('nhat_ky_lich_hen')->count();
        try {
            DB::transaction(fn () => DB::table('nhat_ky_lich_hen')->insert(['ma_lich_hen' => 2147483647, 'ma_nguoi_thao_tac' => $this->people['admin']->getKey(), 'du_lieu_truoc' => '{}', 'du_lieu_sau' => '{}', 'ly_do' => 'Kiểm tra FK', 'thoi_diem_thao_tac' => now(), 'phien_ban_lich' => 1]));
            $this->fail('Missing foreign-key enforcement');
        } catch (QueryException $error) {
            $this->assertStringContainsString('foreign key', strtolower($error->getMessage()));
        }
        $this->assertSame($before, DB::table('nhat_ky_lich_hen')->count());
    }

    public function test_history_and_reschedule_both_rollback_together(): void
    {
        $before = DB::table('nhat_ky_lich_hen')->count();
        DB::beginTransaction();
        try {
            $this->actingAs($this->people['customer'])->post('/lich-hen/'.$this->booking.'/doi-lich', ['ngay' => '2026-11-13', 'gio' => '15:00', 'ly_do' => 'Thử hoàn tác', 'phien_ban' => 1])->assertSessionHasNoErrors();
            $this->assertSame($before + 1, DB::table('nhat_ky_lich_hen')->count());
        } finally {
            DB::rollBack();
        }
        $this->assertSame($before, DB::table('nhat_ky_lich_hen')->count());
        $this->assertDatabaseHas('lich_hen', ['ma_lich_hen' => $this->booking, 'thoi_diem_bat_dau' => '2026-11-13 10:00:00', 'phien_ban' => 1]);
    }

    public function test_staff_rescheduling_pending_clears_old_employee_and_bed(): void
    {
        DB::table('lich_hen')->where('ma_lich_hen', $this->booking)->update(['trang_thai' => 'pending']);
        $this->actingAs($this->people['admin'])->post('/lich-hen/'.$this->booking.'/doi-lich', ['ngay' => '2026-11-13', 'gio' => '20:00', 'ly_do' => 'Đổi yêu cầu chờ', 'phien_ban' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('lich_hen', ['ma_lich_hen' => $this->booking, 'trang_thai' => 'pending', 'ma_nhan_vien' => null, 'ma_giuong' => null]);
        $row = DB::table('nhat_ky_lich_hen')->where('ma_lich_hen', $this->booking)->first();
        $this->assertNull(json_decode($row->du_lieu_sau, true)['nhan_vien']);
    }

    public function test_walk_in_creates_guest_and_confirms_checked_resources_once(): void
    {
        $key = (string) Str::uuid();
        $data = ['email_moi' => 'guest-'.Str::uuid().'@example.test', 'ten_khach_moi' => 'Khách vãng lai kiểm thử', 'dien_thoai_moi' => '0912345678', 'ma_dich_vu' => $this->service, 'ngay' => '2026-11-13', 'gio' => '14:00', 'ma_nhan_vien' => $this->people['employee']->getKey(), 'ma_giuong' => $this->bed, 'xac_nhan_ngay' => 1, 'khoa_yeu_cau' => $key, 'vai_tro' => 'admin'];
        $this->actingAs($this->people['admin'])->post('/giao-dich/tiep-nhan', $data)->assertSessionHasNoErrors();
        $log = DB::table('nhat_ky_quan_tri')->where('khoa_yeu_cau', $key)->first();
        $id = json_decode($log->du_lieu_sau, true)['ma_lich_hen'];
        $kh = DB::table('nguoi_dung')->where('ma_nguoi_dung', $log->ma_doi_tuong)->first();
        $this->assertSame('customer', $kh->vai_tro);
        $this->assertNull($kh->mat_khau);
        $this->assertDatabaseHas('lich_hen', ['ma_lich_hen' => $id, 'trang_thai' => 'confirmed', 'nguon_dat_lich' => 'walk_in', 'ma_nhan_vien' => $this->people['employee']->getKey()]);
        $this->assertDatabaseHas('nhac_hen', ['ma_lich_hen' => $id, 'trang_thai' => 'pending']);
        $before = DB::table('lich_hen')->count();
        $this->post('/giao-dich/tiep-nhan', $data)->assertSessionHasNoErrors();
        $this->assertSame($before, DB::table('lich_hen')->count());
    }

    public function test_walk_in_rejects_busy_resource_and_rolls_back_new_guest(): void
    {
        $before = DB::table('nguoi_dung')->count();
        $this->actingAs($this->people['admin'])->post('/giao-dich/tiep-nhan', ['ten_khach_moi' => 'Không lưu khách lỗi', 'dien_thoai_moi' => '0912345678', 'ma_dich_vu' => $this->service, 'ngay' => '2026-11-13', 'gio' => '10:00', 'ma_nhan_vien' => $this->people['employee']->getKey(), 'ma_giuong' => $this->bed, 'xac_nhan_ngay' => 1])->assertSessionHasErrors();
        $this->assertSame($before, DB::table('nguoi_dung')->count());
    }

    public function test_walk_in_uses_only_owned_paid_package_and_does_not_charge_or_deduct(): void
    {
        $data = ['ma_khach_hang' => $this->people['other']->getKey(), 'ma_dich_vu' => $this->service, 'ma_goi_da_mua' => $this->source, 'ngay' => '2026-11-13', 'gio' => '14:00', 'ma_nhan_vien' => $this->people['employee']->getKey(), 'ma_giuong' => $this->bed, 'xac_nhan_ngay' => 1];
        $this->actingAs($this->people['admin'])->post('/giao-dich/tiep-nhan', $data)->assertSessionHasErrors();
        $data['ma_khach_hang'] = $this->people['customer']->getKey();
        $invoices = DB::table('hoa_don')->count();
        $this->post('/giao-dich/tiep-nhan', $data)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('lich_hen', ['ma_goi_da_mua' => $this->source, 'trang_thai' => 'confirmed', 'thoi_diem_bat_dau' => '2026-11-13 14:00:00']);
        $this->assertSame($invoices, DB::table('hoa_don')->count());
        $this->assertDatabaseHas('goi_da_mua', ['ma_goi_da_mua' => $this->source, 'so_buoi_da_dung' => 2]);
    }
}
