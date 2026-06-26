<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\RegistrarVisitaYBitacora::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response, \Throwable $exception, \Illuminate\Http\Request $request) {
            if (in_array($response->getStatusCode(), [403, 404, 500, 503])) {
                if ($request->hasHeader('X-Inertia') || $request->acceptsHtml()) {
                    return \Inertia\Inertia::render('Error', [
                        'status' => $response->getStatusCode(),
                        'message' => $exception->getMessage(),
                    ])
                    ->toResponse($request)
                    ->setStatusCode($response->getStatusCode());
                }
            }

            return $response;
        });
    })->create();
