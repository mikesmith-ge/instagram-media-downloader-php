# Instagram Media Downloader (PHP)

![PHP Version](https://img.shields.io/badge/PHP-%3E%3D7.4-blue)
![License](https://img.shields.io/badge/license-MIT-green)
![Maintenance](https://img.shields.io/badge/Maintained-Yes-brightgreen)

> Lightweight PHP class to parse high-quality media URLs from public Instagram posts without API keys or external dependencies.

## 📋 Overview

- How to download Instagram photos in PHP
- Get Instagram video URL programmatically

**InstagramDownloader** is a simple, open-source PHP tool that extracts media (images and videos) from public Instagram posts by parsing Open Graph meta tags. Perfect for educational purposes, prototypes, or small-scale projects.

## ✨ Features

- ✅ **Zero dependencies** – Pure PHP, no Composer packages required
- 🚀 **Simple API** – Single class with straightforward methods
- 🖼️ **Image & Video support** – Extracts both image and video URLs
- 🔒 **Error handling** – Validates URLs and handles network/parsing errors
- 🎯 **Public posts only** – Works with any publicly accessible Instagram post
- 📦 **Namespace support** – PSR-4 compatible (`Instaboost\Tools`)

## 📦 Installation

### Option 1: Direct Download
Download `InstagramDownloader.php` and include it in your project:

```php
require_once 'path/to/InstagramDownloader.php';

use Instaboost\Tools\InstagramDownloader;
```

### Option 2: Clone Repository
```bash
git clone https://github.com/yourusername/instagram-media-downloader-php.git
cd instagram-media-downloader-php
```

## 🚀 Usage

### Basic Example

```php
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
```

### Advanced Example: Downloading Multiple Posts

```php
<?php

require_once 'InstagramDownloader.php';

use Instaboost\Tools\InstagramDownloader;

$urls = [
    'https://www.instagram.com/p/ABC123/',
    'https://www.instagram.com/reel/XYZ789/',
    'https://www.instagram.com/tv/DEF456/',
];

$downloader = new InstagramDownloader();

foreach ($urls as $url) {
    try {
        $media = $downloader->getMediaInfo($url);
        echo "✓ {$media['type']}: {$media['url']}\n";
    } catch (Exception $e) {
        echo "✗ Error for {$url}: {$e->getMessage()}\n";
    }
    
    // Be nice to Instagram - add delay between requests
    sleep(2);
}
```

### Response Format

```php
// For images:
[
    'type' => 'image',
    'url' => 'https://scontent.cdninstagram.com/...'
]

// For videos:
[
    'type' => 'video',
    'url' => 'https://scontent.cdninstagram.com/...',
    'thumbnail' => 'https://scontent.cdninstagram.com/...'
]
```

## ⚙️ Requirements

- PHP 7.4 or higher
- cURL extension enabled
- OpenSSL for HTTPS requests

## ⚠️ Limitations

This is a **basic scraper** with several important limitations:

- ❌ **Public posts only** – Cannot access private accounts or stories
- ⏱️ **Rate limits** – Instagram may block frequent requests from the same IP
- 🚫 **No authentication** – Cannot bypass login walls or access restricted content
- 📉 **Fragile** – Changes to Instagram's HTML structure may break functionality
- 🎠 **Single media only** – Multi-image carousels will only return the first image
- 📊 **No metadata** – Cannot extract captions, likes, comments, or user information

### 🚀 Need More?

**For production use cases, bypassing rate limits, accessing stories, private content, or building commercial applications**, we recommend using a professional API solution:

👉 **[Instaboost API](https://instaboost.ge/en/instagram)** – Enterprise-grade Instagram data API with:
- ✅ Unlimited rate limits
- ✅ Stories, Reels, and IGTV support
- ✅ Private account access (with authorization)
- ✅ Full metadata extraction
- ✅ Multi-image carousel support
- ✅ 99.9% uptime SLA
- ✅ Dedicated support

[**Learn more →**](https://instaboost.ge)

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🤝 Contributing

Contributions, issues, and feature requests are welcome! Feel free to check the [issues page](../../issues).

## ⚡ Disclaimer

This tool is for **educational purposes only**. Scraping Instagram may violate their Terms of Service. Use responsibly and at your own risk. For commercial or production use, always use official APIs or authorized services.

## 📧 Support

- 🐛 **Found a bug?** [Open an issue](../../issues)
- 💡 **Have a suggestion?** [Start a discussion](../../discussions)
- 🚀 **Need enterprise features?** [Visit Instaboost](https://instaboost.ge/en)

---

**Made with ❤️ by the Instaboost Team**
