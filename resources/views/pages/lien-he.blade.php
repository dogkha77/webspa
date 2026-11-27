@extends('layouts.spa')

@section('title', 'Hỗ trợ đặt lịch')

@section('content')
    <section class="page-section contact-intro">
        <div class="container">
            <h1>Hỗ trợ đặt lịch</h1>
            <p>Kiểm tra lịch đã gửi, gói đã mua hoặc chọn một dịch vụ để đặt lịch mới.</p>
            <div class="button-row">
                <a class="btn" href="{{ route('lich-hen-cua-ban') }}">Xem lịch hẹn của tôi</a>
                <a class="btn btn-soft" href="{{ route('goi-cua-toi') }}">Xem gói của tôi</a>
            </div>
        </div>
    </section>

    <section class="page-section" id="tu-van">
        <div class="container">
            <h2>Dịch vụ đang nhận lịch</h2>
            <ul class="check-list">
                @forelse($dichVu as $dichVuHienCo)
                    <li>
                        <a href="{{ route('dat-lich', ['dich_vu' => $dichVuHienCo->ma_dich_vu]) }}">
                            {{ $dichVuHienCo->ten_dich_vu }} · {{ $dichVuHienCo->thoi_luong_phut }} phút
                        </a>
                    </li>
                @empty
                    <li>Chưa có dịch vụ đang cung cấp.</li>
                @endforelse
            </ul>
        </div>
    </section>
@endsection
