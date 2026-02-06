<?php

namespace Instaboost\Tools;

/**
 * InstagramDownloader
 * 
 * A lightweight PHP class to extract media URLs from public Instagram posts
 * by parsing Open Graph meta tags. No API key required.
 * 
 * @author Instaboost Team
 * @license MIT
 * @version 1.0.0
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
    
    /**
     * Download media from a public Instagram post URL
     * 
     * @param string $url Instagram post URL (e.g., https://www.instagram.com/p/ABC123/)
     * @return array Array containing 'type' (image|video), 'url' (media URL), and 'thumbnail' (for videos)
     * @throws \Exception on invalid URL, network errors, or media not found
     */
    public function download(string $url): array
    {
        // Validate Instagram URL format
        if (!$this->isValidInstagramUrl($url)) {
            throw new \Exception('Invalid Instagram URL. Please provide a valid post URL (e.g., https://www.instagram.com/p/ABC123/)');
        }
        
        // Fetch HTML content
        $html = $this->fetchHtml($url);
        
        // Extract media URLs from Open Graph meta tags
        $media = $this->parseMediaFromHtml($html);
        
        if (empty($media)) {
            throw new \Exception('Could not extract media from this post. It may be private, deleted, or Instagram has updated their HTML structure.');
        }
        
        return $media;
    }
    
    /**
     * Validate if the URL is a proper Instagram post URL
     * 
     * @param string $url
     * @return bool
     */
    private function isValidInstagramUrl(string $url): bool
    {
        $pattern = '/^https?:\/\/(www\.)?instagram\.com\/(p|reel|tv)\/[a-zA-Z0-9_-]+\/?/';
        return preg_match($pattern, $url) === 1;
    }
    
    /**
     * Fetch HTML content from Instagram URL using cURL
     * 
     * @param string $url
     * @return string HTML content
     * @throws \Exception on network errors or HTTP errors
     */
    private function fetchHtml(string $url): string
    {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_USERAGENT => self::USER_AGENT,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.9',
                'Accept-Encoding: gzip, deflate, br',
                'Connection: keep-alive',
                'Upgrade-Insecure-Requests: 1',
            ],
            CURLOPT_ENCODING => '', // Handle gzip/deflate automatically
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        
        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($html === false) {
            throw new \Exception("Network error: {$error}");
        }
        
        if ($httpCode === 404) {
            throw new \Exception('Post not found. The URL may be incorrect or the post has been deleted.');
        }
        
        if ($httpCode === 403 || $httpCode === 429) {
            throw new \Exception('Access denied or rate limited by Instagram. Please try again later or use a professional API service.');
        }
        
        if ($httpCode !== 200) {
            throw new \Exception("HTTP error: {$httpCode}");
        }
        
        return $html;
    }
    
    /**
     * Parse media URLs from HTML using Open Graph meta tags
     * 
     * @param string $html
     * @return array
     */
    private function parseMediaFromHtml(string $html): array
    {
        $media = [];
        
        // Extract og:video (if exists)
        if (preg_match('/<meta\s+property=["\']og:video["\']\s+content=["\'](.*?)["\']/i', $html, $videoMatch)) {
            $media['type'] = 'video';
            $media['url'] = html_entity_decode($videoMatch[1]);
            
            // Try to get video thumbnail
            if (preg_match('/<meta\s+property=["\']og:image["\']\s+content=["\'](.*?)["\']/i', $html, $thumbMatch)) {
                $media['thumbnail'] = html_entity_decode($thumbMatch[1]);
            }
        }
        // Extract og:image (fallback for images)
        elseif (preg_match('/<meta\s+property=["\']og:image["\']\s+content=["\'](.*?)["\']/i', $html, $imageMatch)) {
            $media['type'] = 'image';
            $media['url'] = html_entity_decode($imageMatch[1]);
        }
        
        return $media;
    }
    
    /**
     * Get media info without downloading (useful for previews)
     * 
     * @param string $url Instagram post URL
     * @return array Media information
     */
    public function getMediaInfo(string $url): array
    {
        return $this->download($url);
    }
}
