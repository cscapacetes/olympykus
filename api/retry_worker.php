<?php
/**
 * /api/retry_worker.php
 * Reenvia eventos TikTok que falharam (5xx, timeout, etc.).
 *
 * Uso recomendado (cron a cada 1-2 min):
 *   * * * * * curl -s "https://seudominio.com/api/retry_worker.php?secret=XYZ" >/dev/null
 *
 * Ou manualmente do admin (logado).
 *
 * Backoff: 60s, 5min, 30min, 2h, 6h, 24h. Após 6 tentativas, desiste.
 */
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

session_start();
$secret      = getSetting('retry_worker_secret', '');
$passSecret  = ($_GET['secret'] ?? '') === $secret && $secret !== '';
$passSession = !empty($_SESSION['ptracker_logged']);
if (!$passSecret && !$passSession) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'forbidden']);
    exit;
}

$MAX_ATTEMPTS = 6;
$BACKOFF = [0, 60, 300, 1800, 7200, 21600, 86400];

$processed = 0; $succeeded = 0; $failed = 0; $dropped = 0; $details = [];

$r = $db->query("SELECT * FROM pixel_retry_queue
                 WHERE next_try_at <= datetime('now')
                 ORDER BY next_try_at ASC
                 LIMIT 20");

while ($row = $r->fetchArray(SQLITE3_ASSOC)) {
    $processed++;

    if ((int)$row['attempts'] >= $MAX_ATTEMPTS) {
        $db->exec("DELETE FROM pixel_retry_queue WHERE id = " . (int)$row['id']);
        $dropped++;
        $details[] = ['event_id' => $row['event_id'], 'status' => 'dropped (max attempts)'];
        continue;
    }

    $payload = json_decode($row['payload_json'], true);
    if (!$payload) {
        $db->exec("DELETE FROM pixel_retry_queue WHERE id = " . (int)$row['id']);
        $dropped++;
        continue;
    }

    $tok = getSetting('tt_access_token');
    $headers = ['Content-Type: application/json', 'Access-Token: ' . $tok];

    $ch = curl_init('https://business-api.tiktok.com/open_api/v1.3/event/track/');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    $parsed = $resp ? json_decode($resp, true) : null;
    $ok     = ($code >= 200 && $code < 300);
    $errMsg = '';
    if ($ok) {
        $ttCode = $parsed['code'] ?? -1;
        if ($ttCode !== 0) {
            $ok = false;
            $errMsg = "TikTok code=$ttCode · " . ($parsed['message'] ?? '');
        }
    }

    if ($ok) {
        $db->exec("DELETE FROM pixel_retry_queue WHERE id = " . (int)$row['id']);
        $succeeded++;
        $details[] = ['event_id' => $row['event_id'], 'event' => $row['event'], 'status' => 'sent ('.$code.')'];
    } else {
        $nextAttempts = (int)$row['attempts'] + 1;
        $delay = $BACKOFF[min($nextAttempts, count($BACKOFF)-1)];
        $stmt = $db->prepare("UPDATE pixel_retry_queue
                              SET attempts = :a, last_error = :err, next_try_at = datetime('now', '+' || :d || ' seconds')
                              WHERE id = :id");
        $stmt->bindValue(':a',   $nextAttempts, SQLITE3_INTEGER);
        $stmt->bindValue(':err', substr($err ?: $errMsg ?: ('HTTP ' . $code), 0, 500), SQLITE3_TEXT);
        $stmt->bindValue(':d',   $delay, SQLITE3_INTEGER);
        $stmt->bindValue(':id',  (int)$row['id'], SQLITE3_INTEGER);
        $stmt->execute();
        $failed++;
        $details[] = ['event_id' => $row['event_id'], 'event' => $row['event'], 'status' => 'failed ('.$code.') ' . substr($errMsg, 0, 80) . ' retry in '.$delay.'s'];
    }
}

echo json_encode([
    'ok'        => true,
    'processed' => $processed,
    'succeeded' => $succeeded,
    'failed'    => $failed,
    'dropped'   => $dropped,
    'details'   => $details,
]);
