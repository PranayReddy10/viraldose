<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public const CACHE_KEY = 'settings.all';

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'site_name' => 'ViralDose',
            'site_tagline' => 'Latest News, Viral Stories & Trending Updates',
            'site_description' => 'ViralDose brings you the latest breaking news, viral stories, entertainment, sports, technology and trending updates from India and around the world.',
            'site_keywords' => 'news, viral news, breaking news, trending, india news, entertainment, sports, technology',
            'logo' => '',
            'logo_dark' => '',
            'favicon' => '',
            'default_og_image' => '',
            'contact_email' => 'contact@viraldose.in',
            'contact_address' => '',
            'footer_about' => 'ViralDose is an independent digital news platform covering breaking news, viral stories and trending topics.',
            'copyright' => '© {year} ViralDose. All rights reserved.',
            'facebook_url' => '',
            'twitter_url' => '',
            'instagram_url' => '',
            'youtube_url' => '',
            'telegram_url' => '',
            'whatsapp_url' => '',
            'twitter_handle' => '',
            'posts_per_page' => 12,
            'post_url_format' => 'category',
            'show_breaking_bar' => 1,
            'comments_enabled' => 1,
            'comments_auto_approve' => 0,
            'google_analytics_id' => '',
            'google_site_verification' => '',
            'bing_site_verification' => '',
            'adsense_client_id' => '',
            'ads_txt' => '',
            'head_scripts' => '',
            'body_scripts' => '',
            'organization_type' => 'NewsMediaOrganization',
            'organization_founded' => '',
            'publisher_logo' => '',
            'google_news_publication_name' => 'ViralDose',
            'language' => 'en',
            'languages' => 'en:English,hi:Hindi,te:Telugu,ta:Tamil',
            'timezone_display' => 'Asia/Kolkata',
            // Storage
            'storage_driver' => 'public',
            'spaces_key' => '',
            'spaces_secret' => '',
            'spaces_region' => 'blr1',
            'spaces_bucket' => '',
            'spaces_endpoint' => 'https://blr1.digitaloceanspaces.com',
            'spaces_cdn_url' => '',
            // Google & indexing
            'google_sc_site_url' => '',
            'ga4_property_id' => '',
            'google_auto_index' => 0,
            'indexnow_enabled' => 1,
            'indexnow_key' => '',
            // Code injection
            'body_start_scripts' => '',
            'custom_css' => '',
            // Ads
            'ads_enabled' => 1,
            'adsense_auto_ads' => 0,
            'mobile_sticky_ad' => 1,
            // Instagram
            'instagram_business_id' => '',
            'instagram_access_token' => '',
            'instagram_username' => 'viraldose_news',
            'instagram_auto_share' => 0,
            'instagram_hashtags' => '#viraldose #news #breakingnews #india',
            'instagram_caption_template' => "{title}\n\n{excerpt}\n\nRead the full story on viraldose.in (link in bio)\n\n{hashtags}",
            // AI images
            'ai_images_enabled' => 0,
            'openai_api_key' => '',
            'ai_images_quality' => 'medium',
            // Reels
            'reels_enabled' => 1,
            'reels_per_page' => 10,
            'reels_ad_every' => 4,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function all_cached(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            try {
                $stored = static::query()->pluck('value', 'key')->all();
            } catch (\Throwable) {
                $stored = [];
            }

            return array_merge(static::defaults(), $stored);
        });
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = static::all_cached();
        if (array_key_exists($key, $all) && $all[$key] !== null && $all[$key] !== '') {
            return $all[$key];
        }

        return $default ?? (static::defaults()[$key] ?? null);
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => is_array($value) ? json_encode($value) : $value]);
        static::flush();
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public static function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            static::updateOrCreate(['key' => $key], ['value' => is_array($value) ? json_encode($value) : $value]);
        }
        static::flush();
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Site languages as code => label, from the "languages" setting.
     *
     * @return array<string, string>
     */
    public static function languages(): array
    {
        $out = [];
        foreach (explode(',', (string) static::get('languages', 'en:English')) as $pair) {
            [$code, $label] = array_pad(explode(':', trim($pair), 2), 2, null);
            if ($code) {
                $out[trim($code)] = trim($label ?: strtoupper($code));
            }
        }

        return $out ?: ['en' => 'English'];
    }
}
