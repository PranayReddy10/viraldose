<?php

use App\Exceptions\StorageUploadException;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\NormalizeUrl;
use App\Http\Middleware\SecurityHeaders;
use App\Models\Redirect;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Global so it runs before route matching (uppercase/trailing-slash URLs would otherwise 404).
        $middleware->prepend(NormalizeUrl::class);
        $middleware->web(append: [SecurityHeaders::class]);
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);
        $middleware->redirectGuestsTo(fn () => route('admin.login'));
        $middleware->redirectUsersTo(fn () => route('admin.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Media could not be written (local permissions or DigitalOcean Spaces): show why, keep the form.
        $exceptions->render(function (StorageUploadException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withInput()->withErrors(['image' => $e->getMessage()]);
        });

        // Legacy URL support: before showing a 404, consult the redirects table
        // so links from the old site (and Google's index) keep working with 301s.
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->isMethod('GET') && ! $request->expectsJson()) {
                try {
                    $redirect = Redirect::findForPath($request->getPathInfo());
                } catch (Throwable) {
                    $redirect = null;
                }
                if ($redirect) {
                    Redirect::withoutTimestamps(fn () => $redirect->increment('hits'));

                    return redirect($redirect->to_path, $redirect->status_code ?: 301);
                }
            }

            return null;
        });
    })->create();

// Shared hosting (e.g. Bluehost public_html): the public folder may live outside the project.
if ($publicPath = $_ENV['APP_PUBLIC_PATH'] ?? $_SERVER['APP_PUBLIC_PATH'] ?? getenv('APP_PUBLIC_PATH')) {
    $app->usePublicPath($publicPath);
}

return $app;
