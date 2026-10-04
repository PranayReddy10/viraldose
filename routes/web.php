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
Route::get('/news-sitemap.xml', [Front\SitemapController::class, 'news'])->name('sitemap.news');
Route::get('/robots.txt', [Front\SitemapController::class, 'robots'])->name('robots');
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
        Route::post('media/upload', [Admin\MediaController::class, 'upload'])->name('media.upload');

        // Editors + admins
        Route::middleware('role:admin,editor')->group(function () {
            Route::resource('categories', Admin\CategoryController::class)->except(['show']);
            Route::resource('tags', Admin\TagController::class)->only(['index', 'store', 'update', 'destroy']);
            Route::resource('pages', Admin\PageController::class)->except(['show']);
            Route::post('comments/bulk', [Admin\CommentController::class, 'bulk'])->name('comments.bulk');
            Route::resource('comments', Admin\CommentController::class)->only(['index', 'update', 'destroy']);
        });

        // Admins only
        Route::middleware('role:admin')->group(function () {
            Route::resource('users', Admin\UserController::class)->except(['show']);
            Route::resource('ads', Admin\AdController::class)->except(['show']);
            Route::post('redirects/import', [Admin\RedirectController::class, 'import'])->name('redirects.import');
            Route::resource('redirects', Admin\RedirectController::class)->only(['index', 'store', 'destroy']);
            Route::get('settings', [Admin\SettingController::class, 'edit'])->name('settings.edit');
            Route::put('settings', [Admin\SettingController::class, 'update'])->name('settings.update');
            Route::get('subscribers/export', [Admin\SubscriberController::class, 'export'])->name('subscribers.export');
            Route::resource('subscribers', Admin\SubscriberController::class)->only(['index', 'destroy']);
            Route::resource('messages', Admin\MessageController::class)->only(['index', 'show', 'destroy']);
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
