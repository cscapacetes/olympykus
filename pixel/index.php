<?php
/**
 * /pixel/index.php
 * Painel mínimo do tracker — só duas coisas: Pixel ID + Access Token.
 *
 * Login default: admin / admin (muda no primeiro acesso).
 */
session_start();
require_once __DIR__ . '/../api/db.php';

/* ─── Logout ─── */
if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: index.php');
    exit;
}

/* ─── Login ─── */
$loginErr = '';
if (!empty($_POST['__login'])) {
    $u = trim($_POST['user'] ?? '');
    $p = trim($_POST['pass'] ?? '');
    if ($u === getSetting('admin_user', 'admin') &&
        password_verify($p, getSetting('admin_pass'))) {
        $_SESSION['ptracker_logged'] = true;
        header('Location: index.php');
        exit;
    }
    $loginErr = 'Credenciais inválidas.';
}

if (empty($_SESSION['ptracker_logged'])):
?>
<!doctype html><html><head>
<meta charset="utf-8"><title>Login · Pixel TikTok</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
  *{box-sizing:border-box}
  body{margin:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#0f172a;color:#e2e8f0;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px}
  .card{background:#1e293b;padding:32px;border-radius:12px;width:100%;max-width:380px;box-shadow:0 10px 40px rgba(0,0,0,.4)}
  h1{margin:0 0 8px;font-size:22px;font-weight:700}
  .sub{color:#94a3b8;font-size:13px;margin:0 0 24px}
  label{display:block;margin:14px 0 6px;font-size:13px;font-weight:500;color:#cbd5e1}
  input{width:100%;padding:10px 12px;background:#0f172a;border:1px solid #334155;color:#e2e8f0;border-radius:7px;font-size:14px;font-family:inherit}
  input:focus{outline:none;border-color:#3b82f6}
  button{width:100%;margin-top:20px;padding:11px;background:#3b82f6;color:#fff;border:none;border-radius:7px;font-size:14px;font-weight:600;cursor:pointer;font-family:inherit}
  button:hover{background:#2563eb}
  .err{background:#7f1d1d;color:#fecaca;padding:10px 12px;border-radius:7px;font-size:13px;margin-bottom:14px}
</style>
</head><body>
<form method="POST" class="card">
  <h1>🎯 Pixel TikTok</h1>
  <p class="sub">Painel de configuração do tracker.</p>
  <?php if ($loginErr): ?><div class="err"><?= htmlspecialchars($loginErr) ?></div><?php endif; ?>
  <label>Utilizador</label>
  <input type="text" name="user" autocomplete="username" required>
  <label>Senha</label>
  <input type="password" name="pass" autocomplete="current-password" required>
  <input type="hidden" name="__login" value="1">
  <button type="submit">Entrar</button>
</form>
</body></html>
<?php
exit;
endif;

/* ─── Painel logado ─── */

$msg = '';
$msgType = 'ok';

/* Salvar Pixel ID + Access Token */
if (!empty($_POST['__save_pixel'])) {
    $pid = trim($_POST['tt_pixel_id'] ?? '');
    $tok = trim($_POST['tt_access_token'] ?? '');
    $tec = trim($_POST['tt_test_event_code'] ?? '');
    setSetting('tt_pixel_id',        $pid);
    setSetting('tt_access_token',    $tok);
    setSetting('tt_test_event_code', $tec);
    $msg = '✅ Pixel guardado. Os próximos eventos já usam estas credenciais.';
}

/* Mudar senha */
if (!empty($_POST['__change_pass'])) {
    $cur = $_POST['cur_pass'] ?? '';
    $new = $_POST['new_pass'] ?? '';
    if (!password_verify($cur, getSetting('admin_pass'))) {
        $msg = '❌ Senha atual incorreta.'; $msgType = 'err';
    } elseif (strlen($new) < 6) {
        $msg = '❌ Nova senha precisa ter pelo menos 6 caracteres.'; $msgType = 'err';
    } else {
        setSetting('admin_pass', password_hash($new, PASSWORD_DEFAULT));
        $msg = '✅ Senha atualizada.';
    }
}

/* Limpar fila de retry */
if (!empty($_POST['__clear_retry'])) {
    $db->exec("DELETE FROM pixel_retry_queue");
    $msg = '✅ Fila de retry limpa.';
}

/* Configurações avançadas */
if (!empty($_POST['__save_advanced'])) {
    setSetting('tt_content_id',         trim($_POST['tt_content_id'] ?? 'cliente'));
    setSetting('tt_content_name',       trim($_POST['tt_content_name'] ?? 'cliente'));
    setSetting('tt_pixel_event_value',  str_replace(',', '.', trim($_POST['tt_pixel_event_value'] ?? '19.90')));
    setSetting('pixel_country',         strtolower(trim($_POST['pixel_country'] ?? 'br')));
    $msg = '✅ Configurações avançadas guardadas.';
}

/* Estatísticas básicas */
$pid = getSetting('tt_pixel_id');
$tok = getSetting('tt_access_token');
$tec = getSetting('tt_test_event_code');

$totalEvents = (int)$db->querySingle("SELECT COUNT(*) FROM pixel_events_log WHERE created_at >= datetime('now','-24 hours')");
$okEvents    = (int)$db->querySingle("SELECT COUNT(*) FROM pixel_events_log WHERE status BETWEEN 200 AND 299 AND created_at >= datetime('now','-24 hours')");
$errEvents   = $totalEvents - $okEvents;
$retryCount  = (int)$db->querySingle("SELECT COUNT(*) FROM pixel_retry_queue");
$activeSessions = (int)$db->querySingle("SELECT COUNT(*) FROM sessions WHERE last_seen >= datetime('now','-5 minutes')");

/* Eventos recentes (top 10) */
$recentEvents = [];
$r = $db->query("SELECT event, pixel_id, status, has_email, has_phone, has_external_id, has_ip, has_user_agent, has_click_id, created_at
                 FROM pixel_events_log
                 ORDER BY id DESC LIMIT 10");
while ($row = $r->fetchArray(SQLITE3_ASSOC)) $recentEvents[] = $row;
?>
<!doctype html><html><head>
<meta charset="utf-8"><title>Pixel TikTok · Configuração</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
  *{box-sizing:border-box}
  body{margin:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#0f172a;color:#e2e8f0;min-height:100vh}
  .top{background:#1e293b;border-bottom:1px solid #334155;padding:14px 24px;display:flex;justify-content:space-between;align-items:center}
  .top h1{margin:0;font-size:16px;font-weight:700}
  .top a{color:#94a3b8;text-decoration:none;font-size:13px}
  .top a:hover{color:#e2e8f0}
  .wrap{max-width:780px;margin:24px auto;padding:0 20px}
  .card{background:#1e293b;border:1px solid #334155;border-radius:10px;padding:20px;margin-bottom:18px}
  .card h2{margin:0 0 6px;font-size:15px;font-weight:600}
  .card .desc{color:#94a3b8;font-size:13px;margin:0 0 16px;line-height:1.5}
  label{display:block;margin:14px 0 6px;font-size:12px;font-weight:600;color:#cbd5e1;text-transform:uppercase;letter-spacing:.4px}
  input,textarea{width:100%;padding:10px 12px;background:#0f172a;border:1px solid #334155;color:#e2e8f0;border-radius:7px;font-size:14px;font-family:inherit}
  textarea{font-family:ui-monospace,Menlo,monospace;font-size:12px;resize:vertical}
  input:focus,textarea:focus{outline:none;border-color:#3b82f6}
  .row{display:grid;grid-template-columns:1fr 1fr;gap:14px}
  @media(max-width:600px){.row{grid-template-columns:1fr}}
  .btn{display:inline-block;padding:10px 18px;background:#3b82f6;color:#fff;border:none;border-radius:7px;font-size:13px;font-weight:600;cursor:pointer;font-family:inherit}
  .btn:hover{background:#2563eb}
  .btn-sec{background:#334155}
  .btn-sec:hover{background:#475569}
  .btn-danger{background:#7f1d1d}
  .btn-danger:hover{background:#991b1b}
  .msg{padding:11px 14px;border-radius:7px;margin-bottom:18px;font-size:13px}
  .msg-ok{background:#064e3b;color:#a7f3d0}
  .msg-err{background:#7f1d1d;color:#fecaca}
  .stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:18px}
  @media(max-width:600px){.stats{grid-template-columns:repeat(2,1fr)}}
  .stat{background:#1e293b;border:1px solid #334155;border-radius:10px;padding:14px;text-align:center}
  .stat .n{font-size:22px;font-weight:700;color:#3b82f6}
  .stat .l{font-size:11px;color:#94a3b8;text-transform:uppercase;letter-spacing:.4px;margin-top:2px}
  .pill{display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600}
  .pill-ok{background:#064e3b;color:#a7f3d0}
  .pill-err{background:#7f1d1d;color:#fecaca}
  table{width:100%;border-collapse:collapse;font-size:12px}
  th,td{padding:8px 10px;text-align:left;border-bottom:1px solid #334155}
  th{color:#94a3b8;font-weight:500;text-transform:uppercase;letter-spacing:.4px;font-size:11px}
  td{color:#cbd5e1}
  .test-result{margin-top:12px;padding:12px;background:#0f172a;border-radius:7px;font-family:ui-monospace,Menlo,monospace;font-size:11px;white-space:pre-wrap;max-height:300px;overflow:auto}
  .info{font-size:12px;color:#94a3b8;line-height:1.6;margin-top:8px}
  .info code{background:#0f172a;padding:2px 6px;border-radius:4px;font-size:11px}
  details summary{cursor:pointer;font-size:13px;font-weight:600;color:#cbd5e1;padding:6px 0}
</style>
</head><body>

<div class="top">
  <h1>🎯 Pixel TikTok · Painel</h1>
  <a href="?logout=1">Sair →</a>
</div>

<div class="wrap">

<?php if ($msg): ?>
  <div class="msg msg-<?= $msgType === 'err' ? 'err' : 'ok' ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<!-- Status atual -->
<div class="stats">
  <div class="stat">
    <div class="n"><?= $pid ? '🟢' : '🔴' ?></div>
    <div class="l"><?= $pid ? 'Configurado' : 'Sem pixel' ?></div>
  </div>
  <div class="stat">
    <div class="n"><?= $totalEvents ?></div>
    <div class="l">Eventos 24h</div>
  </div>
  <div class="stat">
    <div class="n" style="color:<?= $errEvents > 0 ? '#f87171' : '#10b981' ?>"><?= $errEvents ?></div>
    <div class="l">Falharam</div>
  </div>
  <div class="stat">
    <div class="n" style="color:#10b981"><?= $activeSessions ?></div>
    <div class="l">Online agora</div>
  </div>
</div>

<!-- Configuração principal: Pixel ID + Access Token -->
<form method="POST" class="card">
  <h2>Credenciais TikTok Events API</h2>
  <p class="desc">Cole aqui o <strong>Pixel ID</strong> e o <strong>Access Token</strong> do TikTok Events Manager. Estes dois valores são tudo o que o sistema precisa para começar a disparar eventos automaticamente.</p>

  <label>Pixel ID</label>
  <input type="text" name="tt_pixel_id" value="<?= htmlspecialchars($pid) ?>" placeholder="CXXXXXXXXXXXXXXXXX" required>

  <label>Access Token</label>
  <textarea name="tt_access_token" rows="3" placeholder="Cole aqui o access_token (Events API)"><?= htmlspecialchars($tok) ?></textarea>

  <label>Test Event Code <span style="color:#64748b;font-weight:400;text-transform:none;letter-spacing:0">(opcional, para validar no Events Manager)</span></label>
  <input type="text" name="tt_test_event_code" value="<?= htmlspecialchars($tec) ?>" placeholder="TEST12345">

  <input type="hidden" name="__save_pixel" value="1">
  <button type="submit" class="btn" style="margin-top:20px">💾 Guardar credenciais</button>
</form>

<!-- Como usar -->
<div class="card">
  <h2>Como instalar nas páginas</h2>
  <p class="desc">Em qualquer página onde queiras tracking, adiciona estas duas linhas no <code>&lt;head&gt;</code>:</p>
  <div class="test-result" style="margin-top:0">&lt;script src="/api/pixel.js.php" async&gt;&lt;/script&gt;
&lt;script src="/api/tracking.js.php" async&gt;&lt;/script&gt;</div>
  <p class="info">
    <strong>Eventos que o sistema dispara automaticamente:</strong><br>
    • <code>PageView</code> — em todas as páginas (no load)<br>
    • <code>ViewContent</code>, <code>LandingPageView</code>, <code>EngagedSession</code> — quando o user clica num botão de ação (COMPRAR, PARTICIPAR, etc.)<br>
    • <code>InitiateCheckout</code> — quando entra numa página com <code>/checkout</code> ou <code>/pix</code> na URL<br>
    • <code>AddPaymentInfo</code> — quando clica em "Pagar" / "Finalizar" / submete o form<br>
    • <code>Purchase</code> — a página de obrigado deve chamar <code>window.__ptracker_fire_tt('Purchase', eventId, value)</code> inline
  </p>
</div>

<!-- Teste de evento -->
<div class="card">
  <h2>Testar envio</h2>
  <p class="desc">Envia um evento de teste para validar a configuração. Com Test Event Code configurado, o evento aparece em <em>Events Manager → Test Events</em>.</p>
  <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:8px">
    <button type="button" class="btn btn-sec" onclick="testEvent('PageView')">PageView</button>
    <button type="button" class="btn btn-sec" onclick="testEvent('ViewContent')">ViewContent</button>
    <button type="button" class="btn btn-sec" onclick="testEvent('InitiateCheckout')">InitiateCheckout</button>
    <button type="button" class="btn btn-sec" onclick="testEvent('AddPaymentInfo')">AddPaymentInfo</button>
    <button type="button" class="btn btn-sec" onclick="testEvent('Purchase')">Purchase</button>
  </div>
  <div id="testResult"></div>
</div>

<!-- Eventos recentes -->
<?php if (!empty($recentEvents)): ?>
<div class="card">
  <h2>Últimos 10 eventos enviados</h2>
  <table>
    <thead><tr><th>Evento</th><th>Status</th><th>Email</th><th>Phone</th><th>ExtID</th><th>IP</th><th>UA</th><th>ClickID</th><th>Quando</th></tr></thead>
    <tbody>
      <?php foreach ($recentEvents as $e): ?>
        <tr>
          <td><strong><?= htmlspecialchars($e['event']) ?></strong></td>
          <td><span class="pill <?= ($e['status'] >= 200 && $e['status'] < 300) ? 'pill-ok' : 'pill-err' ?>"><?= (int)$e['status'] ?></span></td>
          <td><?= $e['has_email'] ? '✓' : '—' ?></td>
          <td><?= $e['has_phone'] ? '✓' : '—' ?></td>
          <td><?= $e['has_external_id'] ? '✓' : '—' ?></td>
          <td><?= $e['has_ip'] ? '✓' : '—' ?></td>
          <td><?= $e['has_user_agent'] ? '✓' : '—' ?></td>
          <td><?= $e['has_click_id'] ? '✓' : '—' ?></td>
          <td style="color:#64748b"><?= htmlspecialchars($e['created_at']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<!-- Manutenção -->
<details>
  <summary>⚙️ Manutenção e segurança</summary>

  <form method="POST" class="card" style="margin-top:14px">
    <h2>Mudar senha do painel</h2>
    <p class="desc">Por favor mude a senha default <code>admin/admin</code> no primeiro acesso.</p>
    <div class="row">
      <div>
        <label>Senha atual</label>
        <input type="password" name="cur_pass" required>
      </div>
      <div>
        <label>Nova senha (mín. 6 chars)</label>
        <input type="password" name="new_pass" minlength="6" required>
      </div>
    </div>
    <input type="hidden" name="__change_pass" value="1">
    <button type="submit" class="btn" style="margin-top:18px">🔒 Atualizar senha</button>
  </form>

  <div class="card">
    <h2>Fila de retry</h2>
    <p class="desc">Eventos que falharam e estão a ser reenviados pelo <code>retry_worker.php</code>. Atualmente <strong><?= $retryCount ?></strong> na fila.</p>
    <form method="POST" style="display:inline" onsubmit="return confirm('Apagar todos os eventos na fila de retry?')">
      <input type="hidden" name="__clear_retry" value="1">
      <button type="submit" class="btn btn-danger">🗑 Limpar fila</button>
    </form>
    <p class="info">Configura um cron para processar a fila automaticamente:<br>
    <code>* * * * * curl -s "https://teudominio.com/api/retry_worker.php?secret=XXX" >/dev/null</code></p>
  </div>

  <div class="card">
    <h2>Configurações avançadas (opcionais)</h2>
    <form method="POST">
      <div class="row">
        <div>
          <label>Content ID <span style="color:#64748b;font-weight:400;text-transform:none;letter-spacing:0">(SKU)</span></label>
          <input type="text" name="tt_content_id" value="<?= htmlspecialchars(getSetting('tt_content_id', 'cliente')) ?>">
        </div>
        <div>
          <label>Content Name</label>
          <input type="text" name="tt_content_name" value="<?= htmlspecialchars(getSetting('tt_content_name', 'cliente')) ?>">
        </div>
      </div>
      <div class="row">
        <div>
          <label>Valor padrão (BRL)</label>
          <input type="number" step="0.01" min="0" name="tt_pixel_event_value" value="<?= htmlspecialchars(getSetting('tt_pixel_event_value', '19.90')) ?>">
        </div>
        <div>
          <label>País (ISO-2, lowercase)</label>
          <input type="text" name="pixel_country" value="<?= htmlspecialchars(getSetting('pixel_country', 'br')) ?>" maxlength="2">
        </div>
      </div>
      <input type="hidden" name="__save_advanced" value="1">
      <button type="submit" class="btn" style="margin-top:14px">💾 Guardar</button>
    </form>
  </div>
</details>

</div>

<script>
async function testEvent(event){
  const out = document.getElementById('testResult');
  out.innerHTML = '<div class="test-result">A enviar evento ' + event + '...</div>';
  try {
    const r = await fetch('/api/pixel_test.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ event: event, use_test_code: true })
    });
    const d = await r.json();
    out.innerHTML = '<div class="test-result">' +
      (d.ok ? '✅ ' : '❌ ') + 'Resposta: \n' +
      JSON.stringify(d, null, 2) +
      '</div>';
  } catch(e) {
    out.innerHTML = '<div class="test-result">❌ Erro: ' + e.message + '</div>';
  }
}
</script>

</body></html>

