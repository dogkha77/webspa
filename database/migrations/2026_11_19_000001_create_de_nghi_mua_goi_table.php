<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('de_nghi_mua_goi')) {
            Schema::create('de_nghi_mua_goi', function (Blueprint $table): void {
                $table->engine = 'InnoDB';
                $table->increments('ma_de_nghi');
                $table->integer('ma_khach_hang');
                $table->integer('ma_goi_lieu_trinh');
                $table->integer('ma_goi_da_mua')->nullable();
                $table->dateTime('thoi_diem_gui');
                $table->foreign('ma_khach_hang')->references('ma_nguoi_dung')->on('nguoi_dung');
                $table->foreign('ma_goi_lieu_trinh')->references('ma_goi_lieu_trinh')->on('goi_lieu_trinh');
                $table->foreign('ma_goi_da_mua')->references('ma_goi_da_mua')->on('goi_da_mua');
            });
        }

        $coChiMucGoiDaMua = Schema::hasIndex('de_nghi_mua_goi', 'uq_de_nghi_goi_da_mua');
        $coChiMucTimDeNghi = Schema::hasIndex('de_nghi_mua_goi', 'idx_de_nghi_khach_goi');
        Schema::table('de_nghi_mua_goi', function (Blueprint $table) use ($coChiMucGoiDaMua, $coChiMucTimDeNghi): void {
            if (! $coChiMucGoiDaMua) {
                $table->unique('ma_goi_da_mua', 'uq_de_nghi_goi_da_mua');
            }
            if (! $coChiMucTimDeNghi) {
                $table->index(['ma_khach_hang', 'ma_goi_lieu_trinh', 'ma_goi_da_mua'], 'idx_de_nghi_khach_goi');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('de_nghi_mua_goi');
    }
};
