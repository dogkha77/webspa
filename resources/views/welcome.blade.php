@extends('layouts.spa')
@section('title', 'Trang chủ')
@section('body-class', 'home-page')
@php
    $bookingUrl = route('dat-lich');
    $questions = [
        ['question' => 'Tôi có cần tài khoản để đặt lịch không?', 'answer' => 'Có. Bạn cần đăng ký và đăng nhập để gửi yêu cầu đặt lịch. Nếu cần hỗ trợ, bạn có thể liên hệ trực tiếp với spa.'],
        ['question' => 'Gửi yêu cầu có đồng nghĩa lịch đã được xác nhận không?', 'answer' => 'Chưa. Spa cần kiểm tra nhân viên, giường và thời gian phục vụ trước khi xác nhận lịch với bạn.'],
        ['question' => 'Tôi có thể hủy hoặc đổi lịch như thế nào?', 'answer' => 'Trong trang Lịch hẹn của bạn, bạn có thể rút yêu cầu chờ duyệt hoặc gửi yêu cầu đổi ngày giờ cho lịch đủ điều kiện.'],
        ['question' => 'Tôi xem số buổi liệu trình còn lại ở đâu?', 'answer' => 'Khi đặt lịch, bạn có thể chọn gói đủ điều kiện và xem số buổi còn lại. Spa hỗ trợ đối chiếu lịch sử sử dụng khi cần.'],
        ['question' => 'Spa hỗ trợ thanh toán bằng cách nào?', 'answer' => 'Spa hỗ trợ tiền mặt hoặc chuyển khoản. Nhân viên sẽ kiểm tra và xác nhận thanh toán; website không thu tiền trực tuyến.'],
    ];
@endphp

@section('content')

    <section id="trang-chu" class="hero section-anchor">
        <div class="container hero-grid">
            <div class="hero-copy">
                <span class="pill"><x-spa-icon name="leaf" size="16"/>Nơi Thân · Tâm · Trí Giao Hòa</span>
                <h1>Dành một khoảng lặng để chăm sóc bản thân.</h1>
                <p class="hero-description">Khám phá dịch vụ chăm sóc và thư giãn, xem giá rõ ràng và chọn thời gian phù hợp với bạn.</p>
                <div class="button-row"><a class="btn" href="{{ $bookingUrl }}">Đặt lịch <x-spa-icon name="calendar" size="18"/></a><a class="btn btn-soft" href="{{ route('dich-vu') }}">Khám phá dịch vụ</a></div>
                <p class="small-note"><x-spa-icon name="clock" size="16"/>Spa sẽ kiểm tra và xác nhận sau khi bạn gửi yêu cầu.</p>
                <div class="hero-assurances"><span>Thời gian linh hoạt</span><span>Không gian riêng tư yên tĩnh</span><span>Tư vấn tận tâm</span></div>
            </div>
            <figure class="hero-photo"><img src="{{ asset('images/spa/hero.jpg') }}" alt="Làn hơi nước nhẹ bên bát trà và đá phủ rêu trong không gian spa" fetchpriority="high" width="830" height="913"><figcaption><x-spa-icon name="flower" size="18"/>Không gian tĩnh lặng chuẩn mực</figcaption></figure>
        </div>
    </section>

    <section id="dich-vu" class="services section section-anchor">
        <div class="container">
            <div class="section-heading"><p class="kicker">Dịch Vụ Nổi Bật</p><h2>Chọn dịch vụ phù hợp với bạn</h2><p>Minh bạch về thời gian và chi phí, giúp bạn dễ dàng chọn liệu trình vừa vặn nhất cho cơ thể.</p></div>
            <div class="service-grid">
                @forelse ($services as $service)
                    <article class="service-card">
                        <p>{{ $service['duration'] }} phút</p>
                        <h3>{{ $service['name'] }}</h3><p>{{ $service['description'] }}</p>
                        <div class="service-price"><span>CHI PHÍ</span><strong>{{ $service['price'] }} <u>đ</u></strong></div>
                        <a class="btn" href="{{ route('dat-lich', ['dich_vu'=>$service['id']]) }}">Đặt lịch</a>
                    </article>
                @empty<p class="live-empty">Chưa có dịch vụ đang cung cấp.</p>
                @endforelse
            </div>
            <a class="text-link services-link" href="{{ route('goi-lieu-trinh') }}">Khám phá gói liệu trình <x-spa-icon name="arrow" size="18"/></a>
        </div>
    </section>

    <section id="quy-trinh" class="process section section-anchor">
        <div class="container"><div class="process-panel">
            <div class="section-heading"><h2>Quy trình đặt lịch tại An Nhiên Spa</h2><p>Minh bạch và thảnh thơi trong từng thao tác nhỏ nhất.</p><span class="pill pill-white"><x-spa-icon name="clock" size="17"/>Bạn cần đăng nhập để gửi yêu cầu đặt lịch.</span></div>
            <div class="process-grid">
                <article><span class="step-number">01</span><h3>Chọn dịch vụ</h3><p>Duyệt danh mục và chọn liệu trình chăm sóc cơ thể hay da mặt phù hợp với nhu cầu hiện tại.</p></article>
                <article><span class="step-number">02</span><h3>Chọn ngày giờ & gửi yêu cầu</h3><p>Lựa chọn khung giờ mong muốn để trải nghiệm và gửi phiếu thông tin hẹn tới spa.</p></article>
                <article><span class="step-number">03</span><h3>Theo dõi xác nhận từ spa</h3><p>Nhân viên spa đối chiếu lịch trống và gửi thông báo xác nhận lịch hẹn chính thức đến bạn.</p></article>
            </div>
            <p class="process-note"><x-spa-icon name="shield" size="21"/><span>Bạn có thể chọn nhân viên đang có ca trống. Spa phân công giường và xác nhận lịch sau khi kiểm tra yêu cầu.</span></p>
        </div></div>
    </section>

    <section id="goi-lieu-trinh" class="packages section section-anchor">
        <div class="container">
            <div class="section-heading"><p class="kicker">Gói Liệu Trình Dài Lâu</p><h2>Chăm sóc định kỳ để cân bằng thân tâm</h2><p>Duy trì trạng thái cân bằng và tái tạo năng lượng mỗi tuần với giải pháp tiết kiệm chu toàn.</p></div>
            <div class="package-grid">
                @forelse ($packages as $package)
                    <article class="package-card">
                        <span class="package-tag">{{ $package['tag'] }}</span><h3>{{ $package['name'] }}</h3><p class="package-subtitle">{{ $package['subtitle'] }}</p><div class="package-divider"></div>
                        <ul>@foreach ($package['features'] as $feature)<li><x-spa-icon name="check" size="17"/><span>{{ $feature }}</span></li>@endforeach</ul>
                        <div class="package-price"><span>GIÁ GÓI</span><strong>{{ $package['price'] }} <u>đ</u></strong></div>
                        <a class="btn btn-soft" href="{{ route('goi-lieu-trinh') }}">Xem và đăng ký gói</a>
                    </article>
                @empty<p class="live-empty">Chưa có gói đang bán.</p>
                @endforelse
            </div>
            <div class="package-note"><p>Bạn có thể gửi đề nghị mua gói trực tuyến. Spa xác nhận thanh toán trước khi bạn đặt lịch bằng gói.</p><a href="{{ route('goi-cua-toi') }}">Xem gói của tôi <x-spa-icon name="arrow" size="16"/></a></div>
        </div>
    </section>

    <section id="ve-spa" class="about section section-anchor">
        <div class="container about-grid">
            <div class="about-copy"><span class="pill"><x-spa-icon name="flower" size="16"/>Không gian an trú</span><h2>Chốn dừng chân nhẹ nhàng giữa lòng phố thị</h2>
                <p>Bước qua cánh cửa An Nhiên Spa, bạn sẽ được đón chào bằng tách trà thảo mộc ấm dịu thơm hương gừng và hoa cúc. Không gian ngập tràn thoáng thoảng mùi hương sả chanh tự nhiên, xua tan ngay những nhọc nhằn, khói bụi sau những giờ làm việc căng thẳng.</p>
                <p>Từng chiếc khăn bông hữu cơ mềm mịn, ánh sáng tự nhiên len lỏi qua rèm tre mộc mạc và thanh âm thiền định không lời giúp bạn hoàn toàn an tâm thả lỏng để thân thể được lắng nghe và phục hồi trọn vẹn.</p>
                <div class="about-features"><div><x-spa-icon name="cup" size="24"/><h3>Trà thảo mộc tiếp đón</h3><p>Hương thiên nhiên ấm êm trước mỗi liệu trình</p></div><div><x-spa-icon name="sparkle" size="24"/><h3>Âm nhạc thiền định</h3><p>Không tạp âm, chỉ có sự bình yên tĩnh lặng</p></div></div>
            </div>
            <div class="about-photos"><figure><img src="{{ asset('images/spa/tea.jpg') }}" alt="Tách trà thảo mộc ấm được rót để chào đón khách" loading="lazy" width="483" height="639"><figcaption>“Thân an, tâm tự tại”</figcaption></figure><img class="room-photo" src="{{ asset('images/spa/space.jpg') }}" alt="Không gian spa ngập ánh nắng qua rèm cửa" loading="lazy" width="478" height="639"></div>
        </div>
    </section>

    <section id="cau-hoi" class="faq section section-anchor"><div class="container">
        <div class="section-heading"><p class="kicker">Giải đáp thắc mắc</p><h2>Câu hỏi thường gặp</h2></div>
        <div class="faq-list">@foreach ($questions as $question)<details><summary>{{ $question['question'] }}<x-spa-icon name="chevron" size="20"/></summary><p>{{ $question['answer'] }}</p></details>@endforeach</div>
    </div></section>

    <section class="cta section"><div class="container"><div class="cta-panel"><span class="cta-symbol"><x-spa-icon name="flower" size="27"/></span><h2>Hãy dành cho bản thân một buổi chiều thư thái trọn vẹn tại An Nhiên Spa.</h2><p>Một khoảng lặng êm đềm với trà thơm, thảo mộc ấm và sự nâng niu dịu dàng dành cho bạn.</p><div class="button-row"><a class="btn" href="{{ $bookingUrl }}">Đặt lịch ngay <x-spa-icon name="calendar" size="18"/></a><a class="btn btn-white" href="{{ route('lien-he') }}">Hỗ trợ đặt lịch</a></div></div></div></section>

@endsection
