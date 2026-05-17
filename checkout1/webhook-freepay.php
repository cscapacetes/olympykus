<?php
header('Content-Type: application/json');

// Habilita o log de erros
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Recebe o payload do webhook
$payload = file_get_contents('php://input');
$event = json_decode($payload, true);

// Log do payload recebido
error_log("[Webhook] 🔄 Iniciando processamento do webhook");
error_log("[Webhook] 📦 Payload recebido: " . $payload);

// Detecta o formato do webhook (FreePay ou GhostsPay/AllowPay v2)
$isFreePay = isset($event['Id']) && isset($event['Status']);
$isAllowPay = isset($event['id']) && isset($event['type']) && isset($event['data']);

if (!$event || (!$isFreePay && !$isAllowPay)) {
    error_log("[Webhook] ❌ Payload inválido recebido. Formato não reconhecido");
    error_log("[Webhook] 🔍 Campos disponíveis: " . print_r(array_keys($event ?? []), true));
    http_response_code(200);
    echo json_encode(['error' => 'Payload inválido']);
    exit;
}

error_log("[Webhook] 🔍 Formato detectado: " . ($isFreePay ? 'FreePay' : 'AllowPay v2'));

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
    // Função para validar e corrigir datas
    function validateAndFixDate($dateString, $fieldName = 'date') {
        if (empty($dateString)) {
            error_log("[Webhook] ⚠️ Data vazia para campo $fieldName, usando data atual");
            return date('Y-m-d H:i:s');
        }
        
        // Verifica se é uma data inválida comum (0001-01-01, 1970-01-01, etc)
        $invalidDates = ['0001-01-01', '1970-01-01', '0000-00-00'];
        foreach ($invalidDates as $invalidDate) {
            if (strpos($dateString, $invalidDate) !== false) {
                error_log("[Webhook] ⚠️ Data inválida detectada ($invalidDate) para campo $fieldName, usando data atual");
                return date('Y-m-d H:i:s');
            }
        }
        
        // Tenta converter a data para timestamp
        $timestamp = strtotime($dateString);
        if ($timestamp === false || $timestamp < 0) {
            error_log("[Webhook] ⚠️ Data não conversível para campo $fieldName: $dateString, usando data atual");
            return date('Y-m-d H:i:s');
        }
        
        // Verifica se a data é muito antiga (mais de 30 dias atrás)
        $thirtyDaysAgo = strtotime('-30 days');
        if ($timestamp < $thirtyDaysAgo) {
            error_log("[Webhook] ⚠️ Data muito antiga para campo $fieldName: $dateString (mais de 30 dias), usando data atual");
            return date('Y-m-d H:i:s');
        }
        
        // Verifica se a data é no futuro (mais de 1 dia à frente)
        $tomorrow = strtotime('+1 day');
        if ($timestamp > $tomorrow) {
            error_log("[Webhook] ⚠️ Data no futuro para campo $fieldName: $dateString, usando data atual");
            return date('Y-m-d H:i:s');
        }
        
        // Data válida, retorna no formato correto
        return date('Y-m-d H:i:s', $timestamp);
    }
    
    // Normaliza os dados de acordo com o formato do webhook
    if ($isFreePay) {
        // Formato FreePay: campos diretos com PascalCase
        $transactionId = $event['Id'] ?? null;
        $status = $event['Status'] ?? null;
        $amount = isset($event['Amount']) ? intval(floatval($event['Amount']) * 100) : null; // Converte de reais para centavos
        
        // Valida e corrige a data de criação
        $rawCreatedAt = $event['CreatedAt'] ?? null;
        $createdAt = validateAndFixDate($rawCreatedAt, 'CreatedAt');
        
        // Valida e corrige a data de pagamento
        $rawPaidAt = $event['PaidAt'] ?? null;
        if (strtoupper($status) === 'PAID' || strtoupper($status) === 'APPROVED') {
            // Se está pago, sempre usa data atual válida
            $paidAt = validateAndFixDate($rawPaidAt, 'PaidAt');
        } else {
            $paidAt = null;
        }
        
        $paymentMethod = strtolower($event['PaymentMethod'] ?? 'pix');
        $items = $event['Items'] ?? [];
        $customer = null; // FreePay não envia dados do customer no webhook
        $fee = null; // FreePay não envia fee no webhook
        $externalId = $event['ExternalId'] ?? null;
        
        error_log("[Webhook] 📊 FreePay - Amount em reais: " . ($event['Amount'] ?? 'N/A') . ", convertido para centavos: " . $amount);
        error_log("[Webhook] 📊 FreePay - CreatedAt original: " . $rawCreatedAt . ", processado: " . $createdAt);
        error_log("[Webhook] 📊 FreePay - PaidAt original: " . $rawPaidAt . ", processado: " . $paidAt);
        error_log("[Webhook] 📊 FreePay - ExternalId: " . $externalId);
    } else {
        // Formato AllowPay v2: estrutura com data wrapper
        $transaction = $event['data'];
        $transactionId = $transaction['id'] ?? null;
        $status = $transaction['status'] ?? null;
        $amount = $transaction['amount'] ?? null; // Já em centavos
        
        // Valida e corrige as datas
        $rawCreatedAt = $transaction['createdAt'] ?? null;
        $createdAt = validateAndFixDate($rawCreatedAt, 'createdAt');
        
        $rawPaidAt = $transaction['paidAt'] ?? null;
        $paidAt = validateAndFixDate($rawPaidAt, 'paidAt');
        
        $paymentMethod = strtolower($transaction['paymentMethod'] ?? 'pix');
        $items = $transaction['items'] ?? [];
        $customer = $transaction['customer'] ?? [];
        $fee = $transaction['fee'] ?? [];
        $externalId = null;
        
        error_log("[Webhook] 📊 AllowPay - CreatedAt original: " . $rawCreatedAt . ", processado: " . $createdAt);
        error_log("[Webhook] 📊 AllowPay - PaidAt original: " . $rawPaidAt . ", processado: " . $paidAt);
    }
    
    error_log("[Webhook] ℹ️ Processando pagamento ID: " . $transactionId . " com status: " . $status);
    
    // Validação dos dados essenciais
    if (!$transactionId || !$status) {
        error_log("[Webhook] ❌ Dados essenciais faltando - Transaction ID: " . ($transactionId ?? 'NULL') . ", Status: " . ($status ?? 'NULL'));
        throw new Exception('Transaction ID ou Status não encontrados no webhook');
    }
    
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
    
    // Continua o processamento em background
    if (strtolower($status) === 'paid' || strtolower($status) === 'approved') {
        error_log("[Webhook] ✅ Pagamento aprovado, iniciando processamento em background");

        // Busca os dados do pedido
        $stmt = $db->prepare("SELECT * FROM pedidos WHERE transaction_id = :transaction_id");
        $stmt->execute(['transaction_id' => $transactionId]);
        $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($pedido) {
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

            // Extrai dados do cliente e transação de acordo com o formato
            if ($isFreePay) {
                // FreePay: busca dados do banco pois o webhook não envia
                $customerName = $pedido['nome'];
                $customerEmail = $pedido['email'];
                $customerPhone = $pedido['telefone'] ?? null;
                $customerDocument = $pedido['cpf'];
            } else {
                // AllowPay v2: extrai do payload
                $customerName = $customer['name'] ?? $pedido['nome'];
                $customerEmail = $customer['email'] ?? $pedido['email'];
                $customerPhone = $customer['phone'] ?? $pedido['telefone'] ?? null;
                $customerDocument = $customer['document'] ?? $pedido['cpf'];
            }
            
            error_log("[Webhook] 📊 Dados extraídos - Customer: " . json_encode([
                'name' => $customerName,
                'email' => $customerEmail,
                'phone' => $customerPhone,
                'document' => $customerDocument
            ]));
            error_log("[Webhook] 📊 Dados extraídos - Amount: " . $amount);
            error_log("[Webhook] 📊 Dados extraídos - PaidAt: " . $paidAt);

            // Dados no formato da nova API Otimizey
            $otimizeyData = [
                'orderId' => $transactionId,
                'platform' => 'Freepay',
                'paymentMethod' => $paymentMethod,
                'status' => 'paid',
                'name' => $customerName, // Nome do cliente no nível raiz
                'phone' => $customerPhone, // Telefone no nível raiz
                'createdAt' => $createdAt, // Já validada
                'approvedDate' => $paidAt, // Já validada
                'paidAt' => $paidAt, // Já validada
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
                        'id' => isset($items[0]) ? ($items[0]['id'] ?? uniqid('PROD_')) : uniqid('PROD_'),
                        'title' => isset($items[0]) ? ($items[0]['title'] ?? getUpsellTitle($amount)) : getUpsellTitle($amount),
                        'quantity' => 1,
                        'unitPrice' => $amount
                    ]
                ],
                'amount' => $amount,
                'fee' => [
                    'fixedAmount' => $isFreePay ? 0 : ($fee['fixedAmount'] ?? 0),
                    'netAmount' => $isFreePay ? $amount : ($fee['netAmount'] ?? $amount)
                ],
                'trackingParameters' => $trackingParameters,
                'isTest' => false
            ];

            // Adiciona externalId se disponível (FreePay)
            if ($externalId) {
                $otimizeyData['externalId'] = $externalId;
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

            // Manter a estrutura do payload para o utmify conforme estava
            $utmifyData = [
                'orderId' => $transactionId,
                'platform' => 'novaera',
                'paymentMethod' => $paymentMethod,
                'status' => 'paid',
                'createdAt' => $createdAt, // Já validada
                'approvedDate' => $paidAt, // Já validada
                'paidAt' => $paidAt, // Já validada
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
                        'id' => isset($items[0]) ? ($items[0]['id'] ?? uniqid('PROD_')) : uniqid('PROD_'),
                        'title' => isset($items[0]) ? ($items[0]['title'] ?? getUpsellTitle($amount)) : getUpsellTitle($amount),
                        'quantity' => 1,
                        'unitPrice' => $amount
                    ]
                ],
                'amount' => $amount,
                'fee' => [
                    'fixedAmount' => $isFreePay ? 0 : ($fee['fixedAmount'] ?? 0),
                    'netAmount' => $isFreePay ? $amount : ($fee['netAmount'] ?? $amount)
                ],
                'trackingParameters' => $trackingParameters,
                'isTest' => false
            ];

            error_log("[Webhook] 📦 Payload completo para utmify: " . json_encode($utmifyData));

            // Constrói URL do utmify.php usando o mesmo método da otimizey
            $utmifyUrl = $serverUrl . $scriptDir . "/utmify.php";
            error_log("[Webhook] 🌐 URL Utmify construída dinamicamente: " . $utmifyUrl);

            // Envia para utmify.php
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