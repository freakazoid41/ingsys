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
    private $visited;
    private $errors404;
    private $errors500;

    public function __construct()
    {
        

        $this->visited = [];
        $this->errors404 = [];
        $this->errors500 = [];
    }

    public function crawl($baseUrl)
    {

        if (!filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException("Invalid URL provided.");
        }

        $this->baseUrl = $baseUrl;
        $parsedBase = parse_url($baseUrl);
        $this->baseHost = $parsedBase['host'];
        $this->toVisit = [$baseUrl];


        while (!empty($this->toVisit)) {
            $currentUrl = array_shift($this->toVisit);
            if (in_array($currentUrl, $this->visited)) {
                continue;
            }
            $this->visited[] = $currentUrl;

            $this->infoPrint("Checking: $currentUrl\n");

            $statusCode = $this->getStatusCode($currentUrl);

            if ($statusCode == 404) {
                $this->errors404[] = $currentUrl;
                $this->infoPrint("404 Error: $currentUrl\n");
            } elseif ($statusCode == 500) {
                $this->errors500[] = $currentUrl;
                $this->infoPrint("500 Error: $currentUrl\n");
            } elseif ($statusCode >= 200 && $statusCode < 300) {
                // Successful response, extract links
                $html = $this->fetchHtml($currentUrl);
                if ($html) {
                    $newLinks = $this->extractLinks($html, $currentUrl);
                    foreach ($newLinks as $link) {
                        if (!in_array($link, $this->visited) && !in_array($link, $this->toVisit)) {
                            $this->toVisit[] = $link;
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
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'PHP Crawler/1.0');
        curl_setopt($ch, CURLOPT_NOBODY, true); // HEAD request for efficiency
        curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $statusCode;
    }

    private function fetchHtml($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'PHP Crawler/1.0');
        $html = curl_exec($ch);
        curl_close($ch);
        return $html;
    }

    private function extractLinks($html, $currentUrl)
    {
        $links = [];
        $dom = new \DOMDocument();
        @$dom->loadHTML($html); // Suppress warnings
        $anchors = $dom->getElementsByTagName('a');
        foreach ($anchors as $anchor) {
            $href = $anchor->getAttribute('href');
            if (!empty($href)) {
                $absoluteUrl = $this->resolveUrl($href, $currentUrl);
                if ($absoluteUrl && $this->isInternalUrl($absoluteUrl)) {
                    $links[] = $absoluteUrl;
                }
            }
        }
        return array_unique($links);
    }

    private function resolveUrl($relative, $base)
    {
        if (filter_var($relative, FILTER_VALIDATE_URL)) {
            return $relative;
        }
        return rtrim($base, '/') . '/' . ltrim($relative, '/');
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
}


?>
