import './styles/app.css';

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-scroll-target]');
    if (!(button instanceof HTMLButtonElement)) {
        return;
    }

    if (button.dataset.scrollTarget === 'page-top') {
        window.scrollTo(0, 0);
        return;
    }

    document.getElementById(button.dataset.scrollTarget)?.scrollIntoView();
});

document.addEventListener('change', (event) => {
    const input = event.target;
    if (!(input instanceof HTMLInputElement)) {
        return;
    }

    const cookieOptions = `; Max-Age=31536000; Path=/; SameSite=Lax${window.location.protocol === 'https:' ? '; Secure' : ''}`;
    if (input.name === 'display_count' && input.validity.valid) {
        document.cookie = `bbs_display_count=${encodeURIComponent(input.value)}${cookieOptions}`;
    }
    if (input.name === 'auto_link') {
        document.cookie = `bbs_auto_link=${input.checked ? '1' : '0'}${cookieOptions}`;
    }
});
