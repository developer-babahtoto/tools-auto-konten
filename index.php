<?php
@ini_set('display_errors', 1);
@ini_set('display_startup_errors', 1);
@error_reporting(E_ALL);

@set_time_limit(600);
@ini_set('memory_limit', '512M');

$PASSWORD = "admin123";
$SESSION_TIMEOUT = 3600;

session_start();

function isAuthenticated() {
    global $SESSION_TIMEOUT;
    if (isset($_SESSION['auth_time']) && (time() - $_SESSION['auth_time']) > $SESSION_TIMEOUT) {
        unset($_SESSION['authenticated']);
        unset($_SESSION['auth_time']);
    }
    return isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_password'])) {
    $inputPassword = $_POST['login_password'];
    
    if ($inputPassword === $PASSWORD) {
        $_SESSION['authenticated'] = true;
        $_SESSION['auth_time'] = time();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $loginError = 'Password yang Anda masukkan salah!';
    }
}

if (!isAuthenticated()) {
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>🔐 Keamanan Akses - TAR Generator</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                padding: 20px;
            }
            .login-container {
                background: white;
                border-radius: 10px;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                max-width: 400px;
                width: 100%;
                padding: 40px;
            }
            .login-header {
                text-align: center;
                margin-bottom: 30px;
            }
            .login-header h1 {
                font-size: 28px;
                color: #333;
                margin-bottom: 10px;
            }
            .login-header p {
                color: #666;
                font-size: 14px;
            }
            .lock-icon {
                font-size: 48px;
                margin-bottom: 15px;
            }
            .form-group {
                margin-bottom: 20px;
            }
            .form-group label {
                display: block;
                margin-bottom: 8px;
                color: #333;
                font-weight: 600;
            }
            .form-group input {
                width: 100%;
                padding: 12px 15px;
                border: 2px solid #ddd;
                border-radius: 5px;
                font-size: 16px;
                transition: border-color 0.3s;
            }
            .form-group input:focus {
                outline: none;
                border-color: #667eea;
                background-color: #f8f9ff;
            }
            .btn-login {
                width: 100%;
                padding: 14px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border: none;
                border-radius: 5px;
                font-size: 16px;
                font-weight: bold;
                cursor: pointer;
                transition: transform 0.2s, box-shadow 0.2s;
            }
            .btn-login:hover {
                transform: translateY(-2px);
                box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
            }
            .btn-login:active {
                transform: translateY(0);
            }
            .alert-error {
                background-color: #fee;
                border: 1px solid #fcc;
                color: #c00;
                padding: 12px 15px;
                border-radius: 5px;
                margin-bottom: 20px;
                font-size: 14px;
            }
            .info-box {
                background-color: #f0f4ff;
                border-left: 4px solid #667eea;
                padding: 12px 15px;
                border-radius: 5px;
                margin-bottom: 20px;
                font-size: 13px;
                color: #555;
            }
            .footer-text {
                text-align: center;
                color: #999;
                font-size: 12px;
                margin-top: 20px;
            }
        </style>
    </head>
    <body>
        <div class="login-container">
            <div class="login-header">
                <div class="lock-icon">🔐</div>
                <h1>Akses Terbatas</h1>
                <p>Silakan masukkan password untuk melanjutkan</p>
            </div>

            <?php if (isset($loginError)): ?>
                <div class="alert-error">
                    ⚠️ <?php echo htmlspecialchars($loginError); ?>
                </div>
            <?php endif; ?>

            <div class="info-box">
                <strong>💡 Info:</strong> Masukkan password yang benar untuk mengakses panel kendali TAR Generator.
            </div>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="password">🔑 Password:</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="login_password" 
                        placeholder="Masukkan password..." 
                        required 
                        autofocus
                    >
                </div>
                <button type="submit" class="btn-login">🔓 Masuk</button>
            </form>

            <div class="footer-text">
                <p>Akses Panel Kontrol TAR Generator v18</p>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$dirFile         = 'dir.txt';
$listFile        = 'list.txt';
$templateFile    = 'template.php';
$templateAmpFile = 'template-amp.php';

$googleFileName = 'google2b748e987f49b9ff.html';
$googleContent  = 'google-site-verification: google2b748e987f49b9ff.html';

$host = $_SERVER['HTTP_HOST'];
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$baseUrl = $protocol . $host;

$extractFisik = (isset($_POST['extract_physical']) && $_POST['extract_physical'] == '1');
$isProcessed  = (isset($_POST['submit_proses']));

function dapatkanDaftarDirektori($url, $dirFile) {
    $cleanDirs = [];
    $blacklist = ['wp-admin', 'wp-content', 'wp-includes', 'category', 'tag', 'author', 'search', 'home', 'login', 'register'];

    if (file_exists($dirFile)) {
        $rawLines = file($dirFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($rawLines as $line) {
            $href = trim(preg_replace('/[\x00-\x1F\x7F-\x9F\xA0]/u', '', $line));
            if (empty($href)) continue;

            $parsedUrl = parse_url($href);
            $path = isset($parsedUrl['path']) ? trim($parsedUrl['path'], '/') : $href;
            $query = isset($parsedUrl['query']) ? $parsedUrl['query'] : '';

            $slug = '';
            if (!empty($path)) {
                $fileInfo = pathinfo($path);
                if (isset($fileInfo['extension']) && in_array(strtolower($fileInfo['extension']), ['php', 'html', 'htm'])) {
                    $dirPrefix = ($fileInfo['dirname'] !== '.' && !empty($fileInfo['dirname'])) ? $fileInfo['dirname'] . '/' : '';
                    $slug = $dirPrefix . $fileInfo['filename'];
                } else {
                    $slug = $path;
                }
            }

            if (empty($slug) && !empty($query)) {
                parse_str($query, $queryParams);
                if (isset($queryParams['']) && !empty($queryParams[''])) {
                    $slug = $queryParams[''];
                } elseif (!empty($queryParams)) {
                    $firstValue = reset($queryParams);
                    if (!empty($firstValue) && is_string($firstValue)) {
                        $slug = $firstValue;
                    }
                }
            }

            $slug = trim($slug, '/');
            if (empty($slug)) continue;

            $segments = explode('/', $slug);
            $isBlacklisted = false;
            foreach ($segments as $segment) {
                if (in_array(strtolower($segment), $blacklist) || strpos($segment, '.') !== false) {
                    $isBlacklisted = true;
                    break;
                }
            }
            if ($isBlacklisted) continue;

            if (preg_match('/^[a-zA-Z0-9_\-\/]+$/', $slug)) {
                $cleanDirs[] = strtolower($slug);
            }
        }
        return array_unique($cleanDirs);
    }

    $options = ["http" => ["header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)\r\n"]];
    $context = stream_context_create($options);
    $html = @file_get_contents($url, false, $context);
    if ($html) {
        preg_match_all('/href=["\']([^"\']+)["\']/i', $html, $matches);
        if (!empty($matches[1])) {
            foreach ($matches[1] as $href) {
                $href = trim($href);
                if (empty($href) || $href === '#' || strpos($href, 'javascript:') === 0) continue;
                $parsedUrl = parse_url($href);
                $path = isset($parsedUrl['path']) ? trim($parsedUrl['path'], '/') : '';
                if (empty($path)) continue;
                $fileInfo = pathinfo($path);
                $slug = (isset($fileInfo['extension']) && in_array(strtolower($fileInfo['extension']), ['php', 'html', 'htm'])) ? (($fileInfo['dirname'] !== '.' && !empty($fileInfo['dirname'])) ? $fileInfo['dirname'] . '/' : '') . $fileInfo['filename'] : $path;
                $slug = trim($slug, '/');
                if (preg_match('/^[a-zA-Z0-9_\-\/]+$/', $slug)) { $cleanDirs[] = strtolower($slug); }
            }
        }
    }
    return (!empty($cleanDirs)) ? array_unique($cleanDirs) : ['main'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TAR Generator v18 - Panel Kontrol</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f4f6f9;
            padding: 20px;
            margin: 0;
        }
        .header-bar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 8px 8px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0;
        }
        .header-bar h2 {
            margin: 0;
            font-size: 22px;
        }
        .logout-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 8px 12px;
            border: 1px solid white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
            transition: 0.3s;
        }
        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        .container {
            max-width: 900px;
            margin: auto;
        }
        .panel {
            background: white;
            border-radius: 8px;
            border: 1px solid #ddd;
            margin-bottom: 20px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .panel h2 {
            margin-top: 0;
            color: #333;
        }
        .form-group {
            margin-bottom: 15px;
            padding: 10px;
            background: #f8f9fa;
            border-left: 4px solid #28a745;
            border-radius: 4px;
        }
        .form-group label {
            font-weight: bold;
            cursor: pointer;
            display: flex;
            align-items: center;
        }
        .form-group input[type="checkbox"] {
            margin-right: 10px;
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        .btn-primary {
            background: #28a745;
            color: white;
            padding: 14px 20px;
            border: none;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
            transition: 0.3s;
        }
        .btn-primary:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }
        .info-box {
            padding: 10px 15px;
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        .success-box {
            background: white;
            border: 2px solid #28a745;
            border-radius: 5px;
            padding: 20px;
            margin-top: 20px;
        }
        .success-box h2 {
            color: #28a745;
            margin-top: 0;
        }
        textarea {
            width: 100%;
            padding: 10px;
            font-family: monospace;
            border: 1px solid #ddd;
            border-radius: 4px;
            resize: vertical;
        }
        .download-link {
            display: inline-block;
            background: #007bff;
            color: white;
            padding: 12px 20px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            margin-bottom: 15px;
            transition: 0.3s;
        }
        .download-link:hover {
            background: #0056b3;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-bar">
            <h2>🛠️ Panel Kendali Sinkronisasi Berkas TAR (v18)</h2>
            <a href="?logout=1" class="logout-btn">🚪 Keluar</a>
        </div>

        <div class="panel">
            <p><b>Mode Kompresi:</b> <span style='color:blue;font-weight:bold;'>Format Kontainer .TAR (Aman 100% Tanpa Modul Zip)</span></p>
            <p><b>Sumber Data:</b> <?php echo (file_exists($dirFile) ? "<span style='color:green;font-weight:bold;'>Terdeteksi file dir.txt (".count(file($dirFile))." baris)</span>" : "<span style='color:orange;font-weight:bold;'>Mode Scrape Otomatis Beranda</span>"); ?></p>

            <div class="info-box">
                <strong>ℹ️ Status Autentikasi:</strong> Anda sudah login. Sesi akan kadaluarsa dalam 1 jam.
            </div>

            <form method='POST' action=''>
                <div class='form-group'>
                    <label>
                        <input type='checkbox' name='extract_physical' value='1' <?php echo ($extractFisik ? "checked" : ""); ?>>
                        Centang jika ingin sekalian membuat Folder FISIK Nyata di Hosting lokal ini.
                    </label>
                </div>
                <button type='submit' name='submit_proses' value='1' class='btn-primary'>🚀 MULAI PROSES SINKRONISASI & BUAT ARSIP TAR</button>
            </form>
        </div>

        <?php
        if ($isProcessed) {

            if (!file_exists($listFile) || !file_exists($templateFile) || !file_exists($templateAmpFile)) {
                die("<div class='panel' style='color:red;'><b>❌ Gagal:</b> Berkas list.txt, template.php, atau template-amp.php tidak lengkap.</div>");
            }

            $targetDirs  = dapatkanDaftarDirektori($baseUrl, $dirFile);
            $brands      = file($listFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $template    = file_get_contents($templateFile);
            $templateAmp = file_get_contents($templateAmpFile);

            $tarNameAsli = "backup_production_package.tar";

            if (file_exists($tarNameAsli)) @unlink($tarNameAsli);

            try {
                $tarAsli = new PharData($tarNameAsli);
                
                $createdFolders = [];
                $summaryData = [];

                foreach ($targetDirs as $key => $dirPath) {
                    $dirPath = trim($dirPath, " /");
                    if (empty($dirPath)) continue;
                    
                    $brandName = isset($brands[$key]) ? trim($brands[$key]) : $brands[$key % count($brands)];
                    $brandName = preg_replace('/[\x00-\x1F\x7F-\x9F\xA0]/u', '', $brandName);

                    $finalContent = str_replace(['{{judul}}', '{{dir}}'], [htmlspecialchars($brandName), $dirPath], $template);
                    $tarAsli->addFromString("$dirPath/index.html", $finalContent);
                    $tarAsli->addFromString("$dirPath/$googleFileName", $googleContent);

                    $finalContentAmp = str_replace(['{{judul}}', '{{dir}}'], [htmlspecialchars($brandName), $dirPath], $templateAmp);
                    $tarAsli->addFromString("amp-version/$dirPath/index.html", $finalContentAmp);

                    if ($extractFisik) {
                        if (!is_dir($dirPath)) {
                            mkdir($dirPath, 0755, true);
                        }
                        file_put_contents("$dirPath/index.html", $finalContent);
                        file_put_contents("$dirPath/$googleFileName", $googleContent);
                    }

                    $createdFolders[] = $dirPath;
                    $summaryData[] = [
                        'url' => "$baseUrl/$dirPath/",
                        'kw'  => $brandName
                    ];
                }

                if (!empty($createdFolders)) {
                    $sitemapContent = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
                    $sitemapContent .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;
                    foreach ($createdFolders as $folder) {
                        $sitemapContent .= '  <url>' . PHP_EOL;
                        $sitemapContent .= '    <loc>' . $baseUrl . '/' . $folder . '/</loc>' . PHP_EOL;
                        $sitemapContent .= '    <lastmod>' . date('Y-m-d') . '</lastmod>' . PHP_EOL;
                        $sitemapContent .= '    <changefreq>daily</changefreq>' . PHP_EOL;
                        $sitemapContent .= '    <priority>0.8</priority>' . PHP_EOL;
                        $sitemapContent .= '  </url>' . PHP_EOL;
                    }
                    $sitemapContent .= '</urlset>';

                    file_put_contents("sitemap.xml", $sitemapContent);
                    $tarAsli->addFromString("sitemap.xml", $sitemapContent);
                }

                echo "<div class='success-box'>";
                echo "<h2>✅ Berkas Kontainer TAR Berhasil Dibuat!</h2>";
                echo "<p><b>Total Sukses Ekstraksi:</b> " . count($createdFolders) . " Jalur Rute Halaman</p>";
                echo "<p><b>Status Penyimpanan Hosting:</b> " . ($extractFisik ? "<span style='color:green;font-weight:bold;'>Folder fisik sukses diterbitkan</span>" : "<span style='color:blue;'>Hemat memori (Hanya dibundel di file TAR)</span>") . "</p>";
                echo "<p>";
                echo "<a href='$tarNameAsli' class='download-link'>📥 DOWNLOAD FILE ALL-IN-ONE TAR</a>";
                echo "</p>";
                echo "<p style='color:#666;'><small>*Catatan: Di dalam file TAR ini, versi Konten Asli berada di root folder, dan versi Konten AMP terkumpul rapi di dalam subfolder bernama <b>amp-version/</b></small></p>";

                echo "<h3>📋 Salin Daftar URL:</h3>";
                echo "<textarea>";
                foreach($summaryData as $item) { echo $item['url'] . "\n"; }
                echo "</textarea>";

                echo "<h3>📋 Salin Daftar Keyword:</h3>";
                echo "<textarea>";
                foreach($summaryData as $item) { echo $item['kw'] . "\n"; }
                echo "</textarea>";
                echo "</div>";

            } catch (Exception $e) {
                echo "<div class='panel' style='color:red; border:2px solid red;'><b>❌ Error Kompresi:</b> " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }
        ?>
    </div>

    <?php
    if (isset($_GET['logout'])) {
        session_destroy();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
    ?>
</body>
</html>
