<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\AiImageGenerator;
use App\Services\FacebookPublisher;
use App\Services\Google\GoogleClient;
use App\Services\ImageService;
use App\Services\IndexNow;
use App\Services\InstagramPublisher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    public function __construct(private ImageService $images, private GoogleClient $google) {}

    public function edit(Request $request)
    {
        return view('admin.settings', [
            'settings' => Setting::all_cached(),
            'tab' => $request->query('tab', 'general'),
            'googleEmail' => $this->google->clientEmail(),
            'indexNowKey' => IndexNow::key(),
            'spacesConfigured' => (bool) setting('spaces_bucket'),
            'instagramReady' => app(InstagramPublisher::class)->isReady(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'site_name' => ['required', 'string', 'max:100'],
            'site_tagline' => ['nullable', 'string', 'max:160'],
            'site_description' => ['nullable', 'string', 'max:320'],
            'site_keywords' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:190'],
            'contact_address' => ['nullable', 'string', 'max:300'],
            'footer_about' => ['nullable', 'string', 'max:1000'],
            'copyright' => ['nullable', 'string', 'max:200'],
            'facebook_url' => ['nullable', 'url', 'max:255'],
            'twitter_url' => ['nullable', 'url', 'max:255'],
            'instagram_url' => ['nullable', 'url', 'max:255'],
            'youtube_url' => ['nullable', 'url', 'max:255'],
            'telegram_url' => ['nullable', 'url', 'max:255'],
            'whatsapp_url' => ['nullable', 'url', 'max:255'],
            'twitter_handle' => ['nullable', 'string', 'max:50'],
            'posts_per_page' => ['required', 'integer', 'min:6', 'max:48'],
            'post_url_format' => ['required', 'in:category,flat'],
            'show_breaking_bar' => ['nullable', 'boolean'],
            'comments_enabled' => ['nullable', 'boolean'],
            'comments_auto_approve' => ['nullable', 'boolean'],
            'google_analytics_id' => ['nullable', 'string', 'max:40', 'regex:/^(G|UA|GT)-[A-Z0-9-]+$/i'],
            'google_site_verification' => ['nullable', 'string', 'max:120'],
            'bing_site_verification' => ['nullable', 'string', 'max:120'],
            'adsense_client_id' => ['nullable', 'string', 'max:40', 'regex:/^(ca-)?pub-[0-9]+$/'],
            'ads_txt' => ['nullable', 'string', 'max:20000'],
            'head_scripts' => ['nullable', 'string', 'max:20000'],
            'body_start_scripts' => ['nullable', 'string', 'max:20000'],
            'body_scripts' => ['nullable', 'string', 'max:20000'],
            'custom_css' => ['nullable', 'string', 'max:50000'],
            'ads_enabled' => ['nullable', 'boolean'],
            'adsense_auto_ads' => ['nullable', 'boolean'],
            'mobile_sticky_ad' => ['nullable', 'boolean'],
            'instagram_business_id' => ['nullable', 'string', 'max:40', 'regex:/^\d*$/'],
            'instagram_access_token' => ['nullable', 'string', 'max:600'],
            'instagram_username' => ['nullable', 'string', 'max:60'],
            'instagram_auto_share' => ['nullable', 'boolean'],
            'facebook_page_id' => ['nullable', 'string', 'max:40', 'regex:/^\d*$/'],
            'facebook_auto_share' => ['nullable', 'boolean'],
            'ai_images_enabled' => ['nullable', 'boolean'],
            'openai_api_key' => ['nullable', 'string', 'max:300'],
            'ai_images_quality' => ['nullable', 'in:low,medium,high'],
            'instagram_hashtags' => ['nullable', 'string', 'max:600'],
            'instagram_caption_template' => ['nullable', 'string', 'max:2000'],
            'reels_enabled' => ['nullable', 'boolean'],
            'reels_per_page' => ['nullable', 'integer', 'min:4', 'max:30'],
            'reels_ad_every' => ['nullable', 'integer', 'min:0', 'max:20'],
            'organization_type' => ['required', 'in:NewsMediaOrganization,Organization'],
            'organization_founded' => ['nullable', 'string', 'max:20'],
            'google_news_publication_name' => ['nullable', 'string', 'max:100'],
            'language' => ['required', 'string', 'max:10'],
            'languages' => ['nullable', 'string', 'max:500', 'regex:/^[a-z]{2,3}(-[A-Za-z]{2,4})?:[^,]+(,\s*[a-z]{2,3}(-[A-Za-z]{2,4})?:[^,]+)*$/'],
            'timezone_display' => ['nullable', 'timezone:all'],
            // Google connection
            'google_sc_site_url' => ['nullable', 'string', 'max:255'],
            'ga4_property_id' => ['nullable', 'string', 'max:30'],
            'google_auto_index' => ['nullable', 'boolean'],
            'indexnow_enabled' => ['nullable', 'boolean'],
            'google_service_account' => ['nullable', 'file', 'mimes:json,txt', 'max:64'],
            'remove_google_service_account' => ['nullable', 'boolean'],
            // Storage
            'storage_driver' => ['required', 'in:public,spaces'],
            'spaces_key' => ['nullable', 'string', 'max:100'],
            'spaces_secret' => ['nullable', 'string', 'max:200'],
            'spaces_region' => ['nullable', 'string', 'max:20'],
            'spaces_bucket' => ['nullable', 'string', 'max:100', 'required_if:storage_driver,spaces'],
            'spaces_endpoint' => ['nullable', 'url', 'max:200'],
            'spaces_cdn_url' => ['nullable', 'url', 'max:200'],
            // Branding
            'logo' => ['nullable', 'image', 'max:1024'],
            'logo_dark' => ['nullable', 'image', 'max:1024'],
            'favicon' => ['nullable', 'file', 'mimes:png,ico,svg', 'max:512'],
            'default_og_image' => ['nullable', 'image', 'max:2048'],
            'publisher_logo' => ['nullable', 'image', 'max:1024'],
        ]);

        foreach (['show_breaking_bar', 'comments_enabled', 'comments_auto_approve', 'google_auto_index', 'indexnow_enabled', 'ads_enabled', 'adsense_auto_ads', 'mobile_sticky_ad', 'instagram_auto_share', 'facebook_auto_share', 'reels_enabled', 'ai_images_enabled'] as $flag) {
            $data[$flag] = $request->boolean($flag) ? 1 : 0;
        }
        unset($data['google_service_account'], $data['remove_google_service_account']);

        // Secrets: keep the stored value when the field is left blank, encrypt otherwise.
        if (blank($data['spaces_secret'] ?? null)) {
            unset($data['spaces_secret']);
        } else {
            $data['spaces_secret'] = Crypt::encryptString($data['spaces_secret']);
        }
        if (blank($data['instagram_access_token'] ?? null)) {
            unset($data['instagram_access_token']);
        } else {
            $data['instagram_access_token'] = Crypt::encryptString(trim($data['instagram_access_token']));
        }

        if (blank($data['openai_api_key'] ?? null)) {
            unset($data['openai_api_key']);
        } else {
            $data['openai_api_key'] = Crypt::encryptString(trim($data['openai_api_key']));
        }

        foreach (['logo', 'logo_dark', 'favicon', 'default_og_image', 'publisher_logo'] as $file) {
            unset($data[$file]);
            if ($request->hasFile($file)) {
                $data[$file] = $this->images->store($request->file($file), 'uploads/site', variants: false);
            } elseif ($request->boolean("remove_{$file}")) {
                $data[$file] = '';
            }
        }

        if ($request->hasFile('google_service_account')) {
            try {
                GoogleClient::storeKey((string) file_get_contents($request->file('google_service_account')->getRealPath()));
            } catch (\Throwable $e) {
                return back()->withErrors(['google_service_account' => $e->getMessage()])->withInput();
            }
        } elseif ($request->boolean('remove_google_service_account')) {
            GoogleClient::removeKey();
        }

        Setting::setMany($data);
        Cache::flush();

        return redirect()->route('admin.settings.edit', ['tab' => $request->input('tab', 'general')])->with('status', 'Settings saved.');
    }

    public function testInstagram(InstagramPublisher $instagram)
    {
        if (! $instagram->isReady()) {
            return back()->withErrors(['instagram_business_id' => 'Enter the Instagram business account ID and access token, save, then test.']);
        }
        try {
            $account = $instagram->account();
        } catch (\Throwable $e) {
            return back()->withErrors(['instagram_business_id' => $e->getMessage()]);
        }
        Setting::set('instagram_username', $account['username']);
        $message = 'Instagram connected: @'.$account['username'].($account['followers'] !== null ? ' ('.number_format($account['followers']).' followers)' : '');

        $facebook = app(FacebookPublisher::class);
        if ($facebook->isReady()) {
            try {
                $page = $facebook->page();
                $message .= ' · Facebook Page: '.$page['name'].($page['followers'] !== null ? ' ('.number_format($page['followers']).' followers)' : '');
            } catch (\Throwable $e) {
                return redirect()->route('admin.settings.edit', ['tab' => 'instagram'])->with('status', $message)->withErrors(['facebook_page_id' => $e->getMessage()]);
            }
        }

        return redirect()->route('admin.settings.edit', ['tab' => 'instagram'])->with('status', $message);
    }

    public function testOpenAi(AiImageGenerator $ai)
    {
        if ($ai->key() === '') {
            return back()->withErrors(['openai_api_key' => 'Paste the OpenAI API key, save, then test.']);
        }
        try {
            $ai->test();
        } catch (\Throwable $e) {
            return redirect()->route('admin.settings.edit', ['tab' => 'ai'])->withErrors(['openai_api_key' => $e->getMessage()]);
        }

        return redirect()->route('admin.settings.edit', ['tab' => 'ai'])->with('status', 'OpenAI key works – '.AiImageGenerator::MODEL.' is available.'.($ai->isReady() ? '' : ' Tick “Generate AI images” and save to switch it on.'));
    }

    /**
     * Writes and deletes a small test object on the configured Spaces bucket.
     */
    public function testStorage()
    {
        if (! setting('spaces_bucket')) {
            return back()->withErrors(['spaces_bucket' => 'Enter the Spaces bucket details and save first.']);
        }
        try {
            $disk = Storage::disk('spaces');
            $path = 'uploads/.connection-test-'.time().'.txt';
            if (! $disk->put($path, 'ok', ['visibility' => 'public'])) {
                throw new \RuntimeException('Write failed – check key, secret, bucket name and endpoint.');
            }
            $url = $disk->url($path);
            $disk->delete($path);
        } catch (\Throwable $e) {
            return back()->withErrors(['spaces_bucket' => 'DigitalOcean Spaces test failed: '.$e->getMessage()]);
        }

        return redirect()->route('admin.settings.edit', ['tab' => 'storage'])->with('status', 'DigitalOcean Spaces connected. Public URL pattern: '.$url);
    }
}
