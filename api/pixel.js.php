<?php
/**
 * /api/pixel.js.php
 * Serve o snippet base do TikTok Pixel (Advanced Matching via ttq.identify).
 *
 * Uso (em qualquer página):
 *   <script src="/api/pixel.js.php" async></script>
 *   <script src="/api/tracking.js.php" async></script>
 */
require_once __DIR__ . '/db.php';

header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: public, max-age=300');

$pixelId     = trim(getSetting('tt_pixel_id'));
$contentId   = trim(getSetting('tt_content_id',   'cliente'));
$contentName = trim(getSetting('tt_content_name', 'cliente'));
$amount      = floatval(getSetting('tt_pixel_event_value', '0'));

if ($pixelId === '') {
    echo "/* TikTok Pixel: sem ID configurado em /pixel/ */\n";
    echo "window.TT_PIXEL_READY = false;\n";
    echo "window.ttq = window.ttq || { track: function(){}, page: function(){}, identify: function(){}, instance: function(){ return { track: function(){}, page: function(){} }; } };\n";
    exit;
}

$pid = json_encode($pixelId);
$cid = json_encode($contentId);
$cnm = json_encode($contentName);
?>
/* TikTok Pixel — carregado via /api/pixel.js.php (com Advanced Matching) */
!function (w, d, t) {
  w.TiktokAnalyticsObject = t;
  var ttq = w[t] = w[t] || [];
  ttq.methods = ["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie","holdConsent","revokeConsent","grantConsent"];
  ttq.setAndDefer = function (t, e) { t[e] = function () { t.push([e].concat(Array.prototype.slice.call(arguments, 0))); }; };
  for (var i = 0; i < ttq.methods.length; i++) ttq.setAndDefer(ttq, ttq.methods[i]);
  ttq.instance = function (t) {
    for (var e = ttq._i[t] || [], n = 0; n < ttq.methods.length; n++) ttq.setAndDefer(e, ttq.methods[n]);
    return e;
  };
  ttq.load = function (e, n) {
    var r = "https://analytics.tiktok.com/i18n/pixel/events.js";
    var o = n && n.partner;
    ttq._i = ttq._i || {}; ttq._i[e] = []; ttq._i[e]._u = r;
    ttq._t = ttq._t || {}; ttq._t[e] = +new Date();
    ttq._o = ttq._o || {}; ttq._o[e] = n || {};
    var s = document.createElement("script");
    s.type = "text/javascript"; s.async = !0;
    s.src = r + "?sdkid=" + e + "&lib=" + t;
    var a = document.getElementsByTagName("script")[0];
    a.parentNode.insertBefore(s, a);
  };

  ttq.load(<?= $pid ?>);

  // ── Advanced Matching: ttq.identify ANTES de ttq.page ──
  // Lê email/phone que o tracking.js persistiu em localStorage (key: ptracker_cd)
  try {
    var cd = {};
    try { cd = JSON.parse(localStorage.getItem('ptracker_cd')||'{}'); } catch(e){}
    var id = {};
    if (cd.email) { id.email = cd.email; id.external_id = cd.email; }
    if (cd.phone) { id.phone_number = cd.phone; }
    if (Object.keys(id).length) ttq.identify(id);
  } catch(e){}

  // ── PageView com content_id/content_name ──
  ttq.page({
    content_id:   <?= $cid ?>,
    content_name: <?= $cnm ?>,
    content_type: 'product',
    currency:     'BRL',
    value:        <?= json_encode($amount) ?>
  });

  w.TT_PIXEL_READY = true;
  // Event ID para dedup com server-side fire-tt-event
  w.__ptracker_pv_id = 'pv_' + (localStorage.getItem('ptracker_sid') || 'anon') + '_' + Math.floor(Date.now()/60000);
}(window, document, 'ttq');
