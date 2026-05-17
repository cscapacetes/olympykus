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

// Verifica se o payload é válido e adapta o formato MangoFy
if (!$event) {
    error_log("[Webhook] ❌ Payload JSON inválido");
    http_response_code(400);
    echo json_encode(['error' => 'Payload JSON inválido']);
    exit;
}

// Adapta o formato da MangoFy para o formato interno
$paymentId = null;
$status = null;

if (isset($event['payment_code']) && isset($event['payment_status'])) {
    // Formato MangoFy
    $paymentId = $event['payment_code'];
    $status = strtoupper($event['payment_status']); // converte para maiúsculo
    error_log("[Webhook] 📦 Formato MangoFy detectado - payment_code: {$paymentId}, payment_status: {$status}");
} elseif (isset($event['paymentId']) && isset($event['status'])) {
    // Formato antigo (compatibilidade)
    $paymentId = $event['paymentId'];
    $status = $event['status'];
    error_log("[Webhook] 📦 Formato antigo detectado - paymentId: {$paymentId}, status: {$status}");
} else {
    error_log("[Webhook] ❌ Payload inválido recebido. Campos necessários não encontrados");
    error_log("[Webhook] 🔍 Campos disponíveis: " . print_r(array_keys($event ?? []), true));
    http_response_code(400);
    echo json_encode(['error' => 'Payload inválido']);
    exit;
}

function getUpsellTitle($valor) {
    // Mapeamento de valores para nomes de upsell
    switch($valor) {
        case 3990:
            return 'Upsell 1';
        case 1970:
            return 'Upsell 2';
        case 1790:
            return 'Upsell 3';
        case 3980:
            return 'Upsell 5';
        case 2490:
            return 'Upsell 4';
        case 1890:
            return 'Upsell 6';
        case 6190:
            return 'Liberação de Benefício'; // Valor original do checkout
        case 2790:
            return 'Taxa de Verificação'; // Valor padrão do checkoutup
        default:
            return 'Produto ' . ($valor/100); // Para outros valores não mapeados
    }
}

try {
    error_log("[Webhook] ℹ️ Processando pagamento ID: " . $paymentId . " com status: " . $status);
    
    // Conecta ao SQLite
    $dbPath = __DIR__ . '/database.sqlite';
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    error_log("[Webhook] ✅ Conexão com banco de dados estabelecida");

    // Atualiza o status do pagamento no banco de dados
    $stmt = $db->prepare("UPDATE pedidos SET status = :status, updated_at = :updated_at WHERE transaction_id = :transaction_id");
    
    $novoStatus = $status === 'APPROVED' ? 'paid' : $status;
    error_log("[Webhook] 🔄 Atualizando status para: " . $novoStatus);
    
    $result = $stmt->execute([
        'status' => $novoStatus,
        'updated_at' => date('c'),
        'transaction_id' => $paymentId
    ]);

    if ($stmt->rowCount() === 0) {
        error_log("[Webhook] ⚠️ Nenhum pedido encontrado com o ID: " . $paymentId);
        error_log("[Webhook] 🔍 Verificando se o pedido existe no banco...");
        
        // Verifica se o pedido existe
        $checkStmt = $db->prepare("SELECT * FROM pedidos WHERE transaction_id = :transaction_id");
        $checkStmt->execute(['transaction_id' => $paymentId]);
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
    if ($status === 'APPROVED') {
        error_log("[Webhook] ✅ Pagamento aprovado, iniciando processamento em background");

        // Busca os dados do pedido
        $stmt = $db->prepare("SELECT * FROM pedidos WHERE transaction_id = :transaction_id");
        $stmt->execute(['transaction_id' => $paymentId]);
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

            // Extrai os parâmetros UTM, garantindo que todos os campos necessários existam
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

            // Remove valores null para manter apenas os parâmetros que existem
            $trackingParameters = array_filter($trackingParameters, function($value) {
                return $value !== null;
            });

            error_log("[Webhook] 📊 Tracking Parameters processados: " . print_r($trackingParameters, true));

            // Extrai dados do cliente
            $customerPhone = $pedido['telefone'] ?? null;
            
            error_log("[Webhook] 📊 Dados extraídos - Customer: " . $pedido['nome']);
            error_log("[Webhook] 📊 Dados extraídos - Document: " . $pedido['cpf']);
            error_log("[Webhook] 📊 Dados extraídos - Phone: " . $customerPhone);
            error_log("[Webhook] 📊 Dados extraídos - Amount: " . $pedido['valor']);

            // Obtém o título do produto baseado no valor
            $produtoTitulo = getUpsellTitle($pedido['valor']);
            error_log("[Webhook] 🏷️ Título do produto baseado no valor {$pedido['valor']}: " . $produtoTitulo);

            // Dados no formato da nova API Otimizey
            $otimizeyData = [
                'orderId' => $paymentId,
                'platform' => 'MangoFy',
                'paymentMethod' => 'pix',
                'status' => 'paid',
                'name' => $pedido['nome'], // Nome do cliente no nível raiz
                'phone' => $customerPhone, // Telefone no nível raiz
                'createdAt' => $pedido['created_at'],
                'approvedDate' => date('Y-m-d H:i:s'),
                'paidAt' => date('Y-m-d H:i:s'),
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
                        'title' => $produtoTitulo,
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

            $utmifyData = [
                'orderId' => $paymentId,
                'platform' => 'MangoFy',
                'paymentMethod' => 'pix',
                'status' => 'paid',
                'createdAt' => $pedido['created_at'],
                'approvedDate' => date('Y-m-d H:i:s'),
                'paidAt' => date('Y-m-d H:i:s'),
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
                        'title' => $produtoTitulo,
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

            // ── xTracky — paid (server-side, confiável) ──────────────────────
            $xtrackyToken  = 'bbca7b66-5226-4401-ab54-4745017ff017';
            $xtrackyUrl    = 'https://api.xtracky.com/api/integrations/api';
            $xtrackyLogDir = __DIR__ . '/../logs';
            if (!is_dir($xtrackyLogDir)) @mkdir($xtrackyLogDir, 0755, true);
            $xtrackyLog = $xtrackyLogDir . '/xtracky-' . date('Y-m-d') . '.log';

            function xtrackyWriteLog($file, $etapa, $dados = []) {
                $linha = '[' . date('Y-m-d H:i:s') . '] [' . $etapa . ']';
                if (!empty($dados)) $linha .= ' ' . json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                @file_put_contents($file, $linha . "\n", FILE_APPEND | LOCK_EX);
            }

            $amountCents   = (int)($pedido['valor'] ?? 0);
            $utmSourceVal  = $utmParams['utm_source'] ?? '';

            $xtrackyPayload = [
                'orderId'    => (string)$paymentId,
                'amount'     => $amountCents,
                'status'     => 'paid',
                'utm_source' => $utmSourceVal,
                'token'      => $xtrackyToken,
            ];

            xtrackyWriteLog($xtrackyLog, 'PAYLOAD_ENVIADO', $xtrackyPayload);
            error_log("[xTracky] 📤 Enviando paid para xTracky: " . json_encode($xtrackyPayload));

            $chXt = curl_init($xtrackyUrl);
            curl_setopt_array($chXt, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode($xtrackyPayload),
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_TIMEOUT        => 15,
            ]);
            $xtResponse  = curl_exec($chXt);
            $xtHttpCode  = curl_getinfo($chXt, CURLINFO_HTTP_CODE);
            $xtCurlErr   = curl_error($chXt);
            curl_close($chXt);

            xtrackyWriteLog($xtrackyLog, 'RESPOSTA', [
                'http_code' => $xtHttpCode,
                'response'  => $xtResponse,
                'curl_err'  => $xtCurlErr ?: null,
                'orderId'   => (string)$paymentId,
            ]);

            if ($xtHttpCode >= 200 && $xtHttpCode < 300) {
                error_log("[xTracky] ✅ paid enviado com sucesso (HTTP $xtHttpCode): $xtResponse");
            } else {
                error_log("[xTracky] ❌ Erro ao enviar paid (HTTP $xtHttpCode): $xtResponse | curl_err: $xtCurlErr");
            }
            // ── fim xTracky ───────────────────────────────────────────────────

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