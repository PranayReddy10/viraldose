<?php

use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\NormalizeUrl;
use App\Http\Middleware\SecurityHeaders;
use App\Models\Redirect;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
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
