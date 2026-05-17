<?php
header('Content-Type: application/json');

// Habilita o log de erros
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);


date_default_timezone_set('America/Sao_Paulo');

// Recebe o payload do webhook
$payload = file_get_contents('php://input');
$webhookData = json_decode($payload, true);

// Log do payload recebido
error_log("[Webhook Naut] 🔄 Iniciando processamento do webhook");
error_log("[Webhook Naut] 📦 Payload recebido: " . $payload);

// Captura headers da Naut
$webhookSignature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? null;
$webhookEvent = $_SERVER['HTTP_X_WEBHOOK_EVENT'] ?? null;

error_log("[Webhook Naut] 🔑 X-Webhook-Signature: " . ($webhookSignature ?: 'não presente'));
error_log("[Webhook Naut] 📡 X-Webhook-Event: " . ($webhookEvent ?: 'não presente'));

// Verifica se o payload é válido - Formato Naut
if (!$webhookData || !isset($webhookData['event']) || !isset($webhookData['data'])) {
    error_log("[Webhook Naut] ❌ Payload inválido recebido. Campos necessários não encontrados");
    error_log("[Webhook Naut] 🔍 Campos disponíveis: " . print_r(array_keys($webhookData ?? []), true));
    http_response_code(200);
    echo json_encode(['error' => 'Payload inválido']);
    exit;
}

// Verificação de assinatura (opcional mas recomendado)
// Para implementar, você precisa do signingSecret do webhook e descomentar o código abaixo
/*
$signingSecret = 'SEU_SIGNING_SECRET_AQUI'; // Pegue do painel Naut ao criar o webhook
if ($webhookSignature && $signingSecret) {
    $expectedSignature = hash_hmac('sha256', $payload, $signingSecret);
    if (!hash_equals($expectedSignature, $webhookSignature)) {
        error_log("[Webhook Naut] ❌ Assinatura inválida! Possível ataque.");
        http_response_code(401);
        echo json_encode(['error' => 'Assinatura inválida']);
        exit;
    }
    error_log("[Webhook Naut] ✅ Assinatura verificada com sucesso");
}
*/

try {
    // Extrair os dados relevantes do formato Naut
    $event = $webhookData['event'];
    $timestamp = $webhookData['timestamp'];
    $data = $webhookData['data'];
    
    $transactionId = $data['transactionId'] ?? null;
    $grossAmount = $data['grossAmount'] ?? 0;
    $netAmount = $data['netAmount'] ?? 0;
    $paymentMethod = $data['paymentMethod'] ?? 'pix';
    $productId = $data['productId'] ?? null;
    
    error_log("[Webhook Naut] ℹ️ Evento: " . $event);
    error_log("[Webhook Naut] ℹ️ Processando pagamento ID: " . $transactionId);
    error_log("[Webhook Naut] 💰 Valor bruto: " . $grossAmount . " centavos");
    error_log("[Webhook Naut] 💵 Valor líquido: " . $netAmount . " centavos");
    
    if (!$transactionId) {
        error_log("[Webhook Naut] ❌ Transaction ID não encontrado no payload");
        http_response_code(200);
        echo json_encode(['error' => 'Transaction ID não encontrado']);
        exit;
    }
    
    // Conecta ao SQLite
    $dbPath = __DIR__ . '/database.sqlite';
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    error_log("[Webhook Naut] ✅ Conexão com banco de dados estabelecida");

    // Mapeia os eventos da Naut para status do banco
    $statusMap = [
        'transaction.paid' => 'paid',
        'transaction.refunded' => 'refunded',
        'transaction.partially_refunded' => 'partially_refunded',
        'transaction.disputed' => 'disputed'
    ];
    
    $novoStatus = $statusMap[$event] ?? 'pending';
    error_log("[Webhook Naut] 🔄 Mapeando evento '$event' para status: " . $novoStatus);

    // Atualiza o status do pagamento no banco de dados
    $stmt = $db->prepare("UPDATE pedidos SET status = :status, updated_at = :updated_at WHERE transaction_id = :transaction_id");
    
    $result = $stmt->execute([
        'status' => $novoStatus,
        'updated_at' => date('c'),
        'transaction_id' => $transactionId
    ]);

    if ($stmt->rowCount() === 0) {
        error_log("[Webhook Naut] ⚠️ Nenhum pedido encontrado com o ID: " . $transactionId);
        error_log("[Webhook Naut] 🔍 Verificando se o pedido existe no banco...");
        
        // Verifica se o pedido existe
        $checkStmt = $db->prepare("SELECT * FROM pedidos WHERE transaction_id = :transaction_id");
        $checkStmt->execute(['transaction_id' => $transactionId]);
        $pedidoExiste = $checkStmt->fetch();
        
        if ($pedidoExiste) {
            error_log("[Webhook Naut] ℹ️ Pedido encontrado mas status não foi alterado. Status atual: " . $pedidoExiste['status']);
        } else {
            error_log("[Webhook Naut] ❌ Pedido não existe no banco de dados");
        }
        
        http_response_code(200);
        echo json_encode(['error' => 'Pedido não encontrado']);
        exit;
    }

    error_log("[Webhook Naut] ✅ Status atualizado com sucesso no banco de dados");

    // Responde imediatamente ao webhook
    http_response_code(200);
    echo json_encode(['success' => true]);
    
    // Fecha a conexão com o cliente
    if (function_exists('fastcgi_finish_request')) {
        error_log("[Webhook Naut] 📤 Fechando conexão com o cliente via fastcgi_finish_request");
        fastcgi_finish_request();
    } else {
        error_log("[Webhook Naut] ⚠️ fastcgi_finish_request não disponível");
    }
    
    // Continua o processamento em background apenas para transaction.paid
    if ($event === 'transaction.paid') {
        error_log("[Webhook Naut] ✅ Pagamento aprovado (transaction.paid), iniciando processamento em background");

        // Busca os dados do pedido
        $stmt = $db->prepare("SELECT * FROM pedidos WHERE transaction_id = :transaction_id");
        $stmt->execute(['transaction_id' => $transactionId]);
        $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($pedido) {
            error_log("[Webhook Naut] ✅ Dados do pedido recuperados do banco");
            error_log("[Webhook Naut] 📊 Dados do pedido: " . print_r($pedido, true));

            // Usa os parâmetros UTM salvos no banco
            $utmParamsFromDb = json_decode($pedido['utm_params'], true);
            error_log("[Webhook Naut] 📊 UTM Params do banco: " . print_r($utmParamsFromDb, true));
            
            // Usa metadata do webhook se disponível, caso contrário usa do banco
            $metadata = $data['metadata'] ?? [];
            
            $trackingParameters = [
                'src' => $metadata['src'] ?? $utmParamsFromDb['src'] ?? null,
                'sck' => $metadata['sck'] ?? $utmParamsFromDb['sck'] ?? null,
                'utm_source' => $metadata['utm_source'] ?? $utmParamsFromDb['utm_source'] ?? null,
                'utm_campaign' => $metadata['utm_campaign'] ?? $utmParamsFromDb['utm_campaign'] ?? null,
                'utm_medium' => $metadata['utm_medium'] ?? $utmParamsFromDb['utm_medium'] ?? null,
                'utm_content' => $metadata['utm_content'] ?? $utmParamsFromDb['utm_content'] ?? null,
                'utm_term' => $metadata['utm_term'] ?? $utmParamsFromDb['utm_term'] ?? null,
                'fbclid' => $utmParamsFromDb['fbclid'] ?? null,
                'gclid' => $utmParamsFromDb['gclid'] ?? null,
                'ttclid' => $utmParamsFromDb['ttclid'] ?? null,
                'xcod' => $utmParamsFromDb['xcod'] ?? null
            ];

            // Remove valores null
            $trackingParameters = array_filter($trackingParameters);

            // Usa dados do banco
            $customerName = $pedido['nome'];
            $customerEmail = $pedido['email'];
            $customerDocument = $pedido['cpf'];
            $customerPhone = $pedido['telefone'] ?? null;
            
            // Usa o grossAmount do webhook
            $finalAmount = $grossAmount;
            
            error_log("[Webhook Naut] 📊 Dados finais - Customer: " . $customerName);
            error_log("[Webhook Naut] 📊 Dados finais - Document: " . $customerDocument);
            error_log("[Webhook Naut] 📊 Dados finais - Phone: " . $customerPhone);
            error_log("[Webhook Naut] 📊 Dados finais - Gross Amount: " . $finalAmount);
            error_log("[Webhook Naut] 📊 Dados finais - Net Amount: " . $netAmount);
            error_log("[Webhook Naut] 📊 Dados finais - Timestamp: " . $timestamp);

            // Dados no formato da nova API Otimizey
            $otimizeyData = [
                'orderId' => $transactionId,
                'platform' => 'Naut',
                'paymentMethod' => $paymentMethod,
                'status' => 'paid',
                'name' => $customerName, // Nome do cliente no nível raiz
                'phone' => $customerPhone, // Telefone no nível raiz
                'createdAt' => $pedido['created_at'],
                'approvedDate' => date('Y-m-d H:i:s', strtotime($timestamp)),
                'paidAt' => date('Y-m-d H:i:s', strtotime($timestamp)),
                'refundedAt' => null,
                'customer' => [
                    'name' => $customerName,
                    'email' => $customerEmail,
                    'phone' => $customerPhone,
                    'document' => [
                        'number' => $customerDocument,
                        'type' => 'CPF'
                    ],
                    'country' => 'BR',
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? null
                ],
                'items' => [
                    [
                        'id' => $productId ?? uniqid('PROD_'),
                        'title' => 'Pagamento PIX',
                        'quantity' => 1,
                        'unitPrice' => $finalAmount
                    ]
                ],
                'amount' => $finalAmount,
                'fee' => [
                    'fixedAmount' => $grossAmount - $netAmount,
                    'netAmount' => $netAmount
                ],
                'trackingParameters' => $trackingParameters,
                'isTest' => false
            ];

            error_log("[Webhook Naut] 📦 Payload completo para otimizey: " . json_encode($otimizeyData));

            // Envia para otimizey.php
            $serverUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
            
            // Método 1: Usar DOCUMENT_ROOT (mais comum)
            $scriptDir = str_replace($_SERVER['DOCUMENT_ROOT'], '', __DIR__);
            $otimizeyUrl = $serverUrl . $scriptDir . "/otimizey.php";
            
            // Método 2: Fallback usando SCRIPT_NAME se o método 1 falhar
            if (empty($scriptDir) || $scriptDir === __DIR__) {
                $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
                $otimizeyUrl = $serverUrl . $scriptDir . "/otimizey.php";
                error_log("[Webhook Naut] ⚠️ Usando fallback SCRIPT_NAME para construir URL");
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
            $otimizeyHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $otimizeyCurlError = curl_error($ch);
            
            error_log("[Webhook Naut] 📤 Resposta do otimizey (HTTP $otimizeyHttpCode): " . $otimizeyResponse);
            if ($otimizeyCurlError) {
                error_log("[Webhook Naut] ❌ Erro ao enviar para otimizey: " . $otimizeyCurlError);
            } else {
                error_log("[Webhook Naut] 📊 Resposta otimizey decodificada: " . print_r(json_decode($otimizeyResponse, true), true));
            }
            
            curl_close($ch);

            // ========================================
            // ENVIO PARA UTMIFY.PHP
            // ========================================
            error_log("[Webhook Naut] 📡 Iniciando envio para utmify.php");

            // Estrutura do payload para o utmify
            $utmifyData = [
                'orderId' => $transactionId,
                'platform' => 'Naut',
                'paymentMethod' => $paymentMethod,
                'status' => 'paid',
                'createdAt' => $pedido['created_at'],
                'approvedDate' => date('Y-m-d H:i:s', strtotime($timestamp)),
                'paidAt' => date('Y-m-d H:i:s', strtotime($timestamp)),
                'refundedAt' => null,
                'customer' => [
                    'name' => $customerName,
                    'email' => $customerEmail,
                    'phone' => $customerPhone,
                    'document' => [
                        'number' => $customerDocument,
                        'type' => 'CPF'
                    ],
                    'country' => 'BR',
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? null
                ],
                'items' => [
                    [
                        'id' => $productId ?? uniqid('PROD_'),
                        'title' => 'Pagamento PIX',
                        'quantity' => 1,
                        'unitPrice' => $finalAmount
                    ]
                ],
                'amount' => $finalAmount,
                'fee' => [
                    'fixedAmount' => $grossAmount - $netAmount,
                    'netAmount' => $netAmount
                ],
                'trackingParameters' => $trackingParameters,
                'isTest' => false
            ];

            error_log("[Webhook Naut] 📦 Payload completo para utmify: " . json_encode($utmifyData));

            // Envia para utmify.php
               // Construir URL do utmify.php de forma robusta com fallbacks
            $serverUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
            
            // Método 1: Usar DOCUMENT_ROOT (mais comum)
            $scriptDir = str_replace($_SERVER['DOCUMENT_ROOT'], '', __DIR__);
            $utmifyUrl = $serverUrl . $scriptDir . "/utmify.php";
            
            // Método 2: Fallback usando SCRIPT_NAME se o método 1 falhar
            if (empty($scriptDir) || $scriptDir === __DIR__) {
                $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
                $utmifyUrl = $serverUrl . $scriptDir . "/utmify.php";
                error_log("[Webhook Naut] ⚠️ Usando fallback SCRIPT_NAME para construir URL");
            }

            $ch = curl_init($utmifyUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($utmifyData),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_TIMEOUT => 30
            ]);

            $utmifyResponse = curl_exec($ch);
            $utmifyHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $utmifyCurlError = curl_error($ch);
            
            error_log("[Webhook Naut] 📤 Resposta do utmify (HTTP $utmifyHttpCode): " . $utmifyResponse);
            if ($utmifyCurlError) {
                error_log("[Webhook Naut] ❌ Erro ao enviar para utmify: " . $utmifyCurlError);
            } else {
                error_log("[Webhook Naut] 📊 Resposta utmify decodificada: " . print_r(json_decode($utmifyResponse, true), true));
            }
            
            curl_close($ch);
            error_log("[Webhook Naut] ✅ Processamento em background concluído");
        } else {
            error_log("[Webhook Naut] ❌ Não foi possível recuperar os dados do pedido do banco");
        }
    } else {
        error_log("[Webhook Naut] ℹ️ Evento '$event' não requer processamento em background");
    }

} catch (Exception $e) {
    error_log("[Webhook Naut] ❌ Erro: " . $e->getMessage());
    error_log("[Webhook Naut] 🔍 Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno do servidor']);
}