<?php
/**
 * SCRIPT: PROFESSIONAL GOOGLE INDEXED URLS EXTRACTOR v3.0
 * Fungsi: Extract direktori/path terindex di Google dengan UI Professional
 * Input: URL domain saja
 * Output: Semua direktori terindex dengan interface premium
 * 
 * METODE EKSTRAKSI:
 * 1. Sitemap.xml (jika ada)
 * 2. Google Cache/Inurl search simulation
 * 3. robots.txt parsing
 * 4. Common directories scanning
 * 5. Meta links extraction dari halaman
 * 6. Web crawling otomatis
 */

@ini_set('display_errors', 1);
@ini_set('display_startup_errors', 1);
@error_reporting(E_ALL);
@set_time_limit(600);
@ini_set('memory_limit', '512M');

// ==========================================
// GLOBAL CONFIGURATION
// ==========================================
$USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
$TIMEOUT = 15;
$MAX_PAGES_CRAWL = 10;

// ==========================================
// FUNCTION: Create safe HTTP context
// ==========================================
function createHttpContext() {
    global $USER_AGENT, $TIMEOUT;
    return stream_context_create([
        "http" => [
            "header" => "User-Agent: $USER_AGENT\r\nConnection: close\r\n",
            "timeout" => $TIMEOUT,
            "ignore_errors" => true
        ],
        "ssl" => [
            "verify_peer" => false,
            "verify_peer_name" => false,
        ]
    ]);
}

// ==========================================
// FUNCTION: Extract URLs dari Sitemap.xml
// ==========================================
function extractFromSitemap($domain) {
    $domain = rtrim($domain, '/');
    $urls = [];
    $context = createHttpContext();
    
    $sitemapUrls = [
        $domain . '/sitemap.xml',
        $domain . '/sitemap_index.xml',
        $domain . '/sitemap1.xml',
        $domain . '/sitemap-index.xml',
        $domain . '/sitemap-main.xml',
    ];
    
    foreach ($sitemapUrls as $sitemapUrl) {
        $content = @file_get_contents($sitemapUrl, false, $context);
        if ($content && strpos($content, '<') !== false) {
            preg_match_all('#<loc>([^<]+)</loc>#i', $content, $matches);
            if (!empty($matches[1])) {
                $urls = array_merge($urls, $matches[1]);
            }
            
            preg_match_all('#<sitemap>.*?<loc>([^<]+)</loc>.*?</sitemap>#is', $content, $submaps);
            if (!empty($submaps[1])) {
                foreach ($submaps[1] as $submap) {
                    $subcontent = @file_get_contents(trim($submap), false, $context);
                    if ($subcontent) {
                        preg_match_all('#<loc>([^<]+)</loc>#i', $subcontent, $submatches);
                        if (!empty($submatches[1])) {
                            $urls = array_merge($urls, $submatches[1]);
                        }
                    }
                }
            }
        }
    }
    
    return array_unique($urls);
}

// ==========================================
// FUNCTION: Extract URLs dari Meta tags & Links
// ==========================================
function extractFromPageLinks($domain) {
    $domain = rtrim($domain, '/');
    $urls = [];
    $context = createHttpContext();
    
    $html = @file_get_contents($domain, false, $context);
    if (!$html) {
        return $urls;
    }
    
    preg_match_all('#href=["\']([^"\']+)["\']#i', $html, $matches);
    if (!empty($matches[1])) {
        foreach ($matches[1] as $link) {
            $link = trim($link);
            if (empty($link) || $link === '#' || strpos($link, 'javascript:') === 0) {
                continue;
            }
            
            if (strpos($link, 'http') !== 0 && strpos($link, '//') !== 0) {
                $link = rtrim($domain, '/') . '/' . ltrim($link, '/');
            }
            
            if (strpos($link, $domain) === 0) {
                $urls[] = $link;
            }
        }
    }
    
    preg_match_all('#(?:canonical|og:url|twitter:url|content)["\']?\s*(?:href=|content=)?["\']([^"\'<>]+)["\']#i', $html, $metamatches);
    if (!empty($metamatches[1])) {
        foreach ($metamatches[1] as $url) {
            if (strpos($url, $domain) === 0) {
                $urls[] = $url;
            }
        }
    }
    
    return array_unique($urls);
}

// ==========================================
// FUNCTION: Crawl website
// ==========================================
function crawlWebsite($domain, $maxPages = 10) {
    $domain = rtrim($domain, '/');
    $context = createHttpContext();
    $visited = [];
    $toVisit = [$domain . '/'];
    $urls = [];
    
    $count = 0;
    while (!empty($toVisit) && $count < $maxPages) {
        $currentUrl = array_shift($toVisit);
        
        if (in_array($currentUrl, $visited)) {
            continue;
        }
        
        $visited[] = $currentUrl;
        
        $html = @file_get_contents($currentUrl, false, $context);
        if (!$html) {
            continue;
        }
        
        $urls[] = $currentUrl;
        
        preg_match_all('#href=["\']([^"\']+)["\']#i', $html, $matches);
        if (!empty($matches[1])) {
            foreach ($matches[1] as $link) {
                $link = trim($link);
                if (empty($link) || $link === '#' || strpos($link, 'javascript:') === 0) {
                    continue;
                }
                
                if (strpos($link, 'http') !== 0 && strpos($link, '//') !== 0) {
                    $link = rtrim($domain, '/') . '/' . ltrim($link, '/');
                }
                
                $parsed = parse_url($link);
                $clean_url = $parsed['scheme'] . '://' . $parsed['host'] . ($parsed['path'] ?? '/');
                if (isset($parsed['query'])) {
                    $clean_url .= '?' . $parsed['query'];
                }
                
                if (strpos($clean_url, $domain) === 0 && !in_array($clean_url, $visited)) {
                    $toVisit[] = $clean_url;
                }
            }
        }
        
        $count++;
        usleep(500000);
    }
    
    return array_unique($urls);
}

// ==========================================
// FUNCTION: Check Common Directories
// ==========================================
function checkCommonDirectories($domain) {
    $domain = rtrim($domain, '/');
    $context = createHttpContext();
    $foundUrls = [];
    
    $commonDirs = [
        'blog', 'news', 'articles', 'post', 'posts', 'page', 'pages',
        'category', 'categories', 'tag', 'tags', 'author', 'authors',
        'product', 'products', 'service', 'services', 'portfolio',
        'tutorial', 'guide', 'documentation', 'docs', 'api',
        'download', 'downloads', 'resource', 'resources',
        'gallery', 'media', 'video', 'videos', 'images',
        'about', 'contact', 'sitemap', 'search', 'archive',
        'privacy', 'terms', 'policy', 'faq', 'help',
        'profile', 'user', 'account', 'dashboard', 'admin',
        'shop', 'store', 'cart', 'checkout', 'order',
        'events', 'event', 'schedule', 'calendar', 'time',
        'testimonial', 'testimonials', 'review', 'reviews',
        'forum', 'comment', 'discussion', 'feed', 'rss'
    ];
    
    foreach ($commonDirs as $dir) {
        $url = $domain . '/' . $dir . '/';
        $response = @get_headers($url, 1, $context);
        
        if ($response !== false) {
            $status = (int) substr($response[0], 9, 3);
            if ($status === 200 || $status === 301 || $status === 302) {
                $foundUrls[] = $url;
            }
        }
    }
    
    return $foundUrls;
}

// ==========================================
// FUNCTION: Extract dari robots.txt
// ==========================================
function extractFromRobotsTxt($domain) {
    $domain = rtrim($domain, '/');
    $context = createHttpContext();
    $urls = [];
    
    $robotsContent = @file_get_contents($domain . '/robots.txt', false, $context);
    if ($robotsContent) {
        preg_match_all('#Sitemap:\s*(.+)#i', $robotsContent, $matches);
        if (!empty($matches[1])) {
            foreach ($matches[1] as $sitemapUrl) {
                $urls[] = trim($sitemapUrl);
            }
        }
        
        preg_match_all('#Disallow:\s*(/[^\s]*)#i', $robotsContent, $disallows);
        if (!empty($disallows[1])) {
            foreach ($disallows[1] as $path) {
                $urls[] = $domain . trim($path);
            }
        }
        
        preg_match_all('#Allow:\s*(/[^\s]*)#i', $robotsContent, $allows);
        if (!empty($allows[1])) {
            foreach ($allows[1] as $path) {
                $urls[] = $domain . trim($path);
            }
        }
    }
    
    return array_unique($urls);
}

// ==========================================
// FUNCTION: Extract Directories
// ==========================================
function extractDirectories($urls, $domain) {
    $domain = rtrim(preg_replace(['#^https?://#', '#/$#'], '', $domain), '/');
    $directories = [];
    $stats = [
        'total_urls' => count($urls),
        'unique_dirs' => 0,
        'max_depth' => 0,
        'categories' => []
    ];
    
    foreach ($urls as $url) {
        $url = trim($url);
        if (empty($url)) continue;
        
        $parsed = parse_url($url);
        $path = isset($parsed['path']) ? trim($parsed['path'], '/') : '';
        
        if (empty($path)) continue;
        
        $pathParts = explode('/', $path);
        $depth = count(array_filter($pathParts));
        $stats['max_depth'] = max($stats['max_depth'], $depth);
        
        for ($i = 0; $i < count($pathParts) - 1; $i++) {
            $dir = implode('/', array_slice($pathParts, 0, $i + 1));
            if (!empty($dir) && !in_array($dir, $directories)) {
                $directories[] = $dir;
            }
        }
        
        $lastPart = end($pathParts);
        if (!preg_match('/\.(php|html|htm|aspx|jsp|pdf|jpg|png|gif|css|js)$/i', $lastPart)) {
            if (!in_array($path, $directories)) {
                $directories[] = $path;
            }
        }
    }
    
    sort($directories);
    $stats['unique_dirs'] = count($directories);
    
    foreach ($directories as $dir) {
        if (preg_match('/(blog|news|article|post)/i', $dir)) {
            $stats['categories'][] = ['type' => 'Blog/Content', 'dir' => $dir];
        } elseif (preg_match('/(product|shop|store|item)/i', $dir)) {
            $stats['categories'][] = ['type' => 'E-commerce', 'dir' => $dir];
        } elseif (preg_match('/(category|tag|filter|search)/i', $dir)) {
            $stats['categories'][] = ['type' => 'Taxonomy', 'dir' => $dir];
        }
    }
    
    return ['directories' => $directories, 'stats' => $stats];
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Professional Google Indexed Extractor v3.0</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #667eea;
            --primary-dark: #764ba2;
            --secondary: #f093fb;
            --success: #4CAF50;
            --warning: #ff9800;
            --danger: #f44336;
            --info: #2196F3;
            --light: #f5f7fa;
            --dark: #2c3e50;
            --border: #e0e6ed;
            --text: #3a4451;
            --text-light: #7a8fa0;
        }

        html, body {
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            padding: 20px;
            min-height: 100vh;
        }

        .wrapper {
            max-width: 1400px;
            margin: 0 auto;
        }

        /* HEADER */
        .header-premium {
            background: white;
            border-radius: 15px;
            padding: 50px 40px;
            margin-bottom: 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }

        .header-premium::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--primary) 0%, var(--secondary) 100%);
        }

        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 30px;
        }

        .header-text h1 {
            font-size: 36px;
            color: var(--dark);
            margin-bottom: 8px;
            font-weight: 700;
        }

        .header-text p {
            color: var(--text-light);
            font-size: 15px;
            line-height: 1.6;
        }

        .header-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 12px 20px;
            border-radius: 25px;
            font-size: 13px;
            font-weight: 600;
            margin-top: 15px;
            width: fit-content;
        }

        .header-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .stat-mini {
            text-align: center;
            padding: 12px;
            background: var(--light);
            border-radius: 10px;
        }

        .stat-mini strong {
            display: block;
            font-size: 22px;
            color: var(--primary);
            margin-bottom: 5px;
        }

        .stat-mini span {
            font-size: 12px;
            color: var(--text-light);
        }

        /* MAIN CONTAINER */
        .container-premium {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        @media (max-width: 1024px) {
            .container-premium {
                grid-template-columns: 1fr;
            }
        }

        /* CARD */
        .card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }

        .card:hover {
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.12);
        }

        .card-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .card-title::before {
            content: '';
            width: 4px;
            height: 24px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border-radius: 2px;
        }

        /* FORM */
        .form-group {
            margin-bottom: 22px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--dark);
            font-size: 14px;
        }

        .form-group input[type="url"],
        .form-group select {
            width: 100%;
            padding: 13px 16px;
            border: 2px solid var(--border);
            border-radius: 10px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.3s ease;
            color: var(--text);
        }

        .form-group input[type="url"]:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        .form-group small {
            display: block;
            margin-top: 6px;
            color: var(--text-light);
            font-size: 12px;
        }

        /* CHECKBOX GRID */
        .checkbox-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .checkbox-item {
            position: relative;
            padding: 14px;
            background: var(--light);
            border: 2px solid var(--border);
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .checkbox-item:hover {
            border-color: var(--primary);
            background: rgba(102, 126, 234, 0.05);
        }

        .checkbox-item input[type="checkbox"] {
            position: absolute;
            opacity: 0;
            cursor: pointer;
        }

        .checkbox-item input[type="checkbox"]:checked + label {
            color: var(--primary);
            font-weight: 700;
        }

        .checkbox-item input[type="checkbox"]:checked ~ .checkmark {
            background: var(--primary);
            border-color: var(--primary);
        }

        .checkbox-item input[type="checkbox"]:checked ~ .checkmark::after {
            display: block;
        }

        .checkmark {
            position: absolute;
            top: 14px;
            left: 14px;
            height: 18px;
            width: 18px;
            background-color: white;
            border: 2px solid var(--border);
            border-radius: 4px;
            transition: all 0.3s ease;
        }

        .checkmark::after {
            content: '';
            position: absolute;
            display: none;
            left: 4px;
            top: 1px;
            width: 6px;
            height: 10px;
            border: solid white;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }

        .checkbox-item label {
            margin-left: 30px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .checkbox-item label strong {
            display: block;
            font-weight: 600;
            color: var(--dark);
        }

        .checkbox-item label span {
            font-size: 11px;
            color: var(--text-light);
        }

        /* BUTTON */
        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 16px 32px;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }

        .btn-primary:active {
            transform: translateY(-1px);
        }

        /* INFO BOX */
        .info-box {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(240, 147, 251, 0.1) 100%);
            border: 1px solid rgba(102, 126, 234, 0.2);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
        }

        .info-box strong {
            color: var(--primary);
            display: block;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .info-box ul {
            list-style: none;
        }

        .info-box li {
            font-size: 13px;
            color: var(--text);
            margin-bottom: 8px;
            padding-left: 20px;
            position: relative;
        }

        .info-box li::before {
            content: '✓';
            position: absolute;
            left: 0;
            color: var(--success);
            font-weight: bold;
        }

        /* RESULT SECTION */
        .results-container {
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            margin-top: 30px;
        }

        .result-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--border);
        }

        .result-header h2 {
            font-size: 24px;
            color: var(--dark);
        }

        .result-status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: rgba(76, 175, 80, 0.1);
            color: var(--success);
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }

        .result-status.loading {
            background: rgba(33, 150, 243, 0.1);
            color: var(--info);
        }

        .result-status.error {
            background: rgba(244, 67, 54, 0.1);
            color: var(--danger);
        }

        /* STATS GRID */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: linear-gradient(135deg, var(--light) 0%, white 100%);
            padding: 25px;
            border-radius: 12px;
            border: 1px solid var(--border);
            text-align: center;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .stat-card strong {
            display: block;
            font-size: 36px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }

        .stat-card span {
            font-size: 13px;
            color: var(--text-light);
            font-weight: 500;
        }

        /* METHODS GRID */
        .methods-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 40px;
        }

        .method-badge {
            background: linear-gradient(135deg, var(--light) 0%, white 100%);
            padding: 20px;
            border-radius: 12px;
            border: 1px solid var(--border);
            text-align: center;
            transition: all 0.3s ease;
        }

        .method-badge:hover {
            border-color: var(--primary);
            background: rgba(102, 126, 234, 0.05);
        }

        .method-badge strong {
            display: block;
            color: var(--dark);
            margin-bottom: 5px;
            font-size: 14px;
        }

        .method-badge .count {
            font-size: 24px;
            color: var(--primary);
            font-weight: 700;
            margin: 8px 0;
        }

        .method-badge span {
            font-size: 11px;
            color: var(--text-light);
        }

        /* ALERT */
        .alert {
            padding: 16px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
        }

        .alert-info {
            background: rgba(33, 150, 243, 0.1);
            border-color: var(--info);
            color: #0d47a1;
        }

        .alert-success {
            background: rgba(76, 175, 80, 0.1);
            border-color: var(--success);
            color: #1b5e20;
        }

        .alert-warning {
            background: rgba(255, 152, 0, 0.1);
            border-color: var(--warning);
            color: #e65100;
        }

        .alert-danger {
            background: rgba(244, 67, 54, 0.1);
            border-color: var(--danger);
            color: #b71c1c;
        }

        .alert::before {
            font-size: 18px;
        }

        /* TABS */
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid var(--border);
        }

        .tab-btn {
            padding: 12px 20px;
            background: none;
            border: none;
            cursor: pointer;
            font-weight: 600;
            color: var(--text-light);
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .tab-btn.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }

        .tab-btn:hover {
            color: var(--dark);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* TEXTAREA */
        .code-block {
            background: #f5f7fa;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 0;
            overflow: hidden;
            margin-bottom: 20px;
        }

        .code-block-header {
            background: var(--light);
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
            font-size: 13px;
            font-weight: 600;
            color: var(--text-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .code-block-content {
            padding: 16px;
        }

        .code-block textarea {
            width: 100%;
            height: 250px;
            padding: 0;
            border: none;
            border-radius: 0;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            background: transparent;
            resize: vertical;
            color: var(--text);
        }

        .code-block textarea:focus {
            outline: none;
        }

        .copy-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 8px 14px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .copy-btn:hover {
            background: var(--primary-dark);
        }

        /* BUTTON GROUP */
        .button-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            margin-top: 25px;
        }

        .btn-export {
            background: var(--success);
            color: white;
            border: none;
            padding: 12px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-export:hover {
            background: #45a049;
            transform: translateY(-2px);
        }

        /* LOADING */
        .spinner {
            border: 3px solid rgba(102, 126, 234, 0.1);
            border-top: 3px solid var(--primary);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .loading-container {
            text-align: center;
            padding: 40px;
        }

        .loading-container p {
            color: var(--text-light);
            margin-top: 10px;
        }

        /* FOOTER */
        .footer {
            text-align: center;
            padding: 20px;
            color: rgba(255, 255, 255, 0.7);
            font-size: 13px;
            margin-top: 40px;
        }
    </style>
</head>
<body>

<div class="wrapper">
    <!-- HEADER -->
    <div class="header-premium">
        <div class="header-content">
            <div class="header-text">
                <h1>🔍 Professional Indexed Extractor</h1>
                <p>Extract semua direktori terindex di Google dengan teknologi multi-method canggih</p>
                <div class="header-badge">
                    ✨ 6 Methods • Multi-Source • Real-Time Analysis
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="container-premium">
        <!-- INPUT CARD -->
        <div class="card">
            <div class="card-title">📝 Input Domain</div>
            <form method="POST">
                <div class="form-group">
                    <label>🌐 URL Domain</label>
                    <input type="url" name="domain" placeholder="https://example.com" required value="<?php echo isset($_POST['domain']) ? htmlspecialchars($_POST['domain']) : ''; ?>">
                    <small>Format: https://example.com (dengan protokol)</small>
                </div>

                <button type="submit" name="extract" value="1" class="btn-primary">🚀 Mulai Analisis</button>
            </form>
        </div>

        <!-- METHODS CARD -->
        <div class="card">
            <div class="card-title">⚙️ Metode Ekstraksi</div>
            <form method="POST">
                <div class="checkbox-grid">
                    <div class="checkbox-item">
                        <input type="checkbox" id="sitemap" name="methods[]" value="sitemap" checked>
                        <div class="checkmark"></div>
                        <label for="sitemap">
                            <strong>Sitemap</strong>
                            <span>Extract dari XML</span>
                        </label>
                    </div>
                    <div class="checkbox-item">
                        <input type="checkbox" id="robots" name="methods[]" value="robots" checked>
                        <div class="checkmark"></div>
                        <label for="robots">
                            <strong>Robots.txt</strong>
                            <span>Parse paths</span>
                        </label>
                    </div>
                    <div class="checkbox-item">
                        <input type="checkbox" id="crawl" name="methods[]" value="crawl" checked>
                        <div class="checkmark"></div>
                        <label for="crawl">
                            <strong>Crawling</strong>
                            <span>Scan website</span>
                        </label>
                    </div>
                    <div class="checkbox-item">
                        <input type="checkbox" id="common" name="methods[]" value="common" checked>
                        <div class="checkmark"></div>
                        <label for="common">
                            <strong>Common Dirs</strong>
                            <span>Check umum</span>
                        </label>
                    </div>
                    <div class="checkbox-item">
                        <input type="checkbox" id="pagelinks" name="methods[]" value="pagelinks" checked>
                        <div class="checkmark"></div>
                        <label for="pagelinks">
                            <strong>Page Links</strong>
                            <span>Extract links</span>
                        </label>
                    </div>
                </div>

                <div class="info-box">
                    <strong>💡 Rekomendasi</strong>
                    <ul>
                        <li>Gunakan semua metode untuk hasil maksimal</li>
                        <li>Proses analysis ~10-30 detik</li>
                        <li>Semakin banyak method = hasil lebih lengkap</li>
                    </ul>
                </div>

                <button type="submit" name="extract" value="1" class="btn-primary">🚀 Mulai Analisis</button>
            </form>
        </div>
    </div>

<?php
// ==========================================
// PROSES EKSTRAKSI
// ==========================================
if (isset($_POST['extract']) && !empty($_POST['domain'])) {
    $domain = $_POST['domain'];
    $selectedMethods = $_POST['methods'] ?? [];
    $allUrls = [];
    $methodResults = [];
    
    if (empty($selectedMethods)) {
        echo '<div class="results-container">';
        echo '<div class="alert alert-danger">❌ Pilih minimal 1 metode ekstraksi!</div>';
        echo '</div>';
    } else {
        echo '<div class="results-container">';
        echo '<div class="result-header">';
        echo '<h2>📊 Hasil Analisis</h2>';
        echo '<span class="result-status loading">⏳ Processing...</span>';
        echo '</div>';
        
        // Method 1: Sitemap
        if (in_array('sitemap', $selectedMethods)) {
            echo '<div class="alert alert-info">📂 Mengekstrak dari Sitemap.xml...</div>';
            $sitemapUrls = extractFromSitemap($domain);
            $allUrls = array_merge($allUrls, $sitemapUrls);
            $methodResults['sitemap'] = count($sitemapUrls);
            flush();
        }
        
        // Method 2: Robots.txt
        if (in_array('robots', $selectedMethods)) {
            echo '<div class="alert alert-info">🤖 Scanning Robots.txt...</div>';
            $robotsUrls = extractFromRobotsTxt($domain);
            $allUrls = array_merge($allUrls, $robotsUrls);
            $methodResults['robots'] = count($robotsUrls);
            flush();
        }
        
        // Method 3: Page Links
        if (in_array('pagelinks', $selectedMethods)) {
            echo '<div class="alert alert-info">🔗 Extracting Page Links...</div>';
            $pageUrls = extractFromPageLinks($domain);
            $allUrls = array_merge($allUrls, $pageUrls);
            $methodResults['pagelinks'] = count($pageUrls);
            flush();
        }
        
        // Method 4: Web Crawling
        if (in_array('crawl', $selectedMethods)) {
            echo '<div class="alert alert-info">🕷️ Web Crawling (prosesnya membutuhkan waktu)...</div>';
            $crawlUrls = crawlWebsite($domain, $MAX_PAGES_CRAWL);
            $allUrls = array_merge($allUrls, $crawlUrls);
            $methodResults['crawl'] = count($crawlUrls);
            flush();
        }
        
        // Method 5: Common Directories
        if (in_array('common', $selectedMethods)) {
            echo '<div class="alert alert-info">📁 Checking Common Directories...</div>';
            $commonUrls = checkCommonDirectories($domain);
            $allUrls = array_merge($allUrls, $commonUrls);
            $methodResults['common'] = count($commonUrls);
            flush();
        }
        
        // Remove duplicates dan empty URLs
        $allUrls = array_unique($allUrls);
        $allUrls = array_filter($allUrls);
        sort($allUrls);
        
        if (empty($allUrls)) {
            echo '<div class="alert alert-warning">⚠️ Tidak ada URL yang ditemukan. Domain mungkin tidak dapat diakses.</div>';
        } else {
            // Extract directories
            $result = extractDirectories($allUrls, $domain);
            $directories = $result['directories'];
            $stats = $result['stats'];
            
            // SUCCESS STATUS
            echo '<script>document.querySelector(".result-status").className = "result-status"; document.querySelector(".result-status").innerHTML = "✅ Analisis Selesai";</script>';
            echo '<div class="alert alert-success">✅ Sukses! Total <strong>' . count($allUrls) . '</strong> URLs ditemukan</div>';
            
            // STATS GRID
            echo '<div class="stats-grid">';
            echo '<div class="stat-card"><strong>' . count($allUrls) . '</strong><span>Total URLs</span></div>';
            echo '<div class="stat-card"><strong>' . count($directories) . '</strong><span>Direktori Unik</span></div>';
            echo '<div class="stat-card"><strong>' . $stats['max_depth'] . '</strong><span>Kedalaman Maksimal</span></div>';
            echo '<div class="stat-card"><strong>' . count($selectedMethods) . '</strong><span>Methods Digunakan</span></div>';
            echo '</div>';
            
            // METHODS SUMMARY
            echo '<h3 style="margin: 30px 0 20px 0; color: var(--dark); font-size: 16px;">📈 Ringkasan Metode:</h3>';
            echo '<div class="methods-grid">';
            $methodLabels = [
                'sitemap' => '📂 Sitemap',
                'robots' => '🤖 Robots',
                'pagelinks' => '🔗 Links',
                'crawl' => '🕷️ Crawl',
                'common' => '📁 Common'
            ];
            foreach ($methodResults as $method => $count) {
                echo '<div class="method-badge">';
                echo '<strong>' . ($methodLabels[$method] ?? $method) . '</strong>';
                echo '<div class="count">' . $count . '</div>';
                echo '<span>URLs</span>';
                echo '</div>';
            }
            echo '</div>';
            
            // TABS & CONTENT
            echo '<div style="margin-top: 30px;">';
            echo '<div class="tabs">';
            echo '<button class="tab-btn active" onclick="switchTab(\'directories\')">📋 Direktori (' . count($directories) . ')</button>';
            echo '<button class="tab-btn" onclick="switchTab(\'urls\')">🔗 URLs Lengkap (' . count($allUrls) . ')</button>';
            echo '</div>';
            
            // TAB: DIRECTORIES
            echo '<div id="directories" class="tab-content active">';
            echo '<div class="code-block">';
            echo '<div class="code-block-header">';
            echo '<span>Daftar Direktori Terindex</span>';
            echo '<button class="copy-btn" onclick="copyToClipboard(\'directories-text\')">📋 Copy</button>';
            echo '</div>';
            echo '<div class="code-block-content">';
            echo '<textarea id="directories-text" readonly>';
            foreach ($directories as $dir) {
                echo $dir . "\n";
            }
            echo '</textarea>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            
            // TAB: URLS
            echo '<div id="urls" class="tab-content">';
            echo '<div class="code-block">';
            echo '<div class="code-block-header">';
            echo '<span>Daftar URL Lengkap</span>';
            echo '<button class="copy-btn" onclick="copyToClipboard(\'urls-text\')">📋 Copy</button>';
            echo '</div>';
            echo '<div class="code-block-content">';
            echo '<textarea id="urls-text" readonly>';
            foreach ($allUrls as $url) {
                echo $url . "\n";
            }
            echo '</textarea>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            
            // EXPORT BUTTONS
            echo '<div class="button-group">';
            echo '<form method="POST">';
            echo '<input type="hidden" name="export_dirs" value="' . base64_encode(json_encode($directories)) . '">';
            echo '<button type="submit" class="btn-export">💾 Export Direktori (.txt)</button>';
            echo '</form>';
            echo '<form method="POST">';
            echo '<input type="hidden" name="export_urls" value="' . base64_encode(json_encode($allUrls)) . '">';
            echo '<button type="submit" class="btn-export">💾 Export URLs (.txt)</button>';
            echo '</form>';
            echo '</div>';
        }
        
        echo '</div>';
    }
}

// ==========================================
// HANDLE EXPORT
// ==========================================
if (isset($_POST['export_dirs']) && !empty($_POST['export_dirs'])) {
    $directories = json_decode(base64_decode($_POST['export_dirs']), true);
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="indexed-directories-' . date('Y-m-d-His') . '.txt"');
    echo implode("\n", $directories);
    exit;
}

if (isset($_POST['export_urls']) && !empty($_POST['export_urls'])) {
    $urls = json_decode(base64_decode($_POST['export_urls']), true);
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="indexed-urls-' . date('Y-m-d-His') . '.txt"');
    echo implode("\n", $urls);
    exit;
}
?>

    <!-- FOOTER -->
    <div class="footer">
        <p>© 2024 Professional Google Indexed Extractor v3.0 | Premium UI Design</p>
    </div>
</div>

<script>
    function switchTab(tabName) {
        const tabs = document.querySelectorAll('.tab-content');
        const buttons = document.querySelectorAll('.tab-btn');
        
        tabs.forEach(tab => {
            tab.classList.remove('active');
        });
        buttons.forEach(btn => {
            btn.classList.remove('active');
        });
        
        document.getElementById(tabName).classList.add('active');
        event.target.classList.add('active');
    }
    
    function copyToClipboard(elementId) {
        const element = document.getElementById(elementId);
        element.select();
        document.execCommand('copy');
        
        const btn = event.target;
        const originalText = btn.innerHTML;
        btn.innerHTML = '✓ Copied!';
        setTimeout(() => {
            btn.innerHTML = originalText;
        }, 2000);
    }
</script>

</body>
</html>
