<?php
// Enable error reporting for debugging
error_reporting(0);
ini_set('display_errors', 0);

/**
 * Smartrz Live Streaming Platform
 * Extracts data from RolexCoderZ and rebrands as Smartrz
 */

class SmartrzScraper {
    private $sourceUrl = 'https://rolexcoderz.live';
    private $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36';
    private $timeout = 30;
    
    public function scrapeAndModify() {
        try {
            // Step 1: Fetch HTML content
            $html = $this->fetchHTML();
            
            if (!$html) {
                throw new Exception('Failed to fetch website content');
            }
            
            // Step 2: Parse HTML and extract stream data
            $streamData = $this->parseStreamData($html);
            
            // Step 3: Modify data (replace brands and URLs)
            $modifiedData = $this->modifyContent($streamData);
            
            return [
                'success' => true,
                'data' => $modifiedData,
                'timestamp' => time()
            ];
            
        } catch (Exception $e) {
            error_log("Scraping error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Unable to fetch live data at the moment',
                'data' => [
                    'live' => [],
                    'upcoming' => [],
                    'completed' => []
                ],
                'timestamp' => time()
            ];
        }
    }
    
    private function fetchHTML() {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->sourceUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_CONNECTTIMEOUT => 15,
        ]);
        
        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($httpCode !== 200 || !$html) {
            error_log("HTTP Error: $httpCode, cURL Error: $error");
            return false;
        }
        
        return $html;
    }
    
    private function parseStreamData($html) {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);
        
        $streams = [
            'live' => [],
            'upcoming' => [], 
            'completed' => []
        ];
        
        // Find all stream cards
        $queries = [
            '//div[contains(@class, "stream-card")]',
            '//div[contains(@class, "card")]',
            '//div[@class="stream-card"]'
        ];
        
        $streamCards = null;
        foreach ($queries as $query) {
            $streamCards = $xpath->query($query);
            if ($streamCards->length > 0) break;
        }
        
        if (!$streamCards || $streamCards->length === 0) {
            return $streams;
        }
        
        foreach ($streamCards as $card) {
            $stream = $this->extractStreamInfo($xpath, $card);
            if ($stream && !empty($stream['title'])) {
                $streams[$stream['status']][] = $stream;
            }
        }
        
        return $streams;
    }
    
    private function extractStreamInfo($xpath, $cardNode) {
        // Extract title
        $title = '';
        $titleQueries = [
            './/h3[contains(@class, "stream-title")]',
            './/h3[@class="stream-title"]',
            './/h3',
            './/h2',
            './/*[contains(@class, "title")]'
        ];
        
        foreach ($titleQueries as $query) {
            $nodes = $xpath->query($query, $cardNode);
            if ($nodes->length > 0) {
                $title = trim($nodes->item(0)->textContent);
                break;
            }
        }
        
        // Extract channel
        $channel = '';
        $channelQueries = [
            './/p[contains(@class, "channel-name")]',
            './/p[@class="channel-name"]',
            './/p[contains(@class, "channel")]',
            './/p'
        ];
        
        foreach ($channelQueries as $query) {
            $nodes = $xpath->query($query, $cardNode);
            if ($nodes->length > 0) {
                $channel = trim($nodes->item(0)->textContent);
                break;
            }
        }
        
        // Extract thumbnail
        $thumbnail = '';
        $imgNodes = $xpath->query('.//img', $cardNode);
        if ($imgNodes->length > 0) {
            $thumbnail = $imgNodes->item(0)->getAttribute('src');
            if ($thumbnail && strpos($thumbnail, 'http') !== 0) {
                $thumbnail = 'https://rolexcoderz.live' . $thumbnail;
            }
        }
        
        // Extract URL
        $url = '';
        $linkQueries = [
            './/a[contains(@class, "stream-btn")]',
            './/a[@class="stream-btn"]',
            './/a[contains(@class, "btn")]',
            './/a'
        ];
        
        foreach ($linkQueries as $query) {
            $nodes = $xpath->query($query, $cardNode);
            if ($nodes->length > 0) {
                $url = $nodes->item(0)->getAttribute('href');
                if ($url && strpos($url, 'http') !== 0) {
                    $url = 'https://rolexcoderz.live' . $url;
                }
                break;
            }
        }
        
        // Determine status
        $status = 'completed';
        $badgeQueries = [
            './/div[contains(@class, "stream-badge")]',
            './/div[contains(@class, "badge")]',
            './/span[contains(@class, "status")]'
        ];
        
        foreach ($badgeQueries as $query) {
            $nodes = $xpath->query($query, $cardNode);
            if ($nodes->length > 0) {
                $badgeText = strtoupper($nodes->item(0)->textContent);
                if (strpos($badgeText, 'LIVE') !== false) {
                    $status = 'live';
                } elseif (strpos($badgeText, 'UPCOMING') !== false || strpos($badgeText, 'SCHEDULED') !== false) {
                    $status = 'upcoming';
                }
                break;
            }
        }
        
        return [
            'title' => $title,
            'channel' => $channel,
            'thumbnail' => $thumbnail ?: $this->getDefaultThumbnail($title),
            'url' => $url,
            'status' => $status
        ];
    }
    
    private function getDefaultThumbnail($title) {
        $colors = ['4a90e2', '2ad9b5', '8a2be2', '00cc66', 'ff6b6b'];
        $color = $colors[array_rand($colors)];
        $text = urlencode(substr($title, 0, 20) ?: 'Smartrz');
        return "https://via.placeholder.com/400x225/1a1a2e/{$color}?text={$text}";
    }
    
    private function modifyContent($streamData) {
        foreach ($streamData as &$category) {
            foreach ($category as &$stream) {
                // Replace brand names in text
                $stream['title'] = $this->replaceBrandText($stream['title']);
                $stream['channel'] = $this->replaceBrandText($stream['channel']);
                
                // Replace URLs
                $stream['url'] = $this->replaceBrandUrls($stream['url']);
                
                // Ensure URL is valid
                if (empty($stream['url'])) {
                    $stream['url'] = 'https://studysmarterx.netlify.app';
                }
            }
        }
        
        return $streamData;
    }
    
    private function replaceBrandText($text) {
        if (empty($text)) return 'Smartrz Live Class';
        
        $replacements = [
            'RolexCoderZ' => 'Smartrz',
            'rolexcoderz' => 'smartrz', 
            'ROLEXCODERZ' => 'SMARTRZ',
            'Rolex CoderZ' => 'Smartrz',
            'Rolex Coder' => 'Smartrz',
            'Rolex' => 'Smart'
        ];
        
        return str_ireplace(array_keys($replacements), array_values($replacements), $text);
    }
    
    private function replaceBrandUrls($url) {
        if (empty($url)) return 'https://studysmarterx.netlify.app';
        
        $url = str_ireplace(
            [
                'rolexcoderz.xyz', 
                'rolexcoderz.live', 
                'www.rolexcoderz.xyz',
                'rolexcoderz.com'
            ],
            'studysmarterx.netlify.app',
            $url
        );
        
        return $url;
    }
}

class DataCache {
    private $cacheFile = __DIR__ . '/stream_cache.json';
    private $cacheTime = 300; // 5 minutes
    
    public function getCachedData() {
        if (!file_exists($this->cacheFile)) {
            return null;
        }
        
        if (time() - filemtime($this->cacheFile) > $this->cacheTime) {
            return null;
        }
        
        $data = json_decode(file_get_contents($this->cacheFile), true);
        return $data && isset($data['timestamp']) ? $data : null;
    }
    
    public function saveToCache($data) {
        try {
            file_put_contents($this->cacheFile, json_encode($data));
            return true;
        } catch (Exception $e) {
            error_log("Cache save error: " . $e->getMessage());
            return false;
        }
    }
}

// Main execution
header('Content-Type: text/html; charset=UTF-8');

$cache = new DataCache();
$cachedData = $cache->getCachedData();

if ($cachedData && $cachedData['success']) {
    $result = $cachedData;
} else {
    $scraper = new SmartrzScraper();
    $result = $scraper->scrapeAndModify();
    $cache->saveToCache($result);
}

$streamData = $result['data'];
$success = $result['success'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔴 Smartrz Live - Interactive Learning Platform</title>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #0d0d0d;
            color: #ffffff;
            min-height: 100vh;
            overflow-x: hidden;
            line-height: 1.6;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 20%, rgba(74, 144, 226, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(42, 217, 181, 0.15) 0%, transparent 50%),
                linear-gradient(135deg, #0d0d0d 0%, #0f1a1a 50%, #0d0f1a 100%);
            z-index: -2;
        }

        .streaming-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0.03;
            background-image: 
                repeating-linear-gradient(0deg, transparent, transparent 2px, #fff 2px, #fff 4px),
                repeating-linear-gradient(90deg, transparent, transparent 2px, #fff 2px, #fff 4px);
            animation: scanlines 8s linear infinite;
            z-index: -1;
        }

        @keyframes scanlines {
            0% { transform: translateY(0); }
            100% { transform: translateY(20px); }
        }

        .header-container {
            background: rgba(13, 13, 13, 0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .logo-img {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            background: linear-gradient(135deg, #4a90e2, #2ad9b5);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2rem;
            box-shadow: 0 0 25px rgba(74, 144, 226, 0.4);
        }

        .logo-text {
            font-family: 'JetBrains Mono', monospace;
            font-size: 1.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #4a90e2, #2ad9b5);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .status-indicator {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.7);
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #2ad9b5;
            animation: pulse 2s infinite;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .live-header {
            text-align: center;
            margin-bottom: 3rem;
            position: relative;
        }

        .live-indicator {
            display: inline-flex;
            align-items: center;
            background: rgba(74, 144, 226, 0.2);
            border: 2px solid #4a90e2;
            border-radius: 50px;
            padding: 0.6rem 1.5rem;
            margin-bottom: 1.5rem;
            backdrop-filter: blur(15px);
            animation: livePulse 2s infinite;
        }

        .live-dot {
            width: 10px;
            height: 10px;
            background: #4a90e2;
            border-radius: 50%;
            margin-right: 0.8rem;
            box-shadow: 0 0 15px rgba(74, 144, 226, 0.8);
            animation: blink 1s infinite alternate;
        }

        .main-title {
            font-family: 'JetBrains Mono', monospace;
            font-size: clamp(2.5rem, 5vw, 3.5rem);
            font-weight: 700;
            background: linear-gradient(135deg, #4a90e2 0%, #2ad9b5 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 1rem;
            text-shadow: 0 0 30px rgba(74, 144, 226, 0.3);
        }

        .stream-subtitle {
            font-size: 1.3rem;
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 2rem;
            font-weight: 500;
        }

        .stream-stats {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-bottom: 3rem;
            flex-wrap: wrap;
        }

        .stat-item {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 1rem 1.5rem;
            backdrop-filter: blur(10px);
            text-align: center;
            min-width: 120px;
            transition: transform 0.3s ease;
        }

        .stat-item:hover {
            transform: translateY(-5px);
        }

        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            font-family: 'JetBrains Mono', monospace;
        }

        .stat-label {
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.7);
            margin-top: 0.3rem;
        }

        .live-tabs {
            display: flex;
            justify-content: center;
            margin-bottom: 3rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 25px;
            padding: 8px;
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            overflow-x: auto;
        }

        .tab-btn {
            background: none;
            border: none;
            color: rgba(255, 255, 255, 0.7);
            padding: 1rem 2rem;
            border-radius: 20px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            white-space: nowrap;
            position: relative;
            overflow: hidden;
        }

        .tab-btn.active {
            background: linear-gradient(135deg, #4a90e2, #2ad9b5);
            color: white;
            box-shadow: 0 0 25px rgba(74, 144, 226, 0.4);
        }

        .tab-btn:hover:not(.active) {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
            animation: fadeIn 0.5s ease;
        }

        .stream-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .stream-card {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 1.2rem;
            position: relative;
            backdrop-filter: blur(15px);
            transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            opacity: 0;
            transform: translateY(30px);
            animation: slideUp 0.8s ease forwards;
        }

        .stream-card:hover {
            transform: translateY(-8px) scale(1.02);
            border-color: rgba(74, 144, 226, 0.3);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }

        .stream-badge {
            position: absolute;
            top: -8px;
            right: 15px;
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            font-family: 'JetBrains Mono', monospace;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            z-index: 2;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .live-badge {
            background: linear-gradient(135deg, #4a90e2, #2ad9b5);
            box-shadow: 0 0 20px rgba(74, 144, 226, 0.5);
        }

        .upcoming-badge {
            background: linear-gradient(135deg, #8a2be2, #4a90e2);
            box-shadow: 0 0 20px rgba(138, 43, 226, 0.5);
        }

        .completed-badge {
            background: linear-gradient(135deg, #00cc66, #2ad9b5);
            box-shadow: 0 0 20px rgba(0, 204, 102, 0.5);
        }

        .badge-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: currentColor;
            animation: pulse 2s infinite;
        }

        .card-thumbnail {
            position: relative;
            border-radius: 15px;
            overflow: hidden;
            height: 200px;
            margin-bottom: 1.2rem;
            background: rgba(0, 0, 0, 0.3);
        }

        .card-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.6s ease;
        }

        .stream-card:hover .card-thumbnail img {
            transform: scale(1.1);
        }

        .stream-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .stream-card:hover .stream-overlay {
            opacity: 1;
        }

        .play-button {
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.2);
            border: 2px solid white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(10px);
            transform: scale(0.8);
            transition: transform 0.3s ease;
        }

        .stream-card:hover .play-button {
            transform: scale(1);
        }

        .play-icon {
            font-size: 1.5rem;
            margin-left: 3px;
        }

        .stream-info {
            text-align: center;
        }

        .stream-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            min-height: 3em;
        }

        .channel-name {
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 1rem;
            font-size: 0.9rem;
            min-height: 1.5em;
        }

        .stream-btn {
            display: inline-block;
            padding: 0.8rem 1.8rem;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            width: 100%;
            text-align: center;
            border: none;
            cursor: pointer;
        }

        .live-btn {
            background: linear-gradient(135deg, #4a90e2, #2ad9b5);
            color: white;
        }

        .live-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(74, 144, 226, 0.4);
        }

        .upcoming-btn {
            background: linear-gradient(135deg, #8a2be2, #4a90e2);
            color: white;
        }

        .upcoming-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(138, 43, 226, 0.4);
        }

        .completed-btn {
            background: linear-gradient(135deg, #00cc66, #2ad9b5);
            color: white;
        }

        .completed-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 204, 102, 0.4);
        }

        .btn-shine {
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.6s ease;
        }

        .stream-btn:hover .btn-shine {
            left: 100%;
        }

        .no-content {
            text-align: center;
            padding: 4rem 2rem;
            background: rgba(255, 255, 255, 0.03);
            border: 2px dashed rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            margin: 2rem 0;
        }

        .status-icon {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            opacity: 0.7;
        }

        .no-content h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: rgba(255, 255, 255, 0.9);
        }

        .no-content p {
            color: rgba(255, 255, 255, 0.6);
            font-size: 1rem;
            max-width: 400px;
            margin: 0 auto;
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(13, 13, 13, 0.95);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            transition: opacity 0.5s ease;
        }

        .loading-spinner {
            width: 60px;
            height: 60px;
            border: 3px solid rgba(255, 255, 255, 0.1);
            border-top: 3px solid #4a90e2;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 1.5rem;
        }

        .loading-text {
            color: rgba(255, 255, 255, 0.8);
            font-size: 1.1rem;
            font-weight: 500;
        }

        .data-status {
            text-align: center;
            padding: 1rem;
            margin: 1rem 0;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.05);
        }

        .data-status.live {
            background: rgba(74, 144, 226, 0.1);
            border: 1px solid rgba(74, 144, 226, 0.3);
            color: #4a90e2;
        }

        .data-status.cached {
            background: rgba(42, 217, 181, 0.1);
            border: 1px solid rgba(42, 217, 181, 0.3);
            color: #2ad9b5;
        }

        @keyframes livePulse {
            0%, 100% { 
                box-shadow: 0 0 0 0 rgba(74, 144, 226, 0.7);
            }
            50% {
                box-shadow: 0 0 0 10px rgba(74, 144, 226, 0);
            }
        }

        @keyframes blink {
            0% { opacity: 1; }
            100% { opacity: 0.3; }
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.2); opacity: 0.7; }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes slideUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @media (max-width: 768px) {
            .header-content {
                padding: 1rem;
            }

            .logo-text {
                font-size: 1.2rem;
            }

            .container {
                padding: 1rem;
            }

            .main-title {
                font-size: 2.2rem;
            }

            .stream-subtitle {
                font-size: 1.1rem;
            }

            .stream-stats {
                gap: 1rem;
            }

            .stat-item {
                padding: 0.8rem 1rem;
                min-width: 100px;
            }

            .stat-number {
                font-size: 1.5rem;
            }

            .live-tabs {
                padding: 6px;
            }

            .tab-btn {
                padding: 0.8rem 1.2rem;
                font-size: 0.9rem;
            }

            .stream-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }

            .stream-card {
                padding: 1rem;
            }

            .card-thumbnail {
                height: 180px;
            }
        }

        @media (max-width: 480px) {
            .stream-stats {
                flex-direction: column;
                align-items: center;
            }
            
            .stat-item {
                width: 100%;
                max-width: 200px;
            }
            
            .live-tabs {
                flex-direction: column;
                gap: 5px;
            }
            
            .tab-btn {
                border-radius: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="streaming-bg"></div>
    
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
        <div class="loading-text">Initializing Smartrz Live Platform...</div>
    </div>

    <header class="header-container">
        <div class="header-content">
            <div class="logo-section">
                <div class="logo-img">S</div>
                <span class="logo-text">Smartrz</span>
            </div>
            <div class="status-indicator">
                <div class="status-dot"></div>
                <span>Live Data: <?php echo $success ? 'Connected' : 'Offline'; ?></span>
            </div>
        </div>
    </header>

    <div class="container">
        <div class="live-header">
            <div class="live-indicator">
                <div class="live-dot"></div>
                <span>SMARTRZ LIVE STREAMING PLATFORM</span>
            </div>
            <h1 class="main-title">Smartrz Live</h1>
            <p class="stream-subtitle">Interactive Learning • Expert-Led Sessions • Real-time Education</p>
            
            <div class="stream-stats">
                <div class="stat-item">
                    <div class="stat-number" style="color: #4a90e2;" id="liveCount"><?php echo count($streamData['live']); ?></div>
                    <div class="stat-label">Live Now</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number" style="color: #8a2be2;" id="upcomingCount"><?php echo count($streamData['upcoming']); ?></div>
                    <div class="stat-label">Scheduled</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number" style="color: #00cc66;" id="completedCount"><?php echo count($streamData['completed']); ?></div>
                    <div class="stat-label">Recordings</div>
                </div>
            </div>

            <?php if (!$success): ?>
            <div class="data-status">
                ⚠️ Using cached data. Live updates temporarily unavailable.
            </div>
            <?php elseif ($cachedData): ?>
            <div class="data-status cached">
                ✅ Live data loaded successfully (Cached)
            </div>
            <?php else: ?>
            <div class="data-status live">
                🔄 Fresh data loaded from source
            </div>
            <?php endif; ?>
        </div>

        <div class="live-tabs">
            <button class="tab-btn active" onclick="switchTab('live')">
                🔴 Live Streams
            </button>
            <button class="tab-btn" onclick="switchTab('upcoming')">
                ⏳ Upcoming Sessions
            </button>
            <button class="tab-btn" onclick="switchTab('completed')">
                📺 Recorded Classes
            </button>
        </div>

        <!-- Live Streams Tab -->
        <div id="live" class="tab-content active">
            <?php if (empty($streamData['live'])): ?>
                <div class='no-content'>
                    <div class='status-icon'>🔴</div>
                    <h3>No Live Classes Available</h3>
                    <p>There are no live sessions at the moment. Check back later or browse our upcoming sessions and recordings.</p>
                </div>
            <?php else: ?>
                <div class='stream-grid'>
                    <?php foreach ($streamData['live'] as $index => $stream): ?>
                    <div class='stream-card' style='animation-delay: <?php echo $index * 0.1; ?>s'>
                        <div class='stream-badge live-badge'>
                            <div class='badge-dot'></div>LIVE NOW
                        </div>
                        <div class='card-thumbnail'>
                            <img src='<?php echo htmlspecialchars($stream['thumbnail']); ?>' alt='<?php echo htmlspecialchars($stream['title']); ?>' loading='lazy' onerror="this.src='https://via.placeholder.com/400x225/1a1a2e/4a90e2?text=Smartrz+Live'">
                            <div class='stream-overlay'>
                                <div class='play-button'>
                                    <div class='play-icon'>▶</div>
                                </div>
                            </div>
                        </div>
                        <div class='stream-info'>
                            <h3 class='stream-title'><?php echo htmlspecialchars($stream['title']); ?></h3>
                            <p class='channel-name'><?php echo htmlspecialchars($stream['channel']); ?></p>
                            <a href='<?php echo htmlspecialchars($stream['url']); ?>' target='_blank' class='stream-btn live-btn'>
                                <span>Join Live Session</span>
                                <div class='btn-shine'></div>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Upcoming Streams Tab -->
        <div id="upcoming" class="tab-content">
            <?php if (empty($streamData['upcoming'])): ?>
                <div class='no-content'>
                    <div class='status-icon'>⏰</div>
                    <h3>No Upcoming Sessions</h3>
                    <p>No sessions are scheduled at the moment. New classes will be added soon - stay tuned!</p>
                </div>
            <?php else: ?>
                <div class='stream-grid'>
                    <?php foreach ($streamData['upcoming'] as $index => $stream): ?>
                    <div class='stream-card' style='animation-delay: <?php echo $index * 0.1; ?>s'>
                        <div class='stream-badge upcoming-badge'>
                            <div class='badge-dot'></div>COMING SOON
                        </div>
                        <div class='card-thumbnail'>
                            <img src='<?php echo htmlspecialchars($stream['thumbnail']); ?>' alt='<?php echo htmlspecialchars($stream['title']); ?>' loading='lazy' onerror="this.src='https://via.placeholder.com/400x225/1a1a2e/8a2be2?text=Smartrz+Upcoming'">
                            <div class='stream-overlay'>
                                <div class='play-button'>
                                    <div class='play-icon'>⏰</div>
                                </div>
                            </div>
                        </div>
                        <div class='stream-info'>
                            <h3 class='stream-title'><?php echo htmlspecialchars($stream['title']); ?></h3>
                            <p class='channel-name'><?php echo htmlspecialchars($stream['channel']); ?></p>
                            <a href='<?php echo htmlspecialchars($stream['url']); ?>' target='_blank' class='stream-btn upcoming-btn'>
                                <span>Set Reminder</span>
                                <div class='btn-shine'></div>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Completed Streams Tab -->
        <div id="completed" class="tab-content">
            <?php if (empty($streamData['completed'])): ?>
                <div class='no-content'>
                    <div class='status-icon'>📺</div>
                    <h3>No Recorded Classes Available</h3>
                    <p>No class recordings are available at the moment. Recordings will appear here after live sessions end.</p>
                </div>
            <?php else: ?>
                <div class='stream-grid'>
                    <?php foreach ($streamData['completed'] as $index => $stream): ?>
                    <div class='stream-card' style='animation-delay: <?php echo $index * 0.1; ?>s'>
                        <div class='stream-badge completed-badge'>
                            <div class='badge-dot'></div>RECORDED
                        </div>
                        <div class='card-thumbnail'>
                            <img src='<?php echo htmlspecialchars($stream['thumbnail']); ?>' alt='<?php echo htmlspecialchars($stream['title']); ?>' loading='lazy' onerror="this.src='https://via.placeholder.com/400x225/1a1a2e/00cc66?text=Smartrz+Recording'">
                            <div class='stream-overlay'>
                                <div class='play-button'>
                                    <div class='play-icon'>📺</div>
                                </div>
                            </div>
                        </div>
                        <div class='stream-info'>
                            <h3 class='stream-title'><?php echo htmlspecialchars($stream['title']); ?></h3>
                            <p class='channel-name'><?php echo htmlspecialchars($stream['channel']); ?></p>
                            <a href='<?php echo htmlspecialchars($stream['url']); ?>' target='_blank' class='stream-btn completed-btn'>
                                <span>Watch Recording</span>
                                <div class='btn-shine'></div>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Tab switching functionality
        function switchTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Remove active class from all tab buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to clicked tab button
            event.target.classList.add('active');
        }

        // Auto-hide loading screen
        window.addEventListener('load', () => {
            setTimeout(() => {
                const loadingOverlay = document.getElementById('loadingOverlay');
                loadingOverlay.style.opacity = '0';
                setTimeout(() => {
                    loadingOverlay.style.display = 'none';
                }, 500);
            }, 1000);
        });

        // Auto-refresh live data every 2 minutes
        setInterval(() => {
            if (document.querySelector('.tab-content.active').id === 'live') {
                window.location.reload();
            }
        }, 120000);

        // Add animation to stat counters
        function animateCounter(elementId, targetValue) {
            const element = document.getElementById(elementId);
            let current = 0;
            const increment = targetValue / 30;
            
            const timer = setInterval(() => {
                current += increment;
                if (current >= targetValue) {
                    element.textContent = targetValue;
                    clearInterval(timer);
                } else {
                    element.textContent = Math.floor(current);
                }
            }, 50);
        }

        // Initialize counter animations
        document.addEventListener('DOMContentLoaded', () => {
            const liveCount = <?php echo count($streamData['live']); ?>;
            const upcomingCount = <?php echo count($streamData['upcoming']); ?>;
            const completedCount = <?php echo count($streamData['completed']); ?>;
            
            setTimeout(() => {
                animateCounter('liveCount', liveCount);
                animateCounter('upcomingCount', upcomingCount);
                animateCounter('completedCount', completedCount);
            }, 500);
        });
    </script>
</body>
</html>
