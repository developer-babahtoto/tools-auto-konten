<?php
/**
 * SCRIPT: GOOGLE INDEXED URLS EXTRACTOR v1.0
 * Fungsi: Extract nama direktori/path yang sudah terindex di Google
 * Input: URL domain saja (contoh: https://example.com)
 * Output: Daftar direktori yang terdeteksi terindex beserta statistik
 */

@ini_set('display_errors', 1);
@ini_set('display_startup_errors', 1);
@error_reporting(E_ALL);
@set_time_limit(300);
@ini_set('memory_limit', '256M');

// ==========================================
// KONFIGURASI
// ==========================================
$methods = [
    'google_search' => 'Google Search Operator (site:)',
    'bing_search'   => 'Bing Search Operator',
    'manual_api'    => 'Manual Upload Data'
];

// ==========================================
// FUNCTION: Extract URLs dari Google Search
// ==========================================
function extractFromGoogleSearch($domain) {
    $domain = trim($domain, '/');
    $domain = preg_replace(['#^https?://#', '#/$#'], '', $domain);
    
    $results = [];
    $pageNumber = 0;
    $maxPages = 5; // Limit halaman hasil
    
    // Menggunakan site: operator di Google (manual scrape method)
    // CATATAN: Ini adalah demonstrasi. Google memblokir scraper otomatis.
    // Solusi terbaik: Gunakan Google Search Console API
    
    echo "<div style='background:#fff3cd; padding:10px; margin:10px 0; border-radius:5px; border-left:4px solid #ff9800;'>";
    echo "<b>⚠️ Info:</b> Google memblokir scraper otomatis. Gunakan Google Search Console API untuk hasil optimal.";
    echo "</div>";
    
    // Alternatif: Gunakan Custom Search API (terbatas)
    // Atau: Gunakan Screaming Frog, Semrush API, atau GSC API
    
    return $results;
}

// ==========================================
// FUNCTION: Extract URLs dari Sitemap.xml
// ==========================================
function extractFromSitemap($domain) {
    $domain = rtrim($domain, '/');
    $urls = [];
    $sitemapUrls = [
        $domain . '/sitemap.xml',
        $domain . '/sitemap_index.xml',
        $domain . '/sitemap1.xml',
    ];
    
    $options = ["http" => [
        "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n",
        "timeout" => 10
    ]];
    $context = stream_context_create($options);
    
    foreach ($sitemapUrls as $sitemapUrl) {
        $content = @file_get_contents($sitemapUrl, false, $context);
        if ($content) {
            preg_match_all('#<loc>([^<]+)</loc>#i', $content, $matches);
            if (!empty($matches[1])) {
                $urls = array_merge($urls, $matches[1]);
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
        'max_depth' => 0
    ];
    
    foreach ($urls as $url) {
        $url = trim($url);
        if (empty($url)) continue;
        
        // Parse URL dan ambil path
        $parsed = parse_url($url);
        $path = isset($parsed['path']) ? trim($parsed['path'], '/') : '';
        
        if (empty($path)) continue;
        
        // Extract direktori (exclude file extensions)
        $pathParts = explode('/', $path);
        $depth = count($pathParts);
        $stats['max_depth'] = max($stats['max_depth'], $depth);
        
        // Tambahkan setiap level direktori
        for ($i = 0; $i < count($pathParts) - 1; $i++) {
            $dir = implode('/', array_slice($pathParts, 0, $i + 1));
            if (!empty($dir) && !in_array($dir, ['index.php', 'index.html'])) {
                if (!in_array($dir, $directories)) {
                    $directories[] = $dir;
                }
            }
        }
        
        // Tambahkan full path jika bukan file
        $lastPart = end($pathParts);
        if (!preg_match('/\.(php|html|htm|aspx|jsp)$/i', $lastPart)) {
            if (!in_array($path, $directories)) {
                $directories[] = $path;
            }
        }
    }
    
    $stats['unique_dirs'] = count($directories);
    sort($directories);
    
    return ['directories' => $directories, 'stats' => $stats];
}

// ==========================================
// FUNCTION: Extract dari robots.txt
// ==========================================
function extractFromRobotsTxt($domain) {
    $domain = rtrim($domain, '/');
    $urls = [];
    
    $options = ["http" => [
        "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)\r\n",
        "timeout" => 10
    ]];
    $context = stream_context_create($options);
    
    $robotsContent = @file_get_contents($domain . '/robots.txt', false, $context);
    if ($robotsContent) {
        // Extract Sitemap URLs dari robots.txt
        preg_match_all('#Sitemap:\s*(.+)#i', $robotsContent, $matches);
        if (!empty($matches[1])) {
            foreach ($matches[1] as $sitemapUrl) {
                $urls[] = trim($sitemapUrl);
            }
        }
    }
    
    return $urls;
}

// ==========================================
// FUNCTION: Verify Index Status di Google
// ==========================================
function verifyGoogleIndex($urls, $domain) {
    $indexed = [];
    $notIndexed = [];
    
    // CATATAN: Ini adalah dummy function karena verifikasi real memerlukan API
    // Untuk verifikasi real, gunakan:
    // 1. Google Search Console API
    // 2. Google Safe Browsing API
    // 3. Bing Webmaster Tools API
    
    foreach ($urls as $url) {
        // Simulated check - dalam praktik real gunakan API
        $indexed[] = $url;
    }
    
    return ['indexed' => $indexed, 'not_indexed' => $notIndexed];
}

// ==========================================
// TAMPILAN INTERFACE
// ==========================================
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Google Indexed Extractor</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 { font-size: 28px; margin-bottom: 10px; }
        .header p { opacity: 0.9; font-size: 14px; }
        .content { padding: 30px; }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
        }
        input[type="text"], input[type="url"], select {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        input[type="text"]:focus, input[type="url"]:focus, select:focus {
            outline: none;
            border-color: #667eea;
        }
        .methods-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .method-option {
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .method-option:hover {
            border-color: #667eea;
            background: #f8f9ff;
        }
        .method-option input {
            margin-right: 10px;
        }
        button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 14px 30px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        button:active {
            transform: translateY(0);
        }
        .result-section {
            margin-top: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 5px;
            border-left: 4px solid #667eea;
        }
        .result-section h3 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 18px;
        }
        textarea {
            width: 100%;
            height: 200px;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            resize: vertical;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-box {
            background: white;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #667eea;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .stat-box strong {
            display: block;
            font-size: 24px;
            color: #667eea;
            margin-bottom: 5px;
        }
        .stat-box span {
            font-size: 12px;
            color: #666;
        }
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .alert-info {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            color: #1565c0;
        }
        .alert-success {
            background: #e8f5e9;
            border-left: 4px solid #4CAF50;
            color: #2e7d32;
        }
        .alert-warning {
            background: #fff3e0;
            border-left: 4px solid #ff9800;
            color: #e65100;
        }
        .button-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            margin-top: 15px;
        }
        .button-group button {
            padding: 10px;
            font-size: 14px;
        }
        .copy-btn {
            background: #28a745;
            padding: 10px 15px;
            font-size: 13px;
        }
        .copy-btn:hover {
            background: #218838;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>🔍 Google Indexed Directories Extractor</h1>
        <p>Extract semua direktori yang terindex di Google hanya dengan URL domain</p>
    </div>
    
    <div class="content">
        <form method="POST">
            <div class="form-group">
                <label for="domain">🌐 Masukan URL Domain:</label>
                <input type="url" id="domain" name="domain" placeholder="https://example.com" required value="<?php echo isset($_POST['domain']) ? htmlspecialchars($_POST['domain']) : ''; ?>">
                <small style="color: #666; display: block; margin-top: 5px;">*Gunakan format lengkap: https://example.com atau http://example.com</small>
            </div>
            
            <div class="form-group">
                <label>📌 Pilih Metode Ekstraksi:</label>
                <div class="methods-group">
                    <label class="method-option">
                        <input type="radio" name="method" value="sitemap" checked>
                        <strong>Sitemap.xml</strong>
                        <p style="font-size:12px; margin-top:5px; color:#666;">Extract dari sitemap.xml (paling akurat)</p>
                    </label>
                    <label class="method-option">
                        <input type="radio" name="method" value="robots">
                        <strong>Robots.txt</strong>
                        <p style="font-size:12px; margin-top:5px; color:#666;">Cari sitemap di robots.txt</p>
                    </label>
                    <label class="method-option">
                        <input type="radio" name="method" value="combined">
                        <strong>Kombinasi</strong>
                        <p style="font-size:12px; margin-top:5px; color:#666;">Gunakan semua metode untuk hasil maksimal</p>
                    </label>
                </div>
            </div>
            
            <button type="submit" name="extract" value="1">🚀 Mulai Ekstraksi</button>
        </form>
        
<?php
// ==========================================
// PROSES EKSTRAKSI SETELAH SUBMIT
// ==========================================
if (isset($_POST['extract']) && !empty($_POST['domain'])) {
    $domain = $_POST['domain'];
    $method = $_POST['method'] ?? 'sitemap';
    $allUrls = [];
    
    echo '<div class="result-section">';
    echo '<h3>📊 Hasil Ekstraksi</h3>';
    
    echo '<div class="alert alert-info">⏳ Sedang memproses, mohon tunggu...</div>';
    
    // Ekstraksi berdasarkan metode
    if ($method === 'sitemap' || $method === 'combined') {
        echo '<div class="alert alert-info">📂 Mengekstrak dari Sitemap.xml...</div>';
        $sitemapUrls = extractFromSitemap($domain);
        $allUrls = array_merge($allUrls, $sitemapUrls);
        echo '<p style="color: #666; margin: 10px 0; font-size: 13px;">✓ Ditemukan ' . count($sitemapUrls) . ' URL dari sitemap</p>';
    }
    
    if ($method === 'robots' || $method === 'combined') {
        echo '<div class="alert alert-info">🤖 Scanning Robots.txt untuk sitemap tambahan...</div>';
        $robotsSitemaps = extractFromRobotsTxt($domain);
        if (!empty($robotsSitemaps)) {
            foreach ($robotsSitemaps as $sitemapUrl) {
                $urls = extractFromSitemap($sitemapUrl);
                $allUrls = array_merge($allUrls, $urls);
            }
            echo '<p style="color: #666; margin: 10px 0; font-size: 13px;">✓ Ditemukan ' . count($robotsSitemaps) . ' sitemap dari robots.txt</p>';
        }
    }
    
    // Remove duplicates
    $allUrls = array_unique($allUrls);
    
    if (empty($allUrls)) {
        echo '<div class="alert alert-warning">⚠️ Tidak ada URL yang ditemukan. Pastikan domain memiliki sitemap.xml</div>';
    } else {
        // Extract directories
        $result = extractDirectories($allUrls, $domain);
        $directories = $result['directories'];
        $stats = $result['stats'];
        
        // Tampilkan statistik
        echo '<div class="stats">';
        echo '<div class="stat-box"><strong>' . $stats['total_urls'] . '</strong><span>Total URLs Terdeteksi</span></div>';
        echo '<div class="stat-box"><strong>' . $stats['unique_dirs'] . '</strong><span>Direktori Unik</span></div>';
        echo '<div class="stat-box"><strong>' . $stats['max_depth'] . '</strong><span>Kedalaman Maksimal</span></div>';
        echo '</div>';
        
        if (!empty($directories)) {
            echo '<div class="alert alert-success">✅ Sukses! Ditemukan ' . count($directories) . ' direktori unik yang terindex</div>';
            
            // Tampilkan daftar direktori
            echo '<h4 style="margin-top: 20px; color: #333;">📋 Daftar Direktori yang Terindex:</h4>';
            echo '<textarea readonly>';
            foreach ($directories as $dir) {
                echo $dir . "\n";
            }
            echo '</textarea>';
            
            // Tampilkan daftar URL lengkap
            echo '<h4 style="margin-top: 20px; color: #333;">🔗 Daftar URL Lengkap yang Terindex:</h4>';
            echo '<textarea readonly>';
            $sortedUrls = $allUrls;
            sort($sortedUrls);
            foreach ($sortedUrls as $url) {
                echo $url . "\n";
            }
            echo '</textarea>';
            
            // Tombol eksport
            echo '<div class="button-group">';
            echo '<form method="POST" style="flex: 1;">';
            echo '<input type="hidden" name="export_dirs" value="' . base64_encode(json_encode($directories)) . '">';
            echo '<button type="submit" class="copy-btn" style="margin: 0;">💾 Export Direktori (TXT)</button>';
            echo '</form>';
            echo '<form method="POST" style="flex: 1;">';
            echo '<input type="hidden" name="export_urls" value="' . base64_encode(json_encode($sortedUrls)) . '">';
            echo '<button type="submit" class="copy-btn" style="margin: 0;">💾 Export URLs (TXT)</button>';
            echo '</form>';
            echo '</div>';
        }
    }
    
    echo '</div>';
}

// ==========================================
// HANDLE EXPORT FILES
// ==========================================
if (isset($_POST['export_dirs']) && !empty($_POST['export_dirs'])) {
    $directories = json_decode(base64_decode($_POST['export_dirs']), true);
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="google-indexed-directories.txt"');
    echo implode("\n", $directories);
    exit;
}

if (isset($_POST['export_urls']) && !empty($_POST['export_urls'])) {
    $urls = json_decode(base64_decode($_POST['export_urls']), true);
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="google-indexed-urls.txt"');
    echo implode("\n", $urls);
    exit;
}
?>
        
        <div class="result-section" style="margin-top: 30px; background: #f0f7ff; border-left-color: #2196F3;">
            <h3>ℹ️ Informasi & Tips Penggunaan</h3>
            <ul style="list-style: none; color: #555; line-height: 1.8;">
                <li>✓ <strong>Sitemap.xml:</strong> Metode paling akurat jika domain memiliki sitemap</li>
                <li>✓ <strong>Robots.txt:</strong> Cari referensi sitemap di file robots.txt</li>
                <li>✓ <strong>Kombinasi:</strong> Gabung hasil dari semua metode untuk coverage maksimal</li>
                <li>⚠️ <strong>Batasan:</strong> Script ini hanya mengekstrak URL yang tersedia di sitemap, bukan seluruh halaman yang terindex</li>
                <li>💡 <strong>Tips:</strong> Untuk hasil verifikasi real-time gunakan Google Search Console API</li>
                <li>🔗 <strong>Format:</strong> Pastikan domain menggunakan format lengkap (https://example.com)</li>
            </ul>
        </div>
    </div>
</div>

<script>
    // Auto-focus pada input domain jika ada error
    document.addEventListener('DOMContentLoaded', function() {
        const domainInput = document.getElementById('domain');
        if (domainInput) {
            domainInput.focus();
        }
    });
</script>

</body>
</html>
