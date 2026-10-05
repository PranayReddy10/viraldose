@php
    $footerPages = \App\Models\Page::footerPages();
    $footerCategories = \App\Models\Category::navigation();
    $social = [
        'facebook_url' => 'Facebook', 'twitter_url' => 'X (Twitter)', 'instagram_url' => 'Instagram',
        'youtube_url' => 'YouTube', 'telegram_url' => 'Telegram', 'whatsapp_url' => 'WhatsApp',
    ];
@endphp
@include('partials.ad', ['slot' => 'footer'])
<footer class="mt-12 bg-ink-900 text-gray-300">
    <div class="container-site grid gap-10 py-12 md:grid-cols-2 lg:grid-cols-4">
        <div>
            <a href="{{ url('/') }}" class="inline-block"><x-logo variant="dark" class="h-11 w-auto" /></a>
            <p class="mt-3 text-sm leading-relaxed">{{ setting('footer_about') }}</p>
            <ul class="mt-4 flex flex-wrap gap-3 text-sm">
                @foreach($social as $key => $label)
                    @if(setting($key))<li><a href="{{ setting($key) }}" rel="noopener nofollow" target="_blank" class="hover:text-white">{{ $label }}</a></li>@endif
                @endforeach
            </ul>
        </div>
        <div>
            <h2 class="mb-3 text-sm font-bold uppercase tracking-wider text-white">Categories</h2>
            <ul class="grid grid-cols-2 gap-2 text-sm">
                @foreach($footerCategories as $category)<li><a href="{{ $category->url() }}" class="hover:text-white">{{ $category->name }}</a></li>@endforeach
            </ul>
        </div>
        <div>
            <h2 class="mb-3 text-sm font-bold uppercase tracking-wider text-white">Company</h2>
            <ul class="space-y-2 text-sm">
                @foreach($footerPages as $page)<li><a href="{{ $page->url() }}" class="hover:text-white">{{ $page->title }}</a></li>@endforeach
                <li><a href="{{ route('contact') }}" class="hover:text-white">Contact Us</a></li>
                <li><a href="{{ route('feed') }}" class="hover:text-white">RSS Feed</a></li>
            </ul>
        </div>
        <div>
            <h2 class="mb-3 text-sm font-bold uppercase tracking-wider text-white">Newsletter</h2>
            <p class="text-sm">Get the top stories delivered to your inbox.</p>
            @if(session('newsletter_status'))
                <p class="mt-3 text-sm text-green-400">{{ session('newsletter_status') }}</p>
            @else
                <form action="{{ route('newsletter.subscribe') }}" method="post" class="mt-3 flex gap-2">
                    @csrf
                    <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off">
                    <label for="newsletter-email" class="sr-only">Email address</label>
                    <input id="newsletter-email" type="email" name="email" required placeholder="you@example.com" class="input !bg-ink-700 !border-ink-700 !text-white">
                    <button class="btn-primary" type="submit">Join</button>
                </form>
                @error('email')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            @endif
        </div>
    </div>
    <div class="border-t border-white/10">
        <div class="container-site flex flex-col items-center justify-between gap-2 py-4 text-xs sm:flex-row">
            <p>{{ str_replace('{year}', date('Y'), setting('copyright')) }}</p>
            <p>Powered by <a href="{{ url('/') }}" class="hover:text-white">{{ site_name() }}</a></p>
        </div>
    </div>
</footer>
