@php
    $spec = config('bieu_mau_spa.'.$loai);
    $truongCanhBaoDanhMuc = isset($record->ma_danh_muc) ? 'ten_danh_muc_'.$record->ma_danh_muc : 'ten_danh_muc';
    $canhBaoDanhMuc = $loai === 'danh-muc' && $errors->has($truongCanhBaoDanhMuc);
@endphp
@if($spec)
<details class="live-data-form" @if($canhBaoDanhMuc) open @endif><summary>{{ isset($record)?'Chỉnh sửa':'Thêm '.$spec['title'] }}</summary><form method="POST" action="{{ isset($record)?route('admin.du-lieu.sua',['loai'=>$loai,'id'=>$record->{$spec['key']}]):route('admin.du-lieu.them',['loai'=>$loai]) }}">@csrf
@if($loai === 'nguoi-dung' && isset($record))
    <input type="hidden" name="phien_ban" value="{{ $record->phien_ban }}">
@endif
<div class="live-form-grid">
@foreach($spec['fields'] as $field=>[$label,$type])
@php($value=isset($record)?($record->$field ?? ''):($field==='vai_tro'?($defaultRole??'customer'):($type==='boolean'?(in_array($field,['dang_hoat_dong','co_the_nhan_lich'])?1:0):'')))
@if($canhBaoDanhMuc)
    @php($value = old($field, $value))
@endif
<label class="{{ $type==='textarea'?'live-full-width':'' }}">{{ $label }}
@if(in_array($type,['boolean','category','service','room','employee','customer','package','role','membership']))
<select name="{{ $field }}" @if($type!=='membership') required @endif>@if(!in_array($type,['boolean','role','membership']))<option value="">Chọn {{ mb_strtolower($label) }}</option>@endif @foreach($luaChon[$type] ?? [] as $optionValue=>$optionText)<option value="{{ $optionValue }}" @selected((string)$value===(string)$optionValue)>{{ $optionText }}</option>@endforeach</select>
@elseif($type==='textarea')<textarea name="{{ $field }}" rows="3" maxlength="2000">{{ $value }}</textarea>
@else<input name="{{ $field }}" type="{{ $type }}" value="{{ $type==='password'?'':($type==='time'?substr($value,0,5):$value) }}" @if($type==='number') min="0" step="{{ in_array($field,['gia','ty_le_giam_gia'])?'0.01':'1' }}" @endif @if(!in_array($type,['email','tel','password'])) required @endif @if($type==='password') autocomplete="new-password" minlength="8" @elseif($type==='text') maxlength="150" @endif>
@endif</label>
@if($type==='password')<label>Nhập lại mật khẩu mới<input name="password_confirmation" type="password" autocomplete="new-password" minlength="8"></label>@endif
@endforeach
@if($loai === 'nguoi-dung')
    <label class="live-full-width">Lý do thay đổi
        <textarea name="ly_do" rows="3" maxlength="2000" required></textarea>
    </label>
@endif
@if($canhBaoDanhMuc)
    <label class="live-full-width">
        <input class="live-checkbox" type="checkbox" name="xac_nhan_ten_danh_muc" value="1">
        Tôi đã kiểm tra danh mục gần giống và vẫn muốn lưu tên này.
    </label>
@endif
</div><button class="live-btn" type="submit">Lưu {{ $spec['title'] }}</button><p class="live-hint">Dữ liệu được lưu vào hệ thống. Thay đổi danh mục không tính lại thông tin giao dịch đã phát sinh.</p></form></details>
@endif
