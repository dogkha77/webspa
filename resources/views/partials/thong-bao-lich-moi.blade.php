<details class="live-notifications" data-admin-bookings="{{ route('admin.thong-bao.lich-moi') }}" data-admin-id="{{ auth()->id() }}">
    <summary>
        Thông báo
        <span class="live-notifications-count" data-booking-count hidden></span>
    </summary>
    <div class="live-notifications-panel">
        <h2>Lịch đặt trực tuyến gần đây</h2>
        <p data-booking-status>Đang tải thông báo…</p>
        <ul data-booking-notifications></ul>
        <a href="{{ route('admin.trang', ['muc' => 'lich-hen']) }}">Tất cả lịch hẹn</a>
    </div>
</details>
