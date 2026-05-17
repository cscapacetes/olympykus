<?php
/**
 * /api/pixel_test.php
 * Envia um evento de teste para o TikTok Events API v1.3.
 *
 * POST JSON: { event, value?, use_test_code? }
 *   event: PageView | ViewContent | AddToCart | InitiateCheckout | AddPaymentInfo | Purchase
 *   use_test_code: true (default) → usa Test Event Code; false → evento REAL na produção
 */
require_once __DIR__ . '/db.php';
session_start();

header('Content-Type: application/json');

if (empty($_SESSION['ptracker_logged'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'forbidden']);
    exit;
}

$in = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$event = trim((string)($in['event'] ?? ''));
$allowed = ['PageView','ViewContent','AddToCart','InitiateCheckout','AddPaymentInfo','Purchase','CompletePayment','ClickButton','CompleteRegistration'];
if (!in_array($event, $allowed, true)) {
    echo json_encode(['ok' => false, 'error' => 'event inválido']); exit;
}
if ($event === 'CompletePayment') $event = 'Purchase';

$pid = getSetting('tt_pixel_id');
$tok = getSetting('tt_access_token');
$tec = getSetting('tt_test_event_code');

if (!$pid || !$tok) {
    echo json_encode(['ok' => false, 'error' => 'Pixel ID ou Access Token não configurados']); exit;
}

$value = isset($in['value']) && $in['value'] !== ''
        ? floatval($in['value'])
        : floatval(getSetting('tt_pixel_event_value', '19.99'));

$evtId = 'test_' . bin2hex(random_bytes(6));
$ip    = $_SERVER['REMOTE_ADDR']     ?? '127.0.0.1';
$ua    = $_SERVER['HTTP_USER_AGENT'] ?? 'PHP/test';

$contentId   = getSetting('tt_content_id',   'cliente');
$contentName = getSetting('tt_content_name', 'cliente');
$locale      = strtolower(trim(getSetting('tt_locale', 'pt_BR'))) ?: 'pt_BR';
if ($locale === 'pt' || $locale === 'br') $locale = 'pt_BR';

/* Hashes determinísticos para o admin testar EMQ */
$testEmail  = 'teste@exemplo.com';
$testPhoneE = '+5511987654321';
$testExtId  = 'test_user_emq';

$user = [
    'email'       => [hash('sha256', strtolower(trim($testEmail)))],
    'phone'       => [hash('sha256', $testPhoneE)],
    'external_id' => [hash('sha256', strtolower(trim($testExtId)))],
    'ip'          => $ip,
    'user_agent'  => $ua,
    'locale'      => $locale,
];

$eventData = [
    'event'      => $event,
    'event_time' => time(),
    'event_id'   => $evtId,
    'user'       => $user,
    'properties' => [
        'currency'     => 'BRL',
        'value'        => round($value, 2),
        'content_type' => 'product',
        'content_id'   => $contentId,
        'content_name' => $contentName,
        'contents'     => [[
            'content_id'   => $contentId,
            'content_type' => 'product',
            'content_name' => $contentName,
            'quantity'     => 1,
            'price'        => round($value, 2),
        ]],
    ],
    'page' => [
        'url'      => 'https://' . ($_SERVER['HTTP_HOST'] ?? 'teste.local') . '/',
        'referrer' => '',
    ],
    'limited_data_use' => false,
];

$useTestCode = !isset($in['use_test_code']) ? true : (bool)$in['use_test_code'];

$payload = [
    'event_source'    => 'web',
    'event_source_id' => $pid,
    'data'            => [$eventData],
];
if ($useTestCode && !empty($tec)) {
    $payload['test_event_code'] = $tec;
}

$ch = curl_init('https://business-api.tiktok.com/open_api/v1.3/event/track/');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Access-Token: ' . $tok,
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

$stmt = $db->prepare("INSERT INTO pixel_events_log
    (event, pixel_id, has_email, has_phone, has_external_id, has_ip, has_user_agent, has_click_id, status)
    VALUES (:e, :p, 1, 1, 1, 1, 1, 0, :st)");
$stmt->bindValue(':e',  $event, SQLITE3_TEXT);
$stmt->bindValue(':p',  $pid,   SQLITE3_TEXT);
$stmt->bindValue(':st', (int)$code, SQLITE3_INTEGER);
$stmt->execute();

$parsed = json_decode($resp, true);
echo json_encode([
    'ok'                   => $code >= 200 && $code < 300 && (($parsed['code'] ?? -1) === 0),
    'http_code'            => $code,
    'curl_error'           => $err,
    'event_id'             => $evtId,
    'event_name'           => $event,
    'endpoint'             => 'v1.3/event/track/',
    'test_event_code_used' => $useTestCode ? $tec : '',
    'response'             => $parsed ?: $resp,
]);
