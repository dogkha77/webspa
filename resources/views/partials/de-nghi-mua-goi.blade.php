<section class="container spa-transactions" aria-labelledby="package-orders-title">
    <h2 id="package-orders-title">Các gói bạn đã đặt</h2>
    @forelse($deNghiMua as $deNghi)
        <article class="card package-order-row">
            <h3>Đơn #{{ $deNghi->ma_de_nghi }} · {{ $deNghi->ten_goi }}</h3>
            <p>Đặt lúc {{ \Carbon\Carbon::parse($deNghi->thoi_diem_gui)->format('d/m/Y H:i') }}</p>
            @if(! $deNghi->ma_goi_da_mua)
                <p>Trạng thái: <strong>Chờ spa xác nhận và lập hóa đơn</strong></p>
            @elseif(! $deNghi->so_hoa_don)
                <p>Trạng thái: <strong>Spa đang hoàn tất hồ sơ đơn hàng</strong>. Hóa đơn sẽ xuất hiện tại đây khi được lập.</p>
            @elseif($deNghi->trang_thai_xu_ly === 'error')
                <p role="alert">Trạng thái: cần spa đối soát hóa đơn. Gói chưa thể sử dụng.</p>
            @elseif($deNghi->trang_thai_thanh_toan === 'paid')
                <p>Trạng thái: <strong>Đã thanh toán</strong> · Hóa đơn {{ $deNghi->so_hoa_don }} · Đã ghi nhận {{ number_format($deNghi->so_tien_da_thu, 0, ',', '.') }} đ</p>
            @else
                <p>Trạng thái: <strong>Chờ thanh toán</strong> · Hóa đơn {{ $deNghi->so_hoa_don }} · Cần thanh toán {{ number_format($deNghi->tien_can_thu, 0, ',', '.') }} đ</p>
            @endif
        </article>
    @empty
        <p>Bạn chưa đặt mua gói liệu trình nào.</p>
    @endforelse
    @if(method_exists($deNghiMua, 'links'))
        {{ $deNghiMua->links() }}
    @endif
    @if(request()->routeIs('goi-lieu-trinh'))
        <p><a href="{{ route('goi-cua-toi') }}">Xem toàn bộ đơn đặt và gói của tôi →</a></p>
    @endif
</section>
