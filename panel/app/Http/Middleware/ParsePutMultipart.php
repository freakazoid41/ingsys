<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\FileBag;

class ParsePutMultipart
{
    /**
     * Handle an incoming request.
     *
     * PHP only populates $_POST/$_FILES for POST requests. PUT/PATCH multipart
     * bodies must be parsed manually. PHP 8.4 provides request_parse_body();
     * this app still runs on 8.2, so fall back to the shared parsePut() helper
     * (which also collects files into $_FILES).
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        if (($request->isMethod('put') || $request->isMethod('patch')) &&
            str_contains((string) $request->header('Content-Type'), 'multipart/form-data')) {

            try {
                if (function_exists('request_parse_body')) {
                    [$post, $rawFiles] = request_parse_body();

                    $request->merge($post);

                    $uploadedFiles = [];
                    foreach ($rawFiles as $key => $fileInfo) {
                        if (($fileInfo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                            $uploadedFiles[$key] = new UploadedFile(
                                $fileInfo['tmp_name'],
                                $fileInfo['name'],
                                $fileInfo['type'],
                                $fileInfo['size'],
                                $fileInfo['error'],
                                true
                            );
                        }
                    }

                    $request->files = new FileBag($uploadedFiles);
                } else {
                    // PHP < 8.4 — parsePut() reads php://input, fills $GLOBALS['_PUT']
                    // and puts UploadedFile objects into $_FILES.
                    $post = function_exists('parsePut') ? (parsePut() ?: []) : [];
                    if (! empty($post)) {
                        $request->merge($post);
                    }

                    $uploadedFiles = [];
                    foreach (($_FILES ?? []) as $key => $file) {
                        if ($file instanceof UploadedFile) {
                            $uploadedFiles[$key] = $file;
                        }
                    }
                    if (! empty($uploadedFiles)) {
                        $request->files = new FileBag($uploadedFiles);
                    }
                }
            } catch (\Throwable $e) {
                \Log::error('PUT multipart parsing failed: ' . $e->getMessage());
            }
        }

        return $next($request);
    }
}
