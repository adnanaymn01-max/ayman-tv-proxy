<?php
// AYMAN TV diagnostic proxy
$HOST = 'http://myhand.org:8080';
header('Cache-Control: no-store, no-cache, must-revalidate');

$u = $_GET['username'] ?? '';
$p = $_GET['password'] ?? '';
$mode = $_GET['mode'] ?? 'api';
$debug = isset($_GET['debug']) && $_GET['debug'] === '1';

if (!$u || !$p) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Missing credentials\nproxy.php is running.");
}

if ($mode === 'api') {
    $url = $HOST . '/player_api.php?username=' . rawurlencode($u) . '&password=' . rawurlencode($p);
    if (!empty($_GET['action'])) $url .= '&action=' . rawurlencode($_GET['action']);
    if (!empty($_GET['series_id'])) $url .= '&series_id=' . rawurlencode($_GET['series_id']);
} else {
    $kind = $_GET['kind'] ?? '';
    $id = preg_replace('/[^0-9A-Za-z_-]/', '', $_GET['id'] ?? '');
    $ext = preg_replace('/[^0-9A-Za-z]/', '', $_GET['ext'] ?? 'mp4');
    if (!in_array($kind, ['live','movie','series'], true) || !$id) {
        http_response_code(400);
        exit('Bad stream');
    }
    $url = $HOST . '/' . $kind . '/' . rawurlencode($u) . '/' . rawurlencode($p) . '/' . $id . '.' . $ext;
}

if (!function_exists('curl_init')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit('DIAGNOSTIC: PHP cURL is not available on this hosting account.');
}

$ch = curl_init($url);
$headers = [];
if (isset($_SERVER['HTTP_RANGE'])) $headers[] = 'Range: ' . $_SERVER['HTTP_RANGE'];

curl_setopt_array($ch, [
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_CONNECTTIMEOUT => 12,
    CURLOPT_TIMEOUT => ($mode === 'api' ? 30 : 0),
    CURLOPT_RETURNTRANSFER => ($mode === 'api' || $debug),
    CURLOPT_USERAGENT => 'AYMAN-TV-Web/1.0',
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_HEADER => false,
]);

if ($mode !== 'api' && !$debug) {
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($ch, $h) {
        $l = strtolower($h);
        if (str_starts_with($l, 'content-type:') || str_starts_with($l, 'content-length:') || str_starts_with($l, 'accept-ranges:') || str_starts_with($l, 'content-range:')) header(trim($h));
        return strlen($h);
    });
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $d) { echo $d; flush(); return strlen($d); });
}

$body = curl_exec($ch);
$errno = curl_errno($ch);
$error = curl_error($ch);
$code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: '';
$totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
$primaryIp = curl_getinfo($ch, CURLINFO_PRIMARY_IP) ?: '';
curl_close($ch);

if ($errno || $body === false) {
    http_response_code(502);
    header('Content-Type: text/plain; charset=utf-8');
    echo "AYMAN TV PROXY DIAGNOSTIC\n";
    echo "RESULT: CONNECTION FAILED\n";
    echo "cURL errno: " . $errno . "\n";
    echo "cURL error: " . ($error ?: 'unknown') . "\n";
    echo "HTTP code: " . $code . "\n";
    echo "Target host: myhand.org:8080\n";
    echo "Resolved IP: " . ($primaryIp ?: 'none') . "\n";
    echo "Time: " . $totalTime . " sec\n";
    exit;
}

if ($debug) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "AYMAN TV PROXY DIAGNOSTIC\n";
    echo "RESULT: REQUEST COMPLETED\n";
    echo "HTTP code: " . $code . "\n";
    echo "Content-Type: " . $contentType . "\n";
    echo "Target host: myhand.org:8080\n";
    echo "Resolved IP: " . ($primaryIp ?: 'none') . "\n";
    echo "Time: " . $totalTime . " sec\n";
    echo "Response bytes: " . strlen((string)$body) . "\n";
    echo "Response preview:\n" . substr((string)$body, 0, 1200);
    exit;
}

http_response_code($code ?: 502);
if ($mode === 'api') {
    header('Content-Type: ' . ($contentType ?: 'application/json; charset=utf-8'));
    echo $body;
}
?>
