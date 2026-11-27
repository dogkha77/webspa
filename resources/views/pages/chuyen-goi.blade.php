@extends(auth()->user()->vai_tro === 'customer' ? 'layouts.spa' : 'layouts.van-hanh')
@section('title','Chuyển gói liệu trình')
@section('content')
<section class="container spa-transactions" style="padding-top:40px;padding-bottom:60px">
<p><a href="{{ route('tai-khoan') }}">← Tài khoản</a> @if($actor->vai_tro==='admin') · <a href="{{ route('admin.trang',['muc'=>'chuyen-goi']) }}">Quản trị</a>@endif</p>
<h1>Chuyển gói liệu trình đang dùng</h1>
<p>Giữ lịch sử đã sử dụng, quy đổi phần còn lại và thu riêng tiền chênh lệch. Không hoàn tiền, giữ dư hoặc chuyển sang người khác.</p>
@if($errors->any())<div role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
@if($actor->vai_tro!=='employee' || $actor->co_quyen_tiep_nhan)
<section class="card"><h2>Đề nghị chuyển gói</h2><form method="POST" action="{{ route('chuyen-goi.tao') }}">@csrf
<label>Gói nguồn<select name="ma_goi_nguon" required><option value="">Chọn gói còn buổi</option>@foreach($cacGoi as $g)<option value="{{ $g->ma_goi_da_mua }}">#{{ $g->ma_goi_da_mua }} · {{ $g->khach_hang }} · {{ $g->ten_goi_luc_mua }} · đã dùng {{ $g->so_buoi_da_dung }}/{{ $g->tong_so_buoi }} buổi</option>@endforeach</select></label>
<label>Gói muốn chuyển đến<select name="ma_mau_goi_dich" required><option value="">Chọn mẫu gói</option>@foreach($mauGoi as $g)<option value="{{ $g->ma_goi_lieu_trinh }}">{{ $g->ten_goi }} · {{ $g->tong_so_buoi }} buổi · {{ number_format($g->gia,0,',','.') }} đ trước ưu đãi</option>@endforeach</select></label>
<label>Lý do<textarea name="ly_do" required maxlength="2000" rows="3"></textarea></label><button class="btn">Gửi yêu cầu chuyển gói</button></form></section>
@endif
<h2>Theo dõi và xử lý yêu cầu</h2>
@forelse($yeuCau as $cg)
<article class="card" style="margin-bottom:24px"><h3>Yêu cầu #{{ $cg->ma_chuyen_goi }} · {{ $cg->khach_hang }}</h3>
<p>Gói nguồn #{{ $cg->ma_goi_nguon }} → {{ $cg->ten_goi_dich_luc_duyet ?? $cg->ten_mau_hien_tai }}</p>
<p>Trạng thái: <strong>{{ ['pending'=>'Chờ duyệt','awaiting_payment'=>'Chờ thu chênh lệch','completed'=>'Đã hoàn tất','rejected'=>'Đã từ chối hoặc rút'][$cg->trang_thai] }}</strong></p><p>Lý do: {{ $cg->ly_do_de_nghi }}</p>
@php($bg=$baoGia[$cg->ma_chuyen_goi] ?? null)
@if($cg->trang_thai==='pending' && $bg)
<p>Báo giá hiện tại: giá trị chuyển <strong>{{ number_format($bg['V'],0,',','.') }} đ</strong>; gói mới sau ưu đãi <strong>{{ number_format($bg['P'],0,',','.') }} đ</strong>; cần trả thêm <strong>{{ number_format($bg['D'],0,',','.') }} đ</strong>.</p><p>Giá trị có thể đổi nếu sử dụng thêm buổi hoặc cập nhật giá trước lúc duyệt.</p>
@if($actor->vai_tro==='admin')<form method="POST" action="{{ route('chuyen-goi.xu-ly',$cg->ma_chuyen_goi) }}">@csrf
<input type="hidden" name="hanh_dong" value="approve"><input type="hidden" name="phien_ban" value="{{ $cg->phien_ban }}"><input type="hidden" name="phien_ban_nguon" value="{{ $bg['phien_ban_nguon'] }}"><input type="hidden" name="gia_tri_chuyen" value="{{ $bg['V'] }}"><input type="hidden" name="gia_goi_dich" value="{{ $bg['P'] }}">
<label><input type="checkbox" name="khach_dong_y" value="1" required> Khách đã đồng ý phương án, số buổi, dịch vụ đích và khoản chênh lệch; đã xử lý hết lịch của gói cũ.</label><button class="btn" @disabled($bg['D']<0)>Duyệt chuyển gói</button></form>@endif
@elseif($cg->trang_thai!=='pending')
@if($cg->gia_tri_chuyen!==null)<p>Đã chốt: chuyển {{ number_format($cg->gia_tri_chuyen,0,',','.') }} đ; giá gói mới {{ number_format($cg->gia_goi_dich_sau_giam,0,',','.') }} đ; chênh lệch {{ number_format($cg->tien_thu_them,0,',','.') }} đ. Gói mới có {{ $cg->so_buoi_goi_dich }} buổi {{ $cg->ten_dich_vu_dich_luc_duyet }}.</p>@endif
@endif
@if($cg->trang_thai==='awaiting_payment' && ($actor->vai_tro==='admin' || ($actor->vai_tro==='employee' && $actor->co_quyen_thu_tien)))
<form method="POST" action="{{ route('chuyen-goi.xu-ly',$cg->ma_chuyen_goi) }}">@csrf<input type="hidden" name="hanh_dong" value="complete"><input type="hidden" name="phien_ban" value="{{ $cg->phien_ban }}">
<label>Phương thức<select name="phuong_thuc"><option value="cash">Tiền mặt</option><option value="bank_transfer">Chuyển khoản</option></select></label><label><input type="checkbox" name="da_nhan_du" value="1" required> Đã đối chiếu và nhận đủ {{ number_format($cg->tien_thu_them,0,',','.') }} đ</label><button class="btn">Xác nhận thu và hoàn tất chuyển</button></form>
@endif
@if(in_array($cg->trang_thai,['pending','awaiting_payment']) && ($actor->vai_tro==='admin' || ($actor->vai_tro==='customer' && $cg->trang_thai==='pending')))
<details><summary>Rút hoặc từ chối đề nghị</summary><form method="POST" action="{{ route('chuyen-goi.xu-ly',$cg->ma_chuyen_goi) }}">@csrf<input type="hidden" name="hanh_dong" value="reject"><input type="hidden" name="phien_ban" value="{{ $cg->phien_ban }}"><label>Lý do<input name="ly_do" required maxlength="2000"></label>@if($cg->trang_thai==='awaiting_payment')<label><input type="checkbox" name="chua_nhan_tien" value="1" required> Đã xác minh chưa nhận tiền. Nếu đã nhận hoặc chưa chắc chắn, giữ khóa và đối soát.</label>@endif<button class="btn">Xác nhận rút hoặc từ chối</button></form></details>@endif
@if($cg->ly_do_ket_thuc)<p>Kết luận: {{ $cg->ly_do_ket_thuc }}</p>@endif
@if($actor->vai_tro==='admin' && $cg->trang_thai==='awaiting_payment')
<details><summary>Ghi căn cứ đối soát trước hoàn tất</summary><form method="POST" action="{{ route('chuyen-goi.xu-ly',$cg->ma_chuyen_goi) }}">@csrf<input type="hidden" name="hanh_dong" value="reconcile"><input type="hidden" name="phien_ban" value="{{ $cg->phien_ban }}"><label>Mã tham chiếu nhận tiền và kết quả kiểm tra<textarea name="ly_do" required maxlength="2000"></textarea></label><p>Đã nhận tiền mà lưu lỗi: giữ khóa gói, đối soát giao dịch đã nhận và thử hoàn tất lại, không thu khách lần nữa.</p><button class="btn">Lưu căn cứ đối soát</button></form></details>@endif
<details><summary>Lịch sử xử lý</summary>@foreach($nhatKy->get($cg->ma_chuyen_goi,collect()) as $nk)<p>{{ $nk->thoi_diem_thao_tac }} · {{ ['request_transfer'=>'Đề nghị','approve'=>'Duyệt','complete'=>'Hoàn tất','reject'=>'Rút hoặc từ chối','reconcile'=>'Đối soát'][$nk->hanh_dong] ?? $nk->hanh_dong }}: {{ $nk->ly_do }}</p>@endforeach</details>
@if($cg->trang_thai==='completed')<p>Gói mới #{{ $cg->ma_goi_dich }} · Hóa đơn #{{ $cg->ma_hoa_don }} @if($actor->vai_tro==='admin' || $actor->co_quyen_thu_tien)<a href="{{ route('admin.hoa-don.xuat',$cg->ma_hoa_don) }}">Xem và in hóa đơn</a>@endif</p>@endif
</article>
@empty<p>Chưa có yêu cầu chuyển gói.</p>@endforelse
{{ $yeuCau->links() }}
</section>
@endsection
