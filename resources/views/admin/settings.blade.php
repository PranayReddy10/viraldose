@extends('layouts.admin')
@section('title', 'Settings')
@section('content')
@php
    $s = fn ($k) => old($k, $settings[$k] ?? '');
    $tabs = ['general' => 'General', 'branding' => 'Branding', 'content' => 'Content', 'social' => 'Social', 'seo' => 'SEO', 'google' => 'Google & Indexing', 'instagram' => 'Instagram', 'reels' => 'Reels', 'ads' => 'Ads', 'storage' => 'Storage (DigitalOcean)', 'header' => 'Header Code', 'body' => 'Body Code'];
@endphp
<form method="post" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" data-tabs>
    @csrf @method('PUT')
    <input type="hidden" name="tab" value="{{ $tab }}" data-tab-input>
    <div class="mb-6 flex flex-wrap gap-1 border-b border-ink-300/60">
        @foreach($tabs as $key => $label)
            <button type="button" data-tab="{{ $key }}" class="tab-btn -mb-px border-b-2 px-4 py-2 text-sm font-semibold {{ $tab === $key ? 'border-brand-600 text-brand-600' : 'border-transparent text-ink-500 hover:text-ink-900' }}">{{ $label }}</button>
        @endforeach
    </div>

    <div data-tab-panel="general" class="{{ $tab === 'general' ? '' : 'hidden' }} card max-w-3xl p-6">
        <x-admin.field label="Site name" name="site_name" :value="$s('site_name')" required />
        <x-admin.field label="Tagline" name="site_tagline" :value="$s('site_tagline')" :max="160" counter help="Appended to the home page title." />
        <x-admin.field label="Site description" name="site_description" type="textarea" :value="$s('site_description')" :max="160" counter help="Default meta description." />
        <x-admin.field label="Site keywords" name="site_keywords" :value="$s('site_keywords')" />
        <div class="grid gap-4 sm:grid-cols-2">
            <x-admin.field label="Contact email" name="contact_email" type="email" :value="$s('contact_email')" />
            <x-admin.field label="Display time zone" name="timezone_display" :value="$s('timezone_display')" help="e.g. Asia/Kolkata" />
        </div>
        <x-admin.field label="Address" name="contact_address" :value="$s('contact_address')" />
        <x-admin.field label="Footer about text" name="footer_about" type="textarea" :value="$s('footer_about')" />
        <x-admin.field label="Copyright" name="copyright" :value="$s('copyright')" help="Use {year} for the current year." />
        <div class="grid gap-4 sm:grid-cols-2">
            <x-admin.field label="Default language code" name="language" :value="$s('language')" help="ISO code, e.g. en, hi, te" />
            <x-admin.field label="Available languages" name="languages" :value="$s('languages')" help="code:Label pairs, comma separated. Shown in the post editor." />
        </div>
    </div>

    <div data-tab-panel="branding" class="{{ $tab === 'branding' ? '' : 'hidden' }} card max-w-3xl p-6">
        @foreach(['logo' => 'Logo (PNG/SVG, ~180×40)', 'logo_dark' => 'Logo for dark background', 'favicon' => 'Favicon (PNG/ICO/SVG)', 'default_og_image' => 'Default social share image (1200×630)', 'publisher_logo' => 'Publisher logo for Google News (600×60, PNG)'] as $key => $label)
            <div class="mb-5">
                <label class="label">{{ $label }}</label>
                @if(!empty($settings[$key]))<div class="mb-2 flex items-center gap-3"><img src="{{ media_url($settings[$key]) }}" alt="" class="max-h-12 rounded border bg-ink-100 p-1"><label class="text-xs"><input type="checkbox" name="remove_{{ $key }}" value="1"> remove</label></div>@endif
                <input type="file" name="{{ $key }}" class="block w-full text-sm">
                @error($key)<p class="text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
        @endforeach
    </div>

    <div data-tab-panel="content" class="{{ $tab === 'content' ? '' : 'hidden' }} card max-w-3xl p-6">
        <x-admin.field label="Posts per page" name="posts_per_page" type="number" :value="$s('posts_per_page')" />
        <x-admin.select label="Article URL format" name="post_url_format" :value="$s('post_url_format')" :options="['category' => '/category-slug/post-slug (recommended)', 'flat' => '/post-slug']" help="The other form 301-redirects to the canonical one automatically." />
        <x-admin.checkbox label="Show breaking news ticker" name="show_breaking_bar" :checked="(bool) $s('show_breaking_bar')" />
        <x-admin.checkbox label="Enable comments" name="comments_enabled" :checked="(bool) $s('comments_enabled')" />
        <x-admin.checkbox label="Auto-approve comments" name="comments_auto_approve" :checked="(bool) $s('comments_auto_approve')" />
    </div>

    <div data-tab-panel="social" class="{{ $tab === 'social' ? '' : 'hidden' }} card max-w-3xl p-6">
        @foreach(['facebook_url' => 'Facebook URL', 'twitter_url' => 'X / Twitter URL', 'instagram_url' => 'Instagram URL', 'youtube_url' => 'YouTube URL', 'telegram_url' => 'Telegram URL', 'whatsapp_url' => 'WhatsApp channel URL'] as $k => $l)
            <x-admin.field :label="$l" :name="$k" type="url" :value="$s($k)" />
        @endforeach
        <x-admin.field label="X / Twitter handle" name="twitter_handle" :value="$s('twitter_handle')" help="Used for twitter:site cards, e.g. @viraldose" />
    </div>

    <div data-tab-panel="seo" class="{{ $tab === 'seo' ? '' : 'hidden' }} card max-w-3xl p-6">
        <x-admin.select label="Organization type (schema.org)" name="organization_type" :value="$s('organization_type')" :options="['NewsMediaOrganization' => 'NewsMediaOrganization', 'Organization' => 'Organization']" />
        <div class="grid gap-4 sm:grid-cols-2">
            <x-admin.field label="Founding year" name="organization_founded" :value="$s('organization_founded')" />
            <x-admin.field label="Google News publication name" name="google_news_publication_name" :value="$s('google_news_publication_name')" help="Must match Google Publisher Center." />
        </div>
        <x-admin.field label="Google Search Console verification code" name="google_site_verification" :value="$s('google_site_verification')" help="Only the content value of the HTML-tag method. Not needed once the service account is an owner of the property." />
        <x-admin.field label="Bing Webmaster verification code" name="bing_site_verification" :value="$s('bing_site_verification')" />
        <x-admin.field label="Google Analytics measurement ID" name="google_analytics_id" :value="$s('google_analytics_id')" help="e.g. G-XXXXXXXXXX – adds the gtag snippet to every page." />
        <p class="text-sm text-ink-500">Sitemaps: <a href="{{ route('sitemap.index') }}" target="_blank" class="underline">/sitemap.xml</a> · <a href="{{ route('sitemap.news') }}" target="_blank" class="underline">/news-sitemap.xml</a> · <a href="{{ route('feed') }}" target="_blank" class="underline">/feed</a> · <a href="{{ route('robots') }}" target="_blank" class="underline">/robots.txt</a></p>
    </div>

    <div data-tab-panel="google" class="{{ $tab === 'google' ? '' : 'hidden' }} grid max-w-5xl gap-6 lg:grid-cols-2">
        <div class="card p-6">
            <h2 class="font-bold">Google service account</h2>
            <p class="mt-1 text-sm text-ink-500">One JSON key unlocks Search Console (performance, URL inspection, sitemaps), the Indexing API and GA4 reporting.</p>
            @if($googleEmail)
                <p class="mt-3 rounded bg-green-50 px-3 py-2 text-sm text-green-800">Connected as <strong>{{ $googleEmail }}</strong></p>
                <label class="mt-2 block text-xs"><input type="checkbox" name="remove_google_service_account" value="1"> remove key</label>
            @else
                <p class="mt-3 rounded bg-yellow-50 px-3 py-2 text-sm text-yellow-800">Not connected.</p>
            @endif
            <label class="label mt-4">Upload service-account JSON key</label>
            <input type="file" name="google_service_account" accept=".json,application/json" class="block w-full text-sm">
            @error('google_service_account')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
            <p class="mt-4 text-xs"><a href="https://github.com/PranayReddy10/viraldose/blob/main/docs/google-setup.md" target="_blank" rel="noopener" class="font-semibold text-brand-600 underline">Detailed step-by-step guide (docs/google-setup.md) →</a></p>
            <ol class="mt-2 list-decimal space-y-1 pl-5 text-xs text-ink-700">
                <li>Google Cloud Console → create a project → enable <em>Search Console API</em>, <em>Web Search Indexing API</em> and <em>Google Analytics Data API</em>.</li>
                <li>IAM → Service accounts → create one → Keys → add JSON key → upload it here.</li>
                <li>Search Console → Settings → Users → add the service-account email as <strong>Owner</strong> (required for the Indexing API).</li>
                <li>GA4 → Admin → Property access → add the same email as <strong>Viewer</strong>.</li>
            </ol>
        </div>
        <div class="space-y-6">
            <div class="card p-6">
                <h2 class="mb-3 font-bold">Properties</h2>
                <x-admin.field label="Search Console property" name="google_sc_site_url" :value="$s('google_sc_site_url')" help="sc-domain:viraldose.in (domain property) or https://viraldose.in/ (URL-prefix). Blank = sc-domain of APP_URL." />
                <x-admin.field label="GA4 property ID" name="ga4_property_id" :value="$s('ga4_property_id')" help="Numeric ID from GA4 → Admin → Property details, e.g. 123456789" />
            </div>
            <div class="card p-6">
                <h2 class="mb-3 font-bold">Automatic indexing</h2>
                <x-admin.checkbox label="Submit to Google Indexing API when a post is published or updated" name="google_auto_index" :checked="(bool) $s('google_auto_index')" help="Quota: 200 URLs/day per project." />
                <x-admin.checkbox label="Submit to IndexNow (Bing, Yandex, Seznam, Naver)" name="indexnow_enabled" :checked="(bool) $s('indexnow_enabled')" />
                <p class="mt-2 text-xs text-ink-500">IndexNow key file: <a href="{{ url('/'.$indexNowKey.'.txt') }}" target="_blank" class="underline">/{{ $indexNowKey }}.txt</a> (served automatically).</p>
                <a href="{{ route('admin.google.index') }}" class="btn-outline mt-4">Open Google dashboard →</a>
            </div>
        </div>
    </div>

    <div data-tab-panel="storage" class="{{ $tab === 'storage' ? '' : 'hidden' }} card max-w-3xl p-6">
        <h2 class="font-bold">Media storage</h2>
        <p class="mb-4 mt-1 text-sm text-ink-500">New uploads go to the selected storage. Existing files keep working wherever they were uploaded.</p>
        <x-admin.select label="Storage driver" name="storage_driver" :value="$s('storage_driver')" :options="['public' => 'Local server (storage/app/public)', 'spaces' => 'DigitalOcean Spaces (S3-compatible, CDN)']" help="Currently active for new uploads: {{ \App\Services\ImageService::uploadDisk() === 'spaces' ? 'DigitalOcean Spaces' : 'Local server' }}. Save, then press Test connection – uploads show a clear error if the Space rejects them." />
        <div class="grid gap-4 sm:grid-cols-2">
            <x-admin.field label="Spaces access key" name="spaces_key" :value="$s('spaces_key')" />
            <div class="mb-4">
                <label class="label" for="f-spaces_secret">Spaces secret key</label>
                <input id="f-spaces_secret" type="password" name="spaces_secret" class="input" placeholder="{{ $settings['spaces_secret'] ? '•••••••• (saved – leave blank to keep)' : '' }}" autocomplete="new-password">
                @error('spaces_secret')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <x-admin.field label="Region" name="spaces_region" :value="$s('spaces_region')" help="blr1 (Bangalore), sgp1, nyc3, ams3, fra1, sfo3, syd1" />
            <x-admin.field label="Bucket (Space name)" name="spaces_bucket" :value="$s('spaces_bucket')" />
            <x-admin.field label="Endpoint" name="spaces_endpoint" type="url" :value="$s('spaces_endpoint')" help="https://REGION.digitaloceanspaces.com" />
            <x-admin.field label="CDN / custom URL (optional)" name="spaces_cdn_url" type="url" :value="$s('spaces_cdn_url')" help="https://SPACE.REGION.cdn.digitaloceanspaces.com or your subdomain" />
        </div>
        <div class="flex items-center gap-3">
            <button type="submit" class="btn-primary">Save</button>
            @if($spacesConfigured)<button type="submit" formaction="{{ route('admin.settings.test-storage') }}" formmethod="post" formnovalidate class="btn-outline">Test connection</button>@endif
        </div>
        <p class="mt-4 text-xs text-ink-500">Tip: on DigitalOcean also enable the Space's CDN and set the CDN URL above so images are served from the edge. Set the Space's file listing to private but files to public-read (the app uploads with public visibility).</p>
    </div>

    <div data-tab-panel="instagram" class="{{ $tab === 'instagram' ? '' : 'hidden' }} grid max-w-5xl gap-6 lg:grid-cols-2">
        <div class="card p-6">
            <h2 class="font-bold">Instagram page connection</h2>
            <p class="mt-1 text-sm text-ink-500">Lets editors post any story to <strong>instagram.com/{{ ltrim($s('instagram_username') ?: 'viraldose_news', '@') }}</strong> with one click (image card or reel). Needs an Instagram <em>Business</em> or <em>Creator</em> account linked to a Facebook Page.</p>
            @if($instagramReady)<p class="mt-3 rounded bg-green-50 px-3 py-2 text-sm text-green-800">Connected as {{ '@'.ltrim($s('instagram_username'), '@') }}</p>@else<p class="mt-3 rounded bg-yellow-50 px-3 py-2 text-sm text-yellow-800">Not connected – the “Download card” button still works for manual posting.</p>@endif
            <x-admin.field label="Instagram business account ID" name="instagram_business_id" :value="$s('instagram_business_id')" help="Numeric ID (e.g. 17841400000000000)." class="mt-4" />
            <div class="mb-4">
                <label class="label" for="f-instagram_access_token">Access token</label>
                <input id="f-instagram_access_token" type="password" name="instagram_access_token" class="input" placeholder="{{ $settings['instagram_access_token'] ? '•••••••• (saved – leave blank to keep)' : 'Long-lived Page access token' }}" autocomplete="new-password">
                @error('instagram_access_token')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <x-admin.field label="Instagram username" name="instagram_username" :value="$s('instagram_username')" help="Shown on the generated card." />
            <div class="flex gap-2">
                <button type="submit" class="btn-primary">Save</button>
                @if($s('instagram_business_id'))<button type="submit" formaction="{{ route('admin.settings.test-instagram') }}" formmethod="post" formnovalidate class="btn-outline">Test connection</button>@endif
            </div>
            <ol class="mt-5 list-decimal space-y-1 pl-5 text-xs text-ink-700">
                <li>Switch the Instagram account to Business/Creator and link it to a Facebook Page.</li>
                <li>developers.facebook.com → create an app (Business) → add <em>Instagram Graph API</em>.</li>
                <li>Graph API Explorer → permissions <code>instagram_basic, instagram_content_publish, pages_show_list, pages_read_engagement, business_management</code> → generate a user token → exchange for a long-lived token → get the <strong>Page access token</strong> (does not expire).</li>
                <li>Find the Instagram business account ID: <code>GET /me/accounts?fields=instagram_business_account</code>.</li>
                <li>Paste both values here and press Test connection. Images must be publicly reachable JPEGs — the app generates them automatically.</li>
            </ol>
        </div>
        <div class="card p-6">
            <h2 class="mb-3 font-bold">Posting defaults</h2>
            <x-admin.checkbox label="Automatically post every newly published story to Instagram" name="instagram_auto_share" :checked="(bool) $s('instagram_auto_share')" help="Uses the generated news card. Otherwise editors click “Post to Instagram” on each story." />
            <x-admin.field label="Caption template" name="instagram_caption_template" type="textarea" :rows="6" :value="str_replace('\\n', PHP_EOL, $s('instagram_caption_template'))" help="Placeholders: {title} {excerpt} {category} {url} {hashtags}. Instagram does not make links clickable – keep “link in bio”." />
            <x-admin.field label="Default hashtags" name="instagram_hashtags" :value="$s('instagram_hashtags')" />
            <p class="text-xs text-ink-500">Card format: 1080×1350 JPEG with the featured image, category badge, headline and site name. Preview/download it from any post's Instagram box.</p>
        </div>
    </div>

    <div data-tab-panel="reels" class="{{ $tab === 'reels' ? '' : 'hidden' }} card max-w-3xl p-6">
        <h2 class="font-bold">Reels / Shorts feed</h2>
        <p class="mb-4 mt-1 text-sm text-ink-500">Vertical swipe feed at <a href="{{ route('reels.index') }}" target="_blank" class="underline">/reels</a> plus a strip on the home page. Manage videos under <a href="{{ route('admin.reels.index') }}" class="underline">Reels</a>.</p>
        <x-admin.checkbox label="Enable Reels" name="reels_enabled" :checked="(bool) $s('reels_enabled')" />
        <div class="grid gap-4 sm:grid-cols-2">
            <x-admin.field label="Reels per load" name="reels_per_page" type="number" :value="$s('reels_per_page')" />
            <x-admin.field label="Show an ad after every N reels" name="reels_ad_every" type="number" :value="$s('reels_ad_every')" help="0 = no ads in the feed. Uses the “Reels feed” ad slot." />
        </div>
    </div>

    <div data-tab-panel="ads" class="{{ $tab === 'ads' ? '' : 'hidden' }} card max-w-3xl p-6">
        <h2 class="font-bold">Advertising</h2>
        <p class="mb-4 mt-1 text-sm text-ink-500">Ad units are placed per slot under <a href="{{ route('admin.ads.index') }}" class="underline">Ad Spaces</a> (16 slots: header, in-feed, sidebar, in-article, archive grid, reels feed, sticky mobile, footer). These are the site-wide switches.</p>
        <x-admin.checkbox label="Ads enabled" name="ads_enabled" :checked="(bool) $s('ads_enabled')" help="Turn off to hide every ad slot instantly." />
        <x-admin.field label="AdSense publisher ID" name="adsense_client_id" :value="$s('adsense_client_id')" help="e.g. pub-1234567890123456. Loads the AdSense script on every page." />
        <x-admin.checkbox label="AdSense Auto Ads" name="adsense_auto_ads" :checked="(bool) $s('adsense_auto_ads')" help="Let Google place additional ads automatically (enable Auto ads for the site in AdSense too)." />
        <x-admin.checkbox label="Sticky bottom ad on mobile" name="mobile_sticky_ad" :checked="(bool) $s('mobile_sticky_ad')" help="Shows the “Mobile – sticky bottom anchor” slot as a closable bar." />
        <x-admin.field label="ads.txt content" name="ads_txt" type="textarea" :rows="4" :value="$s('ads_txt')" help="Served at /ads.txt" />
    </div>

    <div data-tab-panel="header" class="{{ $tab === 'header' ? '' : 'hidden' }} card max-w-3xl p-6">
        <h2 class="font-bold">Header code (&lt;head&gt;)</h2>
        <p class="mb-4 mt-1 text-sm text-ink-500">Injected on every public page before <code>&lt;/head&gt;</code>: verification meta tags, Google Tag Manager, Facebook Pixel, fonts, extra meta.</p>
        <x-admin.field label="HTML / scripts in <head>" name="head_scripts" type="textarea" :rows="8" :value="$s('head_scripts')" />
        <x-admin.field label="Custom CSS" name="custom_css" type="textarea" :rows="8" :value="$s('custom_css')" help="Added as a <style> tag after the theme CSS, e.g. .cat-badge{border-radius:0}" />
    </div>

    <div data-tab-panel="body" class="{{ $tab === 'body' ? '' : 'hidden' }} card max-w-3xl p-6">
        <h2 class="font-bold">Body code</h2>
        <p class="mb-4 mt-1 text-sm text-ink-500">Injected on every public page. Use the first box for code that must sit right after <code>&lt;body&gt;</code> (e.g. the GTM noscript iframe) and the second for widgets, chat bubbles or notification scripts loaded before <code>&lt;/body&gt;</code>.</p>
        <x-admin.field label="Right after <body>" name="body_start_scripts" type="textarea" :rows="6" :value="$s('body_start_scripts')" />
        <x-admin.field label="Before </body>" name="body_scripts" type="textarea" :rows="8" :value="$s('body_scripts')" />
    </div>

    <div class="mt-6"><button class="btn-primary" type="submit">Save settings</button></div>
</form>
@endsection
