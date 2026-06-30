<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RegistrarVisitaYBitacora;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

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
            RegistrarVisitaYBitacora::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'pagofacil/callback',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            if (in_array($response->getStatusCode(), [403, 404, 500, 503])) {
                if ($request->hasHeader('X-Inertia') || $request->acceptsHtml()) {
                    return Inertia::render('Error', [
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
