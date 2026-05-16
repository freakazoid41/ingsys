<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Config;

class CspMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = base64_encode(random_bytes(16));

        // Hardcoded domains for dual-domain setup (fallback: env can also be used)
        $hardcodedDomains = 'komurtedarik.cates.com.tr,komurtedarik.yatagantermik.com.tr';
        $extraHosts = trim((string) env('CSP_ADDITIONAL_HOSTS', $hardcodedDomains));
        $extraList = $extraHosts !== '' ? array_filter(array_map('trim', explode(',', $extraHosts))) : [];

        // Also include hosts derived from ASSET_URL and APP_URL (useful when assets are served from a CDN)
        $urlKeys = ['ASSET_URL', 'APP_URL'];
        foreach ($urlKeys as $key) {
            $u = trim((string) env($key, ''));
            if ($u === '' || $u === '/') {
                continue;
            }

            $parts = parse_url($u);
            $host = $parts['host'] ?? null;
            if (!$host) {
                // fallback: strip scheme and trailing slash
                $host = preg_replace('#^https?://#', '', rtrim($u, '/'));
            }

            if ($host && !in_array($host, $extraList, true)) {
                $extraList[] = $host;
            }
        }

        $fontHosts = array_merge(['self', 'data:', 'fonts.gstatic.com', 'cloudflare.com', 'cdnjs.cloudflare.com', 'cdn.fontawesome.com', 'fontawesome.com', 'www.googleapis.com'], $extraList);
        $styleHosts = array_merge(['self', "'unsafe-inline'", 'fonts.googleapis.com', 'cdn.fontawesome.com', 'fontawesome.com', 'cloudflare.com', 'cdnjs.cloudflare.com'], $extraList);
        $scriptHosts = array_merge(['self', 'unpkg.com'], $extraList);
        $defaultHosts = array_merge(['self', 'www.google.com', 'google.com', 'unpkg.com', 'cloudflare.com', 'cdnjs.cloudflare.com'], $extraList);

        $csp = [
            "frame-src 'self' www.google.com",
            "script-src 'nonce-$nonce' " . implode(' ', array_map(function ($h) { return $h === 'self' ? "'self'" : $h; }, $scriptHosts)),
            "font-src " . implode(' ', array_map(function ($h) { return $h === 'self' ? "'self'" : $h; }, $fontHosts)),
            "style-src " . implode(' ', array_map(function ($h) { return $h === 'self' ? "'self'" : $h; }, $styleHosts)),
            // explicit style-src-elem to cover modern browsers
            "style-src-elem " . implode(' ', array_map(function ($h) { return $h === 'self' ? "'self'" : $h; }, $styleHosts)),
            "default-src " . implode(' ', array_map(function ($h) { return $h === 'self' ? "'self'" : $h; }, $defaultHosts)),
            "img-src 'self' data:"
        ];
        
        $policy = implode('; ', $csp);
       
        $response = $next($request);
        if(!env('IS_TEST')) $response->headers->set('Content-Security-Policy', $policy);

        // Add nonce to style and script tags
        if ($response instanceof \Illuminate\Http\Response && $response->getContent()) {
            $content = $response->getContent();
            $content = str_replace("<style", '<style nonce="'.$nonce.'" ', $content);
            $content = str_replace("<script", '<script nonce="'.$nonce.'" ', $content);
            $response->setContent($content);
        }
       
        return $response;
    }
}
