<?php
/**
 * CONTROLADOR DE LOGS UTMIFY
 */
header('Content-Type: application/json');

// Tentar encontrar a pasta logs
$possiblePaths = [
    __DIR__ . '/logs',              // Dentro do checkout
    __DIR__ . '/../logs',           // Pasta pai (raiz)
    dirname(dirname(__FILE__)) . '/logs'  // Alternativa
];

$logsDir = null;
foreach ($possiblePaths as $path) {
    if (is_dir($path)) {
        $logsDir = $path;
        break;
    }
}

if (!$logsDir) {
    $logsDir = __DIR__ . '/logs';
}

// Ler estatísticas do dia
if (isset($_GET['action']) && $_GET['action'] === 'get_stats') {
    $date = $_GET['date'] ?? date('Y-m-d');
    $logFile = $logsDir . '/utmify-' . $date . '.log';
    $logFilePendente = $logsDir . '/utmify-pendente-' . $date . '.log';
    
    $stats = [
        'total' => 0,
        'success' => 0,
        'error' => 0,
        'date' => $date
    ];
    
    // Processar ambos os arquivos de log
    $files = [$logFile, $logFilePendente];
    
    foreach ($files as $file) {
        if (!file_exists($file)) continue;
        
        $content = file_get_contents($file);
        $blocks = explode('----------------------------------------', $content);
        
        foreach ($blocks as $blockText) {
            $blockText = trim($blockText);
            if (empty($blockText)) continue;
            
            // Verificar se é uma resposta da API
            if (strpos($blockText, 'Resposta da API Utmify') !== false) {
                $stats['total']++;
                
                // Verificar se foi sucesso (HTTP 200)
                if (preg_match('/"http_code":\s*200/', $blockText)) {
                    $stats['success']++;
                } else {
                    $stats['error']++;
                }
            } elseif (preg_match('/Erro\s*\r?\nDados:/s', $blockText)) {
                $stats['total']++;
                $stats['error']++;
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'stats' => $stats
    ]);
    exit;
}

// Ler último envio de um log específico
if (isset($_GET['action']) && $_GET['action'] === 'get_last_send') {
    $date = $_GET['date'] ?? date('Y-m-d');
    $logFile = $logsDir . '/utmify-' . $date . '.log';
    $logFilePendente = $logsDir . '/utmify-pendente-' . $date . '.log';
    
    $lastSend = null;
    $lastSuccess = null;
    $lastError = null;
    
    // Processar ambos os arquivos de log
    $files = [
        ['file' => $logFile, 'exists' => file_exists($logFile)],
        ['file' => $logFilePendente, 'exists' => file_exists($logFilePendente)]
    ];
    
    foreach ($files as $fileInfo) {
        if (!$fileInfo['exists']) continue;
        
        $content = file_get_contents($fileInfo['file']);
        $blocks = explode('----------------------------------------', $content);
        
        foreach ($blocks as $blockText) {
            $blockText = trim($blockText);
            if (empty($blockText)) continue;
            
            $parsed = parseUtmifyLogBlock($blockText);
            if ($parsed) {
                $lastSend = $parsed;
                
                if ($parsed['type'] === 'api_response') {
                    if ($parsed['success']) {
                        $lastSuccess = $parsed;
                    } else {
                        $lastError = $parsed;
                    }
                } elseif ($parsed['type'] === 'error') {
                    $lastError = $parsed;
                }
            }
        }
    }
    
    // Priorizar último sucesso
    $result = $lastSuccess ?? $lastError ?? $lastSend;
    
    echo json_encode([
        'success' => true,
        'last_send' => $result
    ]);
    exit;
}

function parseUtmifyLogBlock($blockText) {
    // Extrair timestamp
    if (!preg_match('/^\[([\d\-: ]+)\]/', $blockText, $matches)) {
        return null;
    }
    
    $timestamp = $matches[1];
    
    // Verificar se é uma resposta da API
    if (strpos($blockText, 'Resposta da API Utmify') !== false) {
        if (preg_match('/Dados:\s*(\{[\s\S]+)$/s', $blockText, $jsonMatch)) {
            $jsonString = trim($jsonMatch[1]);
            $data = json_decode($jsonString, true);
            
            if ($data && isset($data['http_code'])) {
                $httpCode = $data['http_code'];
                $isSuccess = ($httpCode === 200);
                
                // Extrair informações relevantes
                $email = null;
                $orderId = null;
                
                if (isset($data['response'])) {
                    // Tentar extrair email e orderId da resposta
                    $responseStr = json_encode($data['response']);
                    if (preg_match('/"email":\s*"([^"]+)"/', $responseStr, $emailMatch)) {
                        $email = $emailMatch[1];
                    }
                    if (preg_match('/"orderId":\s*"([^"]+)"/', $responseStr, $orderMatch)) {
                        $orderId = $orderMatch[1];
                    }
                }
                
                return [
                    'timestamp' => $timestamp,
                    'success' => $isSuccess,
                    'http_code' => $httpCode,
                    'email' => $email,
                    'order_id' => $orderId,
                    'type' => 'api_response'
                ];
            }
        }
    }
    
    // Verificar se é um erro
    if (preg_match('/Erro\s*\r?\nDados:/s', $blockText)) {
        if (preg_match('/Dados:\s*(\{[\s\S]+)$/s', $blockText, $jsonMatch)) {
            $jsonString = trim($jsonMatch[1]);
            $data = json_decode($jsonString, true);
            $message = $data['message'] ?? 'Erro desconhecido';
            
            return [
                'timestamp' => $timestamp,
                'success' => false,
                'message' => $message,
                'type' => 'error'
            ];
        }
    }
    
    return null;
}

echo json_encode([
    'success' => false,
    'message' => 'Ação inválida'
]);
?>
