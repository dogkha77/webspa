<?php

namespace App\Console\Commands;

use App\Models\NguoiDung;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TaoQuanTriSpa extends Command
{
    protected $signature = 'spa:tao-quan-tri';

    protected $description = 'Tạo tài khoản quản trị đầu tiên bằng thông tin do bạn nhập';

    public function handle(): int
    {
        if (DB::table('nguoi_dung')->where('vai_tro', 'admin')->where('dang_hoat_dong', true)->exists()) {
            $this->error('Đã có quản trị viên. Hãy quản lý tài khoản trong giao diện admin.');

            return self::FAILURE;
        }
        $data = ['ten_nguoi_dung' => $this->ask('Họ tên quản trị viên'), 'dia_chi_thu_dien_tu' => $this->ask('Email đăng nhập'),
            'password' => $this->secret('Mật khẩu từ 8 ký tự'), 'password_confirmation' => $this->secret('Nhập lại mật khẩu')];
        $validator = Validator::make($data, ['ten_nguoi_dung' => ['required', 'string', 'max:150'],
            'dia_chi_thu_dien_tu' => ['required', 'email', 'max:190', 'unique:nguoi_dung,dia_chi_thu_dien_tu'], 'password' => ['required', 'string', 'min:8', 'confirmed']]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        if (! $this->confirm('Tạo quản trị viên trên DB '.DB::connection()->getDatabaseName().'?', false)) {
            return self::FAILURE;
        }
        $user = new NguoiDung;
        $user->ten_nguoi_dung = $data['ten_nguoi_dung'];
        $user->dia_chi_thu_dien_tu = $data['dia_chi_thu_dien_tu'];
        $user->mat_khau = $data['password'];
        $user->vai_tro = 'admin';
        $user->dang_hoat_dong = true;
        $user->save();
        $this->info('Đã tạo tài khoản. Bạn có thể đăng nhập và mở /admin.');

        return self::SUCCESS;
    }
}
