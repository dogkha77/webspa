@extends('layouts.spa')

@section('title', 'Gói liệu trình')

@section('content')
    <section class="page-section package-intro">
        <div class="container">
            <span class="pill">LIỆU TRÌNH CHUYÊN BIỆT</span>
            <h1>Chăm sóc dài lâu, an nhiên mỗi ngày</h1>
            <p class="intro-copy">Các gói liệu trình đang được An Nhiên cung cấp. Mỗi lần mua có lịch sử sử dụng và quyền lợi riêng.</p>
        </div>
    </section>

    @auth
        @if(auth()->user()->vai_tro === 'customer')
            @include('partials.de-nghi-mua-goi')
        @endif
    @endauth

    <section class="page-section">
        <div class="container">
            @foreach($errors->all() as $error)
                <p role="alert">{{ $error }}</p>
            @endforeach

            <div class="plans-grid">
                @forelse($cacGoi as $goi)
                    <article class="plan-card">
                        <span class="small-label">{{ $goi->tong_so_buoi }} BUỔI CHĂM SÓC</span>
                        <h2>{{ $goi->ten_goi }}</h2>
                        <p>{{ $goi->ten_dich_vu }}</p>
                        <div class="plan-price">{{ number_format($goi->gia, 0, ',', '.') }}<small> đ / gói</small></div>
                        <ul class="check-list">
                            <li>{{ $goi->tong_so_buoi }} buổi · {{ $goi->thoi_luong_phut }} phút / buổi</li>
                            <li>Thanh toán đủ trước khi sử dụng</li>
                            <li>Theo dõi từng lượt phục vụ</li>
                        </ul>

                        @auth
                            @if(auth()->user()->vai_tro === 'customer')
                                <form method="POST" action="{{ route('goi-lieu-trinh.dang-ky', $goi->ma_goi_lieu_trinh) }}">
                                    @csrf
                                    <button class="btn">Gửi đề nghị mua gói</button>
                                </form>
                            @endif
                        @else
                            <a class="btn" href="{{ route('login') }}">Đăng nhập để đăng ký gói</a>
                        @endauth
                    </article>
                @empty
                    <div class="live-empty">
                        <h2>Chưa có gói đang bán</h2>
                        <p>Bạn vẫn có thể lựa chọn dịch vụ lẻ khi spa mở nhận lịch.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </section>
@endsection
