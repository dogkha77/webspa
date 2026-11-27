@extends('layouts.spa')

@section('title', 'Gói liệu trình của tôi')

@section('content')
    <section class="container spa-transactions" style="padding-top:40px;padding-bottom:60px">
        <h1>Gói liệu trình của tôi</h1>
        <nav class="package-page-actions" aria-label="Thao tác gói liệu trình">
            <a class="btn btn-soft" href="{{ route('goi-lieu-trinh') }}">Xem các gói đang bán</a>
            <a class="btn" href="{{ route('chuyen-goi') }}">Đề nghị chuyển gói</a>
        </nav>

        @foreach($errors->all() as $error)
            <p role="alert">{{ $error }}</p>
        @endforeach

        @include('partials.de-nghi-mua-goi')

        @php
            $nhanTrangThai = [
                'pending_payment' => 'Chờ thanh toán',
                'active' => 'Đang sử dụng',
                'transfer_locked' => 'Đang khóa chờ chuyển gói',
                'transferred' => 'Đã chuyển sang gói khác',
                'completed' => 'Đã dùng hết',
            ];
        @endphp

        @forelse($cacGoi as $goi)
            <article class="card">
                <h2>#{{ $goi->ma_goi_da_mua }} · {{ $goi->ten_goi_luc_mua }}</h2>
                <p>{{ $goi->ten_dich_vu_luc_mua }}</p>
                <p>Trạng thái: <strong>{{ $nhanTrangThai[$goi->trang_thai] }}</strong></p>
                @if($goi->so_hoa_don)
                    <section class="package-payment-status" aria-label="Trạng thái thanh toán gói">
                        <h3>Hóa đơn {{ $goi->so_hoa_don }}</h3>
                        <p>Thanh toán: <strong>{{ $goi->trang_thai_thanh_toan === 'paid' ? 'Đã thanh toán' : 'Chưa thanh toán' }}</strong></p>
                        <p>Cần thanh toán {{ number_format($goi->tien_can_thu, 0, ',', '.') }} đ · Đã ghi nhận {{ number_format($goi->so_tien_da_thu, 0, ',', '.') }} đ</p>
                        @if($goi->trang_thai_xu_ly === 'error')
                            <p role="alert">Hóa đơn đang cần spa đối soát. Gói chưa thể sử dụng cho đến khi được xử lý.</p>
                        @elseif($goi->trang_thai_thanh_toan === 'paid')
                            <p>Phương thức: {{ ['cash' => 'Tiền mặt', 'bank_transfer' => 'Chuyển khoản', 'no_charge' => 'Không phát sinh thu thêm'][$goi->phuong_thuc_thanh_toan] ?? $goi->phuong_thuc_thanh_toan }} · {{ $goi->thoi_diem_thanh_toan }}</p>
                        @else
                            <p>Spa sẽ xác nhận thanh toán sau khi đối chiếu đã nhận đủ tiền. Bạn chưa thể đặt lịch bằng gói này.</p>
                        @endif
                    </section>
                @else
                    <p>Chưa có hóa đơn cho lần mua này. Vui lòng liên hệ spa để được hỗ trợ.</p>
                @endif
                <p>Đã dùng {{ $goi->so_buoi_da_dung }}/{{ $goi->tong_so_buoi }} buổi · Còn {{ $goi->tong_so_buoi - $goi->so_buoi_da_dung }} buổi. Quyền đặt lịch còn phụ thuộc trạng thái thanh toán và các lịch đã giữ buổi.</p>
                <p>Giá trị quyền lợi đã cấp: {{ number_format($goi->gia_tri_cap_von, 0, ',', '.') }} đ. Giá trị này có thể gồm phần chuyển từ gói trước, không đồng nghĩa tiền mới thu.</p>

                @if($goiCoTheDat->has($goi->ma_goi_da_mua))
                    <p><a class="btn" href="{{ route('dat-lich', ['goi' => $goi->ma_goi_da_mua]) }}">Đặt lịch sử dụng gói</a></p>
                @endif

                <details>
                    <summary>Lịch sử các buổi đã sử dụng</summary>
                    @forelse($luotDung->get($goi->ma_goi_da_mua, collect()) as $luot)
                        <p>Lịch #{{ $luot->ma_lich_hen }} · {{ $luot->thoi_diem_su_dung }}</p>
                    @empty
                        <p>Chưa ghi nhận buổi đã sử dụng.</p>
                    @endforelse
                </details>
            </article>
        @empty
            <p>Bạn chưa có gói liệu trình.</p>
        @endforelse

        {{ $cacGoi->links() }}
    </section>
@endsection
