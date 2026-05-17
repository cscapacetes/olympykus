<?php
/**
 * /api/pixel_fire.php
 * firePixel() — dispara evento server-side para o TikTok Events API v1.3.
 * Apenas TikTok (Facebook foi removido da versão enxuta).
 *
 * Recursos:
 *   • Dedup por event_id (5min) — evita duplicar quando client+server disparam o mesmo evento
 *   • Enrichment por session_id (email, phone, ttclid, IP, UA capturados em qualquer página)
 *   • EMQ otimizado: hashes SHA-256, telefone normalizado E.164, external_id em cascade
 *   • Retry queue para erros 5xx/timeout (processada por retry_worker.php)
 */
require_once __DIR__ . '/db.php';

/* ─── enrichment por sessão ─── */
function enrichCtxFromSession($ctx, $sessionId) {
    global $db;
    if (!$sessionId) return $ctx;
    $stmt = $db->prepare("SELECT * FROM session_enrichment WHERE session_id = :s LIMIT 1");
    if (!$stmt) return $ctx;
    $stmt->bindValue(':s', $sessionId, SQLITE3_TEXT);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$row) return $ctx;
    foreach (['email','phone','first_name','last_name','zip','city','ttclid','ttp','fbc','fbp','ip','user_agent'] as $k) {
        if (empty($ctx[$k]) && !empty($row[$k])) $ctx[$k] = $row[$k];
    }
    return $ctx;
}

function saveSessionEnrichment($sessionId, $fields) {
    global $db;
    if (!$sessionId) return;
    $exists = $db->querySingle("SELECT 1 FROM session_enrichment WHERE session_id = '" . SQLite3::escapeString($sessionId) . "'");
    if (!$exists) {
        $stmt = $db->prepare("INSERT INTO session_enrichment (session_id) VALUES (:s)");
        $stmt->bindValue(':s', $sessionId, SQLITE3_TEXT);
        $stmt->execute();
    }
    $sets = []; $vals = [];
    foreach (['email','phone','first_name','last_name','zip','city','ttclid','ttp','fbc','fbp','ip','user_agent'] as $k) {
        if (!empty($fields[$k])) { $sets[] = "$k = :$k"; $vals[$k] = $fields[$k]; }
    }
    if (!$sets) return;
    $sets[] = "updated_at = datetime('now')";
    $sql = "UPDATE session_enrichment SET " . implode(', ', $sets) . " WHERE session_id = :sid";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':sid', $sessionId, SQLITE3_TEXT);
    foreach ($vals as $k => $v) $stmt->bindValue(":$k", $v, SQLITE3_TEXT);
    $stmt->execute();
}

/* ─── event_id determinístico (mesmo client+server) ─── */
function stableEventId($event, $sessionId, $bucket = '') {
    $bucket = $bucket ?: floor(time() / 60);
    return strtolower($event) . '_' . substr(md5(($sessionId ?: 'anon') . '_' . $bucket), 0, 16);
}

/* ─── retry queue ─── */
function queueRetry($event, $eventId, $payload, $err) {
    global $db;
    $stmt = $db->prepare("INSERT OR REPLACE INTO pixel_retry_queue
        (event, event_id, payload_json, last_error, next_try_at, attempts)
        VALUES (:e, :eid, :pl, :err, datetime('now', '+60 seconds'),
                COALESCE((SELECT attempts FROM pixel_retry_queue WHERE event_id=:eid), 0) + 1)");
    $stmt->bindValue(':e',   $event,                SQLITE3_TEXT);
    $stmt->bindValue(':eid', $eventId,              SQLITE3_TEXT);
    $stmt->bindValue(':pl',  json_encode($payload), SQLITE3_TEXT);
    $stmt->bindValue(':err', substr($err ?? '', 0, 500), SQLITE3_TEXT);
    $stmt->execute();
}

/* ─── log para Pixel Score ─── */
function logPixelEvent($event, $pixelId, $fields, $status) {
    global $db;
    try {
        $stmt = $db->prepare("INSERT INTO pixel_events_log
            (event, pixel_id, has_email, has_phone, has_external_id, has_ip, has_user_agent, has_click_id, status)
            VALUES (:e, :p, :em, :ph, :ex, :ip, :ua, :ci, :st)");
        $stmt->bindValue(':e',  $event,   SQLITE3_TEXT);
        $stmt->bindValue(':p',  $pixelId, SQLITE3_TEXT);
        $stmt->bindValue(':em', !empty($fields['email'])       ? 1 : 0, SQLITE3_INTEGER);
        $stmt->bindValue(':ph', !empty($fields['phone'])       ? 1 : 0, SQLITE3_INTEGER);
        $stmt->bindValue(':ex', !empty($fields['external_id']) ? 1 : 0, SQLITE3_INTEGER);
        $stmt->bindValue(':ip', !empty($fields['ip'])          ? 1 : 0, SQLITE3_INTEGER);
        $stmt->bindValue(':ua', !empty($fields['user_agent'])  ? 1 : 0, SQLITE3_INTEGER);
        $stmt->bindValue(':ci', !empty($fields['click_id'])    ? 1 : 0, SQLITE3_INTEGER);
        $stmt->bindValue(':st', $status, SQLITE3_INTEGER);
        $stmt->execute();
    } catch (Exception $e) { /* não quebrar */ }
}

/**
 * firePixel — dispara evento para o TikTok Events API v1.3 (server-side).
 *
 * @param string $event       PageView, ViewContent, InitiateCheckout, AddPaymentInfo, Purchase, etc.
 * @param int    $amountCents
 * @param array  $ctx         email, phone, ip, user_agent, event_id, fbc, fbp, ttclid, ttp, session_id, ...
 */
function firePixel($event, $amountCents, $ctx = []) {
    global $db;
    if (!function_exists('curl_init')) return;

    /* ─── DEDUP GUARD: pula se este event_id já foi disparado nos últimos 5min ─── */
    $evtIdGuard = (string)($ctx['event_id'] ?? '');
    if ($evtIdGuard !== '') {
        try {
            $checkStmt = $db->prepare("SELECT 1 FROM pixel_fired_dedup
                WHERE event = :e AND event_id = :eid
                  AND fired_at >= datetime('now','-5 minutes') LIMIT 1");
            $checkStmt->bindValue(':e',   $event,      SQLITE3_TEXT);
            $checkStmt->bindValue(':eid', $evtIdGuard, SQLITE3_TEXT);
            if ($checkStmt->execute()->fetchArray(SQLITE3_ASSOC)) {
                return; // já disparado, pula
            }
            $insStmt = $db->prepare("INSERT OR REPLACE INTO pixel_fired_dedup
                (event, event_id, fired_at) VALUES (:e, :eid, datetime('now'))");
            $insStmt->bindValue(':e',   $event,      SQLITE3_TEXT);
            $insStmt->bindValue(':eid', $evtIdGuard, SQLITE3_TEXT);
            $insStmt->execute();
        } catch (Exception $e) {}
    }

    /* ─── Enriquece ctx com dados persistidos da sessão ─── */
    if (!empty($ctx['session_id'])) {
        $ctx = enrichCtxFromSession($ctx, $ctx['session_id']);
        saveSessionEnrichment($ctx['session_id'], $ctx);
    }

    $email   = $ctx['email']      ?? '';
    $phone   = $ctx['phone']      ?? '';
    $ip      = $ctx['ip']         ?? '';
    $ua      = $ctx['user_agent'] ?? '';
    $evtId   = !empty($ctx['event_id'])
               ? $ctx['event_id']
               : stableEventId($event, $ctx['session_id'] ?? '', $ctx['event_bucket'] ?? '');
    $pageUrl = $ctx['page_url'] ?? '';
    $ttclid  = $ctx['ttclid']   ?? '';
    $ttp     = $ctx['ttp']      ?? '';

    /* external_id em cascade: explícito → email → session_id (TikTok aceita qualquer string única) */
    $extId = !empty($ctx['external_id'])
            ? (string)$ctx['external_id']
            : ($email !== '' ? $email : (!empty($ctx['session_id']) ? (string)$ctx['session_id'] : ''));

    $fname = $ctx['first_name'] ?? '';
    $lname = $ctx['last_name']  ?? '';
    $zip   = $ctx['zip']        ?? '';
    $city  = $ctx['city']       ?? '';
    $value = round($amountCents / 100, 2);

    /* TikTok valida value > 0 em eventos comerciais — fallback para 0.01 */
    $commercial = ['InitiateCheckout','AddPaymentInfo','Purchase','AddToCart','CompletePayment','PlaceAnOrder'];
    if ($value <= 0 && in_array($event, $commercial, true)) $value = 0.01;

    /* Hash + normalização */
    $h = function($v) { return $v ? hash('sha256', strtolower(trim($v))) : ''; };

    $normPhone = function($v) {
        if (!$v) return '';
        $hasPlus = (substr(trim($v), 0, 1) === '+');
        $digits  = preg_replace('/[^0-9]/', '', $v);
        if ($digits === '') return '';
        // Heurística BR: 10 ou 11 dígitos → adiciona 55
        if (!$hasPlus && (strlen($digits) === 10 || strlen($digits) === 11)) {
            $digits = '55' . $digits;
        }
        if (strlen($digits) < 8 || strlen($digits) > 15) return '';
        return '+' . $digits;
    };

    $emailClean = strtolower(trim($email));
    $emailValid = $emailClean && filter_var($emailClean, FILTER_VALIDATE_EMAIL) !== false;
    $hEmail = $emailValid ? hash('sha256', $emailClean) : '';
    $phoneN = $normPhone($phone);
    $hPhone = $phoneN ? hash('sha256', $phoneN) : '';
    $hExtId = $extId  ? hash('sha256', strtolower(trim($extId))) : '';
    $hFname = $h($fname);
    $hLname = $h($lname);
    $hZip   = $zip  ? hash('sha256', strtolower(trim(preg_replace('/\s/', '', $zip)))) : '';
    $hCity  = $h($city);

    /* country: ISO-3166-1 alpha-2 lowercase, RAW (sem hash) */
    $country = strtolower(trim(getSetting('pixel_country', 'br'))) ?: 'br';

    /* ─── credenciais (vêm do admin via settings) ─── */
    $pid = getSetting('tt_pixel_id');
    $tok = getSetting('tt_access_token');
    $tec = getSetting('tt_test_event_code');

    if (!$pid || !$tok) {
        logPixelEvent($event, '(no_pixel_configured)', [
            'email' => !empty($hEmail), 'phone' => !empty($hPhone),
            'external_id' => !empty($hExtId), 'ip' => !empty($ip),
            'user_agent' => !empty($ua), 'click_id' => !empty($ttclid),
        ], 0);
        return;
    }

    $contentId   = getSetting('tt_content_id',   'cliente');
    $contentName = getSetting('tt_content_name', 'cliente');
    $locale      = strtolower(trim(getSetting('tt_locale', 'pt_BR'))) ?: 'pt_BR';
    if ($locale === 'pt' || $locale === 'br') $locale = 'pt_BR';

    /* TikTok Events API v1.3 user{} format:
       email/phone/external_id  → ARRAYS de hashes SHA-256
       first_name/last_name/city/zip → STRING (hash SHA-256)
       country → STRING RAW (lowercase, sem hash)
       ip/user_agent/ttclid/ttp/locale → STRING RAW                  */
    $user = [];
    if ($hEmail) $user['email']       = [$hEmail];
    if ($hPhone) $user['phone']       = [$hPhone];
    if ($hExtId) $user['external_id'] = [$hExtId];
    if ($hFname) $user['first_name']  = $hFname;
    if ($hLname) $user['last_name']   = $hLname;
    if ($hZip)   $user['zip_code']    = $hZip;
    if ($hCity)  $user['city']        = $hCity;
    if ($country) $user['country']    = $country;
    $user['ip']         = $ip ?: '';
    $user['user_agent'] = $ua ?: '';
    if ($ttclid) $user['ttclid'] = $ttclid;
    if ($ttp)    $user['ttp']    = $ttp;
    $user['locale'] = $locale;

    $eventData = [
        'event'      => $event,
        'event_time' => time(),
        'event_id'   => $evtId,
        'user'       => $user,
        'properties' => [
            'currency'     => 'BRL',
            'value'        => $value,
            'content_type' => 'product',
            'content_id'   => $contentId,
            'content_name' => $contentName,
            'contents'     => [[
                'content_id'   => $contentId,
                'content_type' => 'product',
                'content_name' => $contentName,
                'quantity'     => 1,
                'price'        => $value,
            ]],
        ],
        'page' => [
            'url'      => $pageUrl ?: ((isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'?'https':'http').'://'.($_SERVER['HTTP_HOST']??'').($_SERVER['REQUEST_URI']??'/')),
            'referrer' => $_SERVER['HTTP_REFERER'] ?? '',
        ],
        'limited_data_use' => false,
    ];

    $payload = [
        'event_source'    => 'web',
        'event_source_id' => $pid,
        'data'            => [$eventData],
    ];
    if ($tec) $payload['test_event_code'] = $tec;

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

    /* TikTok devolve HTTP 200 mesmo em erro — verdadeiro status no JSON (code=0 é sucesso) */
    $parsed = $resp ? json_decode($resp, true) : null;
    $ttCode = $parsed['code']    ?? -1;
    $ttMsg  = $parsed['message'] ?? '';
    $isOk   = ($code >= 200 && $code < 300) && ($ttCode === 0);

    logPixelEvent($event, $pid, [
        'email'       => !empty($hEmail),
        'phone'       => !empty($hPhone),
        'external_id' => !empty($hExtId),
        'ip'          => !empty($ip),
        'user_agent'  => !empty($ua),
        'click_id'    => !empty($ttclid),
    ], $isOk ? (int)$code : 500);

    if (!$isOk) {
        $errorMsg = $err ?: ("HTTP $code · TikTok code=$ttCode · " . substr($ttMsg, 0, 300));
        queueRetry($event, $evtId, $payload, $errorMsg);
    }
}
