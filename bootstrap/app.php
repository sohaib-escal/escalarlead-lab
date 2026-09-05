<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->render(function (\Throwable $e, Request $request) {
            if ($request->expectsJson() || $request->header('X-Inertia')) {
                return response()->json([
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                    'file' => $e->getFile() . ':' . $e->getLine(),
                    'trace' => explode("\n", $e->getTraceAsString()),
                ], 500);
            }

            if (isset($_GET['debug']) || env('APP_DEBUG', true)) {
                return response(
                    '<!DOCTYPE html><html><head><title>Debug Error</title><style>body{font-family:sans-serif;padding:2rem;background:#f8fafc;color:#1e293b}pre{background:#0f172a;color:#f8fafc;padding:1rem;border-radius:8px;overflow:auto}</style></head><body>'
                    . '<h1>Debug Error</h1>'
                    . '<p><strong>' . htmlspecialchars($e->getMessage()) . '</strong> in ' . htmlspecialchars($e->getFile()) . ':' . $e->getLine() . '</p>'
                    . '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>'
                    . '</body></html>',
                    500
                );
            }
        });
    })->create();

if (isset($_ENV['VERCEL']) || isset($_SERVER['VERCEL']) || getenv('VERCEL') || isset($_ENV['NOW_REGION']) || isset($_SERVER['NOW_REGION'])) {
    $app->useStoragePath('/tmp/storage');
}

return $app;
