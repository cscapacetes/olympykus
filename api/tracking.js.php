<?php
/**
 * /api/tracking.js.php
 * Snippet cliente do tracker — integração TikTok com EMQ alto.
 *
 * O que faz no boot:
 *   1) Cria session_id persistente (localStorage + cookie, 30d)
 *   2) Captura UTMs + ttclid + ttp + fbc + fbp
 *   3) Intercepta inputs de email/phone em qualquer form (também SPA via MutationObserver)
 *   4) Persiste dados do cliente em localStorage e envia para /api/enrich.php
 *   5) ttq.identify() com email/phone/external_id ANTES de ttq.page()
 *   6) Envia pageview + heartbeats para /api/tracking.php (Live View do admin)
 *   7) Dispara PageView server-side via /api/events.php (resolve EMQ 0% no PageView inicial)
 *   8) Auto-detecta cliques de CTA → ViewContent → InitiateCheckout → AddPaymentInfo
 *
 * Eventos disparados automaticamente no checkout (via auto-detect):
 *   • landing/index   → PageView, ViewContent (no clique de CTA), LandingPageView, EngagedSession
 *   • /checkout, /pix → PageView + InitiateCheckout (no load) + AddPaymentInfo (no clique submit)
 *   • outras páginas  → PageView
 *
 * Para Purchase, o pixel.js inline na página de obrigado/sucesso deve chamar:
 *   window.__ptracker_fire_tt('Purchase', purchaseEventId, value)
 */
header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: public, max-age=300');
?>
(function(){
  'use strict';
  var BASE = location.origin;

  function getCookie(n){try{var m=document.cookie.match(new RegExp('(?:^|; )'+n+'=([^;]*)'));return m?decodeURIComponent(m[1]):'';}catch(e){return '';}}
  function setCookie(n,v,d){try{var e=new Date(Date.now()+d*864e5).toUTCString();document.cookie=n+'='+encodeURIComponent(v)+';expires='+e+';path=/;SameSite=Lax';}catch(e){}}
  function getParam(n){return new URLSearchParams(location.search).get(n)||'';}
  function uuid(){return (crypto&&crypto.randomUUID?crypto.randomUUID():('xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g,function(c){var r=Math.random()*16|0,v=c==='x'?r:(r&0x3|0x8);return v.toString(16);})));}
  function lsGet(k){try{return localStorage.getItem(k)||'';}catch(e){return '';}}
  function lsSet(k,v){try{localStorage.setItem(k,v);}catch(e){}}
  function lsJson(k){try{return JSON.parse(lsGet(k)||'{}');}catch(e){return {};}}
  function qVal(sel){var el=document.querySelector(sel);return el?String(el.value||'').trim():'';}
  function hasAttr(name){return !!((document.documentElement&&document.documentElement.hasAttribute(name))||(document.body&&document.body.hasAttribute(name)));}
  function normalizePhone(v){
    if(!v) return '';
    var raw = String(v).trim();
    var hasPlus = raw.charAt(0) === '+';
    var digits = raw.replace(/[^0-9]/g,'');
    if(!digits) return '';
    if(!hasPlus){
      if(digits.length === 10 || digits.length === 11) digits = '55' + digits;
      else if(digits.length === 9 && digits.charAt(0) === '9') digits = '351' + digits;
    }
    if(digits.length < 8 || digits.length > 15) return '';
    return '+' + digits;
  }
  function splitName(v){
    var cleaned = String(v||'').trim().replace(/\s+/g,' ');
    if(!cleaned) return { first_name:'', last_name:'' };
    var parts = cleaned.split(' ');
    return { first_name: parts[0]||'', last_name: parts.length > 1 ? parts[parts.length - 1] : '' };
  }
  function refreshCheckoutClientData(){
    var checkout = lsJson('checkout_form_data');
    var address  = lsJson('userAddress');
    var email    = (qVal('#email') || checkout.email || address.email || clientData.email || '').trim().toLowerCase();
    var phone    = normalizePhone(qVal('#telephone') || checkout.phone || address.telefone || clientData.phone || '');
    var fullName = qVal('#name') || qVal('#receiver_name') || checkout.name || checkout.receiver_name || address.nome || '';
    var zip      = qVal('#zip_code') || checkout.cep || address.cep || clientData.zip || '';
    var city     = qVal('#city') || checkout.city || address.cidade || clientData.city || '';
    var names    = splitName(fullName);
    var next     = Object.assign({}, clientData);
    if(email && /^[a-z0-9._+-]+@[a-z0-9.-]+\.[a-z]{2,}$/i.test(email)) next.email = email;
    if(phone) next.phone = phone;
    if(names.first_name) next.first_name = names.first_name;
    if(names.last_name)  next.last_name  = names.last_name;
    if(zip)  next.zip  = zip;
    if(city) next.city = city;
    clientData = next;
    lsSet('ptracker_cd', JSON.stringify(clientData));
  }

  // ── session_id persistente (localStorage + cookie) ──
  var sid = lsGet('ptracker_sid') || getCookie('ptracker_sid');
  if(!sid){ sid = uuid(); lsSet('ptracker_sid', sid); setCookie('ptracker_sid', sid, 30); }
  window.__ptracker_session_id = sid;

  // ── Captura UTMs + click IDs (preserva entre páginas) ──
  var stored = {}; try{ stored = JSON.parse(lsGet('ptracker_utms')||'{}'); }catch(e){}
  var ctx = {
    session_id:   sid,
    page_url:     location.href,
    utm_source:   getParam('utm_source')   || stored.utm_source   || '',
    utm_medium:   getParam('utm_medium')   || stored.utm_medium   || '',
    utm_campaign: getParam('utm_campaign') || stored.utm_campaign || '',
    utm_content:  getParam('utm_content')  || stored.utm_content  || '',
    utm_term:     getParam('utm_term')     || stored.utm_term     || '',
    ttclid:       getParam('ttclid')       || stored.ttclid       || getCookie('_ttclid') || '',
    fbc:          getCookie('_fbc')        || stored.fbc          || '',
    fbp:          getCookie('_fbp')        || stored.fbp          || '',
    ttp:          getCookie('_ttp')        || getCookie('ttp')    || stored.ttp || ''
  };
  var clientData = {};
  try { clientData = JSON.parse(lsGet('ptracker_cd')||'{}'); } catch(e){ clientData = {}; }
  if (!clientData.email) clientData.email = '';
  if (!clientData.phone) clientData.phone = '';
  try{ lsSet('ptracker_utms', JSON.stringify(ctx)); }catch(e){}

  // ── tracking interno (Live View + funil no admin) ──
  function trackInternal(event, extra){
    var payload = Object.assign({}, ctx, extra||{}, {event: event, page_url: location.href});
    try {
      var blob = new Blob([JSON.stringify(payload)], {type:'application/json'});
      if (navigator.sendBeacon && event === 'heartbeat') {
        navigator.sendBeacon(BASE + '/api/tracking.php', blob); return;
      }
      fetch(BASE + '/api/tracking.php', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload),keepalive:true}).catch(function(){});
    } catch(e){}
  }
  window.__ptracker_track = trackInternal;

  // ── disparar evento TikTok server-side (via /api/events.php) ──
  function fireTTEvent(ttEvent, eventId, value){
    try { clientData = JSON.parse(lsGet('ptracker_cd')||'{}'); } catch(e){}
    refreshCheckoutClientData();
    tiktokIdentify(); // re-identifica client-side antes de cada evento

    var body = {
      session_id: sid,
      event:    ttEvent,
      event_id: eventId || (ttEvent + '_' + sid + '_' + Date.now()),
      page_url: location.href,
      email:    clientData.email || '',
      phone:    clientData.phone || '',
      // external_id cascade: email → sid (sempre presente)
      external_id: clientData.email || sid,
      first_name: clientData.first_name || '',
      last_name:  clientData.last_name  || '',
      ttclid:   ctx.ttclid || '',
      ttp:      ctx.ttp    || '',
      fbc:      ctx.fbc    || '',
      fbp:      ctx.fbp    || ''
    };
    if (typeof value === 'number') body.value = value;
    try {
      fetch(BASE + '/api/events.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify(body), keepalive: true
      }).catch(function(){});
    } catch(e){}
  }
  window.__ptracker_fire_tt = fireTTEvent;

  // ── ttq.identify (Advanced Matching) ──
  function tiktokIdentify(){
    if (typeof window.ttq === 'undefined' || !window.ttq.identify) return;
    var id = { external_id: clientData.email || sid };
    if (clientData.email) id.email = clientData.email;
    if (clientData.phone) id.phone_number = clientData.phone;
    try { window.ttq.identify(id); } catch(e){}
  }

  // ── envia enrichment (IP, UA, ttclid, email, phone) para o servidor ──
  function sendEnrich(){
    var body = Object.assign({session_id: sid}, clientData, {
      ttclid: ctx.ttclid, ttp: ctx.ttp, fbc: ctx.fbc, fbp: ctx.fbp
    });
    try {
      fetch(BASE + '/api/enrich.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify(body), keepalive: true
      }).catch(function(){});
    } catch(e){}
  }
  window.__ptracker_enrich = sendEnrich;

  // ── captura email/phone em qualquer form (suporta React/SPA) ──
  var _capturedEls = new WeakSet();
  function bindFormCapture(){
    var emailSelectors = 'input[type=email], input[name*=email], input[id*=email]';
    var phoneSelectors = 'input[type=tel], input[name*=phone], input[id*=phone], input[name*=telemovel], input[name*=telefone]';
    var postalSel      = 'input[name*=postal], input[name*=cep], input[id*=postal], input[id*=cep]';
    var citySel        = 'input[name*=city], input[name*=cidade], input[id*=city], input[id*=cidade]';
    var nameSel        = 'input[name*=name], input[name*=nome], input[id*=name], input[id*=nome]';

    function once(el, fn){
      if (_capturedEls.has(el)) return;
      _capturedEls.add(el);
      el.addEventListener('blur', fn);
      el.addEventListener('change', fn);
    }

    document.querySelectorAll(emailSelectors).forEach(function(el){
      once(el, function(){
        var v = (el.value||'').trim().toLowerCase();
        // Validação estrita (precisa TLD) — evita "Email not valid" no TikTok com inputs parciais
        var emailRegex = /^[a-z0-9._+-]+@[a-z0-9.-]+\.[a-z]{2,}$/i;
        if (v && emailRegex.test(v)) {
          clientData.email = v;
          lsSet('ptracker_cd', JSON.stringify(clientData));
          tiktokIdentify();
          sendEnrich();
        }
      });
    });
    document.querySelectorAll(phoneSelectors).forEach(function(el){
      once(el, function(){
        var v = (el.value||'').trim();
        if (v && v.length >= 7) {
          // Normaliza para E.164 (formato exigido pelo TikTok)
          var hasPlus = v.charAt(0) === '+';
          var digits  = v.replace(/[^0-9]/g,'');
          if (!hasPlus) {
            // BR (10-11 dígitos) → 55 ; PT (9 dígitos começando com 9) → 351
            if (digits.length === 10 || digits.length === 11) digits = '55' + digits;
            else if (digits.length === 9 && digits.charAt(0) === '9') digits = '351' + digits;
          }
          if (digits.length >= 8 && digits.length <= 15) {
            clientData.phone = '+' + digits;
            lsSet('ptracker_cd', JSON.stringify(clientData));
            tiktokIdentify();
            sendEnrich();
          }
        }
      });
    });
    document.querySelectorAll(nameSel).forEach(function(el){
      once(el, function(){
        var v = (el.value||'').trim();
        if (v.length < 2) return;
        var parts = v.split(/\s+/);
        clientData.first_name = parts[0] || '';
        clientData.last_name  = parts.length > 1 ? parts[parts.length - 1] : '';
        lsSet('ptracker_cd', JSON.stringify(clientData));
        sendEnrich();
      });
    });
    document.querySelectorAll(postalSel).forEach(function(el){
      once(el, function(){
        var v = (el.value||'').trim();
        if (v.length >= 4) { clientData.zip = v; lsSet('ptracker_cd', JSON.stringify(clientData)); sendEnrich(); }
      });
    });
    document.querySelectorAll(citySel).forEach(function(el){
      once(el, function(){
        var v = (el.value||'').trim();
        if (v.length >= 2) { clientData.city = v; lsSet('ptracker_cd', JSON.stringify(clientData)); sendEnrich(); }
      });
    });
  }

  // SPAs montam forms depois — observa o DOM e re-vincula
  function observeDomForForms(){
    if (!window.MutationObserver) return;
    var obs = new MutationObserver(function(){ bindFormCapture(); });
    obs.observe(document.body || document.documentElement, { childList: true, subtree: true });
  }

  function boot(){
    bindFormCapture();
    observeDomForForms();
    tiktokIdentify();
    sendEnrich();
    trackInternal('pageview');
    setInterval(function(){ trackInternal('heartbeat'); }, 25000);
    window.addEventListener('pagehide', function(){ trackInternal('heartbeat'); });

    var path = (location.pathname || '/').toLowerCase();
    var bucket = function(){ return Math.floor(Date.now() / 1000 / 60); };

    // Sempre dispara PageView server-side (event_id determinístico para dedup com pixel.js client-side)
    fireTTEvent('PageView', 'pv_' + sid + '_' + bucket());

    // ── Detecção de tipo de página (heurística simples) ──
    var isThankYou = hasAttr('data-tt-purchase-page') || hasAttr('data-tt-thank-you-page') ||
                     path.indexOf('/obrigado') >= 0 || path.indexOf('/success') >= 0 ||
                     path.indexOf('/thank') >= 0 || path.indexOf('pagamento-confirmado') >= 0 ||
                     path.indexOf('payment-confirm') >= 0 || path.indexOf('order-confirm') >= 0;
    var isCheckout = !isThankYou && (hasAttr('data-tt-checkout-page') ||
                     path.indexOf('/checkout') >= 0 || path.indexOf('/pix') >= 0 ||
                     path.indexOf('/pagamento') >= 0 || path.indexOf('/payment') >= 0);

    if (isCheckout) {
      // Checkout: dispara InitiateCheckout no load; AddPaymentInfo no clique de submit
      var icId = sessionStorage.getItem('wmb_ic') || ('ic_' + sid + '_' + bucket());
      try { sessionStorage.setItem('wmb_ic', icId); } catch(e){}
      fireTTEvent('InitiateCheckout', icId);

      var apiFired = !!sessionStorage.getItem('wmb_api');
      function fireApi(){
        if (apiFired) return;
        apiFired = true;
        var apiId = 'api_' + sid + '_' + bucket();
        try { sessionStorage.setItem('wmb_api', apiId); } catch(e){}
        fireTTEvent('AddPaymentInfo', apiId);
      }
      var SUBMIT_KWS = ['EFETUAR PAGAMENTO','EFECTUAR PAGAMENTO','GERAR PIX','GERAR QR','PAGAR','CONFIRMAR PAGAMENTO','FINALIZAR','PAY NOW'];
      document.addEventListener('click', function(e){
        if (apiFired) return;
        var el = e.target;
        for (var i = 0; i < 5 && el && el !== document.body; i++) {
          if (!el.tagName) { el = el.parentElement; continue; }
          var tag = el.tagName.toLowerCase();
          var txt = (el.textContent || '').toUpperCase();
          var isBtn = (tag === 'button' || tag === 'a' ||
                       (el.getAttribute && (el.getAttribute('role') === 'button' || el.getAttribute('type') === 'submit')));
          if (isBtn) {
            for (var k = 0; k < SUBMIT_KWS.length; k++) {
              if (txt.indexOf(SUBMIT_KWS[k]) >= 0) { fireApi(); return; }
            }
          }
          el = el.parentElement;
        }
      }, true);
      document.addEventListener('submit', fireApi, true);
      return;
    }

    if (isThankYou) {
      // Página de obrigado/sucesso: a página deve chamar window.__ptracker_fire_tt('Purchase', id, value) inline
      // (porque só ela sabe o valor exato e o ID da transação).
      return;
    }

    // ── Landing / outras páginas: ViewContent no clique de CTA ──
    var vcFired = false;
    function attemptViewContent(){
      if (vcFired) return;
      vcFired = true;
      var b = bucket();
      fireTTEvent('ViewContent',     'vc_'  + sid + '_' + b);
      fireTTEvent('LandingPageView', 'lpv_' + sid + '_' + b);
      fireTTEvent('EngagedSession',  'es_'  + sid + '_' + b);
    }

    var POSITIVE_KWS = [
      'COMPRAR','COMPRA','ADICIONAR','PARTICIPAR','CONTINUAR','COMEÇAR','COMECAR',
      'RESGATAR','RESGATE','SAQUE','QUERO','AVANÇAR','AVANCAR','PRÓXIMO','PROXIMO',
      'START','OBTER','GANHAR','ASSINAR','SUBSCREVER','RECEBER','CHECKOUT','BUY'
    ];
    var NEGATIVE_KWS = ['NÃO QUERO','NAO QUERO','CANCELAR','VOLTAR','SAIR','FECHAR','DESISTIR'];

    document.addEventListener('click', function(e){
      if (vcFired) return;
      var el = e.target;
      for (var i = 0; i < 5 && el && el !== document.body; i++) {
        if (!el.tagName) { el = el.parentElement; continue; }
        var tag = el.tagName.toLowerCase();
        var txt = (el.textContent || '').toUpperCase();
        var isBtn = (tag === 'button' || tag === 'a' ||
                     (el.getAttribute && (el.getAttribute('role') === 'button' || el.getAttribute('type') === 'submit')));
        if (isBtn) {
          var isNeg = false;
          for (var n = 0; n < NEGATIVE_KWS.length; n++) {
            if (txt.indexOf(NEGATIVE_KWS[n]) >= 0) { isNeg = true; break; }
          }
          if (isNeg) { el = el.parentElement; continue; }
          for (var p = 0; p < POSITIVE_KWS.length; p++) {
            if (txt.indexOf(POSITIVE_KWS[p]) >= 0) { attemptViewContent(); return; }
          }
        }
        el = el.parentElement;
      }
    }, true);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
