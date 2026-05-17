<?php
/**
 * /api/db.php
 * Setup mínimo do SQLite para o sistema de tracking TikTok.
 * Não tem tabelas de checkout/PIX/orders — só o que o tracker e o admin precisam.
 */

if (!date_default_timezone_get() || date_default_timezone_get() === 'UTC') {
    date_default_timezone_set('America/Sao_Paulo');
}

$db = new SQLite3(__DIR__ . '/database.sqlite');
$db->busyTimeout(5000);
$db->exec('PRAGMA journal_mode=WAL');
$db->exec('PRAGMA foreign_keys=ON');

/* ─── settings (key/value para pixel_id, access_token, etc.) ─── */
$db->exec("CREATE TABLE IF NOT EXISTS settings (
    key   TEXT PRIMARY KEY,
    value TEXT DEFAULT ''
)");

/* ─── sessões (Live View opcional) ─── */
$db->exec("CREATE TABLE IF NOT EXISTS sessions (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    session_id   TEXT UNIQUE,
    ip           TEXT DEFAULT '',
    country      TEXT DEFAULT '',
    user_agent   TEXT DEFAULT '',
    utm_source   TEXT DEFAULT '',
    utm_medium   TEXT DEFAULT '',
    utm_campaign TEXT DEFAULT '',
    utm_content  TEXT DEFAULT '',
    utm_term     TEXT DEFAULT '',
    fbc          TEXT DEFAULT '',
    fbp          TEXT DEFAULT '',
    ttclid       TEXT DEFAULT '',
    ttp          TEXT DEFAULT '',
    step         TEXT DEFAULT 'visit',
    page_url     TEXT DEFAULT '',
    created_at   TEXT DEFAULT (datetime('now')),
    last_seen    TEXT DEFAULT (datetime('now'))
)");
$db->exec("CREATE INDEX IF NOT EXISTS idx_sess_last ON sessions(last_seen)");
$db->exec("CREATE INDEX IF NOT EXISTS idx_sess_step ON sessions(step)");

/* ─── eventos de funil (pageview, view_content, initiate_checkout, ...) ─── */
$db->exec("CREATE TABLE IF NOT EXISTS funnel_events (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    session_id  TEXT DEFAULT '',
    event       TEXT NOT NULL,
    value_cents INTEGER DEFAULT 0,
    created_at  TEXT DEFAULT (datetime('now'))
)");
$db->exec("CREATE INDEX IF NOT EXISTS idx_fe_created ON funnel_events(created_at)");
$db->exec("CREATE INDEX IF NOT EXISTS idx_fe_event   ON funnel_events(event)");

/* ─── log de eventos enviados ao TikTok (Pixel Score / debug) ─── */
$db->exec("CREATE TABLE IF NOT EXISTS pixel_events_log (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    event           TEXT NOT NULL,
    pixel_id        TEXT DEFAULT '',
    has_email       INTEGER DEFAULT 0,
    has_phone       INTEGER DEFAULT 0,
    has_external_id INTEGER DEFAULT 0,
    has_ip          INTEGER DEFAULT 0,
    has_user_agent  INTEGER DEFAULT 0,
    has_click_id    INTEGER DEFAULT 0,
    status          INTEGER DEFAULT 0,
    created_at      TEXT DEFAULT (datetime('now'))
)");
$db->exec("CREATE INDEX IF NOT EXISTS idx_pel_event ON pixel_events_log(event, created_at)");

/* ─── fila de retry para eventos que falharam (5xx, timeout) ─── */
$db->exec("CREATE TABLE IF NOT EXISTS pixel_retry_queue (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    event        TEXT NOT NULL,
    event_id     TEXT NOT NULL,
    payload_json TEXT NOT NULL,
    attempts     INTEGER DEFAULT 0,
    last_error   TEXT DEFAULT '',
    next_try_at  TEXT DEFAULT (datetime('now')),
    created_at   TEXT DEFAULT (datetime('now'))
)");
$db->exec("CREATE INDEX IF NOT EXISTS idx_retry_next ON pixel_retry_queue(next_try_at)");
$db->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_retry_eid ON pixel_retry_queue(event_id)");

/* ─── enrichment por sessão (max EMQ — email, phone, ttclid, IP, UA…) ─── */
$db->exec("CREATE TABLE IF NOT EXISTS session_enrichment (
    session_id TEXT PRIMARY KEY,
    email      TEXT DEFAULT '',
    phone      TEXT DEFAULT '',
    first_name TEXT DEFAULT '',
    last_name  TEXT DEFAULT '',
    zip        TEXT DEFAULT '',
    city       TEXT DEFAULT '',
    ttclid     TEXT DEFAULT '',
    ttp        TEXT DEFAULT '',
    fbc        TEXT DEFAULT '',
    fbp        TEXT DEFAULT '',
    ip         TEXT DEFAULT '',
    user_agent TEXT DEFAULT '',
    updated_at TEXT DEFAULT (datetime('now'))
)");

/* ─── dedup global de eventos disparados (client+server mesmo event_id) ─── */
$db->exec("CREATE TABLE IF NOT EXISTS pixel_fired_dedup (
    event    TEXT NOT NULL,
    event_id TEXT NOT NULL,
    fired_at TEXT NOT NULL DEFAULT (datetime('now')),
    PRIMARY KEY (event, event_id)
)");

/* ─── credenciais do admin (defaults: admin / admin) ─── */
function getSetting($key, $default = '') {
    global $db;
    $stmt = $db->prepare("SELECT value FROM settings WHERE key = :k");
    if (!$stmt) return $default;
    $stmt->bindValue(':k', $key, SQLITE3_TEXT);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    return ($row && isset($row['value'])) ? $row['value'] : $default;
}

function setSetting($key, $value) {
    global $db;
    $stmt = $db->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES (:k, :v)");
    if (!$stmt) return false;
    $stmt->bindValue(':k', $key, SQLITE3_TEXT);
    $stmt->bindValue(':v', (string)$value, SQLITE3_TEXT);
    return $stmt->execute();
}

if (!getSetting('admin_pass')) {
    setSetting('admin_user', 'admin');
    setSetting('admin_pass', password_hash('admin', PASSWORD_DEFAULT));
}
