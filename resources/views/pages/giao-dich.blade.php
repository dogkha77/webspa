@extends('layouts.van-hanh')
@section('title','Bán gói và thu tiền')
@section('content')
<section class="container spa-transactions" style="padding-top:40px;padding-bottom:60px"><p><a href="{{ route('tai-khoan') }}">← Tài khoản</a> · <a href="{{ route('chuyen-goi') }}">Thu chênh lệch chuyển gói</a></p><h1>Bán gói và thu tiền</h1>
@foreach($errors->all() as $error)<p role="alert">{{ $error }}</p>@endforeach
@if($coTheNhan)<section class="card"><h2>Tiếp nhận lịch tại quầy</h2><form method="POST" action="{{ route('giao-dich.tiep-nhan') }}">@csrf
<input type="hidden" name="khoa_yeu_cau" value="{{ (string)\Illuminate\Support\Str::uuid() }}">
<label>Khách đã có hồ sơ<select name="ma_khach_hang"><option value="">Tạo hồ sơ khách vãng lai bên dưới</option>@foreach($khach as $k)<option value="{{ $k->ma_nguoi_dung }}">#{{ $k->ma_nguoi_dung }} · {{ $k->ten_nguoi_dung }}</option>@endforeach</select></label>
<details><summary>Thông tin khách mới nếu chưa có hồ sơ</summary><label>Họ tên<input name="ten_khach_moi" maxlength="150"></label><label>Điện thoại<input name="dien_thoai_moi" type="tel"></label><label>Email nếu có<input name="email_moi" type="email"></label><p>Hồ sơ vãng lai không có mật khẩu hoặc quyền đăng nhập.</p></details>
<label>Dịch vụ<select name="ma_dich_vu" required>@foreach($dichVu as $dv)<option value="{{ $dv->ma_dich_vu }}">{{ $dv->ten_dich_vu }}</option>@endforeach</select></label>
<label>Gói đã mua nếu sử dụng<select name="ma_goi_da_mua"><option value="">Dịch vụ lẻ</option>@foreach($goiDaMua as $g)<option value="{{ $g->ma_goi_da_mua }}">#{{ $g->ma_goi_da_mua }} · Khách #{{ $g->ma_khach_hang }} · {{ $g->ten_goi_luc_mua }}</option>@endforeach</select></label>
<label>Ngày<input type="date" name="ngay" min="{{ now()->toDateString() }}" required></label><label>Giờ<input type="time" name="gio" required></label>
<label>Nhân viên<select name="ma_nhan_vien"><option value="">Chưa phân công</option>@foreach($nhanVien as $nv)<option value="{{ $nv->ma_nguoi_dung }}">{{ $nv->ten_nguoi_dung }}</option>@endforeach</select></label>
<label>Phòng và giường<select name="ma_giuong"><option value="">Chưa xếp giường</option>@foreach($giuong as $g)<option value="{{ $g->ma_giuong }}">{{ $g->ten_phong }} · {{ $g->ten_giuong }}</option>@endforeach</select></label>
<label>Ghi chú<textarea name="ghi_chu" maxlength="2000"></textarea></label><p>Khi chọn nhân viên hoặc giường, hệ thống kiểm tra ca và lịch trùng trước khi lưu. Xác nhận cần chọn đủ cả hai. Lịch chờ chưa giữ chỗ hoặc trừ buổi gói.</p><button class="btn" name="xac_nhan_ngay" value="0">Lưu lịch chờ</button><button class="btn" name="xac_nhan_ngay" value="1">Kiểm tra và xác nhận lịch</button></form></section>@endif
@if($coTheThu)
<section class="card">
    <h2>Đề nghị mua gói từ khách</h2>
    @forelse($deNghiMua as $dn)
        <form method="POST" action="{{ route('giao-dich.mua-goi') }}">
            @csrf
            <input type="hidden" name="ma_de_nghi" value="{{ $dn->ma_de_nghi }}">
            <p>{{ $dn->ten_nguoi_dung }} · Khách #{{ $dn->ma_khach_hang }} · {{ $dn->ten_goi }} · {{ number_format($dn->gia, 0, ',', '.') }} đ trước ưu đãi · {{ $dn->thoi_diem_gui }}</p>
            <button class="btn">Lập lần mua và tạo hóa đơn</button>
        </form>
    @empty
        <p>Chưa có đề nghị chờ xác nhận.</p>
    @endforelse
    {{ $deNghiMua->links() }}
</section>
<section class="card"><h2>Đăng ký gói cho khách</h2><form method="POST" action="{{ route('giao-dich.mua-goi') }}">@csrf
<label>Khách hàng<select name="ma_khach_hang" required>@foreach($khach as $k)<option value="{{ $k->ma_nguoi_dung }}">#{{ $k->ma_nguoi_dung }} · {{ $k->ten_nguoi_dung }}</option>@endforeach</select></label>
<label>Gói<select name="ma_goi_lieu_trinh" required>@foreach($goi as $g)<option value="{{ $g->ma_goi_lieu_trinh }}">{{ $g->ten_goi }} · {{ number_format($g->gia,0,',','.') }} đ trước ưu đãi</option>@endforeach</select></label><p>Hệ thống áp dụng hạng thành viên đang hoạt động khi lập hóa đơn và lưu mức ưu đãi tại thời điểm đó.</p><button class="btn">Tạo gói và hóa đơn chờ thanh toán</button></form></section>
<h2>Lịch dịch vụ lẻ chờ lập hóa đơn</h2>@forelse($lichLe as $lh)<form method="POST" action="{{ route('admin.hoa-don.lap',$lh->ma_lich_hen) }}">@csrf<p>#{{ $lh->ma_lich_hen }} · {{ $lh->khach_hang }} · {{ $lh->ten_dich_vu_luc_dat }} <button class="btn">Lập hóa đơn dịch vụ lẻ</button></p></form>@empty<p>Không có lịch lẻ đã hoàn thành cần lập hóa đơn.</p>@endforelse
<h2>Hóa đơn chưa thanh toán</h2>@forelse($hoaDon as $hd)<section class="card" style="margin-bottom:24px"><h3>{{ $hd->so_hoa_don }} · {{ $hd->khach_hang }}</h3><p>{{ $hd->ten_noi_dung_luc_lap }} · Ưu đãi {{ number_format($hd->tien_giam,0,',','.') }} đ · Cần thu <strong>{{ number_format($hd->tien_can_thu,0,',','.') }} đ</strong></p><a href="{{ route('admin.hoa-don.xuat',$hd->ma_hoa_don) }}">Xem hóa đơn</a>
@if($hd->trang_thai_xu_ly!=='error')<form method="POST" action="{{ route('admin.hoa-don.thanh-toan',$hd->ma_hoa_don) }}">@csrf<input type="hidden" name="phien_ban" value="{{ $hd->phien_ban }}"><label>Phương thức<select name="phuong_thuc">@if((float)$hd->tien_can_thu===0.0)<option value="no_charge">Không phát sinh tiền</option>@else<option value="cash">Tiền mặt</option><option value="bank_transfer">Chuyển khoản</option>@endif</select></label><label><input type="checkbox" name="xac_nhan" value="1" required> Tôi đã đối chiếu và nhận đủ số tiền cần thu.</label><button class="btn">Xác nhận đã nhận đủ tiền</button></form>@else<p>Hóa đơn có sai sót, chặn thu và cần quản trị viên đối soát.</p>@endif</section>@empty<p>Không có hóa đơn chờ thanh toán.</p>@endforelse{{ $hoaDon->links() }}@endif</section>
@endsection
