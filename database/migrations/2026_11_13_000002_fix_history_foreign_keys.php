<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }
        $n = DB::selectOne('SELECT COUNT(*) AS n FROM nhat_ky_lich_hen nk LEFT JOIN lich_hen lh ON lh.ma_lich_hen=nk.ma_lich_hen LEFT JOIN nguoi_dung nd ON nd.ma_nguoi_dung=nk.ma_nguoi_thao_tac WHERE lh.ma_lich_hen IS NULL OR nd.ma_nguoi_dung IS NULL')->n;
        if ($n) {
            throw new RuntimeException('Cần sao lưu và xử lý nhật ký mất liên kết trước khi bổ sung khóa ngoại.');
        }
        DB::statement('ALTER TABLE nhat_ky_lich_hen ENGINE=InnoDB');
        foreach (['ma_lich_hen' => ['lich_hen', 'ma_lich_hen'], 'ma_nguoi_thao_tac' => ['nguoi_dung', 'ma_nguoi_dung']] as $col => [$table,$key]) {
            $exists = DB::selectOne("SELECT COUNT(*) AS n FROM information_schema.KEY_COLUMN_USAGE WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='nhat_ky_lich_hen' AND COLUMN_NAME=? AND REFERENCED_TABLE_NAME IS NOT NULL", [$col])->n;
            if (! $exists) {
                DB::statement("ALTER TABLE nhat_ky_lich_hen ADD CONSTRAINT fk_history_{$col} FOREIGN KEY ({$col}) REFERENCES {$table}({$key})");
            }
        }
    }

    public function down(): void {}
};
