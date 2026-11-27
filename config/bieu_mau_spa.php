<?php

return [
    'danh-muc' => ['table' => 'danh_muc_dich_vu', 'key' => 'ma_danh_muc', 'title' => 'danh mục', 'fields' => [
        'ten_danh_muc' => ['Tên danh mục', 'text'], 'mo_ta' => ['Mô tả', 'textarea'], 'dang_hoat_dong' => ['Hoạt động', 'boolean'],
    ]],
    'dich-vu' => ['table' => 'dich_vu', 'key' => 'ma_dich_vu', 'title' => 'dịch vụ', 'fields' => [
        'ma_danh_muc' => ['Danh mục', 'category'], 'ten_dich_vu' => ['Tên dịch vụ', 'text'], 'mo_ta' => ['Mô tả', 'textarea'],
        'gia' => ['Giá dịch vụ (đ)', 'number'], 'thoi_luong_phut' => ['Thời lượng (phút)', 'number'], 'dang_hoat_dong' => ['Đang cung cấp', 'boolean'],
    ]],
    'goi-lieu-trinh' => ['table' => 'goi_lieu_trinh', 'key' => 'ma_goi_lieu_trinh', 'title' => 'gói liệu trình', 'fields' => [
        'ten_goi' => ['Tên gói', 'text'], 'ma_dich_vu' => ['Dịch vụ', 'service'], 'tong_so_buoi' => ['Tổng số buổi', 'number'],
        'gia' => ['Giá gói (đ)', 'number'], 'dang_hoat_dong' => ['Đang bán', 'boolean'],
    ]],
    'phong' => ['table' => 'phong', 'key' => 'ma_phong', 'title' => 'phòng', 'fields' => ['ten_phong' => ['Tên phòng', 'text'], 'dang_hoat_dong' => ['Hoạt động', 'boolean']]],
    'phong-giuong' => ['table' => 'giuong', 'key' => 'ma_giuong', 'title' => 'giường', 'fields' => ['ma_phong' => ['Phòng', 'room'], 'ten_giuong' => ['Tên giường', 'text'], 'dang_hoat_dong' => ['Hoạt động', 'boolean']]],
    'hang-thanh-vien' => ['table' => 'hang_thanh_vien', 'key' => 'ma_hang_thanh_vien', 'title' => 'hạng thành viên', 'fields' => ['ten_hang' => ['Tên hạng', 'text'], 'ty_le_giam_gia' => ['Tỷ lệ ưu đãi (%)', 'number'], 'dang_hoat_dong' => ['Hoạt động', 'boolean']]],
    'lich-lam-viec' => ['table' => 'lich_lam_viec', 'key' => 'ma_lich_lam_viec', 'title' => 'ca làm', 'fields' => ['ma_nhan_vien' => ['Nhân viên', 'employee'], 'ngay_lam_viec' => ['Ngày làm việc', 'date'], 'gio_bat_dau' => ['Giờ bắt đầu', 'time'], 'gio_ket_thuc' => ['Giờ kết thúc', 'time'], 'co_the_nhan_lich' => ['Có thể nhận lịch', 'boolean']]],
    'nguoi-dung' => ['table' => 'nguoi_dung', 'key' => 'ma_nguoi_dung', 'title' => 'người dùng', 'fields' => ['ten_nguoi_dung' => ['Họ tên', 'text'], 'dia_chi_thu_dien_tu' => ['Email', 'email'], 'so_dien_thoai' => ['Số điện thoại', 'tel'], 'vai_tro' => ['Vai trò', 'role'], 'ma_hang_thanh_vien' => ['Hạng thành viên', 'membership'], 'co_quyen_tiep_nhan' => ['Quyền tiếp nhận lịch', 'boolean'], 'co_quyen_thu_tien' => ['Quyền thu tiền', 'boolean'], 'dang_hoat_dong' => ['Hoạt động', 'boolean'], 'password' => ['Mật khẩu mới (để trống nếu giữ nguyên)', 'password']]],
    'lieu-trinh' => ['table' => 'goi_da_mua', 'key' => 'ma_goi_da_mua', 'title' => 'lần mua gói', 'fields' => ['ma_khach_hang' => ['Khách hàng', 'customer'], 'ma_goi_lieu_trinh' => ['Gói đang bán', 'package']]],
    'lich-hen' => ['table' => 'lich_hen', 'key' => 'ma_lich_hen', 'title' => 'lịch tại quầy', 'fields' => ['ma_khach_hang' => ['Khách hàng', 'customer'], 'ma_dich_vu' => ['Dịch vụ lẻ', 'service'], 'ngay' => ['Ngày mong muốn', 'date'], 'gio' => ['Giờ mong muốn', 'time'], 'ghi_chu' => ['Ghi chú tiếp nhận', 'textarea']]],
];
