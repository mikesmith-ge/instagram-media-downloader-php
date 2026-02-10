<?php

namespace Instaboost\Tools;

/**
 * InstagramDownloader
 *
 * Lightweight PHP class to extract media URLs from public Instagram posts,
 * Reels, and IGTV by parsing embedded JSON data with og: meta tag fallback.
 *
 * @author  Instaboost Team
 * @license MIT
 * @version 1.1.0
 */
class InstagramDownloader
{
    /**
     * User-Agent string to mimic a real browser request
     */
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    /**
     * Timeout for cURL requests (seconds)
     */
    private const TIMEOUT = 15;

    // -----------------------------------------------------------------------
    // Public API
    // -----------------------------------------------------------------------

    /**
     * Download media from a public Instagram URL.
     *
     * Supports:
     *   - Regular posts:  instagram.com/p/{shortcode}/
     *   - Reels:          instagram.com/reel/{shortcode}/
     *   - IGTV:           instagram.com/tv/{shortcode}/
     *
     * @param  string $url Instagram URL
     * @return array{
     *   type:      string,
     *   url:       string,
     *   thumbnail: string|null,
     *   source:    string
     * }
     * @throws \InvalidArgumentException on invalid URL
     * @throws \RuntimeException         on network errors or extraction failure
     */
    public function download(string $url): array
    {
        if (!$this->isValidUrl($url)) {
            throw new \InvalidArgumentException(
                'Invalid Instagram URL. Supported formats: /p/, /reel/, /tv/'
            );
        }

        $html = $this->fetchHtml($url);

        // Stage 1: JSON blob (more resilient to HTML changes)
        $media = $this->parseJson($html);

        // Stage 2: og: meta tags fallback
        if (empty($media)) {
            $media = $this->parseOgMeta($html);
        }

        if (empty($media)) {
            throw new \RuntimeException(
                'Could not extract media from this post. '
                . 'It may be private, deleted, or both extraction methods failed. '
                . 'For reliable production access visit https://instaboost.ge'
            );
        }

        return $media;
    }

    /**
     * Alias for download() — useful for preview workflows.
     *
     * @param  string $url Instagram URL
     * @return array
     */
    public function getMediaInfo(string $url): array
    {
        return $this->download($url);
    }

    // -----------------------------------------------------------------------
    // URL validation
    // -----------------------------------------------------------------------

    /**
     * Validate Instagram post/reel/tv URL.
     *
     * @param  string $url
     * @return bool
     */
    private function isValidUrl(string $url): bool
    {
        // Matches /p/, /reel/, and /tv/ shortcode URLs
        $pattern = '/^https?:\/\/(www\.)?instagram\.com\/(p|reel|tv)\/[a-zA-Z0-9_-]+\/?/i';
        return preg_match($pattern, $url) === 1;
    }

    // -----------------------------------------------------------------------
    // HTTP fetching
    // -----------------------------------------------------------------------

    /**
     * Fetch HTML from Instagram using cURL.
     *
     * @param  string $url
     * @return string HTML content
     * @throws \RuntimeException on network/HTTP errors
     */
    private function fetchHtml(string $url): string
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_USERAGENT      => self::USER_AGENT,
            CURLOPT_HTTPHEADER     => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.9',
                'Accept-Encoding: gzip, deflate, br',
                'Connection: keep-alive',
                'Upgrade-Insecure-Requests: 1',
            ],
            CURLOPT_ENCODING       => '', // Handle gzip/deflate automatically
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $html     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);

        curl_close($ch);

        if ($html === false) {
            throw new \RuntimeException("Network error: {$error}");
        }

        $this->assertHttpSuccess($httpCode);

        return $html;
    }

    /**
     * Throw appropriate exception for non-200 HTTP codes.
     *
     * @param  int $code HTTP status code
     * @return void
     * @throws \RuntimeException
     */
    private function assertHttpSuccess(int $code): void
    {
        switch ($code) {
            case 200:
                return;
            case 404:
                throw new \RuntimeException(
                    'Post not found. The URL may be incorrect or the post has been deleted.'
                );
            case 403:
                throw new \RuntimeException(
                    'Access denied by Instagram. Try again later or use a proxy.'
                );
            case 429:
                throw new \RuntimeException(
                    'Rate limited by Instagram. '
                    . 'Use a proxy to rotate IPs or use the Instaboost API: https://instaboost.ge'
                );
            default:
                throw new \RuntimeException("HTTP error: {$code}");
        }
    }

    // -----------------------------------------------------------------------
    // Stage 1: JSON blob extraction
    // -----------------------------------------------------------------------

    /**
     * Try to extract media from Instagram's embedded JSON blobs.
     *
     * Instagram embeds post data in several script tag formats:
     *   A) window._sharedData = {...};
     *   B) __additionalDataLoaded('extra', {...})
     *   C) <script type="application/ld+json">
     *
     * This is more reliable than HTML scraping because the JSON schema
     * changes less frequently than the DOM structure.
     *
     * @param  string $html
     * @return array|null
     */
    private function parseJson(string $html): ?array
    {
        // Pattern A: window._sharedData
        $media = $this->trySharedData($html);
        if ($media) {
            return $media;
        }

        // Pattern B: __additionalDataLoaded
        $media = $this->tryAdditionalData($html);
        if ($media) {
            return $media;
        }

        // Pattern C: application/ld+json
        $media = $this->tryLdJson($html);
        if ($media) {
            return $media;
        }

        return null;
    }

    /**
     * Extract from window._sharedData JSON blob.
     *
     * @param  string $html
     * @return array|null
     */
    private function trySharedData(string $html): ?array
    {
        if (!preg_match('/window\._sharedData\s*=\s*(\{.*?\});\s*<\/script>/s', $html, $m)) {
            return null;
        }

        $data = json_decode($m[1], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        try {
            $node = $data['entry_data']['PostPage'][0]['graphql']['shortcode_media'] ?? null;
            return $this->mediaFromGraphqlNode($node);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Extract from __additionalDataLoaded JSON blob.
     *
     * @param  string $html
     * @return array|null
     */
    private function tryAdditionalData(string $html): ?array
    {
        if (!preg_match('/__additionalDataLoaded\s*\(\s*["\'].*?["\']\s*,\s*(\{.*?\})\s*\)/s', $html, $m)) {
            return null;
        }

        $data = json_decode($m[1], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        $node = $data['graphql']['shortcode_media'] ?? null;
        return $this->mediaFromGraphqlNode($node);
    }

    /**
     * Extract from <script type="application/ld+json"> block.
     *
     * @param  string $html
     * @return array|null
     */
    private function tryLdJson(string $html): ?array
    {
        if (!preg_match('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $html, $m)) {
            return null;
        }

        $data = json_decode($m[1], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        // ld+json may be an array
        if (isset($data[0])) {
            $data = $data[0];
        }

        $media = [];

        // Video
        if (!empty($data['video']) && is_array($data['video'])) {
            $videoUrl = $data['video'][0]['contentUrl'] ?? '';
            if ($videoUrl) {
                $media['type']      = 'video';
                $media['url']       = $videoUrl;
                $media['thumbnail'] = $data['thumbnailUrl'] ?? '';
                $media['source']    = 'json';
                return $media;
            }
        }

        // Image
        $imageUrl = $data['image'] ?? '';
        if (is_array($imageUrl)) {
            $imageUrl = $imageUrl[0] ?? '';
        }
        if ($imageUrl) {
            $media['type']   = 'image';
            $media['url']    = $imageUrl;
            $media['source'] = 'json';
            return $media;
        }

        return null;
    }

    /**
     * Convert a GraphQL shortcode_media node to our media array.
     *
     * @param  array|null $node
     * @return array|null
     */
    private function mediaFromGraphqlNode(?array $node): ?array
    {
        if (empty($node)) {
            return null;
        }

        $typename = $node['__typename'] ?? '';
        $isVideo  = !empty($node['is_video']) || $typename === 'GraphVideo';

        if ($isVideo) {
            $videoUrl = $node['video_url'] ?? '';
            if (!$videoUrl) {
                return null;
            }
            return [
                'type'      => 'video',
                'url'       => $videoUrl,
                'thumbnail' => $node['display_url'] ?? $node['thumbnail_src'] ?? '',
                'source'    => 'json',
            ];
        }

        $imageUrl = $node['display_url'] ?? $node['thumbnail_src'] ?? '';
        if (!$imageUrl) {
            return null;
        }

        return [
            'type'   => 'image',
            'url'    => $imageUrl,
            'source' => 'json',
        ];
    }

    // -----------------------------------------------------------------------
    // Stage 2: og: meta tag extraction (fallback)
    // -----------------------------------------------------------------------

    /**
     * Extract media from Open Graph meta tags.
     *
     * Used as fallback if JSON extraction fails.
     *
     * @param  string $html
     * @return array|null
     */
    private function parseOgMeta(string $html): ?array
    {
        // Check for video first
        if (preg_match('/<meta\s+property=["\']og:video["\']\s+content=["\'](.*?)["\']/i', $html, $videoMatch)) {
            $media = [
                'type'   => 'video',
                'url'    => html_entity_decode($videoMatch[1]),
                'source' => 'og_meta',
            ];

            if (preg_match('/<meta\s+property=["\']og:image["\']\s+content=["\'](.*?)["\']/i', $html, $thumbMatch)) {
                $media['thumbnail'] = html_entity_decode($thumbMatch[1]);
            }

            return $media;
        }

        // Fallback to image
        if (preg_match('/<meta\s+property=["\']og:image["\']\s+content=["\'](.*?)["\']/i', $html, $imageMatch)) {
            return [
                'type'   => 'image',
                'url'    => html_entity_decode($imageMatch[1]),
                'source' => 'og_meta',
            ];
        }

        return null;
    }
}
