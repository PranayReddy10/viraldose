<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Makes a news thumbnail (headline in coloured banners over a symbolic, photorealistic
 * scene) with OpenAI's image API. Used when an article has no real photo. The API key
 * lives encrypted in Settings → AI Images and never leaves the server.
 */
class AiImageGenerator
{
    public const ENDPOINT = 'https://api.openai.com/v1/images/generations';

    public const MODEL = 'gpt-image-1-mini';

    /** Always appended – keeps the images honest and safe for a news site. */
    public const RULES = 'No other words anywhere, no logos, no brand marks, no watermarks. '
        .'No identifiable faces of any real person: show people only as silhouettes, from behind or at a distance. '
        .'No blood, injuries, bodies or graphic violence; for accidents, attacks or disasters show the aftermath symbolically. '
        .'No national or party flags that assign blame. It must look like an illustration, not a real photo of the event.';

    public function key(): string
    {
        $raw = (string) setting('openai_api_key');
        if ($raw === '') {
            return '';
        }
        try {
            return Crypt::decryptString($raw);
        } catch (\Throwable) {
            return $raw;
        }
    }

    public function isReady(): bool
    {
        return (bool) setting('ai_images_enabled') && $this->key() !== '';
    }

    /**
     * Splits a headline into two banner lines: at a colon/dash if there is one,
     * otherwise at the comma or word boundary nearest the middle.
     *
     * @return array{0: string, 1: string}
     */
    public static function splitHeadline(string $title): array
    {
        $title = trim(preg_replace('/\s+/', ' ', $title));
        foreach ([':', ' – ', ' — ', ' - '] as $sep) {
            $pos = mb_strpos($title, $sep);
            if ($pos !== false && $pos > 8 && $pos < mb_strlen($title) - 8) {
                return [trim(mb_substr($title, 0, $pos + ($sep === ':' ? 1 : 0))), trim(mb_substr($title, $pos + mb_strlen($sep)))];
            }
        }
        $comma = mb_strrpos($title, ', ');
        if ($comma !== false && $comma > mb_strlen($title) * 0.35 && $comma < mb_strlen($title) * 0.8) {
            return [trim(mb_substr($title, 0, $comma)), trim(mb_substr($title, $comma + 2))];
        }
        $words = explode(' ', $title);
        if (count($words) < 4) {
            return [$title, ''];
        }
        $best = 1;
        $bestDiff = PHP_INT_MAX;
        for ($i = 1; $i < count($words); $i++) {
            $diff = abs(mb_strlen(implode(' ', array_slice($words, 0, $i))) - mb_strlen(implode(' ', array_slice($words, $i))));
            if ($diff < $bestDiff) {
                [$best, $bestDiff] = [$i, $diff];
            }
        }

        return [implode(' ', array_slice($words, 0, $best)), implode(' ', array_slice($words, $best))];
    }

    public function prompt(string $headline, ?string $scene = null, ?string $category = null): string
    {
        [$one, $two] = self::splitHeadline(Str::limit($headline, 110, ''));
        $quote = fn (string $s) => str_replace('"', "'", $s);

        $text = $two === ''
            ? "Headline text, spelled EXACTLY, in one rounded banner across the top: \"{$quote($one)}\" in bright yellow extra-bold letters on a dark red rounded banner.\n"
            : "Headline text, spelled EXACTLY, in two stacked rounded banners across the top:\n"
                ."line 1: \"{$quote($one)}\" in white bold sans-serif on a navy rounded banner;\n"
                ."line 2: \"{$quote($two)}\" in bright yellow extra-bold letters on a dark red rounded banner.\n";

        $scene = trim((string) $scene) !== ''
            ? 'Scene: '.Str::limit(trim($scene), 900)
            : 'Scene: a symbolic, photorealistic scene that clearly illustrates this news story'.($category ? " ({$category})" : '').', with a recognisable real location or landmark if the story names one.';

        return "Eye-catching Indian TV-news style thumbnail, landscape, photorealistic illustration, vivid colours, dramatic lighting.\n"
            .$text.$scene."\n".self::RULES;
    }

    /**
     * Calls the API and returns JPEG bytes (1536×1024).
     *
     * @throws RuntimeException
     */
    public function generate(string $headline, ?string $scene = null, ?string $category = null): string
    {
        if ($this->key() === '') {
            throw new RuntimeException('Add the OpenAI API key under Settings → AI Images.');
        }
        @set_time_limit(300);

        $r = Http::timeout(180)->withToken($this->key())->post(self::ENDPOINT, [
            'model' => self::MODEL,
            'prompt' => $this->prompt($headline, $scene, $category),
            'size' => '1536x1024',
            'quality' => in_array(setting('ai_images_quality'), ['low', 'medium', 'high'], true) ? setting('ai_images_quality') : 'medium',
            'output_format' => 'jpeg',
            'output_compression' => 85,
            'n' => 1,
        ]);
        if (! $r->successful()) {
            throw new RuntimeException('OpenAI: '.($r->json('error.message') ?? Str::limit($r->body(), 300)));
        }
        $bytes = base64_decode((string) $r->json('data.0.b64_json'), true);
        if (! $bytes || ! @getimagesizefromstring($bytes)) {
            throw new RuntimeException('OpenAI returned no image.');
        }

        return $bytes;
    }

    /** Cheap key check (lists models, no image is made). */
    public function test(): void
    {
        $r = Http::timeout(20)->withToken($this->key())->get('https://api.openai.com/v1/models/'.self::MODEL);
        if (! $r->successful()) {
            throw new RuntimeException('OpenAI: '.($r->json('error.message') ?? Str::limit($r->body(), 300)));
        }
    }
}
