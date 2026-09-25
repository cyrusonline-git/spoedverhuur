<?php
/** Boels Spoedverhuur — PHP-limieten controleren. Open: /__check.php?k=<DEPLOY_SECRET> */
$secret = null;
foreach ([__DIR__ . '/../laravel_app/.env', __DIR__ . '/../.env'] as $envFile) {
    if (file_exists($envFile) && preg_match('/^DEPLOY_SECRET=(.+)$/m', file_get_contents($envFile), $m)) { $secret = trim($m[1]); break; }
}
if ($secret === null || ! hash_equals($secret, (string) ($_GET['k'] ?? ''))) { http_response_code(403); exit('forbidden'); }
header('Content-Type: text/plain; charset=utf-8');
foreach (['upload_max_filesize', 'post_max_size', 'memory_limit', 'max_execution_time', 'max_input_time'] as $k) echo str_pad($k, 22) . ini_get($k) . "\n";
echo str_pad('php', 22) . PHP_VERSION . "\n";
echo str_pad('sapi', 22) . PHP_SAPI . "\n";
echo str_pad('sqlite', 22) . (extension_loaded('pdo_sqlite') ? 'ja' : 'NEE') . "\n";
echo str_pad('zip', 22) . (extension_loaded('zip') ? 'ja' : 'NEE') . "\n";
