@extends('layouts.spa')
@section('title', 'Lịch hẹn của bạn')
@section('content')
@if($errors->any())
<div class="container live-error" role="alert"><strong>Chưa cập nhật được lịch hẹn</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
<section class="container booking-history" id="lich-cua-toi">
    @php($trangThai=['pending'=>'Chờ xác nhận','confirmed'=>'Đã xác nhận','completed'=>'Hoàn thành','cancelled'=>'Đã hủy','rejected'=>'Bị từ chối'])
    <div class="page-heading"><span class="small-label">HÀNH TRÌNH CỦA BẠN</span><h2>Lịch hẹn của tôi</h2></div>
    <form method="GET" action="{{ route('lich-hen-cua-ban') }}#lich-cua-toi" class="booking-history-filter"><label for="history-status">Trạng thái<select id="history-status" name="trang_thai"><option value="">Tất cả lịch</option>@foreach($trangThai as $value=>$label)<option value="{{ $value }}" @selected(request('trang_thai')===$value)>{{ $label }}</option>@endforeach</select></label><button class="btn btn-small" type="submit">Lọc lịch</button></form>
    <div class="live-table-wrap"><table class="live-table"><thead><tr><th>Mã</th><th>Dịch vụ</th><th>Thời gian</th><th>Trạng thái</th><th>Chi tiết</th></tr></thead><tbody>
    @forelse($lichCuaToi as $lh)
        <tr id="lich-{{ $lh->ma_lich_hen }}" @class(['booking-just-created'=>session('dat_lich_ma')===$lh->ma_lich_hen])><td>#{{ $lh->ma_lich_hen }}</td><td>{{ $lh->ten_dich_vu_luc_dat }}</td><td>{{ \Carbon\Carbon::parse($lh->thoi_diem_bat_dau)->format('d/m/Y H:i') }}<small>đến {{ \Carbon\Carbon::parse($lh->thoi_diem_ket_thuc)->format('H:i') }}</small></td><td><span class="live-badge {{ $lh->trang_thai }}">{{ $trangThai[$lh->trang_thai] ?? $lh->trang_thai }}</span></td><td>
            <details class="booking-history-details"><summary>Xem chi tiết</summary><div><p><strong>Hình thức:</strong> {{ $lh->ma_goi_da_mua?'Sử dụng gói #'.$lh->ma_goi_da_mua:'Dịch vụ lẻ' }}</p><p><strong>Giá tại lúc đặt:</strong> {{ number_format($lh->gia_dich_vu_luc_dat,0,',','.') }} đ @if($lh->ma_goi_da_mua)(không thu lại buổi thuộc gói)@endif</p><p><strong>Ghi chú của bạn:</strong> {{ $lh->ghi_chu_khach_hang ?: 'Chưa có' }}</p>@if(in_array($lh->trang_thai,['confirmed','completed']))<p><strong>Nhân viên phục vụ:</strong> {{ $lh->nhan_vien_phuc_vu ?: 'Spa sẽ thông báo' }}</p>@endif
            @if(in_array($lh->trang_thai,['pending','confirmed']))<p><a href="{{ route('lich.lich-su',$lh->ma_lich_hen) }}">Lịch sử đổi lịch</a></p><details><summary>Đổi ngày giờ</summary><form method="POST" action="{{ route('lich.doi', $lh->ma_lich_hen) }}">@csrf<input type="hidden" name="phien_ban" value="{{ $lh->phien_ban }}"><label>Ngày mới<input type="date" name="ngay" min="{{ now()->toDateString() }}" required></label><label>Giờ mới<input type="time" name="gio" required></label><label>Lý do<input name="ly_do" maxlength="1000" required></label><p>Khách tự đổi cần spa xác nhận lại nhân viên và giường.</p><button class="live-btn">Gửi yêu cầu đổi lịch</button></form></details>@endif
            @if($lh->trang_thai === 'rejected' && filled($lh->ghi_chu_nhan_vien))
                <p><strong>Lý do spa từ chối:</strong> {{ $lh->ghi_chu_nhan_vien }}</p>
            @endif
            @if($lh->trang_thai==='pending')<form method="POST" action="{{ route('dat-lich.huy', $lh->ma_lich_hen) }}">@csrf<label for="cancel-reason-{{ $lh->ma_lich_hen }}">Lý do rút yêu cầu<input id="cancel-reason-{{ $lh->ma_lich_hen }}" name="ly_do" required maxlength="500"></label><button class="btn btn-small" type="submit">Rút yêu cầu đặt lịch</button></form>@else<p class="live-hint">Liên hệ spa nếu bạn cần hỗ trợ về lịch này.</p>@endif
            </div></details></td></tr>
    @empty
        <tr><td colspan="5" class="live-empty">Chưa có lịch phù hợp. Bạn có thể đổi bộ lọc hoặc đặt lịch mới tại trang Đặt lịch.</td></tr>
    @endforelse
    </tbody></table></div><div class="live-pagination">{{ $lichCuaToi->links() }}</div>
</section>
@endsection
