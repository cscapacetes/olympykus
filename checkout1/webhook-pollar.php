<?php
header('Content-Type: application/json');

// Habilita o log de erros
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);


date_default_timezone_set('America/Sao_Paulo');

// Recebe o payload do webhook
$payload = file_get_contents('php://input');
$event = json_decode($payload, true);

// Log do payload recebido
error_log("[Webhook] 🔄 Iniciando processamento do webhook");
error_log("[Webhook] 📦 Payload recebido: " . $payload);

// Verifica se o payload é válido - Formato Pollar Gateway
if (!$event || !isset($event['event']) || !isset($event['transaction'])) {
    error_log("[Webhook] ❌ Payload inválido recebido. Campos necessários não encontrados");
    error_log("[Webhook] 🔍 Campos disponíveis: " . print_r(array_keys($event ?? []), true));
    http_response_code(200);
    echo json_encode(['error' => 'Payload inválido']);
    exit;
}

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
    // Extrair os dados relevantes do formato Pollar Gateway
    $eventType = $event['event'];
    $transaction = $event['transaction'];
    $sentAt = $event['sent_at'] ?? date('c');
    
    $transactionId = $transaction['id'];
    $status = $transaction['status'];
    $customer = $transaction['customer'] ?? [];
    $amount = $transaction['amount'] ?? 0;
    $paymentMethod = $transaction['payment_method'] ?? 'PIX';
    $paidAt = $transaction['paid_at'] ?? null;
    $createdAt = $transaction['created_at'] ?? date('c');
    $payer = $transaction['payer'] ?? null;
    $pix = $transaction['pix'] ?? [];
    
    error_log("[Webhook] ℹ️ Evento recebido: " . $eventType);
    error_log("[Webhook] ℹ️ Processando transação ID: " . $transactionId . " com status: " . $status);
    
    // Conecta ao SQLite
    $dbPath = __DIR__ . '/database.sqlite';
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    error_log("[Webhook] ✅ Conexão com banco de dados estabelecida");

    // Atualiza o status do pagamento no banco de dados
    $stmt = $db->prepare("UPDATE pedidos SET status = :status, updated_at = :updated_at WHERE transaction_id = :transaction_id");
    
    // Mapeia os status da Pollar Gateway
    $statusMap = [
        'PAID' => 'paid',
        'WAITING_PAYMENT' => 'pending',
        'REFUNDED' => 'refunded',
        'FAILED' => 'failed'
    ];
    
    $novoStatus = $statusMap[$status] ?? strtolower($status);
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
    
    // Continua o processamento em background
    if ($status === 'PAID') {
        error_log("[Webhook] ✅ Pagamento aprovado, iniciando processamento em background");

        // Busca os dados do pedido
        $stmt = $db->prepare("SELECT * FROM pedidos WHERE transaction_id = :transaction_id");
        $stmt->execute(['transaction_id' => $transactionId]);
        $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($pedido) {
            error_log("[Webhook] ✅ Dados do pedido recuperados do banco");
            error_log("[Webhook] 📊 Dados do pedido: " . print_r($pedido, true));

            // Recupera os parâmetros UTM do banco
            $utmParamsFromDb = json_decode($pedido['utm_params'], true);
            error_log("[Webhook] 📊 UTM Params do banco: " . print_r($utmParamsFromDb, true));
            
            // Monta os tracking parameters
            $trackingParameters = [
                'src' => $utmParamsFromDb['src'] ?? null,
                'sck' => $utmParamsFromDb['sck'] ?? null,
                'utm_source' => $utmParamsFromDb['utm_source'] ?? null,
                'utm_campaign' => $utmParamsFromDb['utm_campaign'] ?? null,
                'utm_medium' => $utmParamsFromDb['utm_medium'] ?? null,
                'utm_content' => $utmParamsFromDb['utm_content'] ?? null,
                'utm_term' => $utmParamsFromDb['utm_term'] ?? null,
                'fbclid' => $utmParamsFromDb['fbclid'] ?? null,
                'gclid' => $utmParamsFromDb['gclid'] ?? null,
                'ttclid' => $utmParamsFromDb['ttclid'] ?? null,
                'xcod' => $utmParamsFromDb['xcod'] ?? null
            ];

            // Remove valores null
            $trackingParameters = array_filter($trackingParameters);

            // Usa dados do webhook (prioridade) ou do banco como fallback
            $customerName = $customer['name'] ?? $pedido['nome'];
            $customerEmail = $customer['email'] ?? $pedido['email'];
            $customerDocument = $customer['document'] ?? $pedido['cpf'];
            $customerPhone = $customer['phone'] ?? $pedido['telefone'] ?? null;
            
            error_log("[Webhook] 📊 Dados finais - Customer: " . $customerName);
            error_log("[Webhook] 📊 Dados finais - Document: " . $customerDocument);
            error_log("[Webhook] 📊 Dados finais - Phone: " . $customerPhone);
            error_log("[Webhook] 📊 Dados finais - Amount: " . $amount);
            error_log("[Webhook] 📊 Dados finais - Paid At: " . $paidAt);

            // Dados no formato da nova API Otimizey
            $otimizeyData = [
                'orderId' => $transactionId,
                'platform' => 'PollarGateway',
                'paymentMethod' => $paymentMethod,
                'status' => 'paid',
                'name' => $customerName, // Nome do cliente no nível raiz
                'phone' => $customerPhone, // Telefone no nível raiz
                'createdAt' => $createdAt,
                'approvedDate' => $paidAt,
                'paidAt' => $paidAt,
                'refundedAt' => null,
                'customer' => [
                    'name' => $customerName,
                    'email' => $customerEmail,
                    'phone' => $customerPhone,
                    'document' => [
                        'number' => $customerDocument,
                        'type' => $customer['document_type'] ?? 'CPF'
                    ],
                    'country' => 'BR',
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? null
                ],
                'items' => [
                    [
                        'id' => uniqid('PROD_'),
                        'title' => getUpsellTitle($amount),
                        'quantity' => 1,
                        'unitPrice' => $amount
                    ]
                ],
                'amount' => $amount,
                'fee' => [
                    'fixedAmount' => $transaction['fee'] ?? 0,
                    'netAmount' => $transaction['net_amount'] ?? $amount
                ],
                'trackingParameters' => $trackingParameters,
                'isTest' => false
            ];
            
            // Adiciona informações do pagador se disponível
            if ($payer) {
                $otimizeyData['payer'] = [
                    'name' => $payer['name'] ?? null,
                    'document' => $payer['document'] ?? null,
                    'bank_ispb' => $payer['ispb'] ?? null,
                    'account_type' => $payer['account_type'] ?? null
                ];
            }
            
            // Adiciona informações do PIX se disponível
            if (!empty($pix['end_to_end'])) {
                $otimizeyData['pix_end_to_end'] = $pix['end_to_end'];
            }

            error_log("[Webhook] 📦 Payload completo para otimizey: " . json_encode($otimizeyData));

            // Envia para otimizey.php
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
            $otimizeyHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $otimizeyCurlError = curl_error($ch);
            
            error_log("[Webhook] 📤 Resposta do otimizey (HTTP $otimizeyHttpCode): " . $otimizeyResponse);
            if ($otimizeyCurlError) {
                error_log("[Webhook] ❌ Erro ao enviar para otimizey: " . $otimizeyCurlError);
            } else {
                error_log("[Webhook] 📊 Resposta otimizey decodificada: " . print_r(json_decode($otimizeyResponse, true), true));
            }
            
            curl_close($ch);

            // ========================================
            // ENVIO PARA UTMIFY.PHP
            // ========================================
            error_log("[Webhook] 📡 Iniciando envio para utmify.php");

            // Estrutura do payload para o utmify
            $utmifyData = [
                'orderId' => $transactionId,
                'platform' => 'PollarGateway',
                'paymentMethod' => $paymentMethod,
                'status' => 'paid',
                'createdAt' => $createdAt,
                'approvedDate' => $paidAt,
                'paidAt' => $paidAt,
                'refundedAt' => null,
                'customer' => [
                    'name' => $customerName,
                    'email' => $customerEmail,
                    'phone' => $customerPhone,
                    'document' => [
                        'number' => $customerDocument,
                        'type' => $customer['document_type'] ?? 'CPF'
                    ],
                    'country' => 'BR',
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? null
                ],
                'items' => [
                    [
                        'id' => uniqid('PROD_'),
                        'title' => getUpsellTitle($amount),
                        'quantity' => 1,
                        'unitPrice' => $amount
                    ]
                ],
                'amount' => $amount,
                'fee' => [
                    'fixedAmount' => $transaction['fee'] ?? 0,
                    'netAmount' => $transaction['net_amount'] ?? $amount
                ],
                'trackingParameters' => $trackingParameters,
                'isTest' => false
            ];
            
            // Adiciona informações do pagador se disponível
            if ($payer) {
                $utmifyData['payer'] = [
                    'name' => $payer['name'] ?? null,
                    'document' => $payer['document'] ?? null,
                    'bank_ispb' => $payer['ispb'] ?? null,
                    'account_type' => $payer['account_type'] ?? null
                ];
            }
            
            // Adiciona informações do PIX se disponível
            if (!empty($pix['end_to_end'])) {
                $utmifyData['pix_end_to_end'] = $pix['end_to_end'];
            }

            error_log("[Webhook] 📦 Payload completo para utmify: " . json_encode($utmifyData));

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
                error_log("[Webhook] ⚠️ Usando fallback SCRIPT_NAME para construir URL");
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
            
            error_log("[Webhook] 📤 Resposta do utmify (HTTP $utmifyHttpCode): " . $utmifyResponse);
            if ($utmifyCurlError) {
                error_log("[Webhook] ❌ Erro ao enviar para utmify: " . $utmifyCurlError);
            } else {
                error_log("[Webhook] 📊 Resposta utmify decodificada: " . print_r(json_decode($utmifyResponse, true), true));
            }
            
            curl_close($ch);
            error_log("[Webhook] ✅ Processamento em background concluído");
        } else {
            error_log("[Webhook] ❌ Não foi possível recuperar os dados do pedido do banco");
        }
    } else {
        error_log("[Webhook] ℹ️ Status não é APPROVED ou PAID, pulando processamento em background");
    }

} catch (Exception $e) {
    error_log("[Webhook] ❌ Erro: " . $e->getMessage());
    error_log("[Webhook] 🔍 Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno do servidor']);
}