const menuButton = document.querySelector('.mobile-toggle');
const mobileNavigation = document.getElementById('mobile-navigation');

if (menuButton && mobileNavigation) {
    const closeMenu = () => {
        menuButton.setAttribute('aria-expanded', 'false');
        menuButton.setAttribute('aria-label', 'Mở menu');
        mobileNavigation.hidden = true;
    };

    menuButton.addEventListener('click', () => {
        const expanded = menuButton.getAttribute('aria-expanded') === 'true';
        menuButton.setAttribute('aria-expanded', String(!expanded));
        menuButton.setAttribute('aria-label', expanded ? 'Mở menu' : 'Đóng menu');
        mobileNavigation.hidden = expanded;
    });

    mobileNavigation.querySelectorAll('a').forEach((link) => link.addEventListener('click', closeMenu));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeMenu();
        }
    });
    window.matchMedia('(min-width: 901px)').addEventListener('change', (event) => {
        if (event.matches) {
            closeMenu();
        }
    });
}
