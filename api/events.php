<?php
/**
 * /api/events.php
 * Endpoint AJAX que o tracking.js usa para disparar eventos TikTok server-side.
 *
 * Body JSON:
 *   {
 *     event:        'PageView' | 'ViewContent' | 'InitiateCheckout' | 'AddPaymentInfo' | 'Purchase' | ...
 *     event_id:     string (para dedup com client-side ttq.track)
 *     session_id:   string (UUID gerado pelo tracking.js)
 *     value:        número (BRL) — opcional
 *     email, phone, ttclid, ttp, fbc, fbp, external_id, first_name, last_name, page_url
 *   }
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/pixel_fire.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$raw   = file_get_contents('php://input');
$input = json_decode($raw, true) ?: [];

$evt   = trim((string)($input['event'] ?? ''));
$allow = ['PageView','LandingPageView','EngagedSession','ViewContent','AddToCart','InitiateCheckout','AddPaymentInfo','Purchase','ClickButton','CompleteRegistration','CompletePayment'];
if (!in_array($evt, $allow, true)) {
    echo json_encode(['ok' => false, 'error' => 'invalid event']);
    exit;
}

/* IP cliente (atrás de proxy) */
$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
if (strpos($ip, ',') !== false) $ip = trim(explode(',', $ip)[0]);

$email = trim((string)($input['email'] ?? ''));
$phone = trim((string)($input['phone'] ?? ''));
$sid   = trim((string)($input['session_id'] ?? ''));

$ctx = [
    'session_id'  => $sid,
    'email'       => $email,
    'phone'       => $phone,
    'ip'          => $ip,
    'user_agent'  => $_SERVER['HTTP_USER_AGENT'] ?? '',
    'event_id'    => trim((string)($input['event_id'] ?? uniqid($evt . '_'))),
    'page_url'    => trim((string)($input['page_url'] ?? '')),
    'ttclid'      => trim((string)($input['ttclid']   ?? '')),
    'ttp'         => trim((string)($input['ttp']      ?? '')),
    'fbc'         => trim((string)($input['fbc']      ?? '')),
    'fbp'         => trim((string)($input['fbp']      ?? '')),
    'external_id' => $email ?: trim((string)($input['external_id'] ?? '')),
    'first_name'  => trim((string)($input['first_name'] ?? '')),
    'last_name'   => trim((string)($input['last_name']  ?? '')),
];

$valueCents = isset($input['value']) ? (int) round(floatval($input['value']) * 100) : 0;

firePixel($evt, $valueCents, $ctx);

/* Atualiza step da sessão + funil interno (Live View do admin) */
if ($sid) {
    $stepMap = ['InitiateCheckout' => 'checkout', 'AddPaymentInfo' => 'checkout', 'Purchase' => 'paid'];
    if (isset($stepMap[$evt])) {
        $stmt = $db->prepare("UPDATE sessions SET step = :st, last_seen = datetime('now') WHERE session_id = :s");
        $stmt->bindValue(':st', $stepMap[$evt], SQLITE3_TEXT);
        $stmt->bindValue(':s',  $sid, SQLITE3_TEXT);
        $stmt->execute();
    }
    $funnelMap = [
        'ViewContent'      => 'view_content',
        'InitiateCheckout' => 'initiate_checkout',
        'AddPaymentInfo'   => 'add_payment_info',
        'Purchase'         => 'purchase',
    ];
    if (isset($funnelMap[$evt])) {
        $stmt = $db->prepare("INSERT INTO funnel_events (session_id, event, value_cents) VALUES (:s, :e, :v)");
        $stmt->bindValue(':s', $sid,                 SQLITE3_TEXT);
        $stmt->bindValue(':e', $funnelMap[$evt],     SQLITE3_TEXT);
        $stmt->bindValue(':v', $valueCents,          SQLITE3_INTEGER);
        $stmt->execute();
    }
}

echo json_encode(['ok' => true, 'event' => $evt, 'event_id' => $ctx['event_id']]);
