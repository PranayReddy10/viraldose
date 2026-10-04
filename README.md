# ViralDose — SEO-first news & magazine platform

A ground-up rebuild of [viraldose.in](https://viraldose.in) on **Laravel 13 + MySQL**, replacing the buggy
Varient install. It is designed around two goals: **mobile-first** and **getting indexed** (the old site sat
in Search Console as *"Crawled – currently not indexed"*).

- Public site: home with hero/featured/latest/category sections, article pages, category & sub-category
  archives, tags, author profiles, search, static pages, contact form, newsletter, comments (moderated).
- Admin panel (`/admin`): posts with rich editor + image upload, scheduling, SEO panel with Google snippet
  preview, categories (2 levels), tags, pages, comment moderation, users & roles (admin / editor / author),
  ad slots (AdSense or banners), 301 redirect manager with CSV import, subscribers export, contact inbox, settings.
- SEO built in: canonical URLs, one URL per page (301 for trailing slash / uppercase / legacy form), meta
  title & description per post/category/page, Open Graph + Twitter cards, JSON-LD (`NewsArticle`,
  `BreadcrumbList`, `WebSite` + `SearchAction`, `NewsMediaOrganization`, `ProfilePage`, `CollectionPage`),
  XML sitemap index (posts / categories / tags / pages / authors) + **Google News sitemap**, RSS feeds,
  dynamic `robots.txt` and `ads.txt`, `noindex` on search/thin tag pages/previews, responsive WebP images
  with `srcset`, `width`/`height` and lazy loading, `Last-Modified` headers, security headers.
- Fast: no front-end framework, ~10 KB CSS (gzipped), system fonts, query caching, compiled assets committed.

## Requirements

- PHP 8.3+ with `pdo_mysql`, `gd`, `mbstring`, `dom`, `fileinfo`, `openssl`
- MySQL 8 / MariaDB 10.6+
- Composer 2
- Node 20+ only if you want to rebuild CSS/JS (`public/build` is committed, so hosting without Node works)

## Local setup

```bash
composer install
cp .env.example .env            # edit DB_* and APP_URL
php artisan key:generate
php artisan migrate --seed      # admin user + categories + pages + settings
php artisan storage:link
npm install && npm run build    # optional – only if you change resources/css or resources/js
php artisan serve
```

Sample content for a local preview: `php artisan db:seed --class=DemoContentSeeder`

Admin login: `ADMIN_EMAIL` / `ADMIN_PASSWORD` from `.env` (defaults to `admin@viraldose.in` / `ChangeMe123!`
— **change it after first login** under *My profile*). Create more admins any time:
`php artisan make:admin you@viraldose.in --name="Your Name"`.

Tests: `php artisan test` (41+ feature/unit tests, SQLite in-memory).  Code style: `vendor/bin/pint`.

## Production deployment (cPanel / VPS)

1. Upload the repository (or `git clone`) **outside** `public_html`, e.g. `/home/USER/viraldose`.
2. Point the domain's document root to `/home/USER/viraldose/public`. If you cannot change the document
   root on shared hosting, move the contents of `public/` into `public_html/` and edit `public_html/index.php`
   so the two `require` paths point at `../viraldose/vendor/autoload.php` and `../viraldose/bootstrap/app.php`.
3. `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://viraldose.in`, MySQL credentials,
   `CACHE_STORE=file` (or `redis`), `SESSION_SECURE_COOKIE=true`.
4. Run:
   ```bash
   composer install --no-dev --optimize-autoloader
   php artisan key:generate
   php artisan migrate --force --seed
   php artisan storage:link
   php artisan optimize          # config/route/view cache
   ```
5. Cron (scheduled posts, cache housekeeping): `* * * * * php /home/USER/viraldose/artisan schedule:run >> /dev/null 2>&1`
6. Make `storage/` and `bootstrap/cache/` writable by the web user.
7. Enable HTTPS and the non-www/HTTPS redirect block in `public/.htaccess` (or your nginx config). Serve
   `/storage` and `/build` as static files with long cache headers (the `.htaccess` already does this on Apache).
8. After any deploy: `php artisan optimize:clear && php artisan optimize`.

## Migrating from the old Varient site

Keeping the old URLs alive with 301s is the single most important step for recovering rankings.

1. Add the old database as the `legacy` connection in `.env` (`LEGACY_DB_*`).
2. Dry run: `php artisan import:varient --dry-run`
3. Import: `php artisan import:varient --image-base=https://viraldose.in/uploads --old-url-format=category`
   (use `--old-url-format=flat` if old article URLs looked like `viraldose.in/post-slug`).
   This imports users, categories, tags and posts and **creates a 301 redirect for every old article URL**
   whose path differs from the new canonical URL. Column names follow Varient's schema; if your install was
   customised, adjust `app/Console/Commands/ImportVarientCommand.php`.
4. Any other old URLs (tag pages, pagination, AMP, `index.php?...`): export them from Search Console → Pages,
   build a `from,to` CSV and import it under **Admin → Redirects** or with `php artisan redirects:import file.csv`.
5. Old images stay on their original URLs when you pass `--image-base`; to host them locally copy the uploads
   folder into `storage/app/public/` and run `php artisan images:regenerate`.

### Article URL format

Default is `/{category}/{post-slug}` (set under *Settings → Content*). The other form (`/{post-slug}`) always
301-redirects to the canonical one, so both old link styles keep working.

## Fixing "Crawled – currently not indexed"

That status means Googlebot fetched the pages but decided they were not worth indexing. The technical causes
are handled by this build (one canonical URL per story, no duplicate/thin parameter pages, correct status
codes, structured data, sitemaps, fast mobile pages). What you still have to do, in order:

1. **Launch with redirects in place** (see above) so no indexed URL turns into a 404.
2. In **Search Console**: remove the old sitemap, submit `https://viraldose.in/sitemap.xml` and
   `https://viraldose.in/news-sitemap.xml`; add the verification code under *Settings → SEO*.
3. Use *URL Inspection → Request indexing* on the home page, each category and your 10 best articles.
4. **Content quality is the remaining lever.** Google de-prioritises sites whose articles are short rewrites of
   other outlets. Aim for original reporting/angles, 400+ words, a real author with a bio (author pages and
   `Person` schema are built in), a unique featured image with alt text, 2–3 internal links per article
   (the editor supports links; related/trending blocks add more automatically).
5. Publish consistently (news sitemap only lists the last 48 h), keep categories focused, and `noindex` or
   merge pages that have almost no content (tag pages with < 3 posts are already `noindex`).
6. Fill in *Settings → SEO*: organisation type, publisher logo (600×60 PNG) and social profiles — these feed
   the `NewsMediaOrganization` schema Google News and Discover look at.
7. Set up Bing Webmaster too (free extra traffic; verification field is in Settings).

Give it 2–6 weeks after relaunch; watch *Pages → Not indexed* shrink in Search Console.

## Project layout

```
app/Http/Controllers/Front   public pages, feeds, sitemaps
app/Http/Controllers/Admin   admin panel
app/Http/Middleware          NormalizeUrl (301 hygiene), EnsureUserHasRole, SecurityHeaders
app/Services                 Seo (meta + JSON-LD builder), ImageService (WebP variants), HtmlSanitizer
app/Support/PostUrl.php      single place that decides an article's URL
app/Console/Commands         import:varient, redirects:import, images:regenerate, make:admin
resources/views/front        Blade templates (Tailwind 4)
resources/views/admin        admin templates
database/seeders             production seed + DemoContentSeeder
tests/                       feature tests for public pages, crawl surface, admin
```

## Roles

| Role   | Can do |
|--------|--------|
| admin  | everything, including users, ads, redirects, settings |
| editor | all posts, categories, tags, pages, comment moderation |
| author | create/edit own posts (cannot pin to slider/featured/breaking) |
