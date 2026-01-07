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
        if (($request->isMethod('put') || $request->isMethod('patch')) &&
            str_contains($request->header('Content-Type'), 'multipart/form-data')) {

            try {
                [$post, $rawFiles] = request_parse_body();

                // Merge text fields (works fine)
                $request->merge($post);

                // Convert raw files to UploadedFile objects
                $uploadedFiles = [];
                foreach ($rawFiles as $key => $fileInfo) {
                    if ($fileInfo['error'] === UPLOAD_ERR_OK) {
                        $uploadedFiles[$key] = new UploadedFile(
                            $fileInfo['tmp_name'],          // path
                            $fileInfo['name'],              // original name
                            $fileInfo['type'],              // mime type
                            $fileInfo['size'],              // size
                            $fileInfo['error'],             // error code
                            true                            // test mode = false (real upload)
                        );
                    }
                }

                // Replace with proper UploadedFile objects
                $request->files = new FileBag($uploadedFiles);

            } catch (\Throwable $e) {
                // Optional: log the error
                \Log::error('PUT multipart parsing failed: ' . $e->getMessage());
            }
        }


        return $next($request);
    }
}
