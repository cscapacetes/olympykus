<?php
header('Content-Type: application/json');

// Habilita o log de erros
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Configurações da API DisparoPro (pode ser sobrescrita por variáveis de ambiente)
$DISPARO_TOKEN = getenv('DISPARO_TOKEN') ?: '43ba8b8ba761ae7f95004db48e5760942dcf6477';
$DISPARO_ENDPOINT = 'https://apihttp.disparopro.com.br:8433/mt';
$DISPARO_PARTNER_ID = getenv('DISPARO_PARTNER_ID') ?: '5034e65a0c';

// Helper para formatação do telefone no padrão exigido (ex: 5511999999999)
function formatBrazilPhone($phoneRaw) {
    if (!$phoneRaw) return null;
    // Mantém apenas dígitos
    $digits = preg_replace('/\D+/', '', $phoneRaw);
    if (!$digits) return null;
    // Remove zeros à esquerda (casos raros de DDD 0)
    $digits = ltrim($digits, '0');
    // Se já vier com 55, mantém; senão prefixa 55
    if (strpos($digits, '55') !== 0) {
        $digits = '55' . $digits;
    }
    return $digits;
}

// Idempotência simples usando arquivo de flag na pasta cache/
function disparoAlreadySent($transactionId) {
    $flagPath = __DIR__ . '/cache/disparo_' . $transactionId . '.sent';
    return file_exists($flagPath);
}

function markDisparoSent($transactionId, $result = null, $payloadArray = null) {
    $flagPath = __DIR__ . '/cache/disparo_' . $transactionId . '.sent';
    // Garante que a pasta cache exista
    if (!is_dir(__DIR__ . '/cache')) {
        @mkdir(__DIR__ . '/cache', 0777, true);
    }
    // Conteúdo detalhado com logs da resposta
    $content = [
        'timestamp' => date('c'),
        'transactionId' => $transactionId,
        'disparopro' => [
            'httpCode' => $result['httpCode'] ?? null,
            'error' => $result['error'] ?? null,
            'response' => $result['response'] ?? null,
            'success' => $result['success'] ?? null
        ],
        'request' => [
            'endpoint' => $GLOBALS['DISPARO_ENDPOINT'] ?? null,
            'payload' => $payloadArray
        ]
    ];
    @file_put_contents($flagPath, json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

// Cliente para envio de mensagens via DisparoPro
function sendDisparoPro($endpoint, $token, $payloadArray) {
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payloadArray), // precisa ser um array JSON
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    return [
        'httpCode' => $httpCode,
        'response' => $response,
        'error' => $curlError,
        'success' => !$curlError && $httpCode >= 200 && $httpCode < 300
    ];
}

// Recebe o payload do webhook
$payload = file_get_contents('php://input');
$event = json_decode($payload, true);

// Log do payload recebido
error_log("[Webhook] 🔄 Iniciando processamento do webhook");
error_log("[Webhook] 📦 Payload recebido: " . $payload);

// Verifica se o payload é válido - Formato AllowPay v2
// Modo direto de envio de SMS a partir do frontend
if (is_array($event) && ($event['action'] ?? null) === 'send-sms') {
    $rawPhone = $event['telefone'] ?? null;
    $mensagem = $event['mensagem'] ?? null;
    $transactionId = $event['transactionId'] ?? ('INS_' . time());

    $numero = formatBrazilPhone($rawPhone);
    if (!$numero || !$mensagem) {
        error_log("[Webhook] ❌ Dados insuficientes para envio direto de SMS: telefone ou mensagem ausentes");
        http_response_code(200);
        echo json_encode(['error' => 'Dados insuficientes', 'ok' => false]);
        exit;
    }

    // Evita duplicidade por protocolo/transação
    if (disparoAlreadySent($transactionId)) {
        error_log("[Webhook] 🔁 SMS já marcado como enviado para $transactionId, evitando duplicidade.");
        http_response_code(200);
        echo json_encode(['ok' => true, 'duplicated' => true]);
        exit;
    }

    $payloadArray = [[
        'numero' => $numero,
        'servico' => 'short',
        'mensagem' => $mensagem,
        'parceiro_id' => $DISPARO_PARTNER_ID,
        'codificacao' => '0',
        'nome_campanha' => 'AGENDAMENTO - CONFIRMAÇÃO'
    ]];

    error_log("[Webhook] 📤 Envio direto de SMS: $numero | Mensagem: $mensagem");
    $result = sendDisparoPro($DISPARO_ENDPOINT, $DISPARO_TOKEN, $payloadArray);
    error_log("[Webhook] 📥 DisparoPro resposta (HTTP {$result['httpCode']}): " . $result['response']);
    if ($result['success']) {
        markDisparoSent($transactionId, $result, $payloadArray);
    }

    http_response_code(200);
    echo json_encode(['ok' => $result['success'], 'http' => $result['httpCode']]);
    exit;
}

// Validação do formato - suporta múltiplos formatos:
// Formato 1 (wrapper com event): {event: "...", data: {...}}
// Formato 2 (wrapper com success): {success: true, data: {...}}
// Formato 3 (direto): {transaction_id: "...", status: "..."}

$isWrapperEventFormat = isset($event['event']) && isset($event['data']);
$isWrapperSuccessFormat = isset($event['success']) && isset($event['data']);
$isDirectFormat = isset($event['transaction_id']) || isset($event['id']) || isset($event['status']);

if (!$event || (!$isWrapperEventFormat && !$isWrapperSuccessFormat && !$isDirectFormat)) {
    error_log("[Webhook] ❌ Payload inválido recebido. Campos necessários não encontrados");
    error_log("[Webhook] 🔍 Campos disponíveis: " . print_r(array_keys($event ?? []), true));
    http_response_code(200);
    echo json_encode(['error' => 'Payload inválido']);
    exit;
}

// Extrai o tipo de evento e normaliza os dados
if ($isWrapperEventFormat) {
    $eventType = $event['event'] ?? 'transaction.updated';
    $transaction = $event['data'];
    $formatDetected = 'wrapper-event';
} elseif ($isWrapperSuccessFormat) {
    $eventType = 'transaction.updated';
    $transaction = $event['data'];
    $formatDetected = 'wrapper-success';
} else {
    $eventType = 'transaction.updated';
    $transaction = $event;
    $formatDetected = 'direto';
}

error_log("[Webhook] 📋 Tipo de evento: " . $eventType);
error_log("[Webhook] 📋 Formato detectado: " . $formatDetected);

function getUpsellTitle($valor) {
    
    switch($valor) {
        case 4790:
            return 'Taxa de verificação';
        case 2890:
            return 'Taxa TENF';
        case 4569:
            return 'Taxa IOF';
        case 8500:
            return 'Taxa de Regularização';
        case 1825:
            return 'Validação Bancaria';
        case 3990:
            return 'Taxa de Validação';
        case 5573:
            return 'Front'; 
        case 2490:
            return 'Indenização Adicional'; 
        default:
            return 'Produto ' . ($valor/100); 
    }
}

try {
    // Extrai dados compatíveis com ambos os formatos
    // Formato wrapper: data.id, data.status
    // Formato direto: transaction_id ou id, status
    $transactionId = $transaction['id'] ?? $transaction['transaction_id'] ?? null;
    $status = $transaction['status'] ?? null;
    
    error_log("[Webhook] ℹ️ Processando pagamento ID: " . $transactionId . " com status: " . $status);
    
    // Conecta ao SQLite
    $dbPath = __DIR__ . '/database.sqlite';
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    error_log("[Webhook] ✅ Conexão com banco de dados estabelecida");

    // Atualiza o status do pagamento no banco de dados
    $stmt = $db->prepare("UPDATE pedidos SET status = :status, updated_at = :updated_at WHERE transaction_id = :transaction_id");
    
    $novoStatus = strtolower($status) === 'paid' || strtolower($status) === 'approved' ? 'paid' : strtolower($status);
    error_log("[Webhook] 🔄 Atualizando status para: " . $novoStatus);
    
    $result = $stmt->execute([
        'status' => $novoStatus,
        'updated_at' => date('c'),
        'transaction_id' => $transactionId
    ]);

    if ($stmt->rowCount() === 0) {
        error_log("[Webhook] ⚠️ Nenhum pedido encontrado com o ID: " . $transactionId);
        error_log("[Webhook] 🔍 Verificando se o pedido existe no banco...");
        
        // Verifica se o pedido existe
        $checkStmt = $db->prepare("SELECT * FROM pedidos WHERE transaction_id = :transaction_id");
        $checkStmt->execute(['transaction_id' => $transactionId]);
        $pedidoExiste = $checkStmt->fetch();
        
        if ($pedidoExiste) {
            error_log("[Webhook] ℹ️ Pedido encontrado mas status não foi alterado. Status atual: " . $pedidoExiste['status']);
        } else {
            error_log("[Webhook] ❌ Pedido não existe no banco de dados");
        }
        
        http_response_code(200);
        echo json_encode(['error' => 'Pedido não encontrado']);
        exit;
    }

    error_log("[Webhook] ✅ Status atualizado com sucesso no banco de dados");

    // Responde imediatamente ao webhook
    http_response_code(200);
    echo json_encode(['success' => true]);
    
    // Fecha a conexão com o cliente
    if (function_exists('fastcgi_finish_request')) {
        error_log("[Webhook] 📤 Fechando conexão com o cliente via fastcgi_finish_request");
        fastcgi_finish_request();
    } else {
        error_log("[Webhook] ⚠️ fastcgi_finish_request não disponível");
    }
    
    // =====================


    
    // Continua o processamento em background
    if (strtolower($status) === 'paid' || strtolower($status) === 'approved') {
        error_log("[Webhook] ✅ Pagamento aprovado, iniciando processamento em background");

        // Busca os dados do pedido
        $stmt = $db->prepare("SELECT * FROM pedidos WHERE transaction_id = :transaction_id");
        $stmt->execute(['transaction_id' => $transactionId]);
        $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
        
        
        // ===============================
// CAPTURA FBP E FBCLID (CORRETO)
// ===============================
$fbp = $_COOKIE['_fbp'] ?? null;

// tenta pegar fbclid salvo no banco
$utmParamsTmp = json_decode($pedido['utm_params'] ?? '', true);
$fbclid = $utmParamsTmp['fbclid'] ?? null;

$fbc = null;
if (!empty($fbclid)) {
    $fbc = 'fb.1.' . time() . '.' . $fbclid;
}

        
        
        if ($pedido) {// META CAPI — VALOR FAKE
// =====================
$pixelId     = '1671569840666808';
$accessToken = 'EAAUwlJAqc7sBRMkZCFy3tkhaCyrqZBppzThuQX3YhZABzEXx8BSzf6QsQ3wAKPwAqbFZCZCLgoE5ZAOVcU2RGoyKZCbx0Jf0ISqob6kPxsRhGieGF0eL6RVBMZCFbyxP9NXHs0bUX31C9ZAdf3NwCqdakZA4Bw1Gkpu2cPKOWqr1zQuuXDWs8kKFNliIMpjyfHIgZDZD';

// ===============================


// Suporta ambos: amount (wrapper) e total_value (direto) 
$amount = $transaction['amount'] ?? $transaction['total_value'] ?? $pedido['valor'] ?? 0;

// Converte valor para centavos se vier em reais (JSON desserializa float com decimais)
if (is_float($amount)) {
    $amount = intval($amount * 100);
}

$phoneE164 = formatBrazilPhone($pedido['telefone'] ?? null);

$metaPayload = [
    'data' => [[
        'event_name' => 'Purchase',
        'event_time' => time(),
        'action_source' => 'website',
        'event_id' => 'purchase_' . $transactionId,

        'user_data' => array_filter([
            'em' => hash('sha256', strtolower(trim($pedido['email']))),
            'ph' => $phoneE164 ? hash('sha256', $phoneE164) : null,
            'fn' => !empty($pedido['nome']) 
                ? hash('sha256', strtolower(trim(explode(' ', $pedido['nome'])[0]))) 
                : null,
            'ln' => !empty($pedido['nome']) && count(explode(' ', $pedido['nome'])) > 1
                ? hash('sha256', strtolower(trim(explode(' ', $pedido['nome'])[1])))
                : null,
            'client_ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'client_user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'fbp' => $fbp,
            'fbc' => $fbc,
            'external_id' => hash('sha256', $transactionId . '|purchase'),
            'country' => hash('sha256', 'br')
        ]),

        'custom_data' => [
            'currency' => 'BRL',
            'value' => round($amount / 100, 2)
        ]
    ]]
];


$metaUrl = "https://graph.facebook.com/v19.0/{$pixelId}/events?access_token={$accessToken}";
$chMeta = curl_init($metaUrl);
curl_setopt_array($chMeta, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($metaPayload),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json']
]);
curl_exec($chMeta);
curl_close($chMeta);

            error_log("[Webhook] ✅ Dados do pedido recuperados do banco");
            error_log("[Webhook] 📊 Dados do pedido: " . print_r($pedido, true));

            // Decodifica os parâmetros UTM do banco
            $utmParams = json_decode($pedido['utm_params'], true);
            error_log("[Webhook] 📊 UTM Params brutos do banco: " . print_r($utmParams, true));
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("[Webhook] ⚠️ Erro ao decodificar UTM params: " . json_last_error_msg());
            }

            // Extrai os parâmetros UTM
            $trackingParameters = [
                'src' => $utmParams['utm_source'] ?? null,
                'sck' => $utmParams['sck'] ?? null,
                'utm_source' => $utmParams['utm_source'] ?? null,
                'utm_campaign' => $utmParams['utm_campaign'] ?? null,
                'utm_medium' => $utmParams['utm_medium'] ?? null,
                'utm_content' => $utmParams['utm_content'] ?? null,
                'utm_term' => $utmParams['utm_term'] ?? null,
                'fbclid' => $utmParams['fbclid'] ?? null,
                'gclid' => $utmParams['gclid'] ?? null,
                'ttclid' => $utmParams['ttclid'] ?? null,
                'xcod' => $utmParams['xcod'] ?? null
            ];

            // Remove valores null
            $trackingParameters = array_filter($trackingParameters);

            // Extrai dados do cliente do formato GhostsPay v1
            $customer = $transaction['customer'] ?? [];
            // No formato v1, customer.document pode ser string direta ou objeto
            $customerDocument = is_array($customer['document'] ?? null) 
                ? ($customer['document']['number'] ?? '')
                : ($customer['document'] ?? $pedido['cpf'] ?? '');
            // No formato v1, customer.phone pode não existir, usar do pedido
            $customerPhone = $customer['phone'] ?? $pedido['telefone'] ?? null;
            $fee = $transaction['fee'] ?? [];
            $items = $transaction['items'] ?? [];
            $paidAt = $transaction['paidAt'] ?? $transaction['createdAt'] ?? date('Y-m-d H:i:s');
            // Suporta ambos: amount (wrapper) e total_value (direto)
            $amount = $transaction['amount'] ?? $transaction['total_value'] ?? $pedido['valor'];
            
            // Converte valor para centavos se vier em reais (JSON desserializa float com decimais)
            if (is_float($amount)) {
                $amount = intval($amount * 100); // Converte de reais para centavos
                error_log("[Webhook] 💰 Convertendo valor de reais para centavos: " . $amount);
            }
            
            error_log("[Webhook] 📊 Dados extraídos - Customer: " . json_encode($customer));
            error_log("[Webhook] 📊 Dados extraídos - Document: " . $customerDocument);
            error_log("[Webhook] 📊 Dados extraídos - Phone: " . $customerPhone);
            error_log("[Webhook] 📊 Dados extraídos - Fee: " . json_encode($fee));
            error_log("[Webhook] 📊 Dados extraídos - Amount: " . $amount);
            error_log("[Webhook] 📊 Dados extraídos - PaidAt: " . $paidAt);

            // Dados no formato da nova API Otimizey
            $otimizeyData = [
                'orderId' => $transactionId,
                'platform' => 'GhostsPay',
                'paymentMethod' => 'pix',
                'status' => 'paid',
                'name' => $customer['name'] ?? $pedido['nome'], // Nome do cliente no nível raiz
                'phone' => $customerPhone, // Telefone no nível raiz
                'createdAt' => $transaction['createdAt'] ?? $pedido['created_at'],
                'approvedDate' => $paidAt,
                'paidAt' => $paidAt,
                'refundedAt' => $transaction['refundedAt'] ?? null,
                'customer' => [
                    'name' => $customer['name'] ?? $pedido['nome'],
                    'email' => $customer['email'] ?? $pedido['email'],
                    'phone' => $customerPhone,
                    'document' => [
                        'number' => $customerDocument,
                        'type' => 'CPF'
                    ],
                    'country' => 'BR',
                    'ip' => $transaction['ip'] ?? $_SERVER['REMOTE_ADDR'] ?? null
                ],
                'items' => [
                    [
                        'id' => isset($items[0]) ? ($items[0]['id'] ?? uniqid('PROD_')) : uniqid('PROD_'),
                        'title' => isset($items[0]) ? ($items[0]['title'] ?? getUpsellTitle($amount)) : getUpsellTitle($amount),
                        'quantity' => 1,
                        'unitPrice' => $amount
                    ]
                ],
                'amount' => $amount,
                'fee' => [
                    'fixedAmount' => $fee['fixedAmount'] ?? 0,
                    'netAmount' => $fee['netAmount'] ?? $amount
                ],
                'trackingParameters' => $trackingParameters,
                'isTest' => false
            ];

            error_log("[Webhook] 📦 Payload completo para otimizey: " . json_encode($otimizeyData));

            // Envia para otimizey.php
              // Construir URL do otimizey.php de forma robusta com fallbacks
            $serverUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
            
            // Método 1: Usar DOCUMENT_ROOT (mais comum)
            $scriptDir = str_replace($_SERVER['DOCUMENT_ROOT'], '', __DIR__);
            $otimizeyUrl = $serverUrl . $scriptDir . "/otimizey.php";
            
            // Método 2: Fallback usando SCRIPT_NAME se o método 1 falhar
            if (empty($scriptDir) || $scriptDir === __DIR__) {
                $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
                $otimizeyUrl = $serverUrl . $scriptDir . "/otimizey.php";
                error_log("[Webhook] ⚠️ Usando fallback SCRIPT_NAME para construir URL");
            }


            $ch = curl_init($otimizeyUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($otimizeyData),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_TIMEOUT => 30
            ]);

            $otimizeyResponse = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            
            error_log("[Webhook] 📤 Resposta do otimizey (HTTP $httpCode): " . $otimizeyResponse);
            if ($curlError) {
                error_log("[Webhook] ❌ Erro ao enviar para otimizey: " . $curlError);
            } else {
                error_log("[Webhook] 📊 Resposta decodificada: " . print_r(json_decode($otimizeyResponse, true), true));
            }
            
            curl_close($ch);

            // ========================================
            // ENVIO PARA UTMIFY.PHP
            // ========================================
            error_log("[Webhook] 📡 Iniciando envio para utmify.php");

            // Prepara dados no formato esperado pela utmify.php
            $utmifyData = [
                'orderId' => $transactionId,
                'platform' => 'GhostsPay',
                'paymentMethod' => 'pix',
                'status' => 'paid',
                'createdAt' => $pedido['created_at'],
                'approvedDate' => $paidAt,
                'paidAt' => $paidAt,
                'refundedAt' => null,
                'customer' => [
                    'name' => $customer['name'] ?? $pedido['nome'],
                    'email' => $customer['email'] ?? $pedido['email'],
                    'phone' => $customerPhone,
                    'document' => [
                        'number' => $customerDocument,
                        'type' => 'CPF'
                    ],
                    'country' => 'BR',
                    'ip' => $transaction['ip'] ?? $_SERVER['REMOTE_ADDR'] ?? null
                ],
                'items' => [
                    [
                        'id' => isset($items[0]) ? ($items[0]['id'] ?? uniqid('PROD_')) : uniqid('PROD_'),
                        'title' => isset($items[0]) ? ($items[0]['title'] ?? getUpsellTitle($amount)) : getUpsellTitle($amount),
                        'quantity' => 1,
                        'unitPrice' => $amount
                    ]
                ],
                'amount' => $amount,
                'fee' => [
                    'fixedAmount' => $fee['fixedAmount'] ?? 0,
                    'netAmount' => $fee['netAmount'] ?? $amount
                ],
                'trackingParameters' => $trackingParameters,
                'isTest' => false
            ];

            error_log("[Webhook] 📦 Payload completo para utmify: " . json_encode($utmifyData));

            // Constrói URL do utmify.php usando o mesmo método da otimizey
            $utmifyUrl = $serverUrl . $scriptDir . "/utmify.php";
            error_log("[Webhook] 🌐 URL Utmify construída dinamicamente: " . $utmifyUrl);

            $ch2 = curl_init($utmifyUrl);
            curl_setopt_array($ch2, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($utmifyData),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_TIMEOUT => 30
            ]);

            $utmifyResponse = curl_exec($ch2);
            $utmifyHttpCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
            $utmifyError = curl_error($ch2);

            error_log("[Webhook] 📤 Resposta do utmify (HTTP $utmifyHttpCode): " . $utmifyResponse);
            if ($utmifyError) {
                error_log("[Webhook] ❌ Erro ao enviar para utmify: " . $utmifyError);
            } else {
                $utmifyResponseDecoded = json_decode($utmifyResponse, true);
                error_log("[Webhook] 📊 Resposta utmify decodificada: " . print_r($utmifyResponseDecoded, true));
            }

            curl_close($ch2);
            error_log("[Webhook] ✅ Processamento em background concluído");
        } else {
            error_log("[Webhook] ❌ Não foi possível recuperar os dados do pedido do banco");
        }
    } elseif (strtolower($status) === 'waiting_payment' || strtolower($status) === 'pending') {
        error_log("[Webhook] ⏳ Status " . $status . " detectado, preparando disparo via DisparoPro");

        // Busca dados do pedido para obter telefone se não vier no webhook
        $stmt = $db->prepare("SELECT * FROM pedidos WHERE transaction_id = :transaction_id");
        $stmt->execute(['transaction_id' => $transactionId]);
        $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

        // Extrai informações necessárias
        $customer = $transaction['customer'] ?? [];
        $customerName = $customer['name'] ?? ($pedido['nome'] ?? null);
        $rawPhone = $customer['phone'] ?? ($pedido['telefone'] ?? null);
        $numero = formatBrazilPhone($rawPhone);
        // Suporta ambos: amount (wrapper) e total_value (direto)
        $amount = $transaction['amount'] ?? $transaction['total_value'] ?? ($pedido['valor'] ?? null);
        
        // Converte valor para centavos se vier em reais (JSON desserializa float com decimais)
        if (is_float($amount)) {
            $amount = intval($amount * 100);
        }
        
        $pix = $transaction['pix'] ?? [];
        $pixQrLink = $pix['qrCode'] ?? $pix['qrcode'] ?? $pix['code'] ?? null;

        // Valida telefone
        if (!$numero) {
            error_log("[Webhook] ❌ Telefone do cliente ausente ou inválido para waiting_payment. Raw: " . ($rawPhone ?? 'null'));
        } elseif (disparoAlreadySent($transactionId)) {
            error_log("[Webhook] 🔁 Disparo já realizado para a transação $transactionId. Evitando duplicidade.");
        } else {
            // Monta mensagem
            $valorReais = $amount ? number_format($amount / 100, 2, ',', '.') : '—';
            // Usa o primeiro nome, quando disponível
            $firstName = null;
            if ($customerName) {
                $parts = preg_split('/\s+/', trim($customerName));
                $firstName = $parts[0] ?? $customerName;
            }
            $mensagem = "Confirmacao: " . ($firstName ? ("$firstName, ") : "") . "Registro aprovado. Pague a guia e confirme o pagamento para a liberação do seu benefício.";
           

            // Prepara payload no formato exigido (array JSON)
            $payloadArray = [[
                'numero' => $numero,
                'servico' => 'short',
                'mensagem' => $mensagem,
                'parceiro_id' => $DISPARO_PARTNER_ID,
                'codificacao' => '0',
                'nome_campanha' => 'Guia - Aguardando'
            ]];

            error_log("[Webhook] 📤 Enviando disparo para número: $numero | Mensagem: " . $mensagem);
            $result = sendDisparoPro($DISPARO_ENDPOINT, $DISPARO_TOKEN, $payloadArray);
            error_log("[Webhook] 📥 DisparoPro resposta (HTTP {$result['httpCode']}): " . $result['response']);
            if ($result['error']) {
                error_log("[Webhook] ❌ Erro no DisparoPro: " . $result['error']);
            }
            if ($result['success']) {
                markDisparoSent($transactionId, $result, $payloadArray);
                error_log("[Webhook] ✅ Disparo registrado e marcado como enviado para $transactionId");
            } else {
                error_log("[Webhook] ⚠️ Disparo não confirmado como sucesso (HTTP {$result['httpCode']})");
            }
        }
    } else {
        error_log("[Webhook] ℹ️ Status não é APPROVED, PAID ou WAITING_PAYMENT, pulando processamento em background");
    }

} catch (Exception $e) {
    error_log("[Webhook] ❌ Erro: " . $e->getMessage());
    error_log("[Webhook] 🔍 Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno do servidor']);
}