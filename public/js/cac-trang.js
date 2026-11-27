document.querySelectorAll('[data-password]').forEach((button) => {
    button.addEventListener('click', () => {
        const input = document.getElementById(button.dataset.password);
        if (!input) return;
        const reveal = input.type === 'password';
        input.type = reveal ? 'text' : 'password';
        button.setAttribute('aria-pressed', String(reveal));
        button.setAttribute('aria-label', reveal ? 'Ẩn mật khẩu' : 'Hiện mật khẩu');
    });
});

const filterButtons = document.querySelectorAll('[data-filter]');
const serviceCards = document.querySelectorAll('[data-service-category]');
const resultCount = document.getElementById('service-count');
filterButtons.forEach((button) => {
    button.addEventListener('click', () => {
        filterButtons.forEach((filter) => {
            const active = filter === button;
            filter.classList.toggle('is-active', active);
            filter.setAttribute('aria-pressed', String(active));
        });
        let count = 0;
        serviceCards.forEach((card) => {
            card.hidden = button.dataset.filter !== 'all' && card.dataset.serviceCategory !== button.dataset.filter;
            if (!card.hidden) count += 1;
        });
        if (resultCount) resultCount.textContent = String(count);
    });
});

