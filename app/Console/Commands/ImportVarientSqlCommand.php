<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Post;
use App\Models\Redirect;
use App\Models\Tag;
use App\Models\User;
use App\Services\EmbedRenderer;
use App\Services\HtmlSanitizer;
use App\Services\ImageService;
use App\Support\PostUrl;
use App\Support\SqlDumpReader;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Imports posts from a Varient 2.x SQL dump (phpMyAdmin export of the `posts`
 * table). Optional dumps of `categories`, `images` and `users` are used when
 * present in the same file or in extra files passed with --extra.
 *
 *   php artisan import:varient-sql ~/posts.sql --dry-run
 *   php artisan import:varient-sql ~/posts.sql --category-map="1:news,6:sports" --default-category=news
 *   php artisan import:varient-sql ~/posts.sql --update --category-map="1:news,6:sports"
 */
class ImportVarientSqlCommand extends Command
{
    protected $signature = 'import:varient-sql
        {file : SQL dump containing the posts table}
        {--extra=* : Additional SQL dumps (categories.sql, images.sql, users.sql)}
        {--category-map= : Legacy category id => new category slug, e.g. "1:news,6:sports,7:entertainment"}
        {--default-category=news : Slug of the category used when a legacy id is not mapped}
        {--author= : Email of the author to assign posts to (default: first admin)}
        {--image-base= : URL or storage path prefix for legacy image paths (e.g. uploads-old or https://old.site/uploads)}
        {--download-images : Download remote featured images into local storage}
        {--old-url-format=flat : How the old site built URLs: flat (/slug) or category (/cat/slug)}
        {--update : Re-apply category/author mapping to posts already imported (matched by legacy id)}
        {--dry-run : Report what would happen without writing}';

    protected $description = 'Import posts from a Varient SQL dump file (no database connection to the old site needed)';

    private array $categoryMap = [];

    private array $legacyCategories = [];

    private array $legacyImages = [];

    private array $legacyUsers = [];

    /** @var array<int, Category> */
    private array $categoryCache = [];

    public function handle(HtmlSanitizer $sanitizer, ImageService $images): int
    {
        $file = $this->argument('file');
        try {
            $readers = array_map(fn ($f) => new SqlDumpReader($f), array_merge([$file], $this->option('extra')));
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');
        $this->categoryMap = [];
        $this->categoryCache = [];
        $this->loadLookups($readers);
        $this->buildCategoryMap();

        $defaultCategory = Category::where('slug', $this->option('default-category'))->first() ?? Category::first();
        if (! $defaultCategory) {
            $this->error('No categories exist yet. Run `php artisan db:seed` first.');

            return self::FAILURE;
        }
        $author = $this->option('author')
            ? User::where('email', $this->option('author'))->first()
            : User::where('role', User::ROLE_ADMIN)->orderBy('id')->first();
        if (! $author) {
            $this->error('Author not found.');

            return self::FAILURE;
        }

        $stats = ['imported' => 0, 'updated' => 0, 'skipped' => 0, 'images_downloaded' => 0, 'unmapped' => []];
        $rows = [];
        foreach ($readers as $reader) {
            foreach ($reader->rows('posts') as $row) {
                $rows[] = $row;
            }
        }
        if (! $rows) {
            $this->error('No posts found in the dump.');

            return self::FAILURE;
        }
        $this->info(count($rows).' posts found in the dump.');
        $bar = $this->output->createProgressBar(count($rows));

        foreach ($rows as $row) {
            $bar->advance();
            $legacyId = (int) $row['id'];
            $category = $this->mapCategory($row['category_id'] ?? null, $defaultCategory, $stats);
            $existing = Post::withTrashed()->where('legacy_id', $legacyId)->first();

            if ($existing) {
                if ($this->option('update') && ! $dry) {
                    $existing->forceFill(['category_id' => $category->id])->saveQuietly();
                    $stats['updated']++;
                } else {
                    $stats['skipped']++;
                }

                continue;
            }
            if ($dry) {
                $stats['imported']++;

                continue;
            }

            $isPublished = (int) ($row['status'] ?? 1) === 1 && (int) ($row['visibility'] ?? 1) === 1;
            $createdAt = $this->date($row['created_at'] ?? null) ?? now();
            $image = $this->resolveImage($row, $images, $stats);
            $video = $row['video_url'] ?: $this->videoFromEmbed($row['video_embed_code'] ?? null);
            $type = match ($row['post_type'] ?? 'article') {
                'video' => 'video', 'gallery' => 'gallery', 'audio' => 'audio', default => 'article',
            };
            if ($type === 'video' && ! EmbedRenderer::video($video)) {
                $type = 'article';
            }

            $post = new Post([
                'user_id' => $this->mapUser($row['user_id'] ?? null, $author),
                'category_id' => $category->id,
                'language' => setting('language', 'en'),
                'post_type' => $type,
                'video_url' => $type === 'video' ? $video : null,
                'title' => Str::limit(trim(html_entity_decode($row['title'] ?? 'Untitled', ENT_QUOTES | ENT_HTML5, 'UTF-8')), 200, ''),
                'slug' => $row['slug'] ?: Str::slug($row['title'] ?? ''),
                'excerpt' => Str::limit(trim(strip_tags(html_entity_decode($row['summary'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'))), 500, ''),
                'content' => $sanitizer->clean($this->normalizeContent($row['content'] ?? '')),
                'image' => $image,
                'image_alt' => Str::limit(trim((string) ($row['image_description'] ?? '')), 200, '') ?: null,
                'status' => $isPublished ? Post::STATUS_PUBLISHED : Post::STATUS_DRAFT,
                'published_at' => $createdAt,
                'meta_keywords' => Str::limit(trim((string) ($row['keywords'] ?? '')), 255, '') ?: null,
                'source_url' => $row['optional_url'] ?: ($row['post_url'] ?: null),
                'legacy_id' => $legacyId,
            ]);
            $post->created_at = $createdAt;
            $post->updated_at = $this->date($row['updated_at'] ?? null) ?? $createdAt;
            $post->save();
            Post::withoutTimestamps(fn () => $post->forceFill(['views' => (int) ($row['pageviews'] ?? 0)])->saveQuietly());

            // Keywords double as tags (first 6) so archives and related-post linking work.
            $keywords = collect(explode(',', (string) ($row['keywords'] ?? '')))->map(fn ($k) => trim($k))->filter()->take(6)->implode(',');
            if ($keywords) {
                $post->tags()->sync(Tag::syncFromString($keywords));
            }

            $this->redirects($post, $row);
            $stats['imported']++;
        }
        $bar->finish();
        $this->newLine(2);

        Post::flushCache();
        Category::flushCache();

        $this->table(['Imported', 'Updated', 'Skipped (already imported)', 'Images downloaded'],
            [[$stats['imported'], $stats['updated'], $stats['skipped'], $stats['images_downloaded']]]);
        if ($stats['unmapped']) {
            $this->warn('Legacy category ids without a mapping (imported into "'.$defaultCategory->name.'"):');
            foreach ($stats['unmapped'] as $id => $count) {
                $name = $this->legacyCategories[$id]['name'] ?? '';
                $this->line(sprintf('  %-4s %-30s %d posts', $id, $name, $count));
            }
            $this->line('Re-run with --update --category-map="ID:slug,ID:slug" to move them to the right categories.');
        }
        if ($dry) {
            $this->info('Dry run – nothing was written.');
        }

        return self::SUCCESS;
    }

    /**
     * Reads optional categories / images / users tables from the dumps.
     *
     * @param  array<int, SqlDumpReader>  $readers
     */
    private function loadLookups(array $readers): void
    {
        foreach ($readers as $reader) {
            $tables = $reader->tables();
            if (in_array('categories', $tables, true)) {
                foreach ($reader->rows('categories') as $c) {
                    $this->legacyCategories[(int) $c['id']] = ['name' => $c['name'] ?? ('Category '.$c['id']), 'slug' => $c['name_slug'] ?? $c['slug'] ?? Str::slug($c['name'] ?? ''), 'parent_id' => (int) ($c['parent_id'] ?? 0), 'description' => $c['description'] ?? null, 'color' => $c['color'] ?? null];
                }
            }
            if (in_array('images', $tables, true)) {
                foreach ($reader->rows('images') as $i) {
                    $this->legacyImages[(int) $i['id']] = $i['image_big'] ?? $i['image_default'] ?? $i['image_mid'] ?? null;
                }
            }
            if (in_array('users', $tables, true)) {
                foreach ($reader->rows('users') as $u) {
                    $this->legacyUsers[(int) $u['id']] = ['name' => $u['username'] ?? $u['name'] ?? null, 'email' => $u['email'] ?? null];
                }
            }
        }
        if ($this->legacyCategories) {
            $this->info(count($this->legacyCategories).' legacy categories found – unmapped ones will be created automatically.');
        }
    }

    private function buildCategoryMap(): void
    {
        foreach ((array) config('varient-import.category_map', []) as $id => $slug) {
            $this->categoryMap[(int) $id] = Str::slug($slug);
        }
        foreach (array_filter(explode(',', (string) $this->option('category-map'))) as $pair) {
            [$id, $slug] = array_pad(explode(':', trim($pair), 2), 2, null);
            if ($id !== null && $slug) {
                $this->categoryMap[(int) $id] = Str::slug($slug);
            }
        }
    }

    private function mapCategory(?string $legacyId, Category $default, array &$stats): Category
    {
        $legacyId = (int) $legacyId;
        if (isset($this->categoryCache[$legacyId])) {
            return $this->categoryCache[$legacyId];
        }
        if (isset($this->categoryMap[$legacyId])) {
            $category = Category::firstOrCreate(['slug' => $this->categoryMap[$legacyId]], ['name' => Str::headline($this->categoryMap[$legacyId]), 'show_on_home' => false, 'color' => '#dc2626']);

            return $this->categoryCache[$legacyId] = $category;
        }
        if (isset($this->legacyCategories[$legacyId]) && ! $this->option('dry-run')) {
            $lc = $this->legacyCategories[$legacyId];
            $parent = $lc['parent_id'] && isset($this->legacyCategories[$lc['parent_id']])
                ? Category::firstOrCreate(['slug' => $this->legacyCategories[$lc['parent_id']]['slug']], ['name' => $this->legacyCategories[$lc['parent_id']]['name']])
                : null;
            $category = Category::firstOrCreate(['slug' => $lc['slug'] ?: Str::slug($lc['name'])], array_filter(['name' => $lc['name'], 'description' => $lc['description'], 'color' => $lc['color'], 'parent_id' => $parent?->id]));

            return $this->categoryCache[$legacyId] = $category;
        }
        $stats['unmapped'][$legacyId] = ($stats['unmapped'][$legacyId] ?? 0) + 1;

        return $this->categoryCache[$legacyId] = $default;
    }

    private function mapUser(?string $legacyId, User $fallback): int
    {
        $legacy = $this->legacyUsers[(int) $legacyId] ?? null;
        if ($legacy && $legacy['email'] && ($user = User::where('email', $legacy['email'])->first())) {
            return $user->id;
        }

        return $fallback->id;
    }

    private function resolveImage(array $row, ImageService $images, array &$stats): ?string
    {
        $url = trim((string) ($row['image_url'] ?? ''));
        if ($url === '' && ! empty($row['image_id']) && isset($this->legacyImages[(int) $row['image_id']])) {
            $path = ltrim($this->legacyImages[(int) $row['image_id']], '/');
            $base = rtrim((string) $this->option('image-base'), '/');
            if ($base === '') {
                return null;
            }
            $url = Str::startsWith($base, ['http://', 'https://'])
                ? $base.'/'.preg_replace('#^uploads/#', '', $path)
                : $base.'/'.preg_replace('#^uploads/#', '', $path); // local path on the public disk
            if (! Str::startsWith($url, ['http://', 'https://'])) {
                return $url;
            }
        }
        if ($url === '') {
            return null;
        }
        if ($this->option('download-images') && Str::startsWith($url, ['http://', 'https://'])) {
            try {
                $response = Http::timeout(20)->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; ViralDoseImporter/1.0)'])->get($url);
                $type = (string) $response->header('Content-Type');
                if ($response->successful() && Str::startsWith($type, 'image/') && strlen($response->body()) > 1000) {
                    $ext = match (true) {
                        str_contains($type, 'png') => 'png', str_contains($type, 'webp') => 'webp', str_contains($type, 'gif') => 'gif', default => 'jpg',
                    };
                    $dir = 'uploads/imported/'.Carbon::parse($row['created_at'] ?? now())->format('Y/m');
                    $name = Str::limit(Str::slug(pathinfo(parse_url($url, PHP_URL_PATH) ?: 'image', PATHINFO_FILENAME)), 50, '').'-'.Str::lower(Str::random(6)).'.'.$ext;
                    Storage::disk(ImageService::LOCAL_DISK)->put("{$dir}/{$name}", $response->body());
                    $images->generateVariants("{$dir}/{$name}");
                    $stats['images_downloaded']++;

                    return "{$dir}/{$name}";
                }
            } catch (\Throwable) {
                // keep the remote URL
            }
        }

        return $url;
    }

    private function videoFromEmbed(?string $embed): ?string
    {
        if (! $embed) {
            return null;
        }
        if (preg_match('#src="([^"]+)"#', $embed, $m) && EmbedRenderer::youtubeId($m[1])) {
            return 'https://www.youtube.com/watch?v='.EmbedRenderer::youtubeId($m[1]);
        }

        return null;
    }

    /**
     * TinyMCE output from Varient: strip editor attributes/inline fonts so the
     * site typography applies; the sanitizer removes the rest.
     */
    private function normalizeContent(string $html): string
    {
        $html = preg_replace('/\s(data-start|data-end|data-col-size|dir)="[^"]*"/i', '', $html);
        $html = preg_replace('/\sstyle="[^"]*"/i', '', $html);
        $html = preg_replace('/<span>(.*?)<\/span>/is', '$1', $html);
        $html = preg_replace('/<p>(\s|&nbsp;|<br\s*\/?>)*<\/p>/i', '', $html);
        $html = str_replace(["\r\n", "\r"], "\n", $html);

        return $html;
    }

    private function redirects(Post $post, array $row): void
    {
        $post->setRelation('category', Category::find($post->category_id));
        $new = PostUrl::path($post);
        $old = ['/'.$post->slug];
        if ($this->option('old-url-format') === 'category') {
            $legacySlug = $this->legacyCategories[(int) ($row['category_id'] ?? 0)]['slug'] ?? null;
            if ($legacySlug) {
                $old[] = '/'.$legacySlug.'/'.$post->slug;
            }
        }
        foreach ($old as $path) {
            $normalized = Redirect::normalizePath($path);
            if ($normalized !== $new && $normalized !== '/'.$post->slug) {
                // "/slug" already 301s to the canonical URL via the flat route; only odd forms need a row.
                Redirect::firstOrCreate(['from_path' => $normalized], ['to_path' => $new, 'status_code' => 301]);
            }
        }
    }

    private function date(?string $value): ?Carbon
    {
        if (! $value || str_starts_with($value, '0000')) {
            return null;
        }
        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
