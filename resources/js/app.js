// Front-end behaviour: intentionally tiny. No framework, no render-blocking work.
document.addEventListener('DOMContentLoaded', () => {
    const toggle = (btn, target) => {
        if (!btn || !target) return;
        btn.addEventListener('click', () => {
            const open = target.classList.toggle('hidden') === false;
            btn.setAttribute('aria-expanded', String(open));
            if (open) {
                const input = target.querySelector('input');
                if (input) input.focus();
            }
        });
    };
    toggle(document.getElementById('menu-toggle'), document.getElementById('mobile-menu'));
    toggle(document.getElementById('search-toggle'), document.getElementById('search-bar'));

    // Native share with clipboard fallback
    document.querySelectorAll('[data-share]').forEach((btn) => {
        btn.addEventListener('click', async (e) => {
            e.preventDefault();
            const data = { title: document.title, url: window.location.href };
            try {
                if (navigator.share) {
                    await navigator.share(data);
                } else {
                    await navigator.clipboard.writeText(data.url);
                    btn.textContent = 'Link copied';
                }
            } catch (_) {
                /* user cancelled */
            }
        });
    });

    // Reply-to for comments
    document.querySelectorAll('[data-reply-to]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const form = document.getElementById('comment-form');
            if (!form) return;
            form.querySelector('[name=parent_id]').value = btn.dataset.replyTo;
            const note = form.querySelector('#reply-note');
            if (note) {
                note.textContent = 'Replying to ' + btn.dataset.replyName;
                note.classList.remove('hidden');
            }
            form.scrollIntoView({ behavior: 'smooth' });
            form.querySelector('textarea').focus();
        });
    });
    const cancel = document.getElementById('cancel-reply');
    if (cancel) {
        cancel.addEventListener('click', () => {
            const form = document.getElementById('comment-form');
            form.querySelector('[name=parent_id]').value = '';
            form.querySelector('#reply-note').classList.add('hidden');
        });
    }

    // Reading progress bar on articles
    const bar = document.getElementById('progress-bar');
    if (bar) {
        const update = () => {
            const h = document.documentElement;
            const max = h.scrollHeight - h.clientHeight;
            bar.style.width = (max > 0 ? (h.scrollTop / max) * 100 : 0) + '%';
        };
        document.addEventListener('scroll', update, { passive: true });
        update();
    }

    // Back to top
    const top = document.getElementById('back-to-top');
    if (top) {
        document.addEventListener('scroll', () => top.classList.toggle('hidden', window.scrollY < 600), { passive: true });
        top.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
    }
});
