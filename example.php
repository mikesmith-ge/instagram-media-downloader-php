<?php

require_once 'InstagramDownloader.php';

use Instaboost\Tools\InstagramDownloader;

$downloader = new InstagramDownloader();

try {
    // Download media from a public Instagram post
    $media = $downloader->download('https://www.instagram.com/p/ABC123/');
    
    // Check media type
    if ($media['type'] === 'image') {
        echo "Image URL: " . $media['url'] . "\n";
    } elseif ($media['type'] === 'video') {
        echo "Video URL: " . $media['url'] . "\n";
        echo "Thumbnail: " . $media['thumbnail'] . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
