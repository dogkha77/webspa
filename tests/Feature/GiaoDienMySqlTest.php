<?php

namespace Tests\Feature;

use App\Models\NguoiDung;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GiaoDienMySqlTest extends TestCase
{
    public function test_live_layouts_read_do_an_spa_without_changing_business_data(): void
    {
        if (getenv('RUN_MYSQL_LAYOUT_TESTS') !== '1') {
            $this->markTestSkipped('Chỉ chạy khi chủ động bật kiểm tra đọc MySQL.');
        }
        $connection = config('database.connections.mysql');
        $connection['database'] = 'do_an_spa';
        config(['database.connections.live_layout' => $connection, 'database.default' => 'live_layout']);
        DB::purge('live_layout');
        $this->assertSame('do_an_spa', DB::connection()->getDatabaseName());
        $tables = ['nguoi_dung', 'dich_vu', 'goi_da_mua', 'lich_hen', 'hoa_don', 'chuyen_goi', 'luot_su_dung_goi'];
        $before = collect($tables)->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()]);
        foreach (['/', '/dich-vu', '/goi-lieu-trinh', '/ve-spa', '/lien-he'] as $path) {
            $this->get($path)->assertOk();
        }
        foreach (['admin', 'employee', 'customer'] as $role) {
            $user = NguoiDung::where('vai_tro', $role)->where('dang_hoat_dong', true)->first();
            if (! $user) {
                continue;
            }
            $this->actingAs($user);
            if ($role === 'customer') {
                $response = $this->get('/dat-lich')->assertOk();
            } else {
                $base = $role === 'admin' ? '/admin/' : '/nhan-vien/';
                foreach (config('menu_spa.'.$role) as $key => $item) {
                    $this->get($base.$key)->assertOk();
                }
                $response = $this->get($base)->assertOk();
            }
            if ($dir = getenv('SPA_UI_CAPTURE_DIR')) {
                file_put_contents($dir.'/live-'.$role.'.html', $response->getContent());
            }
            auth()->forgetGuards();
        }
        $after = collect($tables)->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()]);
        $this->assertSame($before->all(), $after->all());
        if ($dir = getenv('SPA_UI_CAPTURE_DIR')) {
            file_put_contents($dir.'/live-counts.json', json_encode($after->all(), JSON_PRETTY_PRINT));
        }
    }
}
