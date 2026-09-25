<?php
/**
 * Boels Spoedverhuur — laatste regels uit storage/logs/laravel.log (alleen-lezen).
 * Open: https://spoedverhuur.sorai.nl/__log.php?k=<DEPLOY_SECRET>&n=120
 */
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
header('Content-Type: text/plain; charset=utf-8');
$n = max(10, min(2000, intval($_GET['n'] ?? 120)));
$root = null;
foreach ([__DIR__ . '/../laravel_app', __DIR__ . '/..'] as $p) {
    if (file_exists($p . '/storage/logs')) { $root = realpath($p); break; }
}
if (! $root) exit("geen storage/logs gevonden\n");
$bestanden = glob($root . '/storage/logs/*.log') ?: [];
usort($bestanden, fn ($a, $b) => filemtime($b) <=> filemtime($a));
if (! $bestanden) exit("geen logbestanden\n");
$f = $bestanden[0];
echo "Log: " . basename($f) . " (" . round(filesize($f) / 1024) . " kB, laatste wijziging " . date('Y-m-d H:i:s', filemtime($f)) . ")\n";
echo str_repeat('=', 70) . "\n";
$regels = [];
$fh = fopen($f, 'r');
fseek($fh, max(0, filesize($f) - 400000));
while (($r = fgets($fh)) !== false) { $regels[] = $r; }
fclose($fh);
echo implode('', array_slice($regels, -$n));
