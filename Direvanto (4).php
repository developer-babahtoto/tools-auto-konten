<?php
/**
 * SCRIPT FINAL V7: VIRTUAL DIR & MEMORY-ONLY ZIP GENERATOR
 * Server: Tidak membuat folder/file fisik sama sekali.
 * ZIP 1 (AMP): Berisi index.html (versi AMP) di dalam struktur path virtual.
 * ZIP 2 (ASLI): Berisi index.html (versi Konten Asli) di dalam struktur path virtual.
 */

$listFile = 'list.txt';
$templateFile = 'template.php';
$templateAmpFile = 'template-amp.php';
$limitDir = 11;

$googleFileName = 'google028f325756238b38.html';
$googleContent  = 'google-site-verification: google028f325756238b38.html';

$host = $_SERVER['HTTP_HOST'];
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$baseUrl = $protocol . $host;

function getCleanDirsOnly($url, $limit) {
    $options = ["http" => ["header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)\r\n"]];
    $context = stream_context_create($options);
    $html = @file_get_contents($url, false, $context);
    
    $cleanDirs = [];
    $blacklist = ['wp-admin', 'wp-content', 'wp-includes', 'category', 'tag', 'author', 'search', 'home', 'login', 'register'];

    if ($html) {
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('//a[@href]');
        
        foreach ($nodes as $node) {
            $href = $node->getAttribute('href');
            $path = parse_url($href, PHP_URL_PATH);
            $slug = trim($path, '/');
            if (empty($slug) || strpos($slug, '.') !== false || strpos($slug, '?') !== false) continue;
            if (in_array(strtolower($slug), $blacklist)) continue;
            if (preg_match('/^[a-z0-9\-]+$/i', $slug)) {
                $cleanDirs[] = strtolower($slug);
            }
        }
    }

    $result = array_slice(array_unique($cleanDirs), 0, $limit);

    // --- FITUR FALLBACK: Jika tidak ada dir latin ditemukan ---
    if (empty($result)) {
        $fallbackList = ['about-us', 'gallery', 'faqs', 'contact-us', 'home'];
        shuffle($fallbackList);
        $result = array_slice($fallbackList, 0, 5); 
    }

    return $result;
}

// 1. Eksekusi Pencarian atau Gunakan Fallback
$targetDirs = getCleanDirsOnly($baseUrl, $limitDir);

// 2. Persiapan Data
$keywords = file_exists($listFile) ? file($listFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : ['Keyword Default'];
$template = file_exists($templateFile) ? file_get_contents($templateFile) : "<h1>{{judul}}</h1>";
$templateAmp = file_exists($templateAmpFile) ? file_get_contents($templateAmpFile) : "<h1>AMP: {{judul}}</h1>";

// 3. Inisialisasi 2 File ZIP (AMP & ASLI)
$zipNameAmp = "amp-$host" . ".zip";
$zipNameAsli = "lp-$host" . ".zip";

$zipAmp = new ZipArchive();
$zipAsli = new ZipArchive();

if ($zipAmp->open($zipNameAmp, ZipArchive::CREATE) !== TRUE) {
    die("Gagal membuat file ZIP AMP.");
}
if ($zipAsli->open($zipNameAsli, ZipArchive::CREATE) !== TRUE) {
    die("Gagal membuat file ZIP Konten Asli.");
}

$summaryData = [];

foreach ($targetDirs as $index => $dir) {
    $currentKw = isset($keywords[$index]) ? trim($keywords[$index]) : $keywords[array_rand($keywords)];
    
    // --- PROSES KONTEN ASLI (ZIP ASLI ONLY - VIRTUAL PATH) ---
    $finalHTML = str_replace(['{{judul}}', '{{dir}}'], [htmlspecialchars($currentKw), $dir], $template);
    
    // addFromString otomatis membuat struktur folder di dalam ZIP secara virtual tanpa membuat dir fisik di server
    $zipAsli->addFromString("$dir/index.html", $finalHTML);
    $zipAsli->addFromString("$dir/$googleFileName", $googleContent);

    // --- PROSES KONTEN AMP (ZIP AMP ONLY - VIRTUAL PATH) ---
    $finalAMP = str_replace(['{{judul}}', '{{dir}}'], [htmlspecialchars($currentKw), $dir], $templateAmp);
    
    $zipAmp->addFromString("$dir/index.html", $finalAMP);
    $zipAmp->addFromString("$dir/$googleFileName", $googleContent);
    
    $summaryData[] = [
        'url' => "$baseUrl/$dir/",
        'kw' => $currentKw
    ];
}

// Buat Struktur Sitemap.xml
$xml = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
foreach ($targetDirs as $d) {
    $xml .= "<url><loc>$baseUrl/$d/</loc><lastmod>".date('Y-m-d')."</lastmod></url>";
}
$xml .= '</urlset>';

// Masukkan sitemap ke kedua ZIP
$zipAmp->addFromString("sitemap.xml", $xml);
$zipAsli->addFromString("sitemap.xml", $xml);

// Tutup kedua file ZIP untuk menyimpan ke disk
$zipAmp->close();
$zipAsli->close();

// 4. Output Laporan
echo "<div style='font-family:sans-serif; padding:20px; max-width:900px; margin:auto;'>";
echo "<h2>✅ Pembuatan Virtual ZIP Selesai (Tanpa Folder Fisik)</h2>";
echo "<p><b>Total Virtual Path:</b> " . count($targetDirs) . " Rute</p>";
echo "<p style='margin-bottom: 20px;'>";
echo "<a href='$zipNameAmp' style='background:orange; color:white; padding:10px; text-decoration:none; border-radius:5px; margin-right:10px; font-weight:bold;'>Download ZIP AMP (index.html)</a>";
echo "<a href='$zipNameAsli' style='background:blue; color:white; padding:10px; text-decoration:none; border-radius:5px; font-weight:bold;'>Download ZIP Asli (index.html)</a>";
echo "</p>";

echo "<h3>Copy Daftar URL:</h3>";
echo "<textarea style='width:100%; height:120px; padding:10px;'>";
foreach($summaryData as $item) { echo $item['url'] . "\n"; }
echo "</textarea>";

echo "<h3>Copy Daftar Keyword:</h3>";
echo "<textarea style='width:100%; height:120px; padding:10px;'>";
foreach($summaryData as $item) { echo $item['kw'] . "\n"; }
echo "</textarea>";
echo "</div>";
?>