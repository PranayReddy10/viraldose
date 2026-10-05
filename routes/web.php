<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Front;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public site
|--------------------------------------------------------------------------
*/
Route::get('/', Front\HomeController::class)->name('home');

Route::get('/category/{slug}', [Front\CategoryController::class, 'show'])->name('category.show');
Route::get('/tag/{slug}', [Front\TagController::class, 'show'])->name('tag.show');
Route::get('/author/{slug}', [Front\AuthorController::class, 'show'])->name('author.show');
Route::get('/search', Front\SearchController::class)->name('search');
Route::get('/reels', [Front\ReelController::class, 'index'])->name('reels.index');
Route::get('/reels/{slug}', [Front\ReelController::class, 'show'])->name('reels.show');
Route::get('/page/{slug}', [Front\PageController::class, 'show'])->name('page.show');

Route::get('/contact', [Front\PageController::class, 'contact'])->name('contact');
Route::post('/contact', [Front\PageController::class, 'sendContact'])->middleware('throttle:5,10')->name('contact.send');

Route::post('/newsletter', [Front\NewsletterController::class, 'subscribe'])->middleware('throttle:5,10')->name('newsletter.subscribe');
Route::get('/newsletter/unsubscribe/{token}', [Front\NewsletterController::class, 'unsubscribe'])->name('newsletter.unsubscribe');

Route::post('/posts/{post}/comments', [Front\CommentController::class, 'store'])->middleware('throttle:10,10')->name('comments.store');

// Feeds & crawl surface
Route::get('/feed', [Front\FeedController::class, 'index'])->name('feed');
Route::get('/feed/category/{slug}', [Front\FeedController::class, 'category'])->name('feed.category');
Route::get('/rss', fn () => redirect()->route('feed', [], 301));
Route::get('/sitemap.xml', [Front\SitemapController::class, 'index'])->name('sitemap.index');
Route::get('/sitemap-posts-{page}.xml', [Front\SitemapController::class, 'posts'])->whereNumber('page')->name('sitemap.posts');
Route::get('/sitemap-categories.xml', [Front\SitemapController::class, 'categories'])->name('sitemap.categories');
Route::get('/sitemap-tags.xml', [Front\SitemapController::class, 'tags'])->name('sitemap.tags');
Route::get('/sitemap-pages.xml', [Front\SitemapController::class, 'pages'])->name('sitemap.pages');
Route::get('/sitemap-authors.xml', [Front\SitemapController::class, 'authors'])->name('sitemap.authors');
Route::get('/sitemap-reels.xml', [Front\SitemapController::class, 'reels'])->name('sitemap.reels');
Route::get('/news-sitemap.xml', [Front\SitemapController::class, 'news'])->name('sitemap.news');
Route::get('/robots.txt', [Front\SitemapController::class, 'robots'])->name('robots');
Route::get('/{key}.txt', [Front\SitemapController::class, 'indexNowKey'])->where('key', '[a-f0-9]{32}')->name('indexnow.key');
Route::get('/download/{file}', [Front\PostController::class, 'download'])->whereNumber('file')->name('post.file.download');
Route::get('/ads.txt', [Front\SitemapController::class, 'adsTxt'])->name('ads.txt');

/*
|--------------------------------------------------------------------------
| Admin panel
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', [Admin\AuthController::class, 'showLogin'])->name('login');
        Route::post('login', [Admin\AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.attempt');
    });

    Route::middleware(['auth', 'role'])->group(function () {
        Route::post('logout', [Admin\AuthController::class, 'logout'])->name('logout');
        Route::get('/', Admin\DashboardController::class)->name('dashboard');

        Route::get('profile', [Admin\ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('profile', [Admin\ProfileController::class, 'update'])->name('profile.update');

        // Posts: all roles (authors are restricted to their own posts inside the controller)
        Route::post('posts/bulk', [Admin\PostController::class, 'bulk'])->name('posts.bulk');
        Route::post('posts/{id}/restore', [Admin\PostController::class, 'restore'])->whereNumber('id')->name('posts.restore');
        Route::delete('posts/{id}/force', [Admin\PostController::class, 'forceDelete'])->whereNumber('id')->name('posts.force');
        Route::resource('posts', Admin\PostController::class)->except(['show']);
        Route::delete('post-images/{image}', [Admin\PostController::class, 'destroyImage'])->name('posts.images.destroy');
        Route::delete('post-files/{file}', [Admin\PostController::class, 'destroyFile'])->name('posts.files.destroy');
        Route::post('posts/{post}/inspect', [Admin\GoogleController::class, 'inspect'])->name('posts.inspect');
        Route::post('posts/{post}/request-indexing', [Admin\GoogleController::class, 'requestIndexing'])->name('posts.index-request');
        Route::post('posts/{post}/share/instagram', [Admin\SocialShareController::class, 'instagram'])->name('posts.share.instagram');
        Route::get('posts/{post}/share/card', [Admin\SocialShareController::class, 'card'])->name('posts.share.card');
        Route::post('shares/{share}/check', [Admin\SocialShareController::class, 'check'])->name('shares.check');
        Route::delete('shares/{share}', [Admin\SocialShareController::class, 'destroy'])->name('shares.destroy');
        Route::resource('reels', Admin\ReelController::class)->except(['show']);
        Route::post('media/upload', [Admin\MediaController::class, 'upload'])->name('media.upload');

        // Editors + admins
        Route::middleware('role:admin,editor')->group(function () {
            Route::resource('categories', Admin\CategoryController::class)->except(['show']);
            Route::resource('tags', Admin\TagController::class)->only(['index', 'store', 'update', 'destroy']);
            Route::resource('pages', Admin\PageController::class)->except(['show']);
            Route::post('comments/bulk', [Admin\CommentController::class, 'bulk'])->name('comments.bulk');
            Route::resource('comments', Admin\CommentController::class)->only(['index', 'update', 'destroy']);

            // RSS feed import
            Route::post('feeds/preview', [Admin\RssFeedController::class, 'preview'])->name('feeds.preview');
            Route::post('feeds/{feed}/fetch', [Admin\RssFeedController::class, 'fetch'])->name('feeds.fetch');
            Route::resource('feeds', Admin\RssFeedController::class)->except(['show']);

            // Google Search Console / Indexing / Analytics
            Route::get('google', [Admin\GoogleController::class, 'index'])->name('google.index');
            Route::post('google/test', [Admin\GoogleController::class, 'test'])->name('google.test');
            Route::post('google/sitemaps', [Admin\GoogleController::class, 'submitSitemaps'])->name('google.sitemaps');
            Route::post('google/bulk-index', [Admin\GoogleController::class, 'bulkIndex'])->name('google.bulk');
            Route::post('google/refresh', [Admin\GoogleController::class, 'refresh'])->name('google.refresh');
        });

        // Admins only
        Route::middleware('role:admin')->group(function () {
            Route::resource('users', Admin\UserController::class)->except(['show']);
            Route::resource('ads', Admin\AdController::class)->except(['show']);
            Route::post('redirects/import', [Admin\RedirectController::class, 'import'])->name('redirects.import');
            Route::resource('redirects', Admin\RedirectController::class)->only(['index', 'store', 'destroy']);
            Route::get('settings', [Admin\SettingController::class, 'edit'])->name('settings.edit');
            Route::put('settings', [Admin\SettingController::class, 'update'])->name('settings.update');
            // The test buttons live inside the settings form, which carries _method=PUT – accept both.
            Route::match(['post', 'put'], 'settings/test-storage', [Admin\SettingController::class, 'testStorage'])->name('settings.test-storage');
            Route::match(['post', 'put'], 'settings/test-instagram', [Admin\SettingController::class, 'testInstagram'])->name('settings.test-instagram');
            Route::get('subscribers/export', [Admin\SubscriberController::class, 'export'])->name('subscribers.export');
            Route::resource('subscribers', Admin\SubscriberController::class)->only(['index', 'destroy']);
            Route::resource('messages', Admin\MessageController::class)->only(['index', 'show', 'destroy']);
            Route::get('import', [Admin\ImportController::class, 'index'])->name('import.index');
            Route::post('import/upload', [Admin\ImportController::class, 'upload'])->name('import.upload');
            Route::post('import/run', [Admin\ImportController::class, 'run'])->name('import.run');
            Route::post('import/fetch-images', [Admin\ImportController::class, 'fetchImages'])->name('import.fetch-images');
            Route::delete('import/{file}', [Admin\ImportController::class, 'destroy'])->name('import.destroy');
        });
    });
});

/*
|--------------------------------------------------------------------------
| Catch-all article routes (must stay last)
|--------------------------------------------------------------------------
| /{category}/{slug}  canonical article URL
| /{slug}             legacy flat URL (page or post) – 301s to canonical
*/
$slug = '[a-z0-9]+(?:-[a-z0-9]+)*';
Route::get('/{category}/{slug}', [Front\PostController::class, 'show'])
    ->where(['category' => $slug, 'slug' => $slug])->name('post.show');
Route::get('/{slug}', [Front\PostController::class, 'flat'])
    ->where('slug', $slug)->name('post.flat');
