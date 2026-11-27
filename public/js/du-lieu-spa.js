document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('booking-form');
    if (form) {
        const service = form.querySelector('#booking-service');
        const packageSelect = form.querySelector('#booking-package');
        const status = document.getElementById('booking-slot-status');
        const slots = document.getElementById('booking-slots');
        const submit = form.querySelector('[data-submit-booking]');
        let loading = false;
        let sending = false;
        let sequence = 0;
        let pendingRequest;
        const update = () => {
            const option = service.selectedOptions[0];
            for (const item of packageSelect.options) {
                item.hidden = !!item.value && item.dataset.service !== service.value;
                item.disabled = item.hidden;
            }
            if (packageSelect.selectedOptions[0]?.disabled) packageSelect.value = '';
            form.elements.gia_da_xem.value = service.value ? option.dataset.price : '';
            form.elements.thoi_luong_da_xem.value = service.value ? option.dataset.duration : '';
            document.getElementById('summary-name').textContent = form.elements.ho_ten.value || 'Chưa nhập';
            document.getElementById('summary-service').textContent = service.value ? option.textContent : 'Chưa chọn';
            document.getElementById('summary-duration').textContent = service.value ? `${option.dataset.duration} phút` : '—';
            document.getElementById('summary-date').textContent = [form.elements.ngay.value.split('-').reverse().join('/'), form.elements.gio.value].filter(Boolean).join(' · ') || 'Chưa chọn';
            document.getElementById('summary-price').textContent = packageSelect.value ? 'Trong gói đã mua' : (service.value ? new Intl.NumberFormat('vi-VN').format(Number(option.dataset.price)) + ' đ' : '—');
            document.getElementById('summary-payment').textContent = packageSelect.value ? 'Không thu lại tiền buổi thuộc gói. Chỉ trừ buổi khi phục vụ hoàn thành.' : 'Giá dịch vụ trước ưu đãi nếu có. Thanh toán được nhân viên xác nhận riêng.';
            submit.disabled = sending || loading || !service.value;
            for (const button of slots?.querySelectorAll('button') || []) {
                button.setAttribute('aria-pressed', String(button.dataset.time === form.elements.gio.value));
            }
        };
        const loadSlots = async () => {
            const current = ++sequence;
            pendingRequest?.abort();
            slots?.replaceChildren();
            if (!service.value || !form.elements.ngay.value || !form.elements.ngay.validity.valid) {
                loading = false;
                if (status) status.textContent = 'Chọn dịch vụ và ngày hợp lệ để xem giờ gợi ý.';
                update();
                return;
            }
            if (!form.dataset.availabilityUrl || !status || !slots) return;
            pendingRequest = new AbortController();
            const url = new URL(form.dataset.availabilityUrl, location.origin);
            url.searchParams.set('ma_dich_vu', service.value);
            url.searchParams.set('ngay', form.elements.ngay.value);
            if (packageSelect.value) url.searchParams.set('ma_goi_da_mua', packageSelect.value);
            loading = true;
            status.textContent = 'Đang kiểm tra giờ theo ca làm và giường trống…';
            update();
            try {
                const response = await fetch(url, {headers: {'Accept': 'application/json'}, signal: pendingRequest.signal});
                if (response.redirected || !response.headers.get('content-type')?.includes('application/json')) throw new Error('session');
                const data = await response.json();
                if (current !== sequence) return;
                if (!response.ok) {
                    status.textContent = Object.values(data.errors || {}).flat()[0] || 'Chưa kiểm tra được giờ. Vui lòng xem lại dịch vụ và ngày.';
                    return;
                }
                service.selectedOptions[0].dataset.price = data.gia;
                service.selectedOptions[0].dataset.duration = data.thoi_luong_phut;
                status.textContent = data.thong_bao;
                for (const slot of data.khung_gio) {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'booking-slot';
                    button.dataset.time = slot.gio;
                    button.textContent = slot.gio;
                    button.setAttribute('aria-label', `Từ ${slot.gio} đến ${slot.ket_thuc}`);
                    button.addEventListener('click', () => { form.elements.gio.value = slot.gio; update(); });
                    slots.append(button);
                }
            } catch (error) {
                if (error.name !== 'AbortError' && current === sequence) {
                    status.textContent = 'Chưa tải được giờ gợi ý. Bạn có thể nhập giờ mong muốn; hệ thống sẽ kiểm tra lại khi gửi. Nếu hết phiên, vui lòng đăng nhập lại.';
                }
            } finally {
                if (current === sequence) { loading = false; update(); }
            }
        };
        form.addEventListener('input', update);
        for (const input of [service, packageSelect, form.elements.ngay]) {
            input.addEventListener('change', () => { update(); loadSlots(); });
        }
        form.addEventListener('submit', event => {
            if (loading || sending || !form.reportValidity()) { event.preventDefault(); return; }
            sending = true;
            submit.disabled = true;
            submit.textContent = 'Đang gửi yêu cầu…';
        });
        window.addEventListener('pageshow', event => {
            if (event.persisted) { sending = false; submit.textContent = 'Gửi yêu cầu đặt lịch →'; update(); loadSlots(); }
        });
        update();
        loadSlots();
    }
    const toggle = document.querySelector('[data-work-menu]');
    const sidebar = document.querySelector('.live-sidebar');
    const scrim = document.querySelector('[data-work-scrim]');
    const setMenu = (open) => {
        sidebar?.classList.toggle('is-open', open);
        if (sidebar) sidebar.inert = innerWidth <= 760 && !open;
        toggle?.setAttribute('aria-expanded', String(open));
        if (scrim) scrim.hidden = !open;
    };
    if (sidebar) sidebar.inert = innerWidth <= 760;
    window.addEventListener('resize', () => { if (sidebar) sidebar.inert = innerWidth <= 760 && !sidebar.classList.contains('is-open'); });
    toggle?.addEventListener('click', () => setMenu(toggle.getAttribute('aria-expanded') !== 'true'));
    scrim?.addEventListener('click', () => setMenu(false));
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') setMenu(false); });
    document.querySelector('[data-print-invoice]')?.addEventListener('click', () => window.print());

    const notifications = document.querySelector('[data-admin-bookings]');
    if (!notifications) return;

    const badge = notifications.querySelector('[data-booking-count]');
    const list = notifications.querySelector('[data-booking-notifications]');
    const status = notifications.querySelector('[data-booking-status]');
    const alert = document.querySelector('[data-booking-alert]');
    const storageKey = `spa-admin-${notifications.dataset.adminId}-last-seen-booking`;
    const labels = {pending: 'Chờ duyệt', confirmed: 'Đã xác nhận', completed: 'Hoàn thành', cancelled: 'Đã hủy', rejected: 'Từ chối'};
    let lastSeen = null;
    let latest = 0;
    let previous = '';
    let stop = false;

    try {
        const saved = localStorage.getItem(storageKey);
        if (saved !== null) lastSeen = Number(saved);
    } catch {
        // Vẫn hiển thị thông báo nếu trình duyệt chặn bộ nhớ cục bộ.
    }

    const markSeen = () => {
        if (lastSeen === null || latest > lastSeen) {
            lastSeen = latest;
            try {
                localStorage.setItem(storageKey, String(lastSeen));
            } catch {
                // Trạng thái đã xem vẫn được giữ trong trang đang mở.
            }
        }
        badge.hidden = true;
        alert.hidden = true;
    };

    notifications.addEventListener('toggle', () => {
        if (notifications.open && previous) markSeen();
    });

    async function refreshBookings() {
        try {
            const response = await fetch(notifications.dataset.adminBookings, {
                headers: {'Accept': 'application/json'},
                cache: 'no-store',
                signal: AbortSignal.timeout(5000),
            });
            if (response.redirected || [401, 403].includes(response.status)) {
                stop = true;
                badge.hidden = true;
                alert.hidden = true;
                list.replaceChildren();
                status.hidden = false;
                status.textContent = 'Phiên quản trị đã hết hoặc quyền truy cập đã thay đổi.';
                return;
            }
            if (!response.ok) throw new Error('Không tải được thông báo');
            const bookings = await response.json();
            latest = bookings[0]?.ma_lich_hen ?? 0;
            if (lastSeen === null || notifications.open) markSeen();

            const snapshot = JSON.stringify(bookings);
            if (snapshot !== previous) {
                list.replaceChildren(...bookings.map(booking => {
                    const item = document.createElement('li');
                    const link = document.createElement('a');
                    const details = document.createElement('small');
                    link.href = booking.url;
                    link.textContent = `#${booking.ma_lich_hen} · ${booking.dich_vu}`;
                    details.textContent = `${booking.gio_hen} · ${labels[booking.trang_thai] ?? booking.trang_thai}`;
                    item.append(link, details);
                    return item;
                }));
                previous = snapshot;
            }

            status.hidden = bookings.length > 0;
            status.textContent = 'Chưa có lịch đặt trực tuyến.';
            const unread = bookings.filter(booking => booking.ma_lich_hen > lastSeen).length;
            badge.textContent = String(unread);
            badge.hidden = unread === 0;
            const message = 'Có yêu cầu đặt lịch trực tuyến mới. Mở Thông báo để xem.';
            if (unread && alert.textContent !== message) alert.textContent = message;
            alert.hidden = unread === 0;
        } catch {
            status.hidden = false;
            status.textContent = 'Chưa tải được thông báo. Hệ thống đang thử lại.';
        } finally {
            if (!stop) window.setTimeout(refreshBookings, 2000);
        }
    }

    refreshBookings();
});
