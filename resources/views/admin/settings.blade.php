@extends('layouts.admin')
@section('title', 'Settings')
@section('content')
@php $s = fn ($k) => old($k, $settings[$k] ?? ''); @endphp
<form method="post" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="grid gap-6 xl:grid-cols-2">
    @csrf @method('PUT')
    <div class="card p-5">
        <h2 class="mb-4 font-bold">General</h2>
        <x-admin.field label="Site name" name="site_name" :value="$s('site_name')" required />
        <x-admin.field label="Tagline" name="site_tagline" :value="$s('site_tagline')" :max="160" counter help="Appended to the home page title." />
        <x-admin.field label="Site description" name="site_description" type="textarea" :value="$s('site_description')" :max="160" counter help="Default meta description." />
        <x-admin.field label="Contact email" name="contact_email" type="email" :value="$s('contact_email')" />
        <x-admin.field label="Address" name="contact_address" :value="$s('contact_address')" />
        <x-admin.field label="Footer about text" name="footer_about" type="textarea" :value="$s('footer_about')" />
        <x-admin.field label="Copyright" name="copyright" :value="$s('copyright')" help="Use {year} for the current year." />
        <x-admin.select label="Language" name="language" :value="$s('language')" :options="['en' => 'English', 'hi' => 'Hindi', 'te' => 'Telugu', 'ta' => 'Tamil', 'kn' => 'Kannada', 'ml' => 'Malayalam', 'mr' => 'Marathi', 'bn' => 'Bengali', 'gu' => 'Gujarati']" />
    </div>

    <div class="space-y-6">
        <div class="card p-5">
            <h2 class="mb-4 font-bold">Branding</h2>
            @foreach(['logo' => 'Logo (PNG/SVG, ~180×40)', 'logo_dark' => 'Logo for dark background', 'favicon' => 'Favicon (PNG/ICO/SVG)', 'default_og_image' => 'Default social share image (1200×630)', 'publisher_logo' => 'Publisher logo for Google News (600×60, PNG)'] as $key => $label)
                <div class="mb-4">
                    <label class="label">{{ $label }}</label>
                    @if(!empty($settings[$key]))<div class="mb-2 flex items-center gap-3"><img src="{{ media_url($settings[$key]) }}" alt="" class="max-h-12 rounded border bg-ink-100 p-1"><label class="text-xs"><input type="checkbox" name="remove_{{ $key }}" value="1"> remove</label></div>@endif
                    <input type="file" name="{{ $key }}" class="block w-full text-sm">
                    @error($key)<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            @endforeach
        </div>
        <div class="card p-5">
            <h2 class="mb-4 font-bold">Content</h2>
            <x-admin.field label="Posts per page" name="posts_per_page" type="number" :value="$s('posts_per_page')" />
            <x-admin.select label="Article URL format" name="post_url_format" :value="$s('post_url_format')" :options="['category' => '/category-slug/post-slug (recommended)', 'flat' => '/post-slug']" help="The other form 301-redirects to the canonical one automatically." />
            <x-admin.checkbox label="Show breaking news ticker" name="show_breaking_bar" :checked="(bool) $s('show_breaking_bar')" />
            <x-admin.checkbox label="Enable comments" name="comments_enabled" :checked="(bool) $s('comments_enabled')" />
            <x-admin.checkbox label="Auto-approve comments" name="comments_auto_approve" :checked="(bool) $s('comments_auto_approve')" />
        </div>
    </div>

    <div class="card p-5">
        <h2 class="mb-4 font-bold">Social</h2>
        @foreach(['facebook_url' => 'Facebook URL', 'twitter_url' => 'X / Twitter URL', 'instagram_url' => 'Instagram URL', 'youtube_url' => 'YouTube URL', 'telegram_url' => 'Telegram URL', 'whatsapp_url' => 'WhatsApp channel URL'] as $k => $l)
            <x-admin.field :label="$l" :name="$k" type="url" :value="$s($k)" />
        @endforeach
        <x-admin.field label="X / Twitter handle" name="twitter_handle" :value="$s('twitter_handle')" help="Used for twitter:site cards, e.g. @viraldose" />
    </div>

    <div class="card p-5">
        <h2 class="mb-4 font-bold">SEO &amp; integrations</h2>
        <x-admin.select label="Organization type (schema.org)" name="organization_type" :value="$s('organization_type')" :options="['NewsMediaOrganization' => 'NewsMediaOrganization', 'Organization' => 'Organization']" />
        <x-admin.field label="Founding year" name="organization_founded" :value="$s('organization_founded')" />
        <x-admin.field label="Google News publication name" name="google_news_publication_name" :value="$s('google_news_publication_name')" help="Must match the name in Google Publisher Center." />
        <x-admin.field label="Google Search Console verification code" name="google_site_verification" :value="$s('google_site_verification')" help="Only the content value of the meta tag." />
        <x-admin.field label="Bing Webmaster verification code" name="bing_site_verification" :value="$s('bing_site_verification')" />
        <x-admin.field label="Google Analytics measurement ID" name="google_analytics_id" :value="$s('google_analytics_id')" help="e.g. G-XXXXXXXXXX" />
        <x-admin.field label="AdSense publisher ID" name="adsense_client_id" :value="$s('adsense_client_id')" help="e.g. pub-1234567890123456. Loads the AdSense script site-wide." />
        <x-admin.field label="ads.txt content" name="ads_txt" type="textarea" :value="$s('ads_txt')" help="Served at /ads.txt" />
        <x-admin.field label="Extra &lt;head&gt; code" name="head_scripts" type="textarea" :value="$s('head_scripts')" help="Verification tags, fonts, etc." />
        <x-admin.field label="Code before &lt;/body&gt;" name="body_scripts" type="textarea" :value="$s('body_scripts')" />
    </div>

    <div class="xl:col-span-2"><button class="btn-primary" type="submit">Save settings</button></div>
</form>
@endsection
