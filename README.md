# Pixel Tracker TikTok — Versão Enxuta

Sistema completo de tracking server-side + client-side para TikTok Events API v1.3, extraído de um sistema maior e simplificado para reuso em outros projetos.

## O que faz (resumo de 1 linha)

Dispara automaticamente PageView, ViewContent, InitiateCheckout, AddPaymentInfo e Purchase para o TikTok com EMQ alto, deduplicado entre client-side e server-side, com retry automático em falhas.

## Estrutura de pastas

```
seu-site/
├── api/
│   ├── db.php              ← schema SQLite + helpers getSetting/setSetting
│   ├── pixel.js.php        ← snippet TikTok client-side (ttq.identify + ttq.page)
│   ├── tracking.js.php     ← cérebro do tracker (cookies, forms, auto-eventos)
│   ├── events.php          ← endpoint AJAX → server-side fire
│   ├── pixel_fire.php      ← chamadas reais ao TikTok Events API v1.3
│   ├── tracking.php        ← Live View + funil interno (heartbeats)
│   ├── enrich.php          ← persiste email/phone/UA por sessão
│   ├── retry_worker.php    ← reprocessa fila de eventos que falharam
│   ├── pixel_test.php      ← endpoint do botão "Testar" no admin
│   └── database.sqlite     ← criado automaticamente no primeiro request
└── pixel/
    └── index.php           ← painel admin (Pixel ID + Access Token)
```

## Instalação (3 passos)

### 1) Coloca os arquivos no servidor

A pasta `/api/` no root do site, e a pasta `/pixel/` também no root. PHP 7.4+ com extensões `pdo_sqlite`/`sqlite3` e `curl`.

### 2) Configura o pixel

Acede a `https://teu-site.com/pixel/`. Login default:

```
admin / admin
```

(muda na primeira vez no painel "Manutenção e segurança")

Cola o **Pixel ID** e o **Access Token** que vieram do TikTok Events Manager. Pronto, está a funcionar.

### 3) Adiciona o snippet nas páginas

No `<head>` de cada página onde queres tracking:

```html
<script src="/api/pixel.js.php" async></script>
<script src="/api/tracking.js.php" async></script>
```

## O que dispara automaticamente

| Evento              | Quando                                                            |
|---------------------|-------------------------------------------------------------------|
| **PageView**        | Em todas as páginas no load (server + client deduplicados)        |
| **ViewContent**     | Clique num botão CTA (COMPRAR, PARTICIPAR, COMEÇAR, etc.)         |
| **LandingPageView** | Junto com ViewContent                                             |
| **EngagedSession**  | Junto com ViewContent                                             |
| **InitiateCheckout**| Load de página com `/checkout`, `/pix`, `/pagamento` na URL       |
| **AddPaymentInfo**  | Clique em "Pagar"/"Finalizar" ou submit do form                   |

Para **Purchase**, a página de obrigado/sucesso precisa chamar inline (porque só ela sabe o valor exato e o ID da transação):

```html
<script>
  // Quando confirmares o pagamento (webhook, polling, etc.)
  window.__ptracker_fire_tt('Purchase', 'pur_<id_único>', 99.90);
</script>
```

## EMQ (Event Match Quality) — o que é otimizado

- **Email/phone**: validados, hashados SHA-256 (telefone normalizado para E.164: `+5511987654321`)
- **External ID**: cascade `email → session_id` (sempre presente, dá match para users sem login)
- **First/Last name + ZIP + City**: capturados de qualquer form na página
- **IP + User Agent**: do servidor (sempre confiável)
- **TTClid + TTP**: do query string + cookies, persistidos entre páginas
- **Country**: via headers de proxy (Cloudflare etc.) com fallback para ip-api.com (cache 24h)
- **Locale**: `pt_BR` por default (configurável)

## Dedup client+server

Cada evento tem um `event_id` determinístico (mesmo formato no client e no server) para evitar duplicação:

```
PageView         → pv_<sid>_<bucket60s>
ViewContent      → vc_<sid>_<bucket60s>
InitiateCheckout → ic_<sid>_<bucket60s>  (persistido em sessionStorage.wmb_ic)
AddPaymentInfo   → api_<sid>_<bucket60s> (persistido em sessionStorage.wmb_api)
Purchase         → pur_<sid>_<bucket60s> ou ID custom da transação
```

## Retry automático

Eventos com erro (HTTP 5xx, timeout, code TikTok ≠ 0) são guardados em `pixel_retry_queue` com backoff exponencial: 60s, 5min, 30min, 2h, 6h, 24h. Após 6 tentativas, desiste.

Configura um cron para processar a fila:

```cron
* * * * * curl -s "https://teu-site.com/api/retry_worker.php?secret=XXX" >/dev/null
```

(o `secret` é gerado automaticamente em settings — vê no admin)

## Tabelas SQLite criadas automaticamente

- `settings` — Pixel ID, Access Token, Test Event Code, content_id/name, country, etc.
- `sessions` — uma linha por visitante, atualizada via heartbeats (Live View)
- `funnel_events` — log de eventos do funil (pageview, view_content, ...)
- `session_enrichment` — email/phone/IP/UA por session_id (para max EMQ)
- `pixel_events_log` — log de TODOS os envios ao TikTok (quais campos foram, status HTTP)
- `pixel_retry_queue` — fila de eventos que falharam
- `pixel_fired_dedup` — guard para não duplicar event_id em janela de 5min

## Senha admin default

```
admin / admin
```

**Mudar imediatamente** no primeiro acesso (painel → Manutenção).

## Notas técnicas

- Sistema só fala com TikTok (sem Facebook). Se precisares de Facebook CAPI, mantém a versão original.
- Sem A/B testing, sem PIX/checkout, sem custos/taxas — só tracking.
- O `tracking.js.php` detecta a página via heurística simples na URL (`/checkout`, `/obrigado`, etc.). Ajusta os keywords em `POSITIVE_KWS`/`SUBMIT_KWS` se o teu site usar termos diferentes.
- O `database.sqlite` é criado no primeiro request — certifica-te que a pasta `/api/` tem permissão de escrita (`chmod 755 api/` e o utilizador do PHP precisa de poder escrever lá).
