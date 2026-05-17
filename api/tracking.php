<?php
/**
 * /api/tracking.php
 * Endpoint leve de tracking — alimenta Live View e funil interno do admin.
 *
 * POST JSON: {
 *   session_id, event, page_url, value?,
 *   utm_source, utm_medium, utm_campaign, utm_content, utm_term,
 *   fbc, fbp, ttclid, ttp
 * }
 * event: 'pageview' | 'view_content' | 'initiate_checkout' | 'add_payment_info' | 'purchase' | 'heartbeat'
 */
require_once __DIR__ . '/db.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$raw = file_get_contents('php://input');
$in  = json_decode($raw, true);
if (!is_array($in)) $in = $_POST ?: $_GET;

$session_id = trim((string)($in['session_id'] ?? ''));
$event      = trim((string)($in['event']      ?? ''));
$allowed    = ['pageview','view_content','initiate_checkout','add_payment_info','purchase','heartbeat'];
if ($session_id === '' || !in_array($event, $allowed, true)) {
    echo json_encode(['ok' => false, 'error' => 'invalid']);
    exit;
}

$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
if (strpos($ip, ',') !== false) $ip = trim(explode(',', $ip)[0]);
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

/* Detecta país: 1) headers de proxy 2) ip-api.com com cache 24h em settings */
function detectCountry($ip) {
    foreach (['HTTP_CF_IPCOUNTRY','HTTP_X_COUNTRY_CODE','HTTP_X_COUNTRY','HTTP_X_APPENGINE_COUNTRY'] as $h) {
        if (!empty($_SERVER[$h]) && strlen($_SERVER[$h]) === 2 && $_SERVER[$h] !== 'XX') {
            return strtoupper($_SERVER[$h]);
        }
    }
    if (!$ip || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE) === false) {
        return '';
    }
    static $memCache = [];
    if (isset($memCache[$ip])) return $memCache[$ip];
    $cacheKey = 'geoip_' . md5($ip);
    $cached = getSetting($cacheKey, '');
    if ($cached !== '') {
        $parts = explode('|', $cached);
        if (isset($parts[1]) && (int)$parts[1] > time() - 86400) {
            return $memCache[$ip] = $parts[0];
        }
    }
    if (function_exists('curl_init')) {
        $ch = curl_init('http://ip-api.com/json/' . urlencode($ip) . '?fields=countryCode');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 2,
            CURLOPT_CONNECTTIMEOUT => 1,
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($resp, true);
        $cc = strtoupper(trim((string)($data['countryCode'] ?? '')));
        if ($cc && strlen($cc) === 2) {
            setSetting($cacheKey, $cc . '|' . time());
            return $memCache[$ip] = $cc;
        }
    }
    return $memCache[$ip] = '';
}

$country = detectCountry($ip);

$utm_source   = trim((string)($in['utm_source']   ?? ''));
$utm_medium   = trim((string)($in['utm_medium']   ?? ''));
$utm_campaign = trim((string)($in['utm_campaign'] ?? ''));
$utm_content  = trim((string)($in['utm_content']  ?? ''));
$utm_term     = trim((string)($in['utm_term']     ?? ''));
$fbc          = trim((string)($in['fbc']          ?? ''));
$fbp          = trim((string)($in['fbp']          ?? ''));
$ttclid       = trim((string)($in['ttclid']       ?? ''));
$ttp          = trim((string)($in['ttp']          ?? ''));
$page_url     = trim((string)($in['page_url']     ?? ''));
$value_cents  = isset($in['value']) ? (int) round(floatval($in['value']) * 100) : 0;

$step_map = [
    'pageview'          => 'visit',
    'view_content'      => 'visit',
    'heartbeat'         => null,
    'initiate_checkout' => 'checkout',
    'add_payment_info'  => 'checkout',
    'purchase'          => 'paid',
];
$newStep = $step_map[$event] ?? null;

/* Upsert sessão */
$stmt = $db->prepare("SELECT id, step FROM sessions WHERE session_id = :s LIMIT 1");
$stmt->bindValue(':s', $session_id, SQLITE3_TEXT);
$row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

if (!$row) {
    $stmt = $db->prepare("INSERT INTO sessions
        (session_id, ip, user_agent, country, utm_source, utm_medium, utm_campaign, utm_content, utm_term,
         fbc, fbp, ttclid, ttp, step, page_url, created_at, last_seen)
        VALUES
        (:s, :ip, :ua, :cc, :usr, :umd, :ucp, :ucn, :utm, :fbc, :fbp, :ttc, :ttp, :step, :pu, datetime('now'), datetime('now'))");
    $stmt->bindValue(':s',    $session_id, SQLITE3_TEXT);
    $stmt->bindValue(':ip',   $ip,         SQLITE3_TEXT);
    $stmt->bindValue(':ua',   $ua,         SQLITE3_TEXT);
    $stmt->bindValue(':cc',   $country,    SQLITE3_TEXT);
    $stmt->bindValue(':usr',  $utm_source, SQLITE3_TEXT);
    $stmt->bindValue(':umd',  $utm_medium, SQLITE3_TEXT);
    $stmt->bindValue(':ucp',  $utm_campaign, SQLITE3_TEXT);
    $stmt->bindValue(':ucn',  $utm_content, SQLITE3_TEXT);
    $stmt->bindValue(':utm',  $utm_term,   SQLITE3_TEXT);
    $stmt->bindValue(':fbc',  $fbc,        SQLITE3_TEXT);
    $stmt->bindValue(':fbp',  $fbp,        SQLITE3_TEXT);
    $stmt->bindValue(':ttc',  $ttclid,     SQLITE3_TEXT);
    $stmt->bindValue(':ttp',  $ttp,        SQLITE3_TEXT);
    $stmt->bindValue(':step', $newStep ?: 'visit', SQLITE3_TEXT);
    $stmt->bindValue(':pu',   $page_url,   SQLITE3_TEXT);
    $stmt->execute();
} else {
    /* Step só escala (visit → checkout → paid), nunca regride */
    $rank = ['visit' => 1, 'checkout' => 2, 'paid' => 3];
    $cur  = $rank[$row['step']] ?? 1;
    $next = $newStep ? ($rank[$newStep] ?? 1) : $cur;
    $finalStep = ($next > $cur) ? $newStep : $row['step'];

    $stmt = $db->prepare("UPDATE sessions SET last_seen = datetime('now'), step = :step,
                          page_url = CASE WHEN :pu != '' THEN :pu ELSE page_url END,
                          country  = CASE WHEN COALESCE(country,'')='' AND :cc != '' THEN :cc ELSE country END
                          WHERE session_id = :s");
    $stmt->bindValue(':step', $finalStep, SQLITE3_TEXT);
    $stmt->bindValue(':pu',   $page_url,  SQLITE3_TEXT);
    $stmt->bindValue(':cc',   $country,   SQLITE3_TEXT);
    $stmt->bindValue(':s',    $session_id, SQLITE3_TEXT);
    $stmt->execute();
}

/* Regista evento (heartbeats só renovam last_seen) */
if ($event !== 'heartbeat') {
    $stmt = $db->prepare("INSERT INTO funnel_events (session_id, event, value_cents)
                          VALUES (:s, :e, :v)");
    $stmt->bindValue(':s', $session_id,  SQLITE3_TEXT);
    $stmt->bindValue(':e', $event,       SQLITE3_TEXT);
    $stmt->bindValue(':v', $value_cents, SQLITE3_INTEGER);
    $stmt->execute();
}

echo json_encode(['ok' => true]);
