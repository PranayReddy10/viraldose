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
            'timezone_display' => 'Asia/Kolkata',
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
}
