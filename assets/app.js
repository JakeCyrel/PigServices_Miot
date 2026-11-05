document.querySelectorAll('.password-toggle').forEach((toggle) => {
    toggle.addEventListener('click', () => {
        const input = document.getElementById(toggle.dataset.target);
        const isHidden = input.type === 'password';
        input.type = isHidden ? 'text' : 'password';
        toggle.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
        toggle.setAttribute('title', isHidden ? 'Hide password' : 'Show password');
    });
});

document.querySelectorAll('.reply-button').forEach((button) => {
    button.addEventListener('click', () => {
        const composer = document.getElementById('message');
        const context = document.getElementById('reply-context');
        const author = button.dataset.replyAuthor;
        const message = button.dataset.replyText;
        context.textContent = `Replying to ${author}`;
        context.hidden = false;
        composer.value = `Replying to ${author}: "${message}"\n\n`;
        composer.focus();
        composer.setSelectionRange(composer.value.length, composer.value.length);
    });
});

const authModal = document.getElementById('access');
if (authModal) {
    const openAuthModal = () => {
        authModal.classList.add('is-open');
        authModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
    };
    const closeAuthModal = () => {
        authModal.classList.remove('is-open');
        authModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
        history.replaceState(null, '', `${window.location.pathname}${window.location.search}`);
    };
    if (window.location.hash === '#access') {
        openAuthModal();
    }
    document.querySelectorAll('a[href*="#access"]').forEach((link) => {
        link.addEventListener('click', (event) => {
            if (link.pathname === window.location.pathname && link.search === window.location.search) {
                event.preventDefault();
                openAuthModal();
                history.replaceState(null, '', `${window.location.pathname}${window.location.search}#access`);
            }
        });
    });
    authModal.addEventListener('click', (event) => {
        if (event.target === authModal) {
            closeAuthModal();
        }
    });
    authModal.querySelector('.modal-close').addEventListener('click', closeAuthModal);
}
