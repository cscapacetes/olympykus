<?php
// Habilita exibição de erros para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php-errors.log');

// ========== CARREGA CONTAS DO ARQUIVO JSON ==========
$configFile = __DIR__ . '/tiktok-accounts.json';

function loadTikTokAccounts($configFile) {
    if (!file_exists($configFile)) {
        error_log("[TikTok] ⚠️ Arquivo de configuração não encontrado: $configFile");
        return [];
    }
    
    $content = file_get_contents($configFile);
    $accounts = json_decode($content, true);
    
    if (!$accounts) {
        error_log("[TikTok] ⚠️ Erro ao decodificar JSON de contas");
        return [];
    }
    
    // Filtra apenas contas ativas
    $activeAccounts = array_filter($accounts, function($account) {
        return isset($account['active']) && $account['active'] === true;
    });
    
    error_log("[TikTok] ✅ Carregadas " . count($activeAccounts) . " contas ativas de " . count($accounts) . " total");
    
    return array_values($activeAccounts);
}

$TIKTOK_ACCOUNTS = loadTikTokAccounts($configFile);

define('TIKTOK_API_VERSION', 'v1.3');
define('TIKTOK_API_URL', 'https://business-api.tiktok.com/open_api/' . TIKTOK_API_VERSION . '/event/track/');

// Headers para log
header('Content-Type: application/json; charset=utf-8');

/**
 * Função principal para enviar evento ao TikTok (TODAS AS CONTAS)
 */
function sendTikTokEvent($eventData) {
    global $TIKTOK_ACCOUNTS;
    
    // Valida dados obrigatórios
    if (empty($eventData['event'])) {
        return [
            'success' => false,
            'error' => 'event é obrigatório'
        ];
    }
    
    // Valida se há contas configuradas
    if (empty($TIKTOK_ACCOUNTS)) {
        return [
            'success' => false,
            'error' => 'Nenhuma conta TikTok configurada'
        ];
    }
    
    // Envia para todas as contas simultaneamente
    $results = [];
    foreach ($TIKTOK_ACCOUNTS as $index => $account) {
        $pixelCode = $account['pixel_id'];
        $accessToken = $account['access_token'];
        $accountName = $account['name'] ?? "Conta #" . ($index + 1);
        
        if (empty($pixelCode) || empty($accessToken)) {
            $results[] = [
                'account' => $accountName,
                'success' => false,
                'error' => 'Pixel ID ou Access Token não configurado'
            ];
            continue;
        }
        
        // Prepara payload conforme Payload Helper do TikTok
        $payload = [
            'event' => $eventData['event'],
            'event_time' => $eventData['timestamp'] ?? time(),
            'user' => [],
            'properties' => [],
            'page' => [
                'url' => $eventData['page_url'] ?? $_SERVER['HTTP_REFERER'] ?? '',
                'referrer' => $_SERVER['HTTP_REFERER'] ?? null
            ]
        ];
        
        // Adiciona test_event_code se fornecido (para Test Events)
        if (!empty($eventData['test_event_code'])) {
            $payload['test_event_code'] = $eventData['test_event_code'];
        }
        
        // Adiciona dados do usuário (hashed) se disponível
        if (!empty($eventData['user'])) {
            $hashedUser = hashUserData($eventData['user']);
            // Só adiciona se tiver pelo menos um campo válido
            if (!empty($hashedUser)) {
                $payload['user'] = array_merge($payload['user'], $hashedUser);
            }
        }
        
        // Adiciona ttclid dentro de user (conforme Payload Helper do TikTok)
        if (!empty($eventData['ttclid'])) {
            $payload['user']['ttclid'] = $eventData['ttclid'];
        }
        
        // Adiciona external_id se disponível
        if (!empty($eventData['external_id'])) {
            $payload['user']['external_id'] = hash('sha256', (string) $eventData['external_id']);
        }
        
        // Adiciona properties do evento (OBRIGATÓRIOS para CompletePayment)
        if (!empty($eventData['properties'])) {
            $props = $eventData['properties'];
            
            // Campos obrigatórios/recomendados
            if (isset($props['currency'])) {
                $payload['properties']['currency'] = $props['currency'];
            }
            if (isset($props['value'])) {
                $payload['properties']['value'] = (float) $props['value'];
            }
            if (isset($props['content_type'])) {
                $payload['properties']['content_type'] = $props['content_type'];
            }
            if (isset($props['content_id'])) {
                $payload['properties']['content_id'] = (string) $props['content_id'];
            }
            if (isset($props['content_name'])) {
                $payload['properties']['content_name'] = $props['content_name'];
            }
            if (isset($props['quantity'])) {
                $payload['properties']['quantity'] = (int) $props['quantity'];
            }
            
            // Contents array (para múltiplos produtos)
            if (isset($props['contents']) && is_array($props['contents'])) {
                $payload['properties']['contents'] = $props['contents'];
            }
        }
        
        // Log do payload antes de enviar
        error_log('=== PAYLOAD DEBUG [' . $accountName . '] ===');
        error_log('Pixel Code: ' . $pixelCode);
        error_log('Event: ' . $eventData['event']);
        error_log('Timestamp: ' . ($eventData['timestamp'] ?? time()));
        error_log('ttclid: ' . ($eventData['ttclid'] ?? 'não fornecido'));
        
        // Envia para esta conta
        $response = sendToTikTok($payload, $pixelCode, $accessToken);
        $response['account'] = $accountName;
        $results[] = $response;
        
        // Log para debug
        logEvent([
            'timestamp' => date('Y-m-d H:i:s'),
            'account' => $accountName,
            'pixel_id' => $pixelCode,
            'event' => $eventData['event'],
            'ttclid' => $eventData['ttclid'] ?? 'não fornecido',
            'value' => $eventData['properties']['value'] ?? 0,
            'response' => $response
        ]);
    }
    
    // Retorna resumo de todas as contas
    $allSuccess = true;
    foreach ($results as $result) {
        if (!$result['success']) {
            $allSuccess = false;
            break;
        }
    }
    
    return [
        'success' => $allSuccess,
        'total_accounts' => count($TIKTOK_ACCOUNTS),
        'results' => $results
    ];
}

/**
 * Envia requisição para TikTok Events API
 */
function sendToTikTok($payload, $pixelId, $accessToken) {
    // Estrutura correta conforme Payload Helper do TikTok
    $requestBody = json_encode([
        'event_source' => 'web',
        'event_source_id' => $pixelId,
        'data' => [$payload]
    ]);
    
    error_log('=== Enviando para TikTok ===');
    error_log('URL: ' . TIKTOK_API_URL);
    error_log('Pixel ID: ' . $pixelId);
    error_log('Body: ' . $requestBody);
    
    $ch = curl_init(TIKTOK_API_URL);
    
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $requestBody,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Access-Token: ' . $accessToken
        ],
        CURLOPT_TIMEOUT => 10
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    error_log('Response HTTP Code: ' . $httpCode);
    error_log('Response Body: ' . $response);
    
    if ($error) {
        error_log('CURL Error: ' . $error);
        return [
            'success' => false,
            'error' => $error,
            'http_code' => $httpCode
        ];
    }
    
    $decoded = json_decode($response, true);
    
    return [
        'success' => $httpCode === 200 && (!isset($decoded['code']) || $decoded['code'] === 0),
        'http_code' => $httpCode,
        'response' => $decoded,
        'message' => $decoded['message'] ?? 'Evento enviado'
    ];
}

/**
 * Hash de dados do usuário (GDPR/LGPD compliant)
 */
function hashUserData($userData) {
    $hashed = [];
    
    if (!empty($userData['email']) && filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
        $hashed['email'] = hash('sha256', strtolower(trim($userData['email'])));
    }
    
    if (!empty($userData['phone'])) {
        // Remove caracteres não numéricos
        $phone = preg_replace('/[^0-9]/', '', $userData['phone']);
        // Só adiciona se tiver pelo menos 10 dígitos
        if (strlen($phone) >= 10) {
            $hashed['phone_number'] = hash('sha256', $phone);
        }
    }
    
    // external_id (ID do usuário no seu sistema)
    if (!empty($userData['external_id'])) {
        $hashed['external_id'] = hash('sha256', (string) $userData['external_id']);
    }
    
    return $hashed;
}

/**
 * Obtém IP real do cliente (considera proxies)
 */
function getClientIP() {
    $ipKeys = [
        'HTTP_CF_CONNECTING_IP', // Cloudflare
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'REMOTE_ADDR'
    ];
    
    foreach ($ipKeys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = $_SERVER[$key];
            // Se for lista de IPs, pega o primeiro
            if (strpos($ip, ',') !== false) {
                $ip = explode(',', $ip)[0];
            }
            return trim($ip);
        }
    }
    
    return '0.0.0.0';
}

/**
 * Log de eventos (para debug e auditoria)
 */
function logEvent($data) {
    $logFile = __DIR__ . '/logs/tiktok-events.log';
    $logDir = dirname($logFile);
    
    // Cria diretório se não existir
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logEntry = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n---\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

// ========== ENDPOINT API ==========

// Só aceita POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido. Use POST.']);
    exit;
}

try {
    // Lê dados do POST
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    // Log de debug
    error_log('=== TikTok Events API Debug ===');
    error_log('Input recebido: ' . $input);
    error_log('Data decodificado: ' . print_r($data, true));
    error_log('Total de contas configuradas: ' . count($TIKTOK_ACCOUNTS));

    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'JSON inválido']);
        exit;
    }

    // Envia evento
    $result = sendTikTokEvent($data);

    // Retorna resposta
    http_response_code($result['success'] ? 200 : 200); // Sempre retorna 200 para evitar erro no frontend
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log('ERRO FATAL: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    
    http_response_code(200); // Retorna 200 mas com erro no JSON
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>
