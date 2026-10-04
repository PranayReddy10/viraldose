<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\ImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SettingController extends Controller
{
    public function __construct(private ImageService $images) {}

    public function edit()
    {
        return view('admin.settings', ['settings' => Setting::all_cached()]);
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
            'body_scripts' => ['nullable', 'string', 'max:20000'],
            'organization_type' => ['required', 'in:NewsMediaOrganization,Organization'],
            'organization_founded' => ['nullable', 'string', 'max:20'],
            'google_news_publication_name' => ['nullable', 'string', 'max:100'],
            'language' => ['required', 'string', 'max:10'],
            'logo' => ['nullable', 'image', 'max:1024'],
            'logo_dark' => ['nullable', 'image', 'max:1024'],
            'favicon' => ['nullable', 'file', 'mimes:png,ico,svg', 'max:512'],
            'default_og_image' => ['nullable', 'image', 'max:2048'],
            'publisher_logo' => ['nullable', 'image', 'max:1024'],
        ]);

        foreach (['show_breaking_bar', 'comments_enabled', 'comments_auto_approve'] as $flag) {
            $data[$flag] = $request->boolean($flag) ? 1 : 0;
        }
        foreach (['logo', 'logo_dark', 'favicon', 'default_og_image', 'publisher_logo'] as $file) {
            unset($data[$file]);
            if ($request->hasFile($file)) {
                $data[$file] = $request->file($file)->store('uploads/site', 'public');
            } elseif ($request->boolean("remove_{$file}")) {
                $data[$file] = '';
            }
        }

        Setting::setMany($data);
        Cache::flush();

        return back()->with('status', 'Settings saved.');
    }
}
