import Quill from 'quill';

// Admin panel helpers: slug generation, SEO counters, Google snippet preview,
// Quill editor with image upload, confirm dialogs, bulk selection.
const slugify = (s) =>
    s
        .toString()
        .normalize('NFKD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/[\s_-]+/g, '-')
        .replace(/^-+|-+$/g, '');

document.addEventListener('DOMContentLoaded', () => {
    // Sidebar toggle (mobile)
    const sb = document.getElementById('admin-sidebar');
    const sbBtn = document.getElementById('admin-sidebar-toggle');
    if (sb && sbBtn) sbBtn.addEventListener('click', () => sb.classList.toggle('-translate-x-full'));

    // Slug auto-fill
    const title = document.querySelector('[data-slug-source]');
    const slug = document.querySelector('[data-slug-target]');
    if (title && slug) {
        let touched = slug.value !== '';
        slug.addEventListener('input', () => (touched = slug.value !== ''));
        title.addEventListener('input', () => {
            if (!touched) slug.value = slugify(title.value);
            updatePreview();
        });
    }

    // Character counters
    document.querySelectorAll('[data-counter]').forEach((el) => {
        const target = document.getElementById(el.dataset.counter);
        const max = parseInt(el.dataset.max || '0', 10);
        if (!target) return;
        const render = () => {
            const n = target.value.length;
            el.textContent = `${n}${max ? ' / ' + max : ''}`;
            el.classList.toggle('text-red-600', max && n > max);
            el.classList.toggle('text-green-600', max && n > 0 && n <= max);
        };
        target.addEventListener('input', render);
        render();
    });

    // Google snippet preview
    const pvTitle = document.getElementById('pv-title');
    const pvDesc = document.getElementById('pv-desc');
    const pvUrl = document.getElementById('pv-url');
    const metaTitle = document.querySelector('[name=meta_title]');
    const metaDesc = document.querySelector('[name=meta_description]');
    const excerpt = document.querySelector('[name=excerpt]');
    const site = document.body.dataset.siteName || '';
    const base = document.body.dataset.siteUrl || '';
    function updatePreview() {
        if (!pvTitle) return;
        const t = (metaTitle && metaTitle.value) || (title && title.value) || 'Post title';
        pvTitle.textContent = t.length > 60 ? t.slice(0, 57) + '…' : t + (metaTitle && metaTitle.value ? '' : ' - ' + site);
        const d = (metaDesc && metaDesc.value) || (excerpt && excerpt.value) || 'Meta description will appear here.';
        pvDesc.textContent = d.length > 160 ? d.slice(0, 157) + '…' : d;
        if (pvUrl) {
            const form = document.getElementById('post-form');
            const cat = document.querySelector('[data-category-select]');
            const catSlug = cat && cat.selectedOptions[0] ? cat.selectedOptions[0].dataset.slug : '';
            const prefix = form && form.dataset.urlFormat === 'category' ? '/' + (catSlug || 'category') : '';
            pvUrl.textContent = base + prefix + '/' + (slug && slug.value ? slug.value : 'post-slug');
        }
    }
    [metaTitle, metaDesc, excerpt, slug].forEach((el) => el && el.addEventListener('input', updatePreview));
    const catSel = document.querySelector('[data-category-select]');
    if (catSel) catSel.addEventListener('change', updatePreview);
    updatePreview();

    // Quill editor
    const editorEl = document.getElementById('editor');
    const hidden = document.getElementById('content');
    if (editorEl && hidden) {
        // Social embed block: stored as <div class="embed" data-provider data-url> and
        // rendered into the real Instagram / X / YouTube embed on the public page.
        const BlockEmbed = Quill.import('blots/block/embed');
        const PROVIDERS = {
            youtube: { label: 'YouTube video', test: (u) => /youtu\.be\/|youtube(-nocookie)?\.com\/(watch|embed|shorts|live)/.test(u), prompt: 'Paste the YouTube video URL' },
            twitter: { label: 'X / Twitter post', test: (u) => /(twitter|x)\.com\/.+\/status\/\d+/.test(u), prompt: 'Paste the X (Twitter) post URL' },
            instagram: { label: 'Instagram post', test: (u) => /instagram\.com\/(p|reel|reels|tv)\//.test(u), prompt: 'Paste the Instagram post / reel URL' },
            facebook: { label: 'Facebook post', test: (u) => /facebook\.com\/|fb\.watch\//.test(u), prompt: 'Paste the Facebook post / video URL' },
        };
        class SocialEmbed extends BlockEmbed {
            static blotName = 'socialEmbed';
            static tagName = 'div';
            static className = 'embed';
            static create(value) {
                const node = super.create();
                node.setAttribute('data-provider', value.provider);
                node.setAttribute('data-url', value.url);
                node.setAttribute('contenteditable', 'false');
                const label = document.createElement('span');
                label.className = 'embed-label';
                label.textContent = (PROVIDERS[value.provider] || { label: value.provider }).label;
                const url = document.createElement('span');
                url.className = 'embed-url';
                url.textContent = value.url;
                node.append(label, url);
                return node;
            }
            static value(node) {
                return { provider: node.getAttribute('data-provider'), url: node.getAttribute('data-url') };
            }
        }
        Quill.register(SocialEmbed, true);

        const insertEmbed = (quill, provider) => {
            const url = (window.prompt(PROVIDERS[provider].prompt) || '').trim();
            if (!url) return;
            if (!/^https:\/\//.test(url) || !PROVIDERS[provider].test(url)) {
                alert('That does not look like a ' + PROVIDERS[provider].label + ' URL.');
                return;
            }
            const range = quill.getSelection(true);
            quill.insertEmbed(range.index, 'socialEmbed', { provider, url }, 'user');
            quill.insertText(range.index + 1, '\n', 'user');
            quill.setSelection(range.index + 2);
        };

        const quill = new Quill(editorEl, {
            theme: 'snow',
            placeholder: 'Write your story…',
            modules: {
                toolbar: {
                    container: document.getElementById('editor-toolbar') || [
                        [{ header: [2, 3, 4, false] }],
                        ['bold', 'italic', 'underline', 'strike'],
                        [{ list: 'ordered' }, { list: 'bullet' }],
                        ['blockquote', 'code-block'],
                        ['link', 'image', 'video'],
                        [{ align: [] }],
                        ['clean'],
                    ],
                    handlers: {
                        youtube() { insertEmbed(this.quill, 'youtube'); },
                        twitter() { insertEmbed(this.quill, 'twitter'); },
                        instagram() { insertEmbed(this.quill, 'instagram'); },
                        facebook() { insertEmbed(this.quill, 'facebook'); },
                        image() {
                            const input = document.createElement('input');
                            input.type = 'file';
                            input.accept = 'image/*';
                            input.onchange = async () => {
                                const file = input.files[0];
                                if (!file) return;
                                const fd = new FormData();
                                fd.append('file', file);
                                const res = await fetch(editorEl.dataset.uploadUrl, {
                                    method: 'POST',
                                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, Accept: 'application/json' },
                                    body: fd,
                                });
                                if (!res.ok) {
                                    alert('Upload failed');
                                    return;
                                }
                                const data = await res.json();
                                const range = quill.getSelection(true);
                                quill.insertEmbed(range.index, 'image', data.url, 'user');
                                quill.setSelection(range.index + 1);
                            };
                            input.click();
                        },
                    },
                },
            },
        });
        quill.root.innerHTML = hidden.value;
        const sync = () => (hidden.value = quill.root.innerHTML);
        quill.on('text-change', sync);
        editorEl.closest('form').addEventListener('submit', sync);
    }

    // Confirmations
    document.querySelectorAll('form[data-confirm]').forEach((f) =>
        f.addEventListener('submit', (e) => {
            if (!confirm(f.dataset.confirm)) e.preventDefault();
        }),
    );

    // Bulk select
    const all = document.getElementById('select-all');
    if (all) {
        all.addEventListener('change', () => document.querySelectorAll('input[name="ids[]"]').forEach((c) => (c.checked = all.checked)));
    }

    // Save as draft / publish buttons
    document.querySelectorAll('[data-save-as]').forEach((btn) =>
        btn.addEventListener('click', () => {
            const hidden = document.getElementById('save_as');
            if (hidden) hidden.value = btn.dataset.saveAs;
        }),
    );

    // Post type → show matching media field
    const typeSel = document.querySelector('[name=post_type]');
    if (typeSel) {
        const sync = () => document.querySelectorAll('[data-post-type]').forEach((el) => el.classList.toggle('hidden', el.dataset.postType !== typeSel.value));
        typeSel.addEventListener('change', sync);
        sync();
    }

    // Reel source switch
    const radios = document.querySelectorAll('[data-source-radio]');
    if (radios.length) {
        const syncSource = () => {
            const v = document.querySelector('[data-source-radio]:checked')?.value;
            document.querySelectorAll('[data-source]').forEach((el) => el.classList.toggle('hidden', el.dataset.source !== v));
        };
        radios.forEach((r) => r.addEventListener('change', syncSource));
        syncSource();
    }

    // Copy to clipboard
    document.querySelectorAll('[data-copy]').forEach((btn) =>
        btn.addEventListener('click', async () => {
            const el = document.querySelector(btn.dataset.copy);
            if (!el) return;
            await navigator.clipboard.writeText(el.value || el.textContent);
            const old = btn.textContent; btn.textContent = 'Copied!'; setTimeout(() => (btn.textContent = old), 1500);
        }),
    );

    // Scheduled post toggle
    const sched = document.getElementById('scheduled-toggle');
    if (sched) {
        const fields = document.getElementById('scheduled-fields');
        sched.addEventListener('change', () => fields.classList.toggle('hidden', !sched.checked));
    }

    // Multi-file name lists
    document.querySelectorAll('input[type=file][data-file-list]').forEach((input) => {
        input.addEventListener('change', () => {
            const list = document.getElementById(input.dataset.fileList);
            if (!list) return;
            list.innerHTML = '';
            [...input.files].forEach((f) => {
                const li = document.createElement('li');
                li.textContent = `${f.name} (${Math.round(f.size / 1024)} KB)`;
                list.appendChild(li);
            });
        });
    });

    // Settings tabs
    const tabForm = document.querySelector('[data-tabs]');
    if (tabForm) {
        tabForm.querySelectorAll('[data-tab]').forEach((btn) =>
            btn.addEventListener('click', () => {
                tabForm.querySelectorAll('[data-tab]').forEach((b) => b.classList.remove('border-brand-600', 'text-brand-600'));
                tabForm.querySelectorAll('[data-tab]').forEach((b) => b.classList.add('border-transparent', 'text-ink-500'));
                btn.classList.add('border-brand-600', 'text-brand-600');
                btn.classList.remove('border-transparent', 'text-ink-500');
                tabForm.querySelectorAll('[data-tab-panel]').forEach((p) => p.classList.toggle('hidden', p.dataset.tabPanel !== btn.dataset.tab));
                const input = tabForm.querySelector('[data-tab-input]');
                if (input) input.value = btn.dataset.tab;
                history.replaceState(null, '', '?tab=' + btn.dataset.tab);
            }),
        );
    }

    // RSS feed preview
    const previewBtn = document.querySelector('[data-feed-preview]');
    if (previewBtn) {
        previewBtn.addEventListener('click', async () => {
            const url = document.querySelector('[name=url]').value;
            const list = document.getElementById('feed-preview');
            list.classList.remove('hidden');
            list.innerHTML = '<li class="p-3 text-ink-500">Fetching…</li>';
            const res = await fetch(previewBtn.dataset.feedPreview, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                body: JSON.stringify({ url }),
            });
            const data = await res.json();
            if (!res.ok) {
                list.innerHTML = `<li class="p-3 text-red-600">${data.error || 'Could not read this feed'}</li>`;
                return;
            }
            list.innerHTML = data.items.length
                ? data.items.map((i) => `<li class="flex items-center gap-3 p-3">${i.image ? `<img src="${i.image}" class="h-10 w-14 rounded object-cover" alt="">` : ''}<span class="min-w-0"><span class="block truncate font-medium">${i.title}</span><span class="block text-xs text-ink-500">${i.date || ''}${i.chars !== undefined ? ` · ${i.chars < 600 ? `teaser only (${i.chars} chars) – full article will be fetched from the page` : `${i.chars} chars of text in feed`}` : ''}</span></span></li>`).join('')
                : '<li class="p-3 text-ink-500">Feed is valid but has no items.</li>';
        });
    }

    // Designed file fields: show chosen names and an image preview
    document.querySelectorAll('[data-file-field]').forEach((field) => {
        const input = field.querySelector('input[type=file]');
        const name = field.querySelector('[data-file-name]');
        const count = field.querySelector('[data-file-count]');
        const preview = field.querySelector('[data-file-preview]');
        if (!input) return;
        if (preview) preview.addEventListener('error', () => preview.classList.add('hidden'));
        input.addEventListener('change', () => {
            const files = [...input.files];
            if (!files.length) return;
            const kb = (f) => (f.size >= 1048576 ? (f.size / 1048576).toFixed(1) + ' MB' : Math.round(f.size / 1024) + ' KB');
            name.textContent = files.length === 1 ? `${files[0].name} (${kb(files[0])})` : files.map((f) => f.name).join(', ');
            name.classList.add('text-ink-900', 'font-medium');
            if (count) { count.textContent = files.length > 1 ? files.length + ' files' : 'ready'; count.classList.remove('hidden'); }
            if (preview && files[0].type.startsWith('image/')) { preview.src = URL.createObjectURL(files[0]); preview.classList.remove('hidden'); }
        });
    });

    // Image preview
    document.querySelectorAll('input[type=file][data-preview]').forEach((input) => {
        input.addEventListener('change', () => {
            const img = document.getElementById(input.dataset.preview);
            if (img && input.files[0]) {
                img.src = URL.createObjectURL(input.files[0]);
                img.classList.remove('hidden');
            }
        });
    });
});
