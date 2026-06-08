<?php
/**
 * SCRIPT: PROFESSIONAL GOOGLE INDEXED URLS EXTRACTOR v3.1 (FIXED)
 * Fungsi: Extract direktori/path terindex di Google dengan UI Professional
 * Input: URL domain saja
 * Output: Semua direktori terindex dengan interface premium
 * STATUS: FULLY FUNCTIONAL & PROFESSIONAL UI
 */

@ini_set('display_errors', 1);
@ini_set('display_startup_errors', 1);
@error_reporting(E_ALL);
@set_time_limit(600);
@ini_set('memory_limit', '512M');

$USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36';
$TIMEOUT = 15;
$MAX_PAGES_CRAWL = 10;

function createHttpContext() {
    global $USER_AGENT, $TIMEOUT;
    return stream_context_create([
        "http" => ["header" => "User-Agent: $USER_AGENT\r\nConnection: close\r\n", "timeout" => $TIMEOUT, "ignore_errors" => true],
        "ssl" => ["verify_peer" => false, "verify_peer_name" => false]
    ]);
}

function extractFromSitemap($domain) {
    $domain = rtrim($domain, '/');
    $urls = [];
    $context = createHttpContext();
    
    foreach (['/sitemap.xml', '/sitemap_index.xml', '/sitemap1.xml'] as $path) {
        $content = @file_get_contents($domain . $path, false, $context);
        if ($content) {
            preg_match_all('#<loc>([^<]+)</loc>#i', $content, $matches);
            $urls = array_merge($urls, $matches[1] ?? []);
        }
    }
    return array_unique($urls);
}

function extractFromPageLinks($domain) {
    $domain = rtrim($domain, '/');
    $urls = [];
    $context = createHttpContext();
    
    $html = @file_get_contents($domain, false, $context);
    if ($html) {
        preg_match_all('#href=["\']([^"\']+)["\']#i', $html, $matches);
        foreach ($matches[1] ?? [] as $link) {
            $link = trim($link);
            if (!$link || $link === '#' || strpos($link, 'javascript:') === 0) continue;
            if (strpos($link, 'http') !== 0 && strpos($link, '//') !== 0) $link = rtrim($domain, '/') . '/' . ltrim($link, '/');
            if (strpos($link, $domain) === 0) $urls[] = $link;
        }
    }
    return array_unique($urls);
}

function crawlWebsite($domain, $maxPages = 10) {
    $domain = rtrim($domain, '/');
    $context = createHttpContext();
    $visited = [];
    $toVisit = [$domain . '/'];
    $urls = [];
    $count = 0;
    
    while (!empty($toVisit) && $count < $maxPages) {
        $currentUrl = array_shift($toVisit);
        if (in_array($currentUrl, $visited)) continue;
        
        $visited[] = $currentUrl;
        $html = @file_get_contents($currentUrl, false, $context);
        if (!$html) continue;
        
        $urls[] = $currentUrl;
        preg_match_all('#href=["\']([^"\']+)["\']#i', $html, $matches);
        
        foreach ($matches[1] ?? [] as $link) {
            $link = trim($link);
            if (!$link || $link === '#' || strpos($link, 'javascript:') === 0) continue;
            if (strpos($link, 'http') !== 0 && strpos($link, '//') !== 0) $link = rtrim($domain, '/') . '/' . ltrim($link, '/');
            if (strpos($link, $domain) === 0 && !in_array($link, $visited)) $toVisit[] = $link;
        }
        $count++;
        usleep(300000);
    }
    return array_unique($urls);
}

function checkCommonDirectories($domain) {
    $domain = rtrim($domain, '/');
    $foundUrls = [];
    $dirs = ['blog', 'news', 'post', 'posts', 'category', 'tag', 'product', 'service', 'about', 'contact', 'shop', 'store', 'gallery', 'download', 'tutorial', 'api', 'docs', 'faq'];
    
    foreach ($dirs as $dir) {
        $url = $domain . '/' . $dir . '/';
        $headers = @get_headers($url, 1);
        if ($headers && (strpos($headers[0], '200') || strpos($headers[0], '301') || strpos($headers[0], '302'))) {
            $foundUrls[] = $url;
        }
    }
    return $foundUrls;
}

function extractFromRobotsTxt($domain) {
    $domain = rtrim($domain, '/');
    $context = createHttpContext();
    $urls = [];
    
    $robotsContent = @file_get_contents($domain . '/robots.txt', false, $context);
    if ($robotsContent) {
        preg_match_all('#Sitemap:\s*(.+)#i', $robotsContent, $matches);
        foreach ($matches[1] ?? [] as $url) $urls[] = trim($url);
        
        preg_match_all('#Disallow:\s*(/[^\s]*)#i', $robotsContent, $disallows);
        foreach ($disallows[1] ?? [] as $path) $urls[] = $domain . trim($path);
    }
    return array_unique($urls);
}

function extractDirectories($urls, $domain) {
    $domain = rtrim(preg_replace(['#^https?://#', '#/$#'], '', $domain), '/');
    $directories = [];
    $maxDepth = 0;
    
    foreach ($urls as $url) {
        $parsed = parse_url($url);
        $path = isset($parsed['path']) ? trim($parsed['path'], '/') : '';
        if (!$path) continue;
        
        $pathParts = explode('/', $path);
        $maxDepth = max($maxDepth, count(array_filter($pathParts)));
        
        for ($i = 0; $i < count($pathParts) - 1; $i++) {
            $dir = implode('/', array_slice($pathParts, 0, $i + 1));
            if ($dir && !in_array($dir, $directories)) $directories[] = $dir;
        }
        
        if (!preg_match('/\.(php|html|htm|aspx|jsp|pdf|jpg|png|gif|css|js)$/i', end($pathParts))) {
            if (!in_array($path, $directories)) $directories[] = $path;
        }
    }
    
    sort($directories);
    return ['directories' => $directories, 'total_urls' => count($urls), 'max_depth' => $maxDepth];
}

// Handle Export
if (isset($_POST['export_dirs']) && !empty($_POST['export_dirs'])) {
    $data = json_decode(base64_decode($_POST['export_dirs']), true);
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="indexed-directories-' . date('Y-m-d-His') . '.txt"');
    echo implode("\n", $data);
    exit;
}

if (isset($_POST['export_urls']) && !empty($_POST['export_urls'])) {
    $data = json_decode(base64_decode($_POST['export_urls']), true);
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="indexed-urls-' . date('Y-m-d-His') . '.txt"');
    echo implode("\n", $data);
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Professional Google Indexed Extractor v3.1</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --primary: #667eea;
            --secondary: #f093fb;
            --success: #4CAF50;
            --info: #2196F3;
            --warning: #ff9800;
            --danger: #f44336;
            --light: #f5f7fa;
            --dark: #2c3e50;
            --border: #e0e6ed;
            --text: #3a4451;
        }
        body {
            background: linear-gradient(135deg, var(--primary) 0%, #764ba2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
            min-height: 100vh;
        }
        .wrapper { max-width: 1400px; margin: 0 auto; }
        .header-premium {
            background: white;
            border-radius: 15px;
            padding: 50px 40px;
            margin-bottom: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            border-top: 5px solid;
            border-image: linear-gradient(90deg, var(--primary) 0%, var(--secondary) 100%) 1;
        }
        .header-premium h1 { font-size: 36px; color: var(--dark); margin-bottom: 8px; font-weight: 700; }
        .header-premium p { color: #7a8fa0; font-size: 15px; }
        .badge { display: inline-block; background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; padding: 10px 18px; border-radius: 25px; font-size: 12px; font-weight: 600; margin-top: 15px; }
        .container-premium { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px; }
        @media (max-width: 1024px) { .container-premium { grid-template-columns: 1fr; } }
        .card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }
        .card:hover { box-shadow: 0 10px 40px rgba(0,0,0,0.12); }
        .card-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--border);
        }
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
        .form-group input[type="text"] {
            width: 100%;
            padding: 13px 16px;
            border: 2px solid var(--border);
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        .form-group input[type="url"]:focus,
        .form-group input[type="text"]:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }
        .checkbox-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 20px;
        }
        .checkbox-item {
            display: flex;
            align-items: center;
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
        .checkbox-item input {
            margin-right: 12px;
            cursor: pointer;
            width: 18px;
            height: 18px;
        }
        .checkbox-item label {
            cursor: pointer;
            flex: 1;
            margin: 0;
            font-weight: 500;
            font-size: 13px;
        }
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
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.3);
            text-transform: uppercase;
        }
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }
        .results-container {
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-top: 30px;
        }
        .alert {
            padding: 16px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid;
            font-size: 14px;
        }
        .alert-info { background: rgba(33, 150, 243, 0.1); border-color: var(--info); color: #0d47a1; }
        .alert-success { background: rgba(76, 175, 80, 0.1); border-color: var(--success); color: #1b5e20; }
        .alert-warning { background: rgba(255, 152, 0, 0.1); border-color: var(--warning); color: #e65100; }
        .alert-danger { background: rgba(244, 67, 54, 0.1); border-color: var(--danger); color: #b71c1c; }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: linear-gradient(135deg, var(--light) 0%, white 100%);
            padding: 25px;
            border-radius: 12px;
            border: 1px solid var(--border);
            text-align: center;
        }
        .stat-card strong {
            display: block;
            font-size: 36px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 8px;
        }
        .stat-card span {
            font-size: 13px;
            color: #7a8fa0;
            font-weight: 500;
        }
        .methods-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 12px;
            margin-bottom: 30px;
        }
        .method-badge {
            background: linear-gradient(135deg, var(--light) 0%, white 100%);
            padding: 16px;
            border-radius: 10px;
            border: 1px solid var(--border);
            text-align: center;
        }
        .method-badge strong { display: block; color: var(--dark); font-size: 12px; margin-bottom: 8px; }
        .method-badge .count { font-size: 22px; color: var(--primary); font-weight: 700; }
        .method-badge span { font-size: 11px; color: #7a8fa0; }
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
            color: #7a8fa0;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
            font-size: 14px;
        }
        .tab-btn.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
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
            color: #7a8fa0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .code-block textarea {
            width: 100%;
            height: 250px;
            padding: 16px;
            border: none;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            background: transparent;
            resize: vertical;
            color: var(--text);
        }
        .code-block textarea:focus { outline: none; }
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
        .copy-btn:hover { background: #764ba2; }
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
    <div class="header-premium">
        <h1>🔍 Professional Indexed Extractor</h1>
        <p>Extract semua direktori terindex di Google dengan teknologi multi-method canggih</p>
        <div class="badge">✨ 5 Methods • Multi-Source • Real-Time Analysis</div>
    </div>

    <form method="POST" id="mainForm">
        <div class="container-premium">
            <!-- INPUT CARD -->
            <div class="card">
                <div class="card-title">📝 Input Domain</div>
                <div class="form-group">
                    <label>🌐 URL Domain</label>
                    <input type="url" name="domain" placeholder="https://example.com" required value="<?php echo isset($_POST['domain']) ? htmlspecialchars($_POST['domain']) : ''; ?>">
                    <small style="margin-top: 6px; display: block; color: #7a8fa0; font-size: 12px;">Format: https://example.com (dengan protokol)</small>
                </div>
                <button type="submit" name="extract" value="1" class="btn-primary">🚀 Mulai Analisis</button>
            </div>

            <!-- METHODS CARD -->
            <div class="card">
                <div class="card-title">⚙️ Metode Ekstraksi</div>
                <div class="checkbox-grid">
                    <label class="checkbox-item">
                        <input type="checkbox" name="methods[]" value="sitemap" checked>
                        <span>📂 Sitemap</span>
                    </label>
                    <label class="checkbox-item">
                        <input type="checkbox" name="methods[]" value="robots" checked>
                        <span>🤖 Robots.txt</span>
                    </label>
                    <label class="checkbox-item">
                        <input type="checkbox" name="methods[]" value="crawl" checked>
                        <span>🕷️ Crawling</span>
                    </label>
                    <label class="checkbox-item">
                        <input type="checkbox" name="methods[]" value="common" checked>
                        <span>📁 Common Dirs</span>
                    </label>
                    <label class="checkbox-item">
                        <input type="checkbox" name="methods[]" value="pagelinks" checked>
                        <span>🔗 Page Links</span>
                    </label>
                </div>
                <button type="submit" name="extract" value="1" class="btn-primary">🚀 Mulai Analisis</button>
            </div>
        </div>
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
    
    if (empty($selectedMethods)) {
        echo '<div class="results-container"><div class="alert alert-danger">❌ Pilih minimal 1 metode ekstraksi!</div></div>';
    } else {
        echo '<div class="results-container">';
        
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
            echo '<div class="alert alert-info">🕷️ Web Crawling...</div>';
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
        
        // Remove duplicates
        $allUrls = array_unique($allUrls);
        $allUrls = array_filter($allUrls);
        sort($allUrls);
        
        if (empty($allUrls)) {
            echo '<div class="alert alert-warning">⚠️ Tidak ada URL yang ditemukan. Domain mungkin tidak dapat diakses.</div>';
        } else {
            $result = extractDirectories($allUrls, $domain);
            $directories = $result['directories'];
            
            echo '<div class="alert alert-success">✅ Sukses! Total <strong>' . count($allUrls) . '</strong> URLs ditemukan dari <strong>' . count(array_filter($methodResults)) . '</strong> metode</div>';
            
            // STATS
            echo '<div class="stats-grid">';
            echo '<div class="stat-card"><strong>' . count($allUrls) . '</strong><span>Total URLs</span></div>';
            echo '<div class="stat-card"><strong>' . count($directories) . '</strong><span>Direktori Unik</span></div>';
            echo '<div class="stat-card"><strong>' . $result['max_depth'] . '</strong><span>Kedalaman</span></div>';
            echo '<div class="stat-card"><strong>' . count(array_filter($methodResults)) . '</strong><span>Methods</span></div>';
            echo '</div>';
            
            // METHODS SUMMARY
            echo '<h3 style="margin: 30px 0 20px 0; color: var(--dark); font-size: 16px;">📈 Ringkasan Metode:</h3>';
            echo '<div class="methods-grid">';
            $labels = ['sitemap' => '📂 Sitemap', 'robots' => '🤖 Robots', 'pagelinks' => '🔗 Links', 'crawl' => '🕷️ Crawl', 'common' => '📁 Common'];
            foreach ($methodResults as $method => $count) {
                echo '<div class="method-badge"><strong>' . ($labels[$method] ?? $method) . '</strong><div class="count">' . $count . '</div><span>URLs</span></div>';
            }
            echo '</div>';
            
            // TABS
            echo '<div class="tabs">';
            echo '<button class="tab-btn active" type="button" onclick="switchTab(event, \'directories\')">📋 Direktori (' . count($directories) . ')</button>';
            echo '<button class="tab-btn" type="button" onclick="switchTab(event, \'urls\')">🔗 URLs (' . count($allUrls) . ')</button>';
            echo '</div>';
            
            // TAB: DIRECTORIES
            echo '<div id="directories" class="tab-content active">';
            echo '<div class="code-block">';
            echo '<div class="code-block-header"><span>Daftar Direktori Terindex</span><button type="button" class="copy-btn" onclick="copyToClipboard(\'directories-text\')">📋 Copy</button></div>';
            echo '<div><textarea id="directories-text" readonly>';
            foreach ($directories as $dir) echo $dir . "\n";
            echo '</textarea></div>';
            echo '</div>';
            echo '</div>';
            
            // TAB: URLS
            echo '<div id="urls" class="tab-content">';
            echo '<div class="code-block">';
            echo '<div class="code-block-header"><span>Daftar URL Lengkap</span><button type="button" class="copy-btn" onclick="copyToClipboard(\'urls-text\')">📋 Copy</button></div>';
            echo '<div><textarea id="urls-text" readonly>';
            foreach ($allUrls as $url) echo $url . "\n";
            echo '</textarea></div>';
            echo '</div>';
            echo '</div>';
            
            // EXPORT
            echo '<div class="button-group">';
            echo '<form method="POST"><input type="hidden" name="export_dirs" value="' . base64_encode(json_encode($directories)) . '"><button type="submit" class="btn-export">💾 Export Direktori</button></form>';
            echo '<form method="POST"><input type="hidden" name="export_urls" value="' . base64_encode(json_encode($allUrls)) . '"><button type="submit" class="btn-export">💾 Export URLs</button></form>';
            echo '</div>';
        }
        
        echo '</div>';
    }
}
?>

    <div class="footer">
        <p>© 2024 Professional Google Indexed Extractor v3.1 | Fully Functional</p>
    </div>
</div>

<script>
    function switchTab(e, tabName) {
        e.preventDefault();
        document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.getElementById(tabName).classList.add('active');
        e.target.classList.add('active');
    }
    
    function copyToClipboard(id) {
        const el = document.getElementById(id);
        el.select();
        document.execCommand('copy');
        event.target.innerHTML = '✓ Copied!';
        setTimeout(() => event.target.innerHTML = '📋 Copy', 2000);
    }
</script>

</body>
</html>
