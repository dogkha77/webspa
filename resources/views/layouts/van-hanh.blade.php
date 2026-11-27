@php
    $laAdmin = $laAdmin ?? auth()->user()->vai_tro === 'admin';
    $tienToRoute = $tienToRoute ?? ($laAdmin ? 'admin.trang' : 'nhan-vien.trang');
    $menu = $menu ?? config('menu_spa.'.($laAdmin ? 'admin' : 'employee'));
    $muc = $muc ?? null;
    $tieuDe = $tieuDe ?? ($muc !== null ? ($menu[$muc]['title'] ?? 'An Nhiên Spa') : 'An Nhiên Spa');
@endphp
<!DOCTYPE html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#e9fff0"><title>@yield('title', $tieuDe) · An Nhiên Spa</title><link rel="stylesheet" href="{{ asset('css/fonts-spa.css') }}"><link rel="stylesheet" href="{{ asset('css/du-lieu-spa.css') }}"><link rel="stylesheet" href="{{ asset('css/nut-bam-ro-rang.css') }}"><script src="{{ asset('js/du-lieu-spa.js') }}" defer></script></head><body class="live-work"><a class="live-skip" href="#noi-dung">Đến nội dung</a>@include('partials.spa-icons')
<aside class="live-sidebar" id="work-menu"><a class="live-brand" href="{{ route($tienToRoute) }}"><span class="live-logo"><x-spa-icon name="flower" size="28"/></span><span>An Nhiên<small>{{ $laAdmin ? 'BẢNG QUẢN TRỊ' : 'CỔNG NHÂN VIÊN' }}</small></span></a><nav aria-label="Điều hướng nội bộ">@foreach($menu as $key=>$item)@continue($item['hidden'] ?? false)<a href="{{ route($tienToRoute, ['muc'=>$key==='tong-quan'?null:$key]) }}" @if($muc===$key) aria-current="page" @endif><x-spa-icon :name="$item['icon']" size="18"/>{{ $item['label'] }}</a>@endforeach</nav><div class="live-sidebar-bottom"><a href="{{ route('tai-khoan') }}">Thông tin cá nhân</a><form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">Đăng xuất</button></form></div></aside>
<button class="live-scrim" data-work-scrim aria-label="Đóng menu" hidden></button><div class="live-work-body"><header class="live-topbar"><button type="button" class="live-menu-toggle" data-work-menu aria-controls="work-menu" aria-expanded="false" aria-label="Mở menu">☰</button><form method="GET" action="{{ route($tienToRoute,['muc'=>'lich-hen']) }}" class="live-search"><span>⌕</span><input name="q" aria-label="Tìm lịch hẹn" placeholder="Tìm lịch hẹn, khách hàng, dịch vụ…" value="{{ request('q') }}"><button type="submit">Tìm lịch hẹn</button></form>@if($laAdmin)
    @include('partials.thong-bao-lich-moi')
@endif
<div class="live-profile"><span class="live-avatar">{{ mb_substr(auth()->user()->ten_nguoi_dung,0,1) }}</span><div><strong>{{ auth()->user()->ten_nguoi_dung }}</strong><small>{{ $laAdmin?'Quản trị viên':'Nhân viên' }}</small></div></div></header><main id="noi-dung" class="live-main">
@if($laAdmin)
    <div class="live-success" data-booking-alert role="status" hidden></div>
@endif
@if(session('thong_bao'))<div class="live-success" role="status">{{ session('thong_bao') }}</div>@endif @if($errors->any())<div class="live-error" role="alert"><strong>Chưa lưu được thay đổi</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif @yield('content')</main><footer class="live-work-footer">An Nhiên Spa · {{ now()->format('d/m/Y H:i') }} · Múi giờ Việt Nam</footer></div></body></html>
