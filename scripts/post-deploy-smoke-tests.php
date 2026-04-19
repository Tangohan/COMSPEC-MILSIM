<?php

declare(strict_types=1);

$options = getopt('', ['base-url::', 'timeout::']);
$baseUrl = isset($options['base-url']) && is_string($options['base-url']) && trim($options['base-url']) !== ''
    ? rtrim(trim($options['base-url']), '/')
    : rtrim((string) (getenv('APP_URL') ?: 'http://localhost'), '/');
$timeout = isset($options['timeout']) ? max(2, (int) $options['timeout']) : 8;

$routes = [
    '/api/health',
    '/dashboard',
    '/formations',
    '/courrier',
];

$ok = true;
echo "Smoke tests sur {$baseUrl}\n";

foreach ($routes as $path) {
    $url = $baseUrl . $path;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_NOBODY => false,
        CURLOPT_HEADER => false,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT => 'COMSPEC-Smoke/1.0',
    ]);
    $start = microtime(true);
    $body = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    $latencyMs = (int) round((microtime(true) - $start) * 1000);

    if ($errno !== 0) {
        $ok = false;
        echo "[KO] {$path} erreur réseau ({$errno}) {$error}\n";
        continue;
    }

    $healthy = $status >= 200 && $status < 500;
    if (!$healthy) {
        $ok = false;
    }

    $snippet = is_string($body) ? trim(substr(preg_replace('/\s+/', ' ', $body) ?? '', 0, 80)) : '';
    echo sprintf("[%s] %s status=%d latency=%dms body=\"%s\"\n", $healthy ? 'OK' : 'KO', $path, $status, $latencyMs, $snippet);
}

if (!$ok) {
    exit(1);
}
