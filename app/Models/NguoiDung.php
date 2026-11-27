<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class NguoiDung extends Authenticatable
{
    protected $table = 'nguoi_dung';

    protected $primaryKey = 'ma_nguoi_dung';

    protected $hidden = ['mat_khau'];

    protected $rememberTokenName = '';

    public const CREATED_AT = 'thoi_diem_tao';

    public const UPDATED_AT = 'thoi_diem_cap_nhat';

    public function getAuthPasswordName(): string
    {
        return 'mat_khau';
    }

    protected function casts(): array
    {
        return [
            'mat_khau' => 'hashed',
            'dang_hoat_dong' => 'boolean',
            'co_quyen_tiep_nhan' => 'boolean',
            'co_quyen_thu_tien' => 'boolean',
            'phien_ban' => 'integer',
        ];
    }
}
