<?php
/**
 * /api/enrich.php
 * Recebe dados parciais do cliente (email, phone, ttclid, etc.) do frontend e
 * persiste em session_enrichment para enriquecer TODOS os eventos seguintes.
 *
 * Chamado pelo tracking.js sempre que o cliente preenche email/phone num form.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/pixel_fire.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$raw = file_get_contents('php://input');
$in  = json_decode($raw, true) ?: $_POST;

$sid = trim((string)($in['session_id'] ?? ''));
if (!$sid) {
    echo json_encode(['ok' => false, 'error' => 'missing session_id']);
    exit;
}

$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
if (strpos($ip, ',') !== false) $ip = trim(explode(',', $ip)[0]);
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

$fields = [
    'email'      => trim((string)($in['email']      ?? '')),
    'phone'      => trim((string)($in['phone']      ?? '')),
    'first_name' => trim((string)($in['first_name'] ?? '')),
    'last_name'  => trim((string)($in['last_name']  ?? '')),
    'zip'        => trim((string)($in['zip']        ?? '')),
    'city'       => trim((string)($in['city']       ?? '')),
    'ttclid'     => trim((string)($in['ttclid']     ?? '')),
    'ttp'        => trim((string)($in['ttp']        ?? '')),
    'fbc'        => trim((string)($in['fbc']        ?? '')),
    'fbp'        => trim((string)($in['fbp']        ?? '')),
    'ip'         => $ip,
    'user_agent' => $ua,
];

saveSessionEnrichment($sid, $fields);

echo json_encode(['ok' => true]);
