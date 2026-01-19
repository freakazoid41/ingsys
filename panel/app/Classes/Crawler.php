<?php
namespace App\Classes;
/**
 * PHP Web Crawler Class to Check for 404 and 500 Errors
 *
 * This class crawls a given website and checks all internal links for 404 and 500 HTTP errors.
 * Usage: php crawler.php <base_url>
 */

class Crawler extends \App\Classes\Utils
{
    private $baseUrl;
    private $baseHost;
    private $toVisit;
    private $toVisitSet;
    private $visited;
    private $errors404;
    private $errors500;
    private $disallowedPaths;
    private $maxDepth;

    public function __construct()
    {
        

        
    }

    public function crawl($baseUrl)
    {

        $this->visited = [];
        $this->toVisitSet = [];
        $this->errors404 = [];
        $this->errors500 = [];

        if (!filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException("Invalid URL provided.");
        }

        $this->baseUrl = $baseUrl;
        $parsedBase = parse_url($baseUrl);
        $this->baseHost = $parsedBase['host'];
        $this->maxDepth = 5; // Maximum crawl depth
        $this->disallowedPaths = $this->parseRobotsTxt($baseUrl);
        $this->parseSitemap($baseUrl); // Add sitemap URLs to queue
        $this->toVisit = [[$baseUrl, 0]];
        $this->toVisitSet[$baseUrl] = true;


        while (!empty($this->toVisit)) {
            $item = array_shift($this->toVisit);
            $currentUrl = $item[0];
            $depth = $item[1];
            if (!$this->isAllowedByRobots($currentUrl)) {
                continue; // Skip disallowed URLs
            }
            if (isset($this->visited[$currentUrl])) {
                continue;
            }
            $this->visited[$currentUrl] = true;

            $this->infoPrint("Checking: $currentUrl\n");

            $statusCode = $this->getStatusCode($currentUrl);
            usleep(100000); // Delay to avoid overwhelming the server

            if ($statusCode == 404) {
                $this->errors404[] = $currentUrl;
                $this->infoPrint("404 Error: $currentUrl\n");
            } elseif ($statusCode == 500) {
                $this->errors500[] = $currentUrl;
                $this->infoPrint("500 Error: $currentUrl\n");
            } elseif ($statusCode >= 200 && $statusCode < 300) {
                // Successful response, extract links
                $html = $this->fetchHtml($currentUrl);
                usleep(100000); // Additional delay after fetching HTML
                if ($html) {
                    $newLinks = $this->extractLinks($html, $currentUrl);
                    foreach ($newLinks as $link) {
                        if ($depth < $this->maxDepth && $this->isAllowedByRobots($link) && !isset($this->visited[$link]) && !isset($this->toVisitSet[$link])) {
                            $this->toVisit[] = [$link, $depth + 1];
                            $this->toVisitSet[$link] = true;
                        }
                    }
                }
            } else {
                $this->infoPrint("Other status ($statusCode): $currentUrl\n");
            }

            // Limit crawl to prevent infinite loops or excessive requests
            if (count($this->visited) > 1000) {
                $this->infoPrint("Crawl limit reached (1000 pages).\n");
                break;
            }
        }

        return $this->outputResults();
    }

    private function getStatusCode($url)
    {
        return $this->requestWithRetry($url, true);
    }

    private function fetchHtml($url)
    {
        return $this->requestWithRetry($url, false);
    }

    private function requestWithRetry($url, $isHead = false)
    {
        $maxRetries = 3;
        $retryDelay = 100000; // 0.1s initial delay

        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_USERAGENT, 'PHP Crawler/1.0');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Verify SSL certificate
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2); // Verify host
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); // Connection timeout

            if ($isHead) {
                curl_setopt($ch, CURLOPT_NOBODY, true); // HEAD request
                curl_exec($ch);
                $result = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            } else {
                $result = curl_exec($ch);
            }

            $error = curl_error($ch);
            $errno = curl_errno($ch);
            curl_close($ch);

            if ($errno === 0 && !$error) {
                return $result; // Success
            }

            // Log error and retry
            $this->infoPrint("Request failed (attempt " . ($attempt + 1) . "): $error\n");
            if ($attempt < $maxRetries - 1) {
                usleep($retryDelay);
                $retryDelay *= 2; // Exponential backoff
            }
        }

        // All retries failed
        $this->infoPrint("Request failed after $maxRetries attempts for $url\n");
        return $isHead ? 404 : false; // Treat failed requests as 404 for status checks
    }

    private function extractLinks($html, $currentUrl)
    {
        $links = [];
        $dom = new \DOMDocument();
        @$dom->loadHTML($html); // Suppress warnings

        // Determine the base URL for resolving relative links
        $baseUrl = $currentUrl;
        $baseElements = $dom->getElementsByTagName('base');
        if ($baseElements->length > 0) {
            $baseHref = $baseElements->item(0)->getAttribute('href');
            if (!empty($baseHref)) {
                $baseUrl = $this->resolveUrl($baseHref, $currentUrl);
            }
        }

        // Extract links from various elements
        $elements = [
            'a' => 'href',
            'area' => 'href',
            'link' => 'href',
            'form' => 'action',
            'img' => 'src', // For images, but only if internal
        ];

        foreach ($elements as $tag => $attr) {
            $nodes = $dom->getElementsByTagName($tag);
            foreach ($nodes as $node) {
                $href = $node->getAttribute($attr);
                if (!empty($href) && strpos($href, 'javascript:void') === false && strpos($href, 'tel:') !== 0 && strpos($href, '+') === false && $href !== '#') {
                    // For <link>, check if it's a stylesheet or similar (not external)
                    if ($tag === 'link') {
                        $rel = $node->getAttribute('rel');
                        if (!in_array($rel, ['stylesheet', 'icon', 'preload', 'modulepreload'])) {
                            continue; // Skip non-navigational links
                        }
                    }
                    // For <img>, only include if it's an internal link (e.g., not data: or external)
                    if ($tag === 'img' && (strpos($href, 'data:') === 0 || !filter_var($href, FILTER_VALIDATE_URL))) {
                        // Resolve and check if internal
                        $absoluteUrl = $this->resolveUrl($href, $baseUrl);
                        if (!$this->isInternalUrl($absoluteUrl)) {
                            continue;
                        }
                    } else {
                        $absoluteUrl = $this->resolveUrl($href, $baseUrl);
                    }
                    // Strip URL fragments (#anchor) as they don't represent separate pages
                    $absoluteUrl = preg_replace('/#.*/', '', $absoluteUrl);
                    if ($absoluteUrl && $this->isInternalUrl($absoluteUrl) && filter_var($absoluteUrl, FILTER_VALIDATE_URL)) {
                        $links[] = $absoluteUrl;
                    }
                }
            }
        }

        return array_unique($links);
    }

    private function resolveUrl($relative, $base)
    {
        if (preg_match('/^https?:\/\//', $relative)) {
            return $relative; // Already absolute URL
        }
        if (preg_match('/^https?:\/[^\/]/', $relative)) {
            return false; // Malformed scheme like 'https:/path'
        }
        if (filter_var($relative, FILTER_VALIDATE_URL)) {
            return $relative;
        }

        $parsedBase = parse_url($base);
        $scheme = $parsedBase['scheme'] ?? 'http';
        $host = $parsedBase['host'] ?? '';
        $port = isset($parsedBase['port']) ? ':' . $parsedBase['port'] : '';
        $basePath = $parsedBase['path'] ?? '/';

        if (substr($relative, 0, 1) === '/') {
            // Absolute path, relative to root
            $path = $relative;
        } else {
            // Relative path, append to base path
            $path = rtrim($basePath, '/') . '/' . $relative;
        }

        // Normalize the path to handle .. and .
        $path = $this->normalizePath($path);

        return $scheme . '://' . $host . $port . $path;
    }

    private function normalizePath($path)
    {
        // Split path into segments
        $segments = explode('/', trim($path, '/'));
        $normalized = [];

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') {
                // Skip empty or current directory
                continue;
            } elseif ($segment === '..') {
                // Go up one directory
                array_pop($normalized);
            } else {
                // Add segment
                $normalized[] = $segment;
            }
        }

        // Rebuild path with leading /
        return '/' . implode('/', $normalized);
    }

    private function isInternalUrl($url)
    {
        $parsed = parse_url($url);
        return isset($parsed['host']) && $parsed['host'] === $this->baseHost;
    }

    private function outputResults()
    {
        $this->infoPrint("Crawl completed.");
       $this->infoPrint("\n=== 404 Errors ===\n");
        foreach ($this->errors404 as $url) {
            $this->infoPrint("$url\n");
        }

        $this->infoPrint("\n=== 500 Errors ===\n");
        foreach ($this->errors500 as $url) {
            $this->infoPrint("$url\n");
        }

        $this->infoPrint("\nTotal pages checked: " . count($this->visited) . "\n");
        $this->infoPrint("404 errors: " . count($this->errors404) . "\n");
        $this->infoPrint("500 errors: " . count($this->errors500) . "\n");

        return [
            'visited'   => $this->visited,
            'errors404' => $this->errors404,
            'errors500' => $this->errors500
        ];
        
    }

    private function parseRobotsTxt($baseUrl)
    {
        $robotsUrl = $this->resolveUrl('/robots.txt', $baseUrl);
        $robotsContent = $this->requestWithRetry($robotsUrl, false);
        $disallowed = [];

        if ($robotsContent) {
            $lines = explode("\n", $robotsContent);
            $userAgent = null;
            foreach ($lines as $line) {
                $line = trim($line);
                if (preg_match('/^User-agent:\s*(.+)$/i', $line, $matches)) {
                    $userAgent = trim($matches[1]);
                } elseif (preg_match('/^Disallow:\s*(.+)$/i', $line, $matches) && ($userAgent === '*' || $userAgent === 'PHP Crawler/1.0')) {
                    $path = trim($matches[1]);
                    if (!empty($path)) {
                        $disallowed[] = $path;
                    }
                }
            }
        }

        return $disallowed;
    }

    private function parseSitemap($baseUrl)
    {
        $sitemapUrl = $this->resolveUrl('/sitemap.xml', $baseUrl);
        $sitemapContent = $this->requestWithRetry($sitemapUrl, false);

        if ($sitemapContent) {
            $dom = new \DOMDocument();
            @$dom->loadXML($sitemapContent);
            $urlElements = $dom->getElementsByTagName('loc');
            foreach ($urlElements as $urlElement) {
                $url = trim($urlElement->textContent);
                if ($this->isInternalUrl($url) && $this->isAllowedByRobots($url) && !isset($this->toVisitSet[$url])) {
                    $this->toVisit[] = [$url, 0]; // Add with depth 0
                    $this->toVisitSet[$url] = true;
                }
            }
        }
    }

    private function isAllowedByRobots($url)
    {
        $parsed = parse_url($url);
        $path = $parsed['path'] ?? '/';

        foreach ($this->disallowedPaths as $disallowed) {
            if (strpos($path, $disallowed) === 0) {
                return false;
            }
        }

        return true;
    }
}


?>
