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

        // Sadece kendi domain + nonce ile script izin ver
        $csp = [
            "frame-src 'self' www.youtube.com www.google.com",
            "script-src 'self' www.youtube.com unpkg.com 'nonce-$nonce'",
            "font-src 'self' fonts.gstatic.com cloudflare.com cdnjs.cloudflare.com cdn.fontawesome.com fontawesome.com www.googleapis.com",
            "style-src 'self' 'unsafe-inline' www.youtube.com fonts.googleapis.com cdn.fontawesome.com fontawesome.com cloudflare.com cdnjs.cloudflare.com",
            "default-src 'self' www.google.com google.com unpkg.com jquery.com cdn.jquery.com cloudflare.com cdnjs.cloudflare.com",
            "img-src 'self' data:"
        ];
        
        $policy = implode('; ', $csp);
       
        $response = $next($request);
        if(!env('IS_TEST')) $response->headers->set('Content-Security-Policy', $policy);
        // Blade'de kullanmak için nonce paylaş
        // 4. Response içeriğini değiştir (Blade'e nonce ekle)
        if ($response instanceof \Illuminate\Http\Response && $response->getContent()) {
            $content = $response->getContent();
            
            // </head> öncesi nonce meta ekle
            /*$meta = '<meta name="csp-nonce" content="' . $nonce . '">';
            $content = preg_replace('/<\/head>/i', $meta . "\n</head>", $content, 1);*/
            // <script> etiketlerine otomatik nonce ekle
            $content = str_replace("<style", '<style nonce="'.$nonce.'" ', $content);
            $content = str_replace("<script", '<script nonce="'.$nonce.'" ', $content);

            $response->setContent($content);
        }

        // 5. Request'e de ekle (gerekirse)
        //$request->attributes->set('csp_nonce', $nonce);
       
        return $response;
    }
}
