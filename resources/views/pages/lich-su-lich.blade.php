@extends(auth()->user()->vai_tro === 'customer' ? 'layouts.spa' : 'layouts.van-hanh')
@section('title','Lịch sử đổi lịch')
@section('content')
<section class="container" style="padding-top:40px;padding-bottom:60px"><h1>Lịch sử đổi lịch #{{ $lich->ma_lich_hen }}</h1><p>{{ $lich->ten_dich_vu_luc_dat }}</p>
@forelse($nhatKy as $nk)@php($cu=json_decode($nk->du_lieu_truoc,true))@php($moi=json_decode($nk->du_lieu_sau,true))
<article class="card"><h2>{{ \Carbon\Carbon::parse($nk->thoi_diem_thao_tac)->format('d/m/Y H:i') }}</h2><p>Giờ cũ: {{ $cu['bat_dau'] }} → {{ $cu['ket_thuc'] }}</p><p>Giờ mới: {{ $moi['bat_dau'] }} → {{ $moi['ket_thuc'] }}</p><p>Lý do: {{ $nk->ly_do }}</p><p>Phiên bản lịch sau đổi: {{ $nk->phien_ban_lich }}</p></article>
@empty<p>Chưa có lần đổi lịch.</p>@endforelse</section>
@endsection
