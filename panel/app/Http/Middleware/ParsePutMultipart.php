<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ParsePutMultipart
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethod('put') || $request->isMethod('patch')) {
            if (str_contains($request->header('Content-Type'), 'multipart/form-data')) {
                try {
                    [$post, $files] = request_parse_body();

                    // Merge into the request so $request->all(), $request->file() work
                    $request->merge($post);
                    $request->files->replace($files);  // Or use add() if replace not available
                } catch (\Throwable $e) {
                    // Log or handle error
                }
            }
        }

        return $next($request);
    }
}
