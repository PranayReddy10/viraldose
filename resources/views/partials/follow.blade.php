{{--
    "Follow ViralDose" buttons for every social link filled in under Settings → Social.
    Usage: @include('partials.follow', ['layout' => 'banner'])  (article footer)
           @include('partials.follow', ['layout' => 'grid'])    (sidebar)
--}}
@php
    $networks = collect([
        'whatsapp_url' => ['WhatsApp', 'bg-[#25d366]', 'M12 2a10 10 0 00-8.6 15.1L2 22l5-1.3A10 10 0 1012 2zm5 13.6c-.2.6-1.2 1.2-1.7 1.2-.4.1-1 .1-1.6-.1-.4-.1-.8-.3-1.4-.5-2.4-1-4-3.5-4.1-3.6-.1-.2-1-1.3-1-2.5s.6-1.8.9-2c.2-.3.5-.3.6-.3h.5c.1 0 .4 0 .6.4l.8 1.9c.1.2.1.3 0 .5l-.3.4-.4.4c-.1.1-.3.3-.1.5.2.3.7 1.1 1.4 1.8 1 .9 1.8 1.1 2.1 1.3.2.1.4.1.5-.1l.7-.8c.2-.2.3-.2.5-.1l1.8.9c.2.1.4.2.4.3.1.1.1.6-.1 1.2z'],
        'instagram_url' => ['Instagram', 'bg-[#e1306c]', 'M7 2h10a5 5 0 015 5v10a5 5 0 01-5 5H7a5 5 0 01-5-5V7a5 5 0 015-5zm5 5a5 5 0 100 10 5 5 0 000-10zm0 2a3 3 0 110 6 3 3 0 010-6zm5.5-3a1.2 1.2 0 100 2.4 1.2 1.2 0 000-2.4z'],
        'facebook_url' => ['Facebook', 'bg-[#1877f2]', 'M14 8h3V4h-3c-2.8 0-4 1.7-4 4.2V10H7v4h3v8h4v-8h3l1-4h-4V8.6c0-.4.3-.6.6-.6z'],
        'twitter_url' => ['X', 'bg-black', 'M17.8 3h3.1l-6.8 7.7L22 21h-6.2l-4.9-6.4L5.3 21H2.2l7.3-8.3L2 3h6.4l4.4 5.8L17.8 3zm-1.1 16.2h1.7L7.4 4.7H5.6l11.1 14.5z'],
        'youtube_url' => ['YouTube', 'bg-red-600', 'M22 8.2a3 3 0 00-2.1-2.1C18 5.6 12 5.6 12 5.6s-6 0-7.9.5A3 3 0 002 8.2 31 31 0 001.6 12 31 31 0 002 15.8a3 3 0 002.1 2.1c1.9.5 7.9.5 7.9.5s6 0 7.9-.5a3 3 0 002.1-2.1c.4-1.2.4-3.8.4-3.8s0-2.6-.4-3.8zM10 15V9l5.2 3L10 15z'],
        'telegram_url' => ['Telegram', 'bg-[#229ed9]', 'M21.5 4.3L2.9 11.5c-1.3.5-1.2 1.2-.2 1.5l4.8 1.5 1.8 5.6c.2.6.4.8.9.8.4 0 .6-.2.9-.4l2.3-2.2 4.7 3.5c.9.5 1.5.2 1.7-.8l3.1-14.6c.3-1.3-.5-1.8-1.4-1.5zM8.7 14.2l9.4-5.9c.4-.3.8-.1.5.2l-7.8 7-.3 3.3-1.8-4.6z'],
    ])->filter(fn ($n, $key) => filled(setting($key)));
    $layout = $layout ?? 'grid';
@endphp
@if($networks->isNotEmpty())
    @if($layout === 'banner')
        {{-- Under each article: one clear call-to-action per channel (WhatsApp, then Instagram). --}}
        @if(setting('whatsapp_url'))
            <aside class="mt-8 flex flex-col gap-3 rounded-lg border border-green-200 bg-green-50 p-5 sm:flex-row sm:items-center sm:justify-between" aria-label="Follow on WhatsApp">
                <p class="text-sm text-green-800"><strong>Get top news on WhatsApp.</strong> Follow the {{ site_name() }} channel for breaking updates – free, no spam.</p>
                <a href="{{ setting('whatsapp_url') }}" target="_blank" rel="noopener nofollow" class="btn shrink-0 bg-[#25d366] text-white"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="{{ $networks['whatsapp_url'][2] }}"/></svg>Follow on WhatsApp</a>
            </aside>
        @endif
        @if(setting('instagram_url'))
            <aside class="{{ setting('whatsapp_url') ? 'mt-4' : 'mt-8' }} flex flex-col gap-3 rounded-lg border p-5 sm:flex-row sm:items-center sm:justify-between" style="background:#fdf2f8;border-color:#fbcfe8" aria-label="Follow on Instagram">
                <p class="text-sm" style="color:#9d174d"><strong>Catch the news in 30 seconds.</strong> Follow {{ '@'.ltrim(setting('instagram_username', 'viraldose_news'), '@') }} on Instagram for daily news cards and reels.</p>
                <a href="{{ setting('instagram_url') }}" target="_blank" rel="noopener nofollow" class="btn shrink-0 bg-[#e1306c] text-white"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="{{ $networks['instagram_url'][2] }}"/></svg>Follow on Instagram</a>
            </aside>
        @endif
    @else
        <section aria-label="Follow {{ site_name() }}">
            <h2 class="section-title">Follow Us</h2>
            <div class="grid grid-cols-2 gap-2">
                @foreach($networks as $key => [$label, $bg, $icon])
                    <a href="{{ setting($key) }}" target="_blank" rel="noopener nofollow" class="btn {{ $bg }} text-white !px-3 !py-2 text-sm"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="{{ $icon }}"/></svg>{{ $label }}</a>
                @endforeach
            </div>
        </section>
    @endif
@endif
