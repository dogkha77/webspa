@php
    $menu = ['trang-chu' => 'Trang chủ', 'dich-vu' => 'Dịch vụ', 'goi-lieu-trinh' => 'Gói liệu trình', 've-spa' => 'Về spa', 'lien-he' => 'Hỗ trợ'];
    if (auth()->check() && auth()->user()->vai_tro === 'customer') {
        $menu['lich-hen-cua-ban'] = 'Lịch hẹn của bạn';
        $menu['goi-cua-toi'] = 'Gói của tôi';
    }
    $bookingUrl = route('dat-lich');
@endphp
<header class="site-header"><div class="container header-inner">
    <a class="brand" href="{{ route('trang-chu') }}" aria-label="An Nhiên Spa — Trang chủ"><span class="brand-mark"><x-spa-icon name="flower" size="32"/></span><span class="brand-text">An Nhiên Spa<small>THẢO MỘC & AN NHIÊN</small></span></a>
    <nav class="desktop-nav" aria-label="Điều hướng chính">@foreach ($menu as $name => $label)<a href="{{ route($name) }}" @if(request()->routeIs($name)) aria-current="page" @endif>{{ $label }}</a>@endforeach</nav>
    <div class="header-actions">
    @auth
        <details class="account-menu"><summary aria-label="Tài khoản của tôi"><span class="avatar"><x-spa-icon name="user" size="16"/></span><span class="account-label">Tài khoản của tôi</span><x-spa-icon name="chevron" size="14"/></summary><div class="account-dropdown"><p>{{ auth()->user()->ten_nguoi_dung }}</p>@if(auth()->user()->vai_tro === 'admin')<a href="{{ route('admin.trang') }}">Khu vực quản trị</a>@elseif(auth()->user()->vai_tro === 'employee')<a href="{{ route('nhan-vien.trang') }}">Khu vực nhân viên</a>@endif<a href="{{ route('tai-khoan') }}">Thông tin tài khoản</a><form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">Đăng xuất</button></form></div></details>
    @else
        <a class="account-link" aria-label="Đăng nhập" href="{{ route('login') }}"><span class="avatar"><x-spa-icon name="user" size="16"/></span><span>Đăng nhập</span></a>
    @endauth
    <a class="btn btn-small" href="{{ $bookingUrl }}">Đặt lịch</a><button class="mobile-toggle" type="button" aria-controls="mobile-navigation" aria-expanded="false" aria-label="Mở menu"><x-spa-icon name="menu" size="24"/></button>
    </div>
</div><nav id="mobile-navigation" class="mobile-nav" aria-label="Điều hướng điện thoại" hidden>@foreach ($menu as $name => $label)<a href="{{ route($name) }}" @if(request()->routeIs($name)) aria-current="page" @endif>{{ $label }}</a>@endforeach @guest<a href="{{ route('register') }}">Đăng ký tài khoản</a>@endguest<a href="{{ $bookingUrl }}">Đặt lịch</a></nav></header>
