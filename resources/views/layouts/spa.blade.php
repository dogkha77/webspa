<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#e9fff0">
    <meta name="description" content="An Nhiên Spa — chăm sóc cơ thể và tìm lại sự thư thái cùng thảo mộc tự nhiên.">
    <title>@yield('title', 'Trang chủ') · An Nhiên Spa</title>
    <link rel="stylesheet" href="{{ asset('css/trang-chu.css') }}">
    <link rel="stylesheet" href="{{ asset('css/cac-trang.css') }}">
    <link rel="stylesheet" href="{{ asset('css/du-lieu-spa.css') }}">
    <link rel="stylesheet" href="{{ asset('css/nut-bam-ro-rang.css') }}">
    <script src="{{ asset('js/trang-chu.js') }}" defer></script>
    <script src="{{ asset('js/cac-trang.js') }}" defer></script>
    <script src="{{ asset('js/du-lieu-spa.js') }}" defer></script>
</head>
<body class="@yield('body-class', 'inner-page')">
    <a class="skip-link" href="#noi-dung">Đến nội dung chính</a>
    @include('partials.spa-icons')
    @include('partials.spa-header')

    <main id="noi-dung">
        @if(session('thong_bao'))
            <div class="container flash-notice" role="status">{{ session('thong_bao') }}</div>
        @endif

        @yield('content')
    </main>

    @include('partials.spa-footer', ['bookingUrl' => route('dat-lich')])
</body>
</html>
