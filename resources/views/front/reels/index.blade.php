@extends('layouts.app')
@section('immersive', '1')
@section('content')
@php $adEvery = (int) setting('reels_ad_every', 4); $feedAds = \App\Models\Ad::forSlot('reels_feed'); @endphp
<div id="reels" class="relative bg-black text-white" data-next="{{ $reels instanceof \Illuminate\Pagination\LengthAwarePaginator && $reels->hasMorePages() ? $reels->nextPageUrl().'&fragment=1' : '' }}">
    <div class="pointer-events-none absolute inset-x-0 top-0 z-20 flex items-center justify-between bg-gradient-to-b from-black/70 to-transparent px-4 py-3">
        <a href="{{ url('/') }}" class="pointer-events-auto flex items-center gap-2 text-sm font-semibold"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg> <img src="{{ media_url(setting('logo_dark')) ?: asset('images/logo-white.svg') }}" alt="{{ site_name() }}" class="h-7 w-auto"></a>
        <span class="text-sm font-bold uppercase tracking-wider">Reels</span>
        <button type="button" id="reels-mute" class="pointer-events-auto rounded-full bg-white/15 p-2" aria-label="Toggle sound" aria-pressed="false">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5L6 9H2v6h4l5 4V5zM23 9l-6 6M17 9l6 6"/></svg>
        </button>
    </div>
    <div id="reels-track" class="h-[100dvh] snap-y snap-mandatory overflow-y-scroll no-scrollbar scroll-smooth">
        @include('front.reels._items', ['reels' => $reels, 'offset' => 0])
        <div id="reels-sentinel" class="h-px"></div>
    </div>
    <p id="reels-hint" class="pointer-events-none absolute bottom-6 left-1/2 z-20 -translate-x-1/2 rounded-full bg-white/15 px-3 py-1 text-xs backdrop-blur sm:hidden">Swipe up for the next reel</p>
</div>
@endsection
@push('scripts')
<script>
(() => {
  const track = document.getElementById('reels-track');
  if (!track) return;
  let muted = true;
  const muteBtn = document.getElementById('reels-mute');
  const hint = document.getElementById('reels-hint');
  const setMuted = (m) => { muted = m; muteBtn.setAttribute('aria-pressed', String(!m)); muteBtn.classList.toggle('bg-brand-600', !m); track.querySelectorAll('video').forEach((v) => (v.muted = m)); };
  muteBtn.addEventListener('click', () => setMuted(!muted));

  const activate = (slide, on) => {
    const video = slide.querySelector('video');
    if (video) { if (on) { video.muted = muted; video.play().catch(() => {}); } else { video.pause(); } }
    const frame = slide.querySelector('iframe[data-src]');
    if (frame && on && !frame.src) frame.src = frame.dataset.src;
    if (frame && !on && frame.src && frame.dataset.src.includes('youtube')) { frame.src = ''; }
    if (on && slide.dataset.viewUrl && !slide.dataset.counted) { slide.dataset.counted = '1'; history.replaceState(null, '', slide.dataset.viewUrl); if (hint) hint.remove(); }
  };
  const io = new IntersectionObserver((entries) => entries.forEach((e) => activate(e.target, e.isIntersecting && e.intersectionRatio >= 0.6)), { root: track, threshold: [0, 0.6, 1] });
  const observe = () => track.querySelectorAll('.reel-slide:not([data-observed])').forEach((s) => { s.dataset.observed = '1'; io.observe(s); });
  observe();

  // Tap to pause/play, buttons for share
  track.addEventListener('click', (e) => {
    const slide = e.target.closest('.reel-slide'); if (!slide || e.target.closest('a,button')) return;
    const v = slide.querySelector('video'); if (v) v.paused ? v.play() : v.pause();
  });
  track.querySelectorAll('[data-share-url]').forEach((b) => b.addEventListener('click', async () => {
    const data = { title: b.dataset.shareTitle, url: b.dataset.shareUrl };
    try { navigator.share ? await navigator.share(data) : await navigator.clipboard.writeText(data.url); } catch (_) {}
  }));

  // Infinite scroll
  const root = document.getElementById('reels');
  const sentinel = document.getElementById('reels-sentinel');
  let loading = false;
  const more = new IntersectionObserver(async (entries) => {
    if (!entries[0].isIntersecting || loading || !root.dataset.next) return;
    loading = true;
    try {
      const res = await fetch(root.dataset.next, { headers: { Accept: 'text/html' } });
      const html = await res.text();
      const tpl = document.createElement('template'); tpl.innerHTML = html.trim();
      const next = tpl.content.querySelector('[data-next-page]')?.dataset.nextPage || '';
      sentinel.before(...tpl.content.children);
      root.dataset.next = next; observe();
    } finally { loading = false; }
  }, { root: track, rootMargin: '600px' });
  more.observe(sentinel);

  // Keyboard navigation on desktop
  document.addEventListener('keydown', (e) => {
    if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') return;
    e.preventDefault();
    track.scrollBy({ top: (e.key === 'ArrowDown' ? 1 : -1) * track.clientHeight, behavior: 'smooth' });
  });
})();
</script>
@endpush
