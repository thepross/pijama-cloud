<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Visita;
use App\Models\Bitacora;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RegistrarVisitaYBitacora
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $isStandardPage = $request->isMethod('GET')
            && !$request->expectsJson()
            && !$request->routeIs('*.assets')
            && !str_starts_with($request->path(), '_')
            && !str_starts_with($request->path(), 'api');

        if ($isStandardPage) {
            $path = $request->path() === '/' ? 'inicio' : $request->path();

            Visita::registrarVisita($path);

            if (Auth::check()) {
                Bitacora::create([
                    'id_usuario' => Auth::id(),
                    'evento' => 'acceso_recurso',
                    'ip' => $request->ip(),
                    'recurso' => $path,
                    'user_agent' => $request->userAgent(),
                ]);
            }
        }

        $isDataChange = Auth::check()
            && ($request->isMethod('POST') || $request->isMethod('PUT') || $request->isMethod('PATCH') || $request->isMethod('DELETE'));

        if ($isDataChange) {
            $path = $request->path();
            $method = $request->method();

            $details = $request->except(['password', 'password_confirmation', 'remember', '_token']);

            Bitacora::create([
                'id_usuario' => Auth::id(),
                'evento' => "accion_{$method}",
                'ip' => $request->ip(),
                'recurso' => $path,
                'detalle' => json_encode($details, JSON_UNESCAPED_UNICODE),
                'user_agent' => $request->userAgent(),
            ]);
        }

        return $response;
    }
}
