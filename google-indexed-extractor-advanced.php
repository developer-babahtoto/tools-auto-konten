<?php
/**
 * SCRIPT: ADVANCED GOOGLE INDEXED URLS EXTRACTOR v2.0
 * Fungsi: Extract direktori/path terindex di Google TANPA HARUS SITEMAP
 * Input: URL domain saja
 * Output: Semua direktori terindex dengan berbagai metode fallback
 * 
 * METODE EKSTRAKSI:
 * 1. Sitemap.xml (jika ada)
 * 2. Google Cache/Inurl search simulation
 * 3. robots.txt parsing
 * 4. Common directories scanning
 * 5. Meta links extraction dari halaman
 * 6. DNS & subdomain enumeration
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
            // Extract loc tags
            preg_match_all('#<loc>([^<]+)</loc>#i', $content, $matches);
            if (!empty($matches[1])) {
                $urls = array_merge($urls, $matches[1]);
            }
            
            // Jika ini sitemap index, extract sitemap references
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
    
    // Extract semua href links
    preg_match_all('#href=["\']([^"\']+)["\']#i', $html, $matches);
    if (!empty($matches[1])) {
        foreach ($matches[1] as $link) {
            $link = trim($link);
            if (empty($link) || $link === '#' || strpos($link, 'javascript:') === 0) {
                continue;
            }
            
            // Convert relative URLs to absolute
            if (strpos($link, 'http') !== 0 && strpos($link, '//') !== 0) {
                $link = rtrim($domain, '/') . '/' . ltrim($link, '/');
            }
            
            // Filter hanya URLs dari domain yang sama
            if (strpos($link, $domain) === 0) {
                $urls[] = $link;
            }
        }
    }
    
    // Extract dari canonical, og:url, dll
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
// FUNCTION: Crawl website untuk extract URLs
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
        
        // Extract links dari halaman
        preg_match_all('#href=["\']([^"\']+)["\']#i', $html, $matches);
        if (!empty($matches[1])) {
            foreach ($matches[1] as $link) {
                $link = trim($link);
                if (empty($link) || $link === '#' || strpos($link, 'javascript:') === 0) {
                    continue;
                }
                
                // Normalize URL
                if (strpos($link, 'http') !== 0 && strpos($link, '//') !== 0) {
                    $link = rtrim($domain, '/') . '/' . ltrim($link, '/');
                }
                
                // Parse URL untuk remove query strings & fragments
                $parsed = parse_url($link);
                $clean_url = $parsed['scheme'] . '://' . $parsed['host'] . ($parsed['path'] ?? '/');
                if (isset($parsed['query'])) {
                    $clean_url .= '?' . $parsed['query'];
                }
                
                // Filter hanya URLs dari domain yang sama
                if (strpos($clean_url, $domain) === 0 && !in_array($clean_url, $visited)) {
                    $toVisit[] = $clean_url;
                }
            }
        }
        
        $count++;
        usleep(500000); // 500ms delay
    }
    
    return array_unique($urls);
}

// ==========================================
// FUNCTION: Extract dari Common Directories
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
        // Extract Sitemap URLs
        preg_match_all('#Sitemap:\s*(.+)#i', $robotsContent, $matches);
        if (!empty($matches[1])) {
            foreach ($matches[1] as $sitemapUrl) {
                $urls[] = trim($sitemapUrl);
            }
        }
        
        // Extract Disallow paths (potential directories)
        preg_match_all('#Disallow:\s*(/[^\s]*)#i', $robotsContent, $disallows);
        if (!empty($disallows[1])) {
            foreach ($disallows[1] as $path) {
                $urls[] = $domain . trim($path);
            }
        }
        
        // Extract Allow paths
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
// FUNCTION: Query Google Cache (simulation)
// ==========================================
function extractFromGoogleCache($domain) {
    $domain = rtrim(preg_replace(['#^https?://#', '#/$#'], '', $domain), '/');
    $context = createHttpContext();
    $urls = [];
    
    // Try accessing Google Cache
    $cacheUrl = 'http://webcache.googleusercontent.com/cache:' . $domain . '/';
    $content = @file_get_contents($cacheUrl, false, $context);
    
    if ($content) {
        preg_match_all('#href=["\']([^"\']+)["\']#i', $content, $matches);
        if (!empty($matches[1])) {
            foreach ($matches[1] as $url) {
                if (strpos($url, $domain) !== false) {
                    $urls[] = $url;
                }
            }
        }
    }
    
    return $urls;
}

// ==========================================
// FUNCTION: Extract Directories dari URLs
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
        
        // Analyze path depth
        $pathParts = explode('/', $path);
        $depth = count(array_filter($pathParts));
        $stats['max_depth'] = max($stats['max_depth'], $depth);
        
        // Extract directories
        for ($i = 0; $i < count($pathParts) - 1; $i++) {
            $dir = implode('/', array_slice($pathParts, 0, $i + 1));
            if (!empty($dir) && !in_array($dir, $directories)) {
                $directories[] = $dir;
            }
        }
        
        // Add full path if not a file
        $lastPart = end($pathParts);
        if (!preg_match('/\.(php|html|htm|aspx|jsp|pdf|jpg|png|gif|css|js)$/i', $lastPart)) {
            if (!in_array($path, $directories)) {
                $directories[] = $path;
            }
        }
    }
    
    sort($directories);
    $stats['unique_dirs'] = count($directories);
    
    // Categorize directories
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
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Google Indexed Extractor v2.0</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        .header h1 { font-size: 32px; margin-bottom: 10px; }
        .header p { opacity: 0.95; font-size: 15px; margin-bottom: 15px; }
        .badge { 
            display: inline-block; 
            background: rgba(255,255,255,0.3); 
            padding: 5px 12px; 
            border-radius: 20px; 
            font-size: 12px; 
            font-weight: 600;
        }
        .content { padding: 40px 30px; }
        .form-group {
            margin-bottom: 25px;
        }
        label {
            display: block;
            font-weight: 700;
            margin-bottom: 10px;
            color: #333;
            font-size: 15px;
        }
        input[type="text"], input[type="url"], select {
            width: 100%;
            padding: 14px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
            font-family: inherit;
        }
        input[type="text"]:focus, input[type="url"]:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .methods-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 12px;
            margin-bottom: 25px;
        }
        .method-card {
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            background: #fafafa;
        }
        .method-card:hover {
            border-color: #667eea;
            background: #f0f4ff;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
        }
        .method-card input {
            margin-right: 10px;
            cursor: pointer;
        }
        .method-card label {
            margin: 0;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
        }
        .method-info {
            font-size: 12px;
            color: #666;
            margin-top: 8px;
            margin-left: 24px;
        }
        button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 16px 40px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }
        button:active {
            transform: translateY(0);
        }
        .result-section {
            margin-top: 30px;
            padding: 25px;
            background: #f8f9fa;
            border-radius: 10px;
            border-left: 5px solid #667eea;
        }
        .result-section h3 {
            color: #333;
            margin-bottom: 20px;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        textarea {
            width: 100%;
            height: 220px;
            padding: 14px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            resize: vertical;
            background: white;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-box {
            background: white;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            text-align: center;
        }
        .stat-box strong {
            display: block;
            font-size: 28px;
            color: #667eea;
            margin-bottom: 8px;
        }
        .stat-box span {
            font-size: 13px;
            color: #666;
            font-weight: 500;
        }
        .alert {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 18px;
            border-left: 4px solid;
            font-size: 14px;
        }
        .alert-info {
            background: #e3f2fd;
            border-color: #2196F3;
            color: #1565c0;
        }
        .alert-success {
            background: #e8f5e9;
            border-color: #4CAF50;
            color: #2e7d32;
        }
        .alert-warning {
            background: #fff3e0;
            border-color: #ff9800;
            color: #e65100;
        }
        .alert-danger {
            background: #ffebee;
            border-color: #f44336;
            color: #c62828;
        }
        .button-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 12px;
            margin-top: 18px;
        }
        .button-group button {
            padding: 12px;
            font-size: 13px;
            background: #28a745;
        }
        .button-group button:hover {
            background: #218838;
        }
        .method-sources {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
            margin-bottom: 25px;
        }
        .source-item {
            background: white;
            padding: 12px;
            border-radius: 6px;
            border: 1px solid #e0e0e0;
            font-size: 13px;
        }
        .source-item strong {
            color: #667eea;
            display: block;
            margin-bottom: 5px;
        }
        .source-item .count {
            color: #666;
            font-weight: 600;
        }
        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .info-box {
            background: #f0f4ff;
            padding: 18px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
            margin-bottom: 20px;
            font-size: 13px;
            line-height: 1.6;
        }
        .info-box ul {
            list-style: none;
            margin-left: 0;
        }
        .info-box li {
            margin-bottom: 8px;
        }
        .info-box li:before {
            content: "✓ ";
            color: #667eea;
            font-weight: bold;
            margin-right: 8px;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>🔍 Advanced Google Indexed Extractor</h1>
        <p>Extract SEMUA direktori terindex di Google tanpa perlu Sitemap</p>
        <span class="badge">✨ Multiple Methods • Multi-Source • No Sitemap Required</span>
    </div>
    
    <div class="content">
        <form method="POST">
            <div class="form-group">
                <label for="domain">🌐 Masukan URL Domain:</label>
                <input type="url" id="domain" name="domain" placeholder="https://example.com" required value="<?php echo isset($_POST['domain']) ? htmlspecialchars($_POST['domain']) : ''; ?>">
                <small style="color: #999; display: block; margin-top: 6px;">Format: https://example.com (dengan protokol)</small>
            </div>
            
            <div class="form-group">
                <label>📌 Pilih Metode Ekstraksi:</label>
                <div class="methods-grid">
                    <label class="method-card">
                        <input type="checkbox" name="methods[]" value="sitemap" checked>
                        <div>
                            <strong>Sitemap.xml</strong>
                            <div class="method-info">Extract dari sitemap.xml (paling akurat)</div>
                        </div>
                    </label>
                    <label class="method-card">
                        <input type="checkbox" name="methods[]" value="crawl" checked>
                        <div>
                            <strong>Web Crawling</strong>
                            <div class="method-info">Scan langsung website untuk menemukan URLs</div>
                        </div>
                    </label>
                    <label class="method-card">
                        <input type="checkbox" name="methods[]" value="robots" checked>
                        <div>
                            <strong>Robots.txt</strong>
                            <div class="method-info">Parse robots.txt untuk paths & sitemaps</div>
                        </div>
                    </label>
                    <label class="method-card">
                        <input type="checkbox" name="methods[]" value="common" checked>
                        <div>
                            <strong>Common Dirs</strong>
                            <div class="method-info">Check direktori umum yang sering ada</div>
                        </div>
                    </label>
                    <label class="method-card">
                        <input type="checkbox" name="methods[]" value="googlecache">
                        <div>
                            <strong>Google Cache</strong>
                            <div class="method-info">Extract dari Google Cache (jika tersedia)</div>
                        </div>
                    </label>
                    <label class="method-card">
                        <input type="checkbox" name="methods[]" value="pagelinks">
                        <div>
                            <strong>Page Links</strong>
                            <div class="method-info">Extract semua links dari halaman utama</div>
                        </div>
                    </label>
                </div>
            </div>
            
            <div class="info-box">
                <strong style="color: #667eea;">💡 Rekomendasi:</strong>
                <ul>
                    <li>Gunakan semua metode untuk hasil maksimal</li>
                    <li>Web Crawling akan memakan waktu ~10-30 detik</li>
                    <li>Semakin banyak metode yang digunakan, semakin lengkap hasilnya</li>
                </ul>
            </div>
            
            <button type="submit" name="extract" value="1">🚀 Mulai Ekstraksi Multi-Method</button>
        </form>
        
<?php
// ==========================================
// PROSES EKSTRAKSI
// ==========================================
if (isset($_POST['extract']) && !empty($_POST['domain'])) {
    $domain = $_POST['domain'];
    $selectedMethods = $_POST['methods'] ?? [];
    $allUrls = [];
    $methodResults = [];
    
    echo '<div class="result-section">';
    echo '<h3>📊 Hasil Ekstraksi Multi-Method</h3>';
    
    if (empty($selectedMethods)) {
        echo '<div class="alert alert-danger">❌ Pilih minimal 1 metode ekstraksi!</div>';
    } else {
        echo '<div class="loading" id="loading">';
        echo '<div class="spinner"></div>';
        echo '<p>Sedang memproses, mohon tunggu...</p>';
        echo '</div>';
        
        echo '<div id="results">';
        
        // Method 1: Sitemap
        if (in_array('sitemap', $selectedMethods)) {
            echo '<div class="alert alert-info">📂 [1/6] Mengekstrak dari Sitemap.xml...</div>';
            $sitemapUrls = extractFromSitemap($domain);
            $allUrls = array_merge($allUrls, $sitemapUrls);
            $methodResults['sitemap'] = count($sitemapUrls);
            echo '<p style="color: #666; font-size: 13px; margin-bottom: 12px;">✓ Ditemukan <strong>' . count($sitemapUrls) . '</strong> URL dari sitemap</p>';
            flush();
        }
        
        // Method 2: Robots.txt
        if (in_array('robots', $selectedMethods)) {
            echo '<div class="alert alert-info">🤖 [2/6] Scanning Robots.txt...</div>';
            $robotsUrls = extractFromRobotsTxt($domain);
            $allUrls = array_merge($allUrls, $robotsUrls);
            $methodResults['robots'] = count($robotsUrls);
            echo '<p style="color: #666; font-size: 13px; margin-bottom: 12px;">✓ Ditemukan <strong>' . count($robotsUrls) . '</strong> paths dari robots.txt</p>';
            flush();
        }
        
        // Method 3: Page Links
        if (in_array('pagelinks', $selectedMethods)) {
            echo '<div class="alert alert-info">🔗 [3/6] Extracting dari Page Links...</div>';
            $pageUrls = extractFromPageLinks($domain);
            $allUrls = array_merge($allUrls, $pageUrls);
            $methodResults['pagelinks'] = count($pageUrls);
            echo '<p style="color: #666; font-size: 13px; margin-bottom: 12px;">✓ Ditemukan <strong>' . count($pageUrls) . '</strong> links dari halaman</p>';
            flush();
        }
        
        // Method 4: Web Crawling
        if (in_array('crawl', $selectedMethods)) {
            echo '<div class="alert alert-info">🕷️ [4/6] Web Crawling (ini membutuhkan waktu)...</div>';
            $crawlUrls = crawlWebsite($domain, $MAX_PAGES_CRAWL);
            $allUrls = array_merge($allUrls, $crawlUrls);
            $methodResults['crawl'] = count($crawlUrls);
            echo '<p style="color: #666; font-size: 13px; margin-bottom: 12px;">✓ Ditemukan <strong>' . count($crawlUrls) . '</strong> URLs dari crawling</p>';
            flush();
        }
        
        // Method 5: Common Directories
        if (in_array('common', $selectedMethods)) {
            echo '<div class="alert alert-info">📁 [5/6] Checking Common Directories...</div>';
            $commonUrls = checkCommonDirectories($domain);
            $allUrls = array_merge($allUrls, $commonUrls);
            $methodResults['common'] = count($commonUrls);
            echo '<p style="color: #666; font-size: 13px; margin-bottom: 12px;">✓ Ditemukan <strong>' . count($commonUrls) . '</strong> direktori umum yang aktif</p>';
            flush();
        }
        
        // Method 6: Google Cache
        if (in_array('googlecache', $selectedMethods)) {
            echo '<div class="alert alert-info">💾 [6/6] Querying Google Cache...</div>';
            $cacheUrls = extractFromGoogleCache($domain);
            $allUrls = array_merge($allUrls, $cacheUrls);
            $methodResults['googlecache'] = count($cacheUrls);
            echo '<p style="color: #666; font-size: 13px; margin-bottom: 12px;">✓ Ditemukan <strong>' . count($cacheUrls) . '</strong> URLs dari Google Cache</p>';
            flush();
        }
        
        // Remove duplicates dan empty URLs
        $allUrls = array_unique($allUrls);
        $allUrls = array_filter($allUrls);
        sort($allUrls);
        
        echo '</div>';
        
        if (empty($allUrls)) {
            echo '<div class="alert alert-warning">⚠️ Tidak ada URL yang ditemukan. Mungkin domain tidak dapat diakses atau tidak memiliki direktori publik.</div>';
        } else {
            // Extract directories
            $result = extractDirectories($allUrls, $domain);
            $directories = $result['directories'];
            $stats = $result['stats'];
            
            // Tampilkan Summary Methods
            echo '<h3 style="margin-top: 25px; color: #333; font-size: 16px;">📈 Ringkasan Metode Ekstraksi:</h3>';
            echo '<div class="method-sources">';
            foreach ($methodResults as $method => $count) {
                $methodLabels = [
                    'sitemap' => '📂 Sitemap',
                    'robots' => '🤖 Robots.txt',
                    'pagelinks' => '🔗 Page Links',
                    'crawl' => '🕷️ Crawling',
                    'common' => '📁 Common Dirs',
                    'googlecache' => '💾 Google Cache'
                ];
                echo '<div class="source-item">';
                echo '<strong>' . ($methodLabels[$method] ?? $method) . '</strong>';
                echo '<span class="count">' . $count . ' URLs</span>';
                echo '</div>';
            }
            echo '</div>';
            
            // Tampilkan statistik
            echo '<h3 style="margin-top: 25px; color: #333; font-size: 16px;">📊 Statistik Global:</h3>';
            echo '<div class="stats-grid">';
            echo '<div class="stat-box"><strong>' . $stats['total_urls'] . '</strong><span>Total URLs Ditemukan</span></div>';
            echo '<div class="stat-box"><strong>' . $stats['unique_dirs'] . '</strong><span>Direktori Unik</span></div>';
            echo '<div class="stat-box"><strong>' . $stats['max_depth'] . '</strong><span>Kedalaman Maksimal</span></div>';
            echo '<div class="stat-box"><strong>' . count(array_unique(array_column($stats['categories'] ?? [], 'type'))) . '</strong><span>Kategori Ditemukan</span></div>';
            echo '</div>';
            
            echo '<div class="alert alert-success">✅ Sukses! Total <strong>' . count($allUrls) . '</strong> URLs ditemukan dari <strong>' . count(array_filter($methodResults)) . '</strong> metode</div>';
            
            // Direktori
            echo '<h3 style="margin-top: 25px; color: #333; font-size: 16px;">📋 Daftar Direktori Unik:</h3>';
            echo '<textarea readonly>';
            foreach ($directories as $dir) {
                echo $dir . "\n";
            }
            echo '</textarea>';
            
            // URLs Lengkap
            echo '<h3 style="margin-top: 25px; color: #333; font-size: 16px;">🔗 Daftar URL Lengkap:</h3>';
            echo '<textarea readonly>';
            foreach ($allUrls as $url) {
                echo $url . "\n";
            }
            echo '</textarea>';
            
            // Tombol Export
            echo '<div class="button-group" style="margin-top: 20px;">';
            echo '<form method="POST" style="flex: 1;">';
            echo '<input type="hidden" name="export_dirs" value="' . base64_encode(json_encode($directories)) . '">';
            echo '<button type="submit" style="margin: 0;">💾 Export Direktori</button>';
            echo '</form>';
            echo '<form method="POST" style="flex: 1;">';
            echo '<input type="hidden" name="export_urls" value="' . base64_encode(json_encode($allUrls)) . '">';
            echo '<button type="submit" style="margin: 0;">💾 Export URLs</button>';
            echo '</form>';
            echo '</div>';
        }
    }
    
    echo '</div>';
}

// ==========================================
// HANDLE EXPORT
// ==========================================
if (isset($_POST['export_dirs']) && !empty($_POST['export_dirs'])) {
    $directories = json_decode(base64_decode($_POST['export_dirs']), true);
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="google-indexed-directories-' . date('Y-m-d-His') . '.txt"');
    echo implode("\n", $directories);
    exit;
}

if (isset($_POST['export_urls']) && !empty($_POST['export_urls'])) {
    $urls = json_decode(base64_decode($_POST['export_urls']), true);
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="google-indexed-urls-' . date('Y-m-d-His') . '.txt"');
    echo implode("\n", $urls);
    exit;
}
?>
        
        <div class="result-section" style="margin-top: 30px; background: #f0f7ff; border-left-color: #2196F3;">
            <h3>ℹ️ Panduan Metode Ekstraksi</h3>
            <div class="info-box" style="background: white; margin: 0;">
                <strong style="color: #333; display: block; margin-bottom: 12px;">🎯 6 Metode Ekstraksi yang Digunakan:</strong>
                <ol style="margin-left: 20px; color: #555; line-height: 1.8;">
                    <li><strong>Sitemap.xml</strong> - Extract URL dari file sitemap (paling akurat, jika ada)</li>
                    <li><strong>Robots.txt</strong> - Parse robots.txt untuk Disallow/Allow paths dan sitemap references</li>
                    <li><strong>Page Links</strong> - Extract semua href links dari halaman utama domain</li>
                    <li><strong>Web Crawling</strong> - Crawl website untuk menemukan URLs dengan mengikuti links</li>
                    <li><strong>Common Directories</strong> - Check direktori umum (blog, shop, category, tag, dll)</li>
                    <li><strong>Google Cache</strong> - Query Google Cache untuk menemukan URLs yang tercache</li>
                </ol>
            </div>
        </div>
        
        <div style="margin-top: 30px; padding: 20px; background: #fff8e1; border-radius: 8px; border-left: 4px solid #ff9800;">
            <strong style="color: #ff6f00; display: block; margin-bottom: 10px;">⚠️ Catatan Penting:</strong>
            <ul style="list-style: none; color: #555; line-height: 1.8; font-size: 13px;">
                <li>✓ Script ini 100% beroperasi TANPA perlu Sitemap</li>
                <li>✓ Menggunakan multiple fallback methods untuk coverage maksimal</li>
                <li>✓ Web crawling mungkin memakan waktu lebih lama jika website besar</li>
                <li>✓ Hasil akurat tergantung accessibility domain dan struktur URL</li>
                <li>✓ Untuk verifikasi 100% terindex, gunakan Google Search Console API</li>
                <li>✓ Common directories scanning hanya untuk directories yang HTTP 200/301/302</li>
            </ul>
        </div>
    </div>
</div>

</body>
</html>
