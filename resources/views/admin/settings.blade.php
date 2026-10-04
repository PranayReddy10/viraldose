@extends('layouts.admin')
@section('title', 'Settings')
@section('content')
@php
    $s = fn ($k) => old($k, $settings[$k] ?? '');
    $tabs = ['general' => 'General', 'branding' => 'Branding', 'content' => 'Content', 'social' => 'Social', 'seo' => 'SEO', 'google' => 'Google & Indexing', 'storage' => 'Storage (DigitalOcean)', 'advanced' => 'Advanced'];
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
        <x-admin.field label="AdSense publisher ID" name="adsense_client_id" :value="$s('adsense_client_id')" help="e.g. pub-1234567890123456. Loads the AdSense script site-wide." />
        <x-admin.field label="ads.txt content" name="ads_txt" type="textarea" :value="$s('ads_txt')" help="Served at /ads.txt" />
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
            <ol class="mt-4 list-decimal space-y-1 pl-5 text-xs text-ink-700">
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
        <x-admin.select label="Storage driver" name="storage_driver" :value="$s('storage_driver')" :options="['public' => 'Local server (storage/app/public)', 'spaces' => 'DigitalOcean Spaces (S3-compatible, CDN)']" />
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

    <div data-tab-panel="advanced" class="{{ $tab === 'advanced' ? '' : 'hidden' }} card max-w-3xl p-6">
        <x-admin.field label="Extra &lt;head&gt; code" name="head_scripts" type="textarea" :rows="5" :value="$s('head_scripts')" help="Verification tags, fonts, pixels." />
        <x-admin.field label="Code before &lt;/body&gt;" name="body_scripts" type="textarea" :rows="5" :value="$s('body_scripts')" />
    </div>

    <div class="mt-6"><button class="btn-primary" type="submit">Save settings</button></div>
</form>
@endsection
