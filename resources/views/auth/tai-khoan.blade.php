@extends(auth()->user()->vai_tro === 'customer' ? 'layouts.spa' : 'layouts.van-hanh')
@section('title', 'Tài khoản')
@section('content')
<section class="card" aria-labelledby="page-title">
    <p class="eyebrow">TÀI KHOẢN CỦA BẠN</p>
    <h1 id="page-title">Xin chào, {{ auth()->user()->ten_nguoi_dung }}</h1>
    <p class="subtitle">Bạn đã đăng nhập vào Website Spa.</p>
    @if(auth()->user()->vai_tro==='customer')<p><a class="btn" href="{{ route('goi-cua-toi') }}">Xem gói và lịch sử sử dụng của tôi</a></p>@endif
    @if(auth()->user()->vai_tro === 'admin')
        <p><a class="btn" href="{{ route('admin.trang') }}">Mở giao diện quản trị</a></p>
    @elseif(auth()->user()->vai_tro === 'employee')
        <p><a class="btn" href="{{ route('nhan-vien.trang') }}">Mở giao diện nhân viên</a></p>
    @endif
    @if(auth()->user()->vai_tro === 'customer' || auth()->user()->vai_tro === 'admin' || auth()->user()->co_quyen_tiep_nhan || auth()->user()->co_quyen_thu_tien)
    <p><a class="btn" href="{{ route('chuyen-goi') }}">Chuyển gói và theo dõi yêu cầu</a></p>
    @endif
    @if(auth()->user()->vai_tro === 'admin' || auth()->user()->co_quyen_thu_tien || auth()->user()->co_quyen_tiep_nhan)<p><a class="btn" href="{{ route('giao-dich') }}">Bán gói và thu tiền</a></p>@endif
    <details><summary>Cập nhật thông tin cá nhân</summary><form method="POST" action="{{ route('tai-khoan.luu') }}">@csrf
    <label>Họ tên<input name="ten_nguoi_dung" value="{{ old('ten_nguoi_dung', auth()->user()->ten_nguoi_dung) }}" required maxlength="100"></label>
    <label>Email<input type="email" name="dia_chi_thu_dien_tu" value="{{ old('dia_chi_thu_dien_tu', auth()->user()->dia_chi_thu_dien_tu) }}" required></label>
    <label>Số điện thoại<input name="so_dien_thoai" value="{{ old('so_dien_thoai', auth()->user()->so_dien_thoai) }}"></label>
    <label>Mật khẩu hiện tại<input type="password" name="mat_khau_hien_tai" required autocomplete="current-password"></label>
    <label>Mật khẩu mới nếu cần đổi<input type="password" name="password" minlength="8" autocomplete="new-password"></label>
    <label>Nhập lại mật khẩu mới<input type="password" name="password_confirmation" autocomplete="new-password"></label>
    <button class="button">Lưu thay đổi hồ sơ</button></form></details>
    <dl class="profile">
        <dt>Hạng thành viên</dt><dd>{{ $hangThanhVien?->ten_hang ?? 'Chưa xếp hạng' }}@if($hangThanhVien) · {{ $hangThanhVien->dang_hoat_dong ? $hangThanhVien->ty_le_giam_gia.'% ưu đãi giao dịch mới' : 'Đang ngừng áp dụng ưu đãi' }}@endif</dd>
        <dt>Email</dt><dd>{{ auth()->user()->dia_chi_thu_dien_tu }}</dd>
        <dt>Số điện thoại</dt><dd>{{ auth()->user()->so_dien_thoai ?: 'Chưa cung cấp' }}</dd>
        <dt>Vai trò</dt><dd>{{ ['customer' => 'Khách hàng', 'employee' => 'Nhân viên', 'admin' => 'Quản trị viên'][auth()->user()->vai_tro] ?? 'Chưa xác định' }}</dd>
    </dl>
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button class="button" type="submit">Đăng xuất</button>
    </form>
</section>
@endsection
