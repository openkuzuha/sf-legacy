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
