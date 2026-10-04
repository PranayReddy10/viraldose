<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Post;
use App\Models\Redirect;
use App\Models\Tag;
use App\Models\User;
use App\Support\PostUrl;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Imports content from a Varient (CodeIgniter) database into this application
 * and creates 301 redirects for every old article URL.
 *
 * Configure the legacy connection in config/database.php ("legacy") via
 * LEGACY_DB_* environment variables, then run:
 *
 *   php artisan import:varient --dry-run
 *   php artisan import:varient --image-base=https://viraldose.in/uploads
 */
class ImportVarientCommand extends Command
{
    protected $signature = 'import:varient
        {--connection=legacy : Database connection name of the old Varient database}
        {--image-base= : Public base URL of the old uploads folder, e.g. https://viraldose.in/uploads}
        {--old-url-format=category : URL format used by the old site: "category" (/cat/slug) or "flat" (/slug)}
        {--dry-run : Show what would be imported without writing}';

    protected $description = 'Import categories, tags, users and posts from a Varient database and create 301 redirects';

    private array $categoryMap = [];

    private array $userMap = [];

    public function handle(): int
    {
        $conn = $this->option('connection');
        $dry = (bool) $this->option('dry-run');

        try {
            DB::connection($conn)->getPdo();
        } catch (\Throwable $e) {
            $this->error("Cannot connect to '{$conn}': ".$e->getMessage());
            $this->line('Set LEGACY_DB_HOST / LEGACY_DB_DATABASE / LEGACY_DB_USERNAME / LEGACY_DB_PASSWORD in .env');

            return self::FAILURE;
        }

        $schema = Schema::connection($conn);
        foreach (['categories', 'posts', 'users'] as $table) {
            if (! $schema->hasTable($table)) {
                $this->error("Legacy table '{$table}' not found. Is this a Varient database?");

                return self::FAILURE;
            }
        }

        $this->importUsers($conn, $dry);
        $this->importCategories($conn, $dry);
        $this->importPosts($conn, $dry);

        Post::flushCache();
        Category::flushCache();
        $this->info($dry ? 'Dry run complete.' : 'Import complete.');

        return self::SUCCESS;
    }

    private function importUsers(string $conn, bool $dry): void
    {
        $rows = DB::connection($conn)->table('users')->get();
        $this->info("Users: {$rows->count()}");
        $fallback = User::where('role', User::ROLE_ADMIN)->first();

        foreach ($rows as $row) {
            $email = $row->email ?? null;
            $existing = $email ? User::where('email', $email)->first() : null;
            if ($existing) {
                $this->userMap[$row->id] = $existing->id;

                continue;
            }
            if ($dry) {
                $this->userMap[$row->id] = $fallback?->id;

                continue;
            }
            $user = User::create([
                'name' => $row->username ?? $row->name ?? 'Author '.$row->id,
                'email' => $email ?: 'legacy-'.$row->id.'@imported.local',
                'password' => Str::random(32),
                'role' => ($row->role ?? '') === 'admin' ? User::ROLE_EDITOR : User::ROLE_AUTHOR,
                'bio' => $row->about_me ?? null,
                'is_active' => true,
            ]);
            $this->userMap[$row->id] = $user->id;
        }
    }

    private function importCategories(string $conn, bool $dry): void
    {
        $rows = DB::connection($conn)->table('categories')->orderBy('parent_id')->orderBy('id')->get();
        $this->info("Categories: {$rows->count()}");

        foreach ($rows as $row) {
            $slug = $row->name_slug ?? $row->slug ?? Str::slug($row->name);
            $category = Category::where('slug', $slug)->first();
            if (! $category && ! $dry) {
                $category = Category::create([
                    'name' => $row->name,
                    'slug' => $slug,
                    'description' => $row->description ?? null,
                    'meta_title' => $row->title ?? null,
                    'meta_description' => $row->description ?? null,
                    'color' => $row->color ?? '#dc2626',
                    'sort_order' => (int) ($row->category_order ?? 0),
                    'show_in_menu' => (bool) ($row->show_at_menu ?? true),
                    'show_on_home' => (bool) ($row->show_on_homepage ?? true),
                    'parent_id' => isset($row->parent_id) && $row->parent_id ? ($this->categoryMap[$row->parent_id] ?? null) : null,
                ]);
            }
            if ($category) {
                $this->categoryMap[$row->id] = $category->id;
            }
        }
    }

    private function importPosts(string $conn, bool $dry): void
    {
        $query = DB::connection($conn)->table('posts')->orderBy('id');
        $total = (clone $query)->count();
        $this->info("Posts: {$total}");
        $bar = $this->output->createProgressBar($total);
        $imageBase = rtrim((string) $this->option('image-base'), '/');
        $hasTags = Schema::connection($conn)->hasTable('tags');
        $fallbackCategory = Category::first();
        $fallbackUser = User::where('role', User::ROLE_ADMIN)->first();
        $imported = 0;

        $query->chunk(200, function ($rows) use (&$imported, $bar, $dry, $imageBase, $hasTags, $conn, $fallbackCategory, $fallbackUser) {
            foreach ($rows as $row) {
                $bar->advance();
                if (Post::withTrashed()->where('legacy_id', $row->id)->exists()) {
                    continue;
                }
                $slug = $row->title_slug ?? $row->slug ?? Str::slug($row->title);
                $categoryId = $this->categoryMap[$row->category_id ?? 0] ?? $fallbackCategory?->id;
                $oldCategorySlug = DB::connection($conn)->table('categories')->where('id', $row->category_id ?? 0)->value('name_slug');
                $visibility = (int) ($row->visibility ?? 1) === 1;
                $status = ($row->status ?? 1) == 1 && $visibility ? Post::STATUS_PUBLISHED : Post::STATUS_DRAFT;
                $image = $row->image_big ?? $row->image_default ?? null;
                if ($image && $imageBase && ! Str::startsWith($image, ['http://', 'https://'])) {
                    $image = $imageBase.'/'.ltrim(str_replace('uploads/', '', $image), '/');
                }

                if ($dry) {
                    $imported++;

                    continue;
                }

                $post = Post::create([
                    'legacy_id' => $row->id,
                    'user_id' => $this->userMap[$row->user_id ?? 0] ?? $fallbackUser?->id,
                    'category_id' => $categoryId,
                    'title' => Str::limit($row->title, 200, ''),
                    'slug' => $slug,
                    'excerpt' => Str::limit(strip_tags((string) ($row->summary ?? '')), 500, ''),
                    'content' => $row->content ?? '',
                    'image' => $image,
                    'image_alt' => $row->image_description ?? null,
                    'status' => $status,
                    'published_at' => $row->created_at ?? now(),
                    'is_slider' => (bool) ($row->is_slider ?? false),
                    'is_featured' => (bool) ($row->is_featured ?? false),
                    'is_breaking' => (bool) ($row->is_breaking ?? false),
                    'is_recommended' => (bool) ($row->is_recommended ?? false),
                    'meta_keywords' => $row->keywords ?? null,
                    'source_url' => $row->optional_url ?? null,
                    'views' => (int) ($row->hit ?? 0),
                ]);
                $post->created_at = $row->created_at ?? now();
                $post->updated_at = $row->updated_at ?? $row->created_at ?? now();
                $post->saveQuietly();

                if ($hasTags) {
                    $tagNames = DB::connection($conn)->table('tags')->where('post_id', $row->id)->pluck('tag')->implode(',');
                    $post->tags()->sync(Tag::syncFromString($tagNames));
                }

                // Redirect old URL -> new canonical URL when they differ.
                $post->setRelation('category', Category::find($categoryId));
                $newPath = PostUrl::path($post);
                $oldPaths = $this->option('old-url-format') === 'flat'
                    ? ['/'.$slug]
                    : array_filter(['/'.$oldCategorySlug.'/'.$slug, '/'.$slug]);
                foreach ($oldPaths as $old) {
                    if (Redirect::normalizePath($old) !== $newPath) {
                        Redirect::firstOrCreate(['from_path' => Redirect::normalizePath($old)], ['to_path' => $newPath, 'status_code' => 301]);
                    }
                }
                $imported++;
            }
        });
        $bar->finish();
        $this->newLine();
        $this->info("Imported {$imported} posts.");
    }
}
