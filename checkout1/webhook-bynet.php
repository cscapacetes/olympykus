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

// Verifica se o payload é válido (formato Bynet)
if (!$event || !isset($event['objectId']) || !isset($event['data']['status'])) {
    error_log("[Webhook] ❌ Payload inválido recebido. Campos necessários não encontrados");
    error_log("[Webhook] 🔍 Campos disponíveis: " . print_r(array_keys($event ?? []), true));
    http_response_code(400);
    echo json_encode(['error' => 'Payload inválido']);
    exit;
}

// Valida se é uma transação (não um reembolso)
if (isset($event['data']['type']) && $event['data']['type'] !== 'transaction') {
    error_log("[Webhook] ℹ️ Evento não é uma transação, ignorando. Tipo: " . $event['data']['type']);
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Evento não processado (não é transação)']);
    exit;
}

try {
    error_log("[Webhook] ℹ️ Processando pagamento ID: " . $event['objectId'] . " com status: " . $event['data']['status']);
    
    // Conecta ao SQLite
    $dbPath = __DIR__ . '/database.sqlite';
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    error_log("[Webhook] ✅ Conexão com banco de dados estabelecida");

    // Atualiza o status do pagamento no banco de dados
    $stmt = $db->prepare("UPDATE pedidos SET status = :status, updated_at = :updated_at WHERE transaction_id = :transaction_id");
    
    // Mapeia o status da Bynet para nosso formato interno
    $statusMapping = [
        'paid' => 'paid',
        'waiting_payment' => 'pending',
        'refused' => 'refused',
        'refunded' => 'refunded',
        'chargedback' => 'chargedback',
        'analyzing' => 'analyzing'
    ];
    
    $statusRecebido = $event['data']['status'];
    $novoStatus = $statusMapping[$statusRecebido] ?? $statusRecebido;
    error_log("[Webhook] 🔄 Atualizando status de '$statusRecebido' para: $novoStatus");
    
    $result = $stmt->execute([
        'status' => $novoStatus,
        'updated_at' => date('c'),
        'transaction_id' => $event['objectId']
    ]);

    if ($stmt->rowCount() === 0) {
        error_log("[Webhook] ⚠️ Nenhum pedido encontrado com o ID: " . $event['objectId']);
        error_log("[Webhook] 🔍 Verificando se o pedido existe no banco...");
        
        // Verifica se o pedido existe
        $checkStmt = $db->prepare("SELECT * FROM pedidos WHERE transaction_id = :transaction_id");
        $checkStmt->execute(['transaction_id' => $event['objectId']]);
        $pedidoExiste = $checkStmt->fetch();
        
        if ($pedidoExiste) {
            error_log("[Webhook] ℹ️ Pedido encontrado mas status não foi alterado. Status atual: " . $pedidoExiste['status']);
        } else {
            error_log("[Webhook] ❌ Pedido não existe no banco de dados");
        }
        
        http_response_code(404);
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
    if ($novoStatus === 'paid') {
        error_log("[Webhook] ✅ Pagamento aprovado, iniciando processamento em background");

        // Busca os dados do pedido do banco de dados
        $stmt = $db->prepare("SELECT * FROM pedidos WHERE transaction_id = :transaction_id");
        $stmt->execute(['transaction_id' => $event['objectId']]);
        $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($pedido) {
            error_log("[Webhook] ✅ Dados do pedido recuperados do banco");
            error_log("[Webhook] 📊 Dados do pedido: " . print_r($pedido, true));

            // Decodifica os parâmetros UTM do banco
            $utmParams = json_decode($pedido['utm_params'], true);
            error_log("[Webhook] 📊 UTM Params brutos do banco: " . print_r($utmParams, true));
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("[Webhook] ⚠️ Erro ao decodificar UTM params: " . json_last_error_msg());
                $utmParams = []; // Fallback para array vazio
            }

            // Extrai os parâmetros UTM
            $trackingParameters = [
                'src' => $utmParams['utm_source'] ?? $utmParams['src'] ?? null,
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

            // Pega a data de pagamento do webhook ou usa a atual
            $paidAt = $event['data']['paidAt'] ?? date('Y-m-d\TH:i:s.000\Z');
            $endToEndId = $event['data']['endToEndId'] ?? null;
            $customerPhone = $pedido['telefone'] ?? null;
            
            error_log("[Webhook] 💰 Pagamento efetuado em: $paidAt");
            if ($endToEndId) {
                error_log("[Webhook] 🔑 End-to-End ID: $endToEndId");
            }
            error_log("[Webhook] 📊 Dados extraídos - Customer: " . $pedido['nome']);
            error_log("[Webhook] 📊 Dados extraídos - Document: " . $pedido['cpf']);
            error_log("[Webhook] 📊 Dados extraídos - Phone: " . $customerPhone);
            error_log("[Webhook] 📊 Dados extraídos - Amount: " . $pedido['valor']);

            // Função auxiliar para obter título do produto
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
                        return 'Pagamento'; 
                }
            }

            // Dados no formato da nova API Otimizey
            $otimizeyData = [
                'orderId' => $event['objectId'],
                'platform' => 'TechByNet',
                'paymentMethod' => 'pix',
                'status' => 'paid',
                'name' => $pedido['nome'], // Nome do cliente no nível raiz
                'phone' => $customerPhone, // Telefone no nível raiz
                'createdAt' => $pedido['created_at'],
                'approvedDate' => $paidAt,
                'paidAt' => $paidAt,
                'refundedAt' => null,
                'customer' => [
                    'name' => $pedido['nome'],
                    'email' => $pedido['email'],
                    'phone' => $customerPhone,
                    'document' => [
                        'number' => $pedido['cpf'],
                        'type' => 'CPF'
                    ],
                    'country' => 'BR',
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? null
                ],
                'items' => [
                    [
                        'id' => uniqid('PROD_'),
                        'title' => getUpsellTitle($pedido['valor']),
                        'quantity' => 1,
                        'unitPrice' => $pedido['valor']
                    ]
                ],
                'amount' => $pedido['valor'],
                'fee' => [
                    'fixedAmount' => 0,
                    'netAmount' => $pedido['valor']
                ],
                'trackingParameters' => $trackingParameters,
                'isTest' => false
            ];

            // Adiciona endToEndId se disponível
            if ($endToEndId) {
                $otimizeyData['endToEndId'] = $endToEndId;
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
            // ENVIO PARA XTRACKY
            // ========================================
            error_log("[Webhook] 📡 Iniciando envio para xTracky");

            // Prepara payload para xTracky (formato compatível)
            $xTrackyData = [
                'orderId' => $event['objectId'],
                'amount' => $pedido['valor'], // Usa o valor do banco
                'status' => 'paid',
                'utm_source' => $utmParams['utm_source'] ?? ''
            ];

            error_log("[Webhook] 📦 Payload para xTracky: " . json_encode($xTrackyData));

            // Envia para xTracky
            $chXTracky = curl_init('https://api.xtracky.com/api/integrations/api');
            curl_setopt_array($chXTracky, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($xTrackyData),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_TIMEOUT => 10
            ]);

            $xTrackyResponse = curl_exec($chXTracky);
            $xTrackyHttpCode = curl_getinfo($chXTracky, CURLINFO_HTTP_CODE);
            $xTrackyError = curl_error($chXTracky);
            curl_close($chXTracky);

            error_log("[Webhook] 📥 Resposta xTracky - HTTP Code: $xTrackyHttpCode");
            if (!empty($xTrackyError)) {
                error_log("[Webhook] ⚠️ Erro ao enviar para xTracky: $xTrackyError");
            } else {
                error_log("[Webhook] ✅ Resposta xTracky: " . $xTrackyResponse);
            }

            if ($xTrackyHttpCode === 200 || $xTrackyHttpCode === 201) {
                error_log("[Webhook] ✅ Conversão 'paid' enviada com sucesso para xTracky");
            } else {
                error_log("[Webhook] ⚠️ xTracky retornou código não-200: $xTrackyHttpCode - " . $xTrackyResponse);
            }

            // ========================================
            // ENVIO PARA UTMIFY.PHP
            // ========================================
            error_log("[Webhook] 📡 Iniciando envio para utmify.php");

            // Prepara payload para Utmify (formato completo)
            $utmifyData = [
                'orderId' => $event['objectId'],
                'platform' => 'TechByNet',
                'paymentMethod' => 'pix',
                'status' => 'paid',
                'createdAt' => $pedido['created_at'],
                'approvedDate' => $paidAt,
                'paidAt' => $paidAt,
                'refundedAt' => null,
                'customer' => [
                    'name' => $pedido['nome'],
                    'email' => $pedido['email'],
                    'phone' => $customerPhone,
                    'document' => [
                        'number' => $pedido['cpf'],
                        'type' => 'CPF'
                    ],
                    'country' => 'BR',
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? null
                ],
                'items' => [
                    [
                        'id' => uniqid('PROD_'),
                        'title' => getUpsellTitle($pedido['valor']),
                        'quantity' => 1,
                        'unitPrice' => $pedido['valor']
                    ]
                ],
                'amount' => $pedido['valor'],
                'fee' => [
                    'fixedAmount' => 0,
                    'netAmount' => $pedido['valor']
                ],
                'trackingParameters' => $trackingParameters,
                'isTest' => false
            ];

            // Adiciona endToEndId se disponível
            if ($endToEndId) {
                $utmifyData['endToEndId'] = $endToEndId;
            }

            error_log("[Webhook] 📦 Payload completo para utmify: " . json_encode($utmifyData));

            // Envia para utmify.php
          $serverUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
            
            // Método 1: Usar DOCUMENT_ROOT (mais comum)
            $scriptDir = str_replace($_SERVER['DOCUMENT_ROOT'], '', __DIR__);
            $utmifyUrl = $serverUrl . $scriptDir . "/utmify.php";
            
            // Método 2: Fallback usando SCRIPT_NAME se o método 1 falhar
            if (empty($scriptDir) || $scriptDir === __DIR__) {
                $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
                $utmifyUrl = $serverUrl . $scriptDir . "/utmify.php";
                error_log("[Webhook QuantumPay] ⚠️ Usando fallback SCRIPT_NAME para construir URL");
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
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            
            error_log("[Webhook] 📤 Resposta do utmify (HTTP $httpCode): " . $utmifyResponse);
            if ($curlError) {
                error_log("[Webhook] ❌ Erro ao enviar para utmify: " . $curlError);
            } else {
                error_log("[Webhook] 📊 Resposta decodificada: " . print_r(json_decode($utmifyResponse, true), true));
            }
            
            curl_close($ch);
            error_log("[Webhook] ✅ Processamento em background concluído");
        } else {
            error_log("[Webhook] ❌ Não foi possível recuperar os dados do pedido do banco");
        }
    } else {
        error_log("[Webhook] ℹ️ Status não é APPROVED, pulando processamento em background");
    }

} catch (Exception $e) {
    error_log("[Webhook] ❌ Erro: " . $e->getMessage());
    error_log("[Webhook] 🔍 Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno do servidor']);
} 