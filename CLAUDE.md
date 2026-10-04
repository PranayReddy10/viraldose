# ViralDose — notes for contributors

Laravel 13 news site, MySQL in production, SQLite for tests. Tailwind 4 via Vite; `public/build` is committed.

- Run `php artisan test` and `vendor/bin/pint` before pushing.
- After changing anything in `resources/css` or `resources/js`, run `npm run build` and commit `public/build`.
- Article URLs come from `App\Support\PostUrl` — never hand-build them in views.
- Every public page sets its metadata through `App\Services\Seo` (title, description, canonical, robots, JSON-LD).
- Editor HTML must pass through `App\Services\HtmlSanitizer` before being stored.
- Cached query results must only contain classes listed in `config/cache.php` → `serializable_classes`.
- Strict Eloquent mode is on outside production: eager-load relations used in loops (see `Post::scopeForListing`).
