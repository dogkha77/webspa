@extends('layouts.spa')
@section('title', 'Đặt lịch trị liệu')
@section('content')
<section class="booking-intro container"><span class="pill">● KHOẢNG LẶNG THÂN TÂM</span><h1>Đặt Lịch Trị Liệu &<br>Chăm Sóc Thân Tâm</h1><p>Chọn dịch vụ, xem giờ gợi ý và gửi yêu cầu. An Nhiên sẽ kiểm tra và xác nhận lịch đón tiếp của bạn.</p></section>
<section class="booking-shell container">
    @if($errors->any())
        <div class="live-error" role="alert"><strong>Chưa gửi được yêu cầu</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    @if(session('dat_lich_ma'))
        <div class="booking-confirmation" role="status"><strong>Yêu cầu #{{ session('dat_lich_ma') }} đã được ghi nhận</strong><p>Trạng thái: Chờ xác nhận. Bạn chưa bị thu tiền hoặc trừ buổi gói từ việc gửi yêu cầu.</p><a href="#lich-{{ session('dat_lich_ma') }}">Xem lịch vừa gửi ↓</a></div>
    @endif
    @if($dichVu->isEmpty())
        <div class="live-empty"><h2>Chưa có dịch vụ nhận lịch</h2><p>Vui lòng quay lại sau hoặc liên hệ spa để được hỗ trợ.</p><a href="{{ route('lien-he') }}" class="btn">Liên hệ spa</a></div>
    @else
    <form method="POST" action="{{ route('dat-lich.luu') }}" id="booking-form" class="booking-grid" data-availability-url="{{ route('dat-lich.khung-gio') }}">
        @csrf
        <input type="hidden" name="gia_da_xem" value="{{ old('gia_da_xem') }}">
        <input type="hidden" name="thoi_luong_da_xem" value="{{ old('thoi_luong_da_xem') }}">
        <div>
            <fieldset><legend>1. THÔNG TIN LIÊN HỆ</legend><div class="booking-fields">
                <label for="booking-name">Họ và tên <span>*</span><input id="booking-name" name="ho_ten" required maxlength="150" autocomplete="name" value="{{ old('ho_ten', auth()->user()->ten_nguoi_dung) }}"></label>
                <label for="booking-phone">Số điện thoại <span>*</span><input id="booking-phone" name="so_dien_thoai" required type="tel" maxlength="16" pattern="[+]?[0-9]{9,15}" autocomplete="tel" value="{{ old('so_dien_thoai', auth()->user()->so_dien_thoai) }}"></label>
            </div></fieldset>
            <fieldset><legend>2. DỊCH VỤ & LIỆU TRÌNH</legend>
                <label for="booking-service">Dịch vụ chăm sóc <span>*</span><select name="ma_dich_vu" id="booking-service" required><option value="">Chọn dịch vụ</option>@foreach($dichVu as $dv)<option value="{{ $dv->ma_dich_vu }}" data-price="{{ $dv->gia }}" data-duration="{{ $dv->thoi_luong_phut }}" @selected((string) old('ma_dich_vu', $goiDaChon?->ma_dich_vu ?? request('dich_vu')) === (string) $dv->ma_dich_vu)>{{ $dv->ten_dich_vu }} · {{ $dv->thoi_luong_phut }} phút</option>@endforeach</select></label>
                <label for="booking-package">Hình thức sử dụng<select name="ma_goi_da_mua" id="booking-package"><option value="">Dịch vụ lẻ</option>@foreach($cacGoi as $g)<option value="{{ $g->ma_goi_da_mua }}" data-service="{{ $g->ma_dich_vu }}" @selected((string) old('ma_goi_da_mua', $goiDaChon?->ma_goi_da_mua) === (string) $g->ma_goi_da_mua)>#{{ $g->ma_goi_da_mua }} · {{ $g->ten_goi_luc_mua }} · Còn {{ $g->tong_so_buoi - $g->so_buoi_da_dung }} buổi, đã giữ {{ $g->so_buoi_da_giu }} buổi cho lịch xác nhận</option>@endforeach</select></label>
                <p class="live-hint">Mỗi lịch dành cho một khách, một dịch vụ. Chỉ hiển thị gói của bạn, đã thanh toán và còn buổi chưa được giữ.</p>
            </fieldset>
            <fieldset><legend>3. NGÀY & GIỜ ĐÓN TIẾP</legend><div class="booking-fields">
                <label for="booking-date">Chọn ngày <span>*</span><input name="ngay" id="booking-date" type="date" required min="{{ now()->toDateString() }}" value="{{ old('ngay', now()->toDateString()) }}"></label>
                <label>Nhân viên phục vụ<select name="ma_nhan_vien" id="booking-employee"><option value="">Spa sắp xếp nhân viên</option></select><small id="employee-message">Chọn dịch vụ, ngày và giờ để xem nhân viên phù hợp. Yêu cầu vẫn chờ spa xác nhận.</small></label>
                <label for="booking-time">Giờ mong muốn <span>*</span><input name="gio" id="booking-time" type="time" required value="{{ old('gio') }}"></label>
            </div><p id="booking-slot-status" class="live-hint" role="status" aria-live="polite">Chọn dịch vụ và ngày để xem giờ gợi ý.</p><div id="booking-slots" class="booking-slots" role="group" aria-label="Giờ có thể gửi yêu cầu"></div><p class="live-hint">Giờ gợi ý được tính từ ca làm và giường đang hoạt động. Yêu cầu chờ xử lý chưa giữ độc quyền nhân viên hoặc giường.</p></fieldset>
            <label for="booking-note">Ghi chú cho spa<textarea id="booking-note" name="ghi_chu" maxlength="2000" rows="4" placeholder="Nhu cầu chăm sóc hoặc lưu ý để spa chuẩn bị…">{{ old('ghi_chu') }}</textarea></label>
        </div>
        <aside class="booking-summary"><span class="eyebrow">KIỂM TRA TRƯỚC KHI GỬI</span><h2>Tóm Tắt Trải Nghiệm <x-spa-icon name="leaf" size="25"/></h2><dl><dt>Khách hàng</dt><dd id="summary-name">{{ auth()->user()->ten_nguoi_dung }}</dd><dt>Dịch vụ</dt><dd id="summary-service">Chưa chọn</dd><dt>Thời lượng</dt><dd id="summary-duration">—</dd><dt>Ngày & giờ</dt><dd id="summary-date">Chưa chọn</dd><dt>Số lượng</dt><dd>1 khách</dd></dl><p class="summary-note">✧ Giá và thời lượng được kiểm tra lại khi gửi. Spa sẽ báo bạn xem lại nếu thông tin dịch vụ đã thay đổi.</p><div class="summary-total"><span>Giá dịch vụ</span><strong id="summary-price">—</strong></div><p id="summary-payment" class="live-hint">Tiền thanh toán được nhân viên xác nhận riêng.</p><button class="btn" type="submit" data-submit-booking>Gửi yêu cầu đặt lịch <span>→</span></button><p class="live-hint">Sau khi gửi, bạn theo dõi trạng thái bên dưới. Với lịch đã xác nhận, vui lòng liên hệ spa nếu cần thay đổi.</p></aside>
    </form>
    @endif
</section>
<section class="container booking-history" id="lich-cua-toi">
    @php($trangThai=['pending'=>'Chờ xác nhận','confirmed'=>'Đã xác nhận','completed'=>'Hoàn thành','cancelled'=>'Đã hủy','rejected'=>'Bị từ chối'])
    <div class="page-heading"><span class="small-label">HÀNH TRÌNH CỦA BẠN</span><h2>Lịch hẹn của tôi</h2></div>
    <form method="GET" action="{{ route('dat-lich') }}#lich-cua-toi" class="booking-history-filter"><label for="history-status">Trạng thái<select id="history-status" name="trang_thai"><option value="">Tất cả lịch</option>@foreach($trangThai as $value=>$label)<option value="{{ $value }}" @selected(request('trang_thai')===$value)>{{ $label }}</option>@endforeach</select></label><button class="btn btn-small" type="submit">Lọc lịch</button></form>
    <div class="live-table-wrap"><table class="live-table"><thead><tr><th>Mã</th><th>Dịch vụ</th><th>Thời gian</th><th>Trạng thái</th><th>Chi tiết</th></tr></thead><tbody>
    @forelse($lichCuaToi as $lh)
        <tr id="lich-{{ $lh->ma_lich_hen }}" @class(['booking-just-created'=>session('dat_lich_ma')===$lh->ma_lich_hen])><td>#{{ $lh->ma_lich_hen }}</td><td>{{ $lh->ten_dich_vu_luc_dat }}</td><td>{{ \Carbon\Carbon::parse($lh->thoi_diem_bat_dau)->format('d/m/Y H:i') }}<small>đến {{ \Carbon\Carbon::parse($lh->thoi_diem_ket_thuc)->format('H:i') }}</small></td><td><span class="live-badge {{ $lh->trang_thai }}">{{ $trangThai[$lh->trang_thai] ?? $lh->trang_thai }}</span></td><td>
            <details class="booking-history-details"><summary>Xem chi tiết</summary><div><p><strong>Hình thức:</strong> {{ $lh->ma_goi_da_mua?'Sử dụng gói #'.$lh->ma_goi_da_mua:'Dịch vụ lẻ' }}</p><p><strong>Giá tại lúc đặt:</strong> {{ number_format($lh->gia_dich_vu_luc_dat,0,',','.') }} đ @if($lh->ma_goi_da_mua)(không thu lại buổi thuộc gói)@endif</p><p><strong>Ghi chú của bạn:</strong> {{ $lh->ghi_chu_khach_hang ?: 'Chưa có' }}</p>@if(in_array($lh->trang_thai,['confirmed','completed']))<p><strong>Nhân viên phục vụ:</strong> {{ $lh->nhan_vien_phuc_vu ?: 'Spa sẽ thông báo' }}</p>@endif
            @if(in_array($lh->trang_thai,['pending','confirmed']))<p><a href="{{ route('lich.lich-su',$lh->ma_lich_hen) }}">Lịch sử đổi lịch</a></p><details><summary>Đổi ngày giờ</summary><form method="POST" action="{{ route('lich.doi', $lh->ma_lich_hen) }}">@csrf<input type="hidden" name="phien_ban" value="{{ $lh->phien_ban }}"><label>Ngày mới<input type="date" name="ngay" min="{{ now()->toDateString() }}" required></label><label>Giờ mới<input type="time" name="gio" required></label><label>Lý do<input name="ly_do" maxlength="1000" required></label><p>Khách tự đổi cần spa xác nhận lại nhân viên và giường.</p><button class="live-btn">Gửi yêu cầu đổi lịch</button></form></details>@endif
            @if($lh->trang_thai==='pending')<form method="POST" action="{{ route('dat-lich.huy', $lh->ma_lich_hen) }}">@csrf<label for="cancel-reason-{{ $lh->ma_lich_hen }}">Lý do rút yêu cầu<input id="cancel-reason-{{ $lh->ma_lich_hen }}" name="ly_do" required maxlength="500"></label><button class="btn btn-small" type="submit">Rút yêu cầu này</button></form>@else<p class="live-hint">Liên hệ spa nếu bạn cần hỗ trợ về lịch này.</p>@endif
            </div></details></td></tr>
    @empty
        <tr><td colspan="5" class="live-empty">Chưa có lịch phù hợp. Bạn có thể đổi bộ lọc hoặc gửi yêu cầu mới ở phía trên.</td></tr>
    @endforelse
    </tbody></table></div><div class="live-pagination">{{ $lichCuaToi->links() }}</div>
</section>
<script>
document.addEventListener('DOMContentLoaded',()=>{const s=document.querySelector('#booking-service'),d=document.querySelector('#booking-date'),t=document.querySelector('#booking-time'),e=document.querySelector('#booking-employee'),m=document.querySelector('#employee-message');if(!e)return;let seq=0;async function load(){const n=++seq;e.replaceChildren(new Option('Spa sắp xếp nhân viên',''));if(!s.value||!d.value||!t.value)return;e.disabled=true;try{const r=await fetch('{{ route('dat-lich.nhan-vien') }}?'+new URLSearchParams({ma_dich_vu:s.value,ngay:d.value,gio:t.value}),{headers:{Accept:'application/json'}});if(!r.ok)throw Error();const rows=await r.json();if(n!==seq)return;rows.forEach(v=>e.add(new Option(v.ten_nguoi_dung,v.ma_nguoi_dung)));m.textContent=rows.length?'Nhân viên có ca và chưa trùng lịch ở thời điểm tra cứu.':'Chưa có nhân viên phù hợp, vui lòng chọn giờ khác.';}catch{if(n===seq)m.textContent='Chưa tải được danh sách nhân viên. Vui lòng thử lại.';}finally{if(n===seq)e.disabled=false;}}[s,d,t].forEach(x=>x.addEventListener('change',load));document.addEventListener('click',ev=>{if(ev.target.closest('.booking-slot'))setTimeout(load,0);});load();});
</script>
@endsection
