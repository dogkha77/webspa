<?php

namespace Tests\Feature;

use App\Models\NguoiDung;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\SchemaSpa;
use Tests\TestCase;

class XacThucTest extends TestCase
{
    use SchemaSpa;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'auth.providers.users.model' => NguoiDung::class,
            'database.default' => 'auth_testing',
            'database.connections.auth_testing' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);

        Schema::connection('auth_testing')->create('nguoi_dung', function (Blueprint $table): void {
            $table->increments('ma_nguoi_dung');
            $table->string('ten_nguoi_dung', 150);
            $table->string('dia_chi_thu_dien_tu', 190)->nullable()->unique();
            $table->string('so_dien_thoai', 25)->nullable();
            $table->string('mat_khau')->nullable();
            $table->string('vai_tro')->default('customer');
            $table->unsignedInteger('ma_hang_thanh_vien')->nullable();
            $table->boolean('dang_hoat_dong')->default(true);
            $table->timestamp('thoi_diem_tao')->nullable();
            $table->timestamp('thoi_diem_cap_nhat')->nullable();
        });
        $this->taoCauTrucSpa();
    }

    public function test_guest_can_view_forms_and_home_but_not_account(): void
    {
        $this->get('/')->assertOk()->assertSee('Đăng nhập');
        $this->get('/dang-nhap')->assertOk()->assertSee('name="_token"', false);
        $this->get('/dang-ky')->assertOk()->assertSee('Nhập lại mật khẩu');
        $this->get('/tai-khoan')->assertRedirect('/dang-nhap');
    }

    public function test_registration_hashes_password_and_cannot_elevate_role(): void
    {
        $this->post('/dang-ky', $this->registrationData([
            'dia_chi_thu_dien_tu' => '  KHA@EXAMPLE.COM  ',
            'vai_tro' => 'admin',
            'dang_hoat_dong' => false,
            'ma_hang_thanh_vien' => 999,
        ]))->assertRedirect('/dang-nhap')->assertSessionHasNoErrors();

        $user = NguoiDung::query()->sole();
        $this->assertSame('kha@example.com', $user->dia_chi_thu_dien_tu);
        $this->assertSame('customer', $user->vai_tro);
        $this->assertTrue($user->dang_hoat_dong);
        $this->assertNull($user->ma_hang_thanh_vien);
        $this->assertTrue(Hash::check('Spa12345!', $user->mat_khau));
        $this->assertNotNull($user->thoi_diem_tao);
        $this->assertArrayNotHasKey('mat_khau', $user->toArray());
        $this->assertGuest();
        $this->actingAs($user)->get('/tai-khoan')->assertOk()->assertSee('Khách hàng');
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $this->createUser();
        $this->from('/dang-ky')->post('/dang-ky', $this->registrationData())
            ->assertRedirect('/dang-ky')->assertSessionHasErrors('dia_chi_thu_dien_tu');
        $this->assertSame(1, NguoiDung::query()->count());
        $this->assertGuest();
    }

    public function test_invalid_registration_is_rejected_without_flashing_passwords(): void
    {
        $this->from('/dang-ky')->post('/dang-ky', $this->registrationData([
            'ten_nguoi_dung' => '',
            'dia_chi_thu_dien_tu' => 'khong-phai-email',
            'so_dien_thoai' => 'abc',
            'password' => '123',
            'password_confirmation' => '456',
        ]))->assertRedirect('/dang-ky')
            ->assertSessionHasErrors(['ten_nguoi_dung', 'dia_chi_thu_dien_tu', 'so_dien_thoai', 'password'])
            ->assertSessionMissing('_old_input.password')
            ->assertSessionMissing('_old_input.password_confirmation');
        $this->assertSame(0, NguoiDung::query()->count());
    }

    public function test_password_over_bcrypt_byte_limit_is_rejected(): void
    {
        $password = str_repeat('ấ', 25);
        $this->post('/dang-ky', $this->registrationData([
            'password' => $password, 'password_confirmation' => $password,
        ]))->assertSessionHasErrors('password');
        $this->assertSame(0, NguoiDung::query()->count());
    }

    public function test_phone_is_optional(): void
    {
        $this->post('/dang-ky', $this->registrationData(['so_dien_thoai' => '']))
            ->assertRedirect('/dang-nhap')->assertSessionHasNoErrors();
        $this->assertNull(NguoiDung::query()->sole()->so_dien_thoai);
    }

    public function test_login_uses_vietnamese_password_column_and_rotates_session(): void
    {
        $user = $this->createUser();
        $this->get('/dang-nhap')->assertOk();
        $previousId = session()->getId();
        $this->post('/dang-nhap', [
            'dia_chi_thu_dien_tu' => 'KHA@EXAMPLE.COM', 'password' => 'Spa12345!',
        ])->assertRedirect('/')->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($previousId, session()->getId());
    }

    public function test_wrong_password_and_inactive_or_passwordless_accounts_cannot_login(): void
    {
        $user = $this->createUser();
        $this->post('/dang-nhap', [
            'dia_chi_thu_dien_tu' => $user->dia_chi_thu_dien_tu, 'password' => 'sai-mat-khau',
        ])->assertSessionHasErrors('dia_chi_thu_dien_tu');
        $this->assertGuest();

        $user->dang_hoat_dong = false;
        $user->save();
        $this->post('/dang-nhap', [
            'dia_chi_thu_dien_tu' => $user->dia_chi_thu_dien_tu, 'password' => 'Spa12345!',
        ])->assertSessionHasErrors('dia_chi_thu_dien_tu');
        $this->assertGuest();

        $user->dang_hoat_dong = true;
        $user->mat_khau = null;
        $user->save();
        $this->post('/dang-nhap', [
            'dia_chi_thu_dien_tu' => $user->dia_chi_thu_dien_tu, 'password' => 'Spa12345!',
        ])->assertSessionHasErrors('dia_chi_thu_dien_tu');
        $this->assertGuest();
    }

    public function test_login_throttles_repeated_failed_attempts(): void
    {
        $this->createUser();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/dang-nhap', ['dia_chi_thu_dien_tu' => 'kha@example.com', 'password' => 'sai-mat-khau']);
        }

        $this->post('/dang-nhap', ['dia_chi_thu_dien_tu' => 'kha@example.com', 'password' => 'Spa12345!'])
            ->assertSessionHasErrors('dia_chi_thu_dien_tu');
        $this->assertGuest();

        RateLimiter::clear('dang-nhap:'.hash('sha256', 'kha@example.com|127.0.0.1'));
        $this->post('/dang-nhap', ['dia_chi_thu_dien_tu' => 'kha@example.com', 'password' => 'Spa12345!'])
            ->assertRedirect('/');
        $this->assertAuthenticated();
    }

    public function test_guest_forms_redirect_signed_in_user_and_logout_invalidates_session(): void
    {
        $this->actingAs($this->createUser());
        $this->get('/dang-nhap')->assertRedirect('/');
        $this->get('/dang-ky')->assertRedirect('/');
        $this->withSession(['private_value' => 'secret'])->post('/dang-xuat')
            ->assertRedirect('/')->assertSessionMissing('private_value');
        $this->assertGuest();
        $this->get('/tai-khoan')->assertRedirect('/dang-nhap');
        $this->get('/dang-xuat')->assertStatus(405);
    }

    public function test_account_disabled_after_login_is_signed_out(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);
        $user->dang_hoat_dong = false;
        $user->save();
        $this->get('/tai-khoan')->assertRedirect('/dang-nhap')
            ->assertSessionHasErrors('dia_chi_thu_dien_tu');
        $this->assertGuest();
    }

    public function test_protected_page_can_reload_user_from_session(): void
    {
        $user = $this->createUser();
        $this->post('/dang-nhap', ['dia_chi_thu_dien_tu' => 'kha@example.com', 'password' => 'Spa12345!']);
        Auth::forgetGuards();
        $this->get('/tai-khoan')->assertOk()->assertSee($user->ten_nguoi_dung);
        $this->assertAuthenticatedAs($user);
    }

    public function test_forms_reject_posts_without_csrf_token(): void
    {
        $this->app->instance('env', 'local');
        $this->post('/dang-ky', $this->registrationData())->assertStatus(419);
        $this->post('/dang-nhap', ['dia_chi_thu_dien_tu' => 'kha@example.com', 'password' => 'Spa12345!'])
            ->assertStatus(419);
        $this->actingAs($this->createUser())->post('/dang-xuat')->assertStatus(419);
    }

    public function test_registration_is_throttled_by_ip(): void
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->post('/dang-ky', []);
        }
        $this->post('/dang-ky', $this->registrationData())->assertStatus(429);
        $this->assertSame(0, DB::table('nguoi_dung')->count());
    }

    public function test_registration_keeps_intended_page_and_requires_login(): void
    {
        $this->get('/tai-khoan')->assertRedirect('/dang-nhap');
        $this->get('/dang-nhap')->assertOk();
        $this->get('/dang-ky')->assertOk();
        $this->post('/dang-ky', $this->registrationData())
            ->assertRedirect('/dang-nhap')
            ->assertSessionHas('_old_input.dia_chi_thu_dien_tu', 'kha@example.com')
            ->assertSessionMissing('_old_input.password')
            ->assertSessionMissing('_old_input.password_confirmation');
        $this->assertGuest();
        $this->get('/dang-nhap')->assertOk()->assertSee('Đăng ký thành công');
        $this->post('/dang-nhap', $this->registrationData())
            ->assertRedirect('/tai-khoan')->assertSessionMissing('url.intended');
        $this->get('/tai-khoan')->assertOk();
    }

    public function test_login_remembers_previous_public_page(): void
    {
        $this->createUser();
        $this->from('/?dich_vu=1')->get('/dang-nhap')->assertOk();
        $this->post('/dang-nhap', $this->registrationData())->assertRedirect('/?dich_vu=1');
        $this->get('/?dich_vu=1')->assertOk()->assertSee('Đăng nhập thành công.');
    }

    #[DataProvider('invalidDestinations')]
    public function test_login_ignores_invalid_destinations(string $destination): void
    {
        $this->createUser();
        $this->withSession(['url.intended' => $destination])
            ->post('/dang-nhap', $this->registrationData())
            ->assertRedirect('/')->assertSessionMissing('url.intended');
        $this->assertAuthenticated();
    }

    public static function invalidDestinations(): array
    {
        return [
            'external' => ['https://example.org/tai-khoan'],
            'protocol relative' => ['//example.org/tai-khoan'],
            'javascript' => ['javascript:alert(1)'],
            'backslash' => ['/\\example.org'],
            'missing page' => ['/khong-ton-tai'],
            'login loop' => ['/dang-nhap'],
            'registration loop' => ['/dang-ky'],
            'logout is POST' => ['/dang-xuat'],
        ];
    }

    public function test_login_ignores_external_referrer_and_keeps_protected_destination(): void
    {
        $this->from('https://example.org')->get('/dang-nhap')->assertOk()->assertSessionMissing('url.intended');
        $this->get('/tai-khoan')->assertRedirect('/dang-nhap');
        $this->from('/')->get('/dang-nhap')->assertOk()
            ->assertSessionHas('url.intended', url('/tai-khoan'));
    }

    #[DataProvider('accountRoles')]
    public function test_all_active_roles_can_login_and_request_cannot_change_role(string $role): void
    {
        $user = $this->createUser();
        $user->vai_tro = $role;
        $user->save();
        $this->post('/dang-nhap', $this->registrationData(['vai_tro' => 'admin']))
            ->assertRedirect(match ($role) {
                'admin' => '/admin',
                'employee' => '/nhan-vien',
                default => '/',
            });
        $this->assertAuthenticatedAs($user);
        $this->assertSame($role, $user->fresh()->vai_tro);
    }

    public static function accountRoles(): array
    {
        return [['customer'], ['employee'], ['admin']];
    }

    public function test_unknown_email_does_not_create_account_or_flash_password(): void
    {
        $this->from('/dang-nhap')->post('/dang-nhap', $this->registrationData())
            ->assertSessionHasErrors('dia_chi_thu_dien_tu')
            ->assertSessionMissing('_old_input.password')
            ->assertSessionMissing('_old_input.password_confirmation');
        $this->assertGuest();
        $this->assertSame(0, NguoiDung::query()->count());
    }

    public function test_login_rejects_password_exceeding_bcrypt_byte_limit(): void
    {
        $user = $this->createUser();
        $user->mat_khau = str_repeat('a', 72);
        $user->save();
        $this->post('/dang-nhap', $this->registrationData(['password' => str_repeat('a', 72).'suffix']))
            ->assertSessionHasErrors('password');
        $this->assertGuest();
    }

    #[DataProvider('pagesForDisabledAccount')]
    public function test_disabled_session_is_removed_on_web_pages(string $path): void
    {
        $user = $this->createUser();
        $this->actingAs($user);
        $user->dang_hoat_dong = false;
        $user->save();
        $this->withSession(['private_value' => 'secret'])->get($path)
            ->assertRedirect('/dang-nhap')->assertSessionMissing('private_value');
        $this->assertGuest();
    }

    public static function pagesForDisabledAccount(): array
    {
        return [['/'], ['/dang-nhap'], ['/dang-ky'], ['/tai-khoan']];
    }

    public function test_private_pages_are_not_cached_and_logout_rotates_token_and_session(): void
    {
        $this->actingAs($this->createUser());
        $response = $this->get('/tai-khoan')->assertOk();
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
        $oldId = session()->getId();
        $oldToken = session()->token();
        $this->withSession(['url.intended' => url('/tai-khoan')])->post('/dang-xuat')
            ->assertRedirect('/')->assertSessionMissing('url.intended');
        $this->assertGuest();
        $this->assertNotSame($oldId, session()->getId());
        $this->assertNotSame($oldToken, session()->token());
        $this->get('/')->assertOk()->assertSee('Bạn đã đăng xuất.')->assertDontSee('Tài khoản của tôi');
        $this->get('/tai-khoan')->assertRedirect('/dang-nhap');
    }

    #[DataProvider('roleDestinations')]
    public function test_login_returns_each_role_to_the_correct_area(string $role, ?string $intended, string $expected): void
    {
        $user = $this->createUser();
        $user->vai_tro = $role;
        $user->save();
        if ($intended !== null) {
            $this->withSession(['url.intended' => url($intended)]);
        }
        $this->post('/dang-nhap', $this->registrationData())->assertRedirect($expected)->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
    }

    public static function roleDestinations(): array
    {
        return [
            ['admin', null, '/admin'],
            ['employee', null, '/nhan-vien'],
            ['customer', null, '/'],
            ['admin', '/dich-vu', '/admin'],
            ['employee', '/goi-lieu-trinh', '/nhan-vien'],
            ['admin', '/admin/hoa-don?q=HD', '/admin/hoa-don?q=HD'],
            ['employee', '/nhan-vien/lich-hen', '/nhan-vien/lich-hen'],
            ['admin', '/nhan-vien/lich-hen', '/admin'],
        ];
    }

    #[DataProvider('signedInRoleDestinations')]
    public function test_signed_in_users_opening_login_or_register_return_to_their_area(string $role, string $expected): void
    {
        $user = $this->createUser();
        $user->vai_tro = $role;
        $user->save();
        $this->actingAs($user)->get('/dang-nhap')->assertRedirect($expected);
        $this->get('/dang-ky')->assertRedirect($expected);
    }

    public static function signedInRoleDestinations(): array
    {
        return [['admin', '/admin'], ['employee', '/nhan-vien'], ['customer', '/']];
    }

    private function registrationData(array $overrides = []): array
    {
        return array_replace([
            'ten_nguoi_dung' => 'Huỳnh Đông Kha',
            'dia_chi_thu_dien_tu' => 'kha@example.com',
            'so_dien_thoai' => '0901234567',
            'password' => 'Spa12345!',
            'password_confirmation' => 'Spa12345!',
        ], $overrides);
    }

    private function createUser(): NguoiDung
    {
        $user = new NguoiDung;
        $user->ten_nguoi_dung = 'Huỳnh Đông Kha';
        $user->dia_chi_thu_dien_tu = 'kha@example.com';
        $user->mat_khau = 'Spa12345!';
        $user->vai_tro = 'customer';
        $user->dang_hoat_dong = true;
        $user->save();

        return $user;
    }
}
