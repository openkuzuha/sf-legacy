import './styles/app.css';

const colorStorageKey = 'bbs_color_settings';
const colorProperties = {
    background: '--bbs-background',
    text: '--bbs-text',
    link: '--bbs-link',
    visitedLink: '--bbs-visited-link',
    activeLink: '--bbs-active-link',
    hoverLink: '--bbs-hover-link',
    subject: '--bbs-subject',
    quote: '--bbs-quote',
};
const colorPattern = /^#[0-9a-f]{3}(?:[0-9a-f]{3})?$/i;
const rootStyle = document.documentElement.style;
const defaultColors = Object.fromEntries(
    Object.entries(colorProperties).map(([key, property]) => [key, rootStyle.getPropertyValue(property).trim()]),
);

const readColors = () => {
    try {
        const colors = JSON.parse(localStorage.getItem(colorStorageKey) ?? '{}');
        if (colors === null || typeof colors !== 'object' || Array.isArray(colors)) {
            return {};
        }

        return Object.fromEntries(Object.entries(colors).filter(
            ([key, color]) => key in colorProperties && typeof color === 'string' && colorPattern.test(color),
        ));
    } catch {
        return {};
    }
};

const applyColors = (colors) => {
    for (const [key, property] of Object.entries(colorProperties)) {
        rootStyle.setProperty(property, colors[key] ?? defaultColors[key]);
    }
};

const storedColors = readColors();
applyColors(storedColors);

const colorSettingsForm = document.querySelector('[data-color-settings]');
if (colorSettingsForm instanceof HTMLFormElement) {
    const colorInputs = colorSettingsForm.querySelectorAll('input[data-color-key]');
    for (const input of colorInputs) {
        if (input instanceof HTMLInputElement && storedColors[input.dataset.colorKey]) {
            input.value = storedColors[input.dataset.colorKey].slice(1);
        }
    }

    colorSettingsForm.addEventListener('submit', (event) => {
        event.preventDefault();
        if (!colorSettingsForm.reportValidity()) {
            return;
        }

        const colors = {};
        for (const input of colorInputs) {
            if (input instanceof HTMLInputElement) {
                colors[input.dataset.colorKey] = `#${input.value.toLowerCase()}`;
            }
        }

        try {
            localStorage.setItem(colorStorageKey, JSON.stringify(colors));
        } catch {
            return;
        }
        applyColors(colors);
        window.location.assign(colorSettingsForm.dataset.homeUrl);
    });

    colorSettingsForm.querySelector('[data-reset-colors]')?.addEventListener('click', () => {
        try {
            localStorage.removeItem(colorStorageKey);
        } catch {
            return;
        }
        applyColors(defaultColors);
        for (const input of colorInputs) {
            if (input instanceof HTMLInputElement) {
                input.value = defaultColors[input.dataset.colorKey].slice(1);
            }
        }
    });
}

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

const postForm = document.getElementById('post-form');
if (postForm instanceof HTMLFormElement) {
    const characterInputs = postForm.querySelectorAll('[data-character-limit]');
    const content = postForm.querySelector('[data-message-line-limit]');

    const updateCharacterLimit = (input) => {
        if (!(input instanceof HTMLInputElement)) {
            return;
        }
        const limit = Number(input.dataset.characterLimit);
        const length = Array.from(input.value).length;
        const overLimit = length > limit;
        const output = document.getElementById(`${input.id}-limit`);
        if (output instanceof HTMLOutputElement) {
            output.value = overLimit ? `文字数オーバー：${length}/${limit}文字` : '';
            output.dataset.overLimit = String(overLimit);
        }
        input.setCustomValidity(overLimit ? `${limit}文字以内で入力してください。` : '');
    };

    const updateMessageLimit = () => {
        if (!(content instanceof HTMLTextAreaElement)) {
            return;
        }
        const lineLimit = Number(content.dataset.messageLineLimit);
        const lineCharLimit = Number(content.dataset.lineCharLimit);
        const normalized = content.value.replace(/\r\n?/g, '\n');
        const lines = normalized === '' ? [] : normalized.split('\n');
        const longestLine = Math.max(0, ...lines.map((line) => Array.from(line).length));
        const overLimit = lines.length > lineLimit || longestLine > lineCharLimit;
        const output = document.getElementById('content-limit');
        if (output instanceof HTMLOutputElement) {
            output.value = `${lines.length}/${lineLimit}行・最長行${longestLine}/${lineCharLimit}文字`;
            output.dataset.overLimit = String(overLimit);
        }

        let message = '';
        if (lines.length > lineLimit) {
            message = `本文は${lineLimit}行以内で入力してください。`;
        } else if (longestLine > lineCharLimit) {
            message = `本文の1行は${lineCharLimit}文字以内で入力してください。`;
        }
        content.setCustomValidity(message);
    };

    for (const input of characterInputs) {
        updateCharacterLimit(input);
        input.addEventListener('input', () => updateCharacterLimit(input));
    }
    updateMessageLimit();
    content?.addEventListener('input', updateMessageLimit);
    postForm.addEventListener('reset', () => queueMicrotask(() => {
        for (const input of characterInputs) {
            updateCharacterLimit(input);
        }
        updateMessageLimit();
    }));
}
