@once
@push('scripts')
<script>
    // Mobile-friendly sharing for X and the WhatsApp Channel (no build step needed).
    window.vdIsMobile = () => window.matchMedia('(pointer: coarse)').matches || /Android|iPhone|iPad/i.test(navigator.userAgent);

    // X: on phones use the system share sheet (pick the X app – it opens with the text ready);
    // on desktop open the x.com composer.
    window.vdShareX = (text) => {
        if (vdIsMobile() && navigator.share) {
            navigator.share({ text }).catch(() => {});
            return false;
        }
        window.open('https://x.com/intent/post?text=' + encodeURIComponent(text), '_blank', 'noopener');
        return false;
    };

    // WhatsApp Channel: WhatsApp cannot pre-fill a channel message, so copy the text first and then
    // open the channel – long-press the message box and Paste.
    window.vdCopyAndOpen = (text, url, btn) => {
        const done = () => { if (btn) { btn.textContent = 'Copied – paste in channel'; } window.location.href = url; };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(done, done);
        } else {
            const t = document.createElement('textarea'); t.value = text; document.body.appendChild(t); t.select();
            try { document.execCommand('copy'); } catch (e) {}
            t.remove(); done();
        }
        return false;
    };

    // Generic system share sheet (phones): lets you pick WhatsApp, Telegram, etc.
    window.vdShare = (text) => {
        if (navigator.share) { navigator.share({ text }).catch(() => {}); }
        else { navigator.clipboard && navigator.clipboard.writeText(text); alert('Copied to clipboard'); }
        return false;
    };
</script>
@endpush
@endonce
