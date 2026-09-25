<?php
/**
 * Boels Spoedverhuur — Migrate-only script.
 *
 * Open: https://spoedverhuur.sorai.nl/__migrate.php?k=<DEPLOY_SECRET>
 * BLIJFT staan op de server (net als __pull_deploy.php) — je gebruikt hem
 * bij elke deploy opnieuw, en hij is beveiligd met de DEPLOY_SECRET.
 */

// Sleutel komt uit de server-.env (DEPLOY_SECRET); oude vaste sleutel
// geldt alleen zolang __harden.php nog niet gedraaid heeft.
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
header('Content-Type: text/plain; charset=utf-8');

echo "Boels Spoedverhuur — Migrate\n";
echo str_repeat('=', 50) . "\n\n";

$candidates = [__DIR__ . '/../laravel_app', __DIR__ . '/..'];
$root = null;
foreach ($candidates as $p) {
    if (file_exists($p . '/vendor/autoload.php')) {
        $root = realpath($p);
        break;
    }
}
if (! $root) exit("FOUT: Laravel niet gevonden.\n");
echo "Laravel root: $root\n\n";

require $root . '/vendor/autoload.php';
$app = require_once $root . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$buf = new \Symfony\Component\Console\Output\BufferedOutput;

try {
    echo "[1/3] migrate --force\n";
    $exit = $kernel->call('migrate', ['--force' => true], $buf);
    echo $buf->fetch();
    echo "exit code: $exit\n\n";

    echo "[2/3] cache clearen\n";
    $kernel->call('config:clear', [], $buf);   echo $buf->fetch();
    $kernel->call('view:clear', [], $buf);     echo $buf->fetch();
    $kernel->call('route:clear', [], $buf);    echo $buf->fetch();
    $kernel->call('cache:clear', [], $buf);    echo $buf->fetch();

    // Alle Blade-views alvast compileren: een fout in een view valt hier
    // op i.p.v. pas bij de eerste bezoeker.
    echo "[3/3] views compileren\n";
    $kernel->call('view:cache', [], $buf);     echo $buf->fetch();

    if ($exit === 0) {
        echo "\n✓ Migrate geslaagd.\n";
    } else {
        echo "\n⚠ Migrate gefaald.\n";
    }
} catch (\Throwable $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
    echo $e->getFile() . ":" . $e->getLine() . "\n";
}
