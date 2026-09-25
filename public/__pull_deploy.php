<?php
/**
 * Boels Spoedverhuur — Pull deploy van GitHub Releases.
 *
 * Downloadt de laatste release zips via HTTPS van GitHub
 * (omzeilt de instabiele FTP-verbinding van Antagonist).
 *
 * Aanroep:
 *   https://spoedverhuur.sorai.nl/__pull_deploy.php?k=<DEPLOY_SECRET>
 *
 * Optioneel: een specifieke tag:
 *   https://spoedverhuur.sorai.nl/__pull_deploy.php?k=<DEPLOY_SECRET>&tag=v1.0.5
 *
 * BLIJFT staan op de server — verwijdert zichzelf NIET, want je gebruikt
 * hem bij elke deploy opnieuw.
 */

// Sleutel komt uit de server-.env (DEPLOY_SECRET). Zonder .env geen deploy.
$secret = null;
foreach ([__DIR__ . '/../laravel_app/.env', __DIR__ . '/../.env'] as $envFile) {
    if (file_exists($envFile) && preg_match('/^DEPLOY_SECRET=(.+)$/m', file_get_contents($envFile), $m)) {
        $secret = trim($m[1]);
        break;
    }
}
if ($secret === null || ! hash_equals($secret, (string) ($_GET['k'] ?? ''))) {
    http_response_code(403);
    exit('forbidden');
}

@set_time_limit(300);
@ini_set('memory_limit', '256M');
header('Content-Type: text/plain; charset=utf-8');

// ============ CONFIG ============
$githubOwner = 'cyrusonline-git';
$githubRepo  = 'spoedverhuur';
$tag         = $_GET['tag'] ?? 'latest';
// =================================

$here = __DIR__;
$tmpDir = $here . '/_deploy_tmp';
if (! is_dir($tmpDir)) { mkdir($tmpDir, 0755, true); }

echo "Boels CORE — Pull Deploy van GitHub\n";
echo str_repeat('=', 60) . "\n\n";
echo "Repository: $githubOwner/$githubRepo\n";
echo "Tag:        $tag\n";
echo "Tijd:       " . date('Y-m-d H:i:s') . "\n\n";

// === STAP 1: Haal release info op via GitHub API ===
$apiUrl = $tag === 'latest'
    ? "https://api.github.com/repos/$githubOwner/$githubRepo/releases/latest"
    : "https://api.github.com/repos/$githubOwner/$githubRepo/releases/tags/$tag";

echo "[1/4] Release info ophalen...\n";
$ctx = stream_context_create([
    'http' => [
        'header' => "User-Agent: Boels-Voorraad-PullDeploy\r\nAccept: application/vnd.github+json\r\n",
        'timeout' => 30,
    ],
]);
$json = @file_get_contents($apiUrl, false, $ctx);
if (! $json) {
    exit("FOUT: kon GitHub API niet bereiken ($apiUrl)\n");
}
$release = json_decode($json, true);
if (! isset($release['assets'])) {
    exit("FOUT: geen assets in release: " . substr($json, 0, 200) . "\n");
}

echo "      → Release: " . ($release['tag_name'] ?? '?') . "\n";
echo "      → Naam:    " . ($release['name'] ?? '?') . "\n";
echo "      → Assets:  " . count($release['assets']) . "\n\n";

// === STAP 2: Download beide zips ===
echo "[2/4] Zips downloaden...\n";

$downloads = [];
foreach ($release['assets'] as $asset) {
    $name = $asset['name'];
    if (! in_array($name, ['laravel_app.zip', 'public_html.zip'])) {
        continue;
    }
    $url = $asset['browser_download_url'];
    $size = round($asset['size'] / 1024 / 1024, 1);
    $localPath = "$tmpDir/$name";

    echo "      → $name ($size MB) ... ";
    $fp = fopen($localPath, 'w');
    if (! $fp) { exit("FOUT: kan niet schrijven naar $localPath\n"); }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Boels-Voorraad-PullDeploy');
    curl_setopt($ch, CURLOPT_TIMEOUT, 180);
    $ok = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fp);

    if (! $ok || $httpCode !== 200) {
        exit("FAIL (HTTP $httpCode)\n");
    }
    echo "OK\n";
    $downloads[$name] = $localPath;
}
echo "\n";

// === STAP 3: Pak uit ===
echo "[3/4] Uitpakken...\n";

if (isset($downloads['public_html.zip'])) {
    $z = new ZipArchive;
    if ($z->open($downloads['public_html.zip']) === true) {
        $z->extractTo($here);
        $z->close();
        echo "      → public_html.zip uitgepakt in $here\n";
    } else {
        echo "      → FAIL: kon public_html.zip niet openen\n";
    }
}

if (isset($downloads['laravel_app.zip'])) {
    $larDir = dirname($here) . '/laravel_app';
    if (! is_dir($larDir)) { mkdir($larDir, 0755, true); }
    $z = new ZipArchive;
    if ($z->open($downloads['laravel_app.zip']) === true) {
        $z->extractTo($larDir);
        $z->close();
        echo "      → laravel_app.zip uitgepakt in $larDir\n";
    } else {
        echo "      → FAIL: kon laravel_app.zip niet openen\n";
    }
}
echo "\n";

// === STAP 4: Cache clear + opruimen ===
echo "[4/4] Migreren + cache clear + opruimen...\n";

$candidates = [$here . '/../laravel_app', $here . '/..'];
$root = null;
foreach ($candidates as $p) {
    if (file_exists($p . '/vendor/autoload.php')) {
        $root = realpath($p);
        break;
    }
}

if ($root) {
    require $root . '/vendor/autoload.php';
    try {
        $app = require_once $root . '/bootstrap/app.php';
        $kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
        $kernel->bootstrap();

        $buf = new \Symfony\Component\Console\Output\BufferedOutput;
        $exit = $kernel->call('migrate', ['--force' => true], $buf);
        echo "      → migrate: " . trim($buf->fetch()) . " (exit $exit)
";
        $kernel->call('config:clear', [], $buf);
        $kernel->call('view:clear', [], $buf);
        $kernel->call('route:clear', [], $buf);
        $kernel->call('cache:clear', [], $buf);
        echo "      → Cache gecleared.\n";
    } catch (\Throwable $e) {
        echo "      → Cache clear waarschuwing: " . $e->getMessage() . "\n";
    }
}

// Opruimen
foreach ($downloads as $path) {
    @unlink($path);
}
@rmdir($tmpDir);
echo "      → Tijdelijke bestanden verwijderd.\n\n";

echo str_repeat('=', 60) . "\n";
echo "✓ Deploy klaar! Spoedverhuur is bijgewerkt.\n";
echo str_repeat('=', 60) . "\n";
echo "\nTip: voor een specifieke versie, gebruik:\n";
echo "  __pull_deploy.php?k=<DEPLOY_SECRET>&tag=v1.0.X\n";
