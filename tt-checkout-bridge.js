(function () {
  'use strict';

  function safeGetItem(key) {
    try {
      return localStorage.getItem(key) || '';
    } catch (e) {
      return '';
    }
  }

  function safeSetItem(key, value) {
    try {
      localStorage.setItem(key, value);
    } catch (e) {}
  }

  function safeGetJson(key) {
    try {
      return JSON.parse(localStorage.getItem(key) || '{}');
    } catch (e) {
      return {};
    }
  }

  function safeMergeJson(key, nextData) {
    var current = safeGetJson(key);
    var merged = Object.assign({}, current, nextData || {});
    Object.keys(merged).forEach(function (field) {
      if (merged[field] === '' || merged[field] == null) {
        delete merged[field];
      }
    });
    safeSetItem(key, JSON.stringify(merged));
    return merged;
  }

  function getFieldValue(selector) {
    var el = document.querySelector(selector);
    return el ? String(el.value || '').trim() : '';
  }

  function normalizePhone(value) {
    if (!value) return '';
    var raw = String(value).trim();
    var hasPlus = raw.charAt(0) === '+';
    var digits = raw.replace(/[^0-9]/g, '');
    if (!digits) return '';
    if (!hasPlus) {
      if (digits.length === 10 || digits.length === 11) {
        digits = '55' + digits;
      } else if (digits.length === 9 && digits.charAt(0) === '9') {
        digits = '351' + digits;
      }
    }
    if (digits.length < 8 || digits.length > 15) return '';
    return '+' + digits;
  }

  function splitName(fullName) {
    var cleaned = String(fullName || '').trim().replace(/\s+/g, ' ');
    if (!cleaned) return { first_name: '', last_name: '' };
    var parts = cleaned.split(' ');
    return {
      first_name: parts[0] || '',
      last_name: parts.length > 1 ? parts[parts.length - 1] : ''
    };
  }

  function buildClientData() {
    var checkout = safeGetJson('checkout_form_data');
    var userAddress = safeGetJson('userAddress');

    var name =
      getFieldValue('#name') ||
      checkout.name ||
      checkout.receiver_name ||
      userAddress.nome ||
      '';
    var email = (
      getFieldValue('#email') ||
      checkout.email ||
      userAddress.email ||
      ''
    ).toLowerCase();
    var phone = normalizePhone(
      getFieldValue('#telephone') ||
      checkout.phone ||
      userAddress.telefone ||
      ''
    );
    var zip =
      getFieldValue('#zip_code') ||
      checkout.cep ||
      userAddress.cep ||
      '';
    var city =
      getFieldValue('#city') ||
      checkout.city ||
      userAddress.cidade ||
      '';
    var names = splitName(name);

    return {
      email: email,
      phone: phone,
      first_name: names.first_name,
      last_name: names.last_name,
      zip: zip,
      city: city
    };
  }

  function syncTrackerClientData(sendEnrich) {
    var merged = safeMergeJson('ptracker_cd', buildClientData());
    if (sendEnrich && typeof window.__ptracker_enrich === 'function') {
      try {
        window.__ptracker_enrich();
      } catch (e) {}
    }
    return merged;
  }

  var enrichTimer = null;
  function scheduleEnrich() {
    if (enrichTimer) clearTimeout(enrichTimer);
    enrichTimer = setTimeout(function () {
      syncTrackerClientData(true);
    }, 250);
  }

  function isRelevantField(el) {
    if (!el || typeof el.matches !== 'function') return false;
    return el.matches(
      '#email, #telephone, #name, #document, #zip_code, #city, #receiver_name,' +
      'input[name="email"], input[name="telephone"], input[name="name"], input[name="document"],' +
      'input[name="zip_code"], input[name="city"], input[name="receiver_name"]'
    );
  }

  function attachFieldListeners() {
    document.addEventListener(
      'input',
      function (event) {
        if (!isRelevantField(event.target)) return;
        syncTrackerClientData(false);
      },
      true
    );

    document.addEventListener(
      'change',
      function (event) {
        if (!isRelevantField(event.target)) return;
        syncTrackerClientData(false);
        scheduleEnrich();
      },
      true
    );

    document.addEventListener(
      'blur',
      function (event) {
        if (!isRelevantField(event.target)) return;
        syncTrackerClientData(false);
        scheduleEnrich();
      },
      true
    );
  }

  function sanitizeIdPart(value) {
    return String(value || '')
      .replace(/[^a-zA-Z0-9_-]/g, '')
      .slice(0, 80);
  }

  function getPurchaseValue() {
    var paymentData = safeGetJson('payment_data');
    var cents = Number(paymentData.valor || 0);
    if (cents > 0) {
      return Math.round(cents) / 100;
    }

    var checkoutTotal = Number(safeGetItem('checkout_total') || 0);
    if (checkoutTotal > 0) {
      return checkoutTotal;
    }

    return 0.01;
  }

  function maybeFirePurchase() {
    var isPurchasePage =
      (document.documentElement && document.documentElement.hasAttribute('data-tt-purchase-page')) ||
      (document.body && document.body.hasAttribute('data-tt-purchase-page'));
    if (!isPurchasePage) return;

    syncTrackerClientData(true);

    var paymentData = safeGetJson('payment_data');
    var token = safeGetItem('payment_token') || paymentData.token || paymentData.timestamp || Date.now();
    var eventId = 'pur_' + sanitizeIdPart(token);
    var sentKey = 'tt_purchase_sent_' + eventId;
    if (safeGetItem(sentKey)) return;

    var value = getPurchaseValue();

    function attemptFire(tryCount) {
      if (typeof window.__ptracker_fire_tt !== 'function') {
        if (tryCount < 20) {
          setTimeout(function () {
            attemptFire(tryCount + 1);
          }, 250);
        }
        return;
      }

      try {
        window.__ptracker_fire_tt('Purchase', eventId, value);
        safeSetItem(sentKey, '1');
      } catch (e) {}
    }

    attemptFire(0);
  }

  function init() {
    syncTrackerClientData(false);
    attachFieldListeners();

    setTimeout(function () {
      syncTrackerClientData(true);
      maybeFirePurchase();
    }, 500);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
