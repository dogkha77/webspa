<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nhat_ky_lich_hen', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->increments('ma_nhat_ky');
            $table->integer('ma_lich_hen');
            $table->integer('ma_nguoi_thao_tac');
            $table->json('du_lieu_truoc');
            $table->json('du_lieu_sau');
            $table->text('ly_do');
            $table->dateTime('thoi_diem_thao_tac');
            $table->integer('phien_ban_lich');
            $table->foreign('ma_lich_hen')->references('ma_lich_hen')->on('lich_hen');
            $table->foreign('ma_nguoi_thao_tac')->references('ma_nguoi_dung')->on('nguoi_dung');
            $table->index(['ma_lich_hen', 'thoi_diem_thao_tac']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nhat_ky_lich_hen');
    }
};
