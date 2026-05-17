<?php
/**
 * CONTROLADOR DE LOGS OTIMIZEY
 */
header('Content-Type: application/json');

// Tentar encontrar a pasta logs (pode estar na raiz ou no mesmo nível do checkout)
$possiblePaths = [
    __DIR__ . '/../logs',           // Pasta pai (raiz)
    __DIR__ . '/../../logs',        // Dois níveis acima
    dirname(dirname(__FILE__)) . '/logs'  // Alternativa
];

$logsDir = null;
foreach ($possiblePaths as $path) {
    if (is_dir($path)) {
        $logsDir = $path;
        break;
    }
}

// Se não encontrou, usar o primeiro caminho como padrão
if (!$logsDir) {
    $logsDir = __DIR__ . '/../logs';
}

// Debug: verificar caminho dos logs
if (isset($_GET['action']) && $_GET['action'] === 'debug_path') {
    echo json_encode([
        'success' => true,
        'controller_dir' => __DIR__,
        'logs_dir' => $logsDir,
        'logs_dir_exists' => is_dir($logsDir),
        'logs_dir_readable' => is_readable($logsDir),
        'possible_paths_checked' => $possiblePaths,
        'files_in_logs' => is_dir($logsDir) ? scandir($logsDir) : []
    ]);
    exit;
}

// Listar arquivos de log disponíveis
if (isset($_GET['action']) && $_GET['action'] === 'list_logs') {
    $logFiles = [];
    
    if (!is_dir($logsDir)) {
        echo json_encode([
            'success' => false,
            'message' => 'Diretório de logs não encontrado: ' . $logsDir,
            'logs' => []
        ]);
        exit;
    }
    
    if (!is_readable($logsDir)) {
        echo json_encode([
            'success' => false,
            'message' => 'Diretório de logs não tem permissão de leitura: ' . $logsDir,
            'logs' => []
        ]);
        exit;
    }
    
    $files = scandir($logsDir);
    foreach ($files as $file) {
        if (preg_match('/^utmify-pendente-(\d{4}-\d{2}-\d{2})\.log$/', $file, $matches)) {
            $logFiles[] = [
                'filename' => $file,
                'date' => $matches[1],
                'size' => filesize($logsDir . '/' . $file),
                'modified' => filemtime($logsDir . '/' . $file)
            ];
        }
    }
    
    // Ordenar por data (mais recente primeiro)
    usort($logFiles, function($a, $b) {
        return strcmp($b['date'], $a['date']);
    });
    
    echo json_encode([
        'success' => true,
        'logs' => $logFiles,
        'logs_dir' => $logsDir
    ]);
    exit;
}

// Debug: testar parse de log
if (isset($_GET['action']) && $_GET['action'] === 'debug_parse') {
    $date = $_GET['date'] ?? date('Y-m-d');
    $logFile = $logsDir . '/utmify-pendente-' . $date . '.log';
    
    if (!file_exists($logFile)) {
        echo json_encode([
            'success' => false,
            'message' => 'Log não encontrado',
            'file' => $logFile
        ]);
        exit;
    }
    
    $content = file_get_contents($logFile);
    $blocks = explode('----------------------------------------', $content);
    
    $debugInfo = [
        'file' => $logFile,
        'file_size' => filesize($logFile),
        'total_blocks' => count($blocks),
        'blocks_sample' => []
    ];
    
    // Pegar últimos 3 blocos para debug
    $lastBlocks = array_slice($blocks, -5);
    foreach ($lastBlocks as $i => $block) {
        $block = trim($block);
        if (empty($block)) continue;
        
        $debugInfo['blocks_sample'][] = [
            'index' => $i,
            'length' => strlen($block),
            'first_100_chars' => substr($block, 0, 100),
            'has_api_response' => strpos($block, '✅ Resposta da API Otimizey') !== false,
            'has_error' => strpos($block, '❌ Erro') !== false,
            'parsed' => parseLogBlockFromText($block)
        ];
    }
    
    echo json_encode($debugInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Ler último envio de um log específico
if (isset($_GET['action']) && $_GET['action'] === 'get_last_send') {
    $date = $_GET['date'] ?? date('Y-m-d');
    $logFile = $logsDir . '/utmify-pendente-' . $date . '.log';
    
    if (!file_exists($logFile)) {
        echo json_encode([
            'success' => false,
            'message' => 'Log não encontrado para esta data'
        ]);
        exit;
    }
    
    $content = file_get_contents($logFile);
    
    // Dividir por blocos usando o separador
    $blocks = explode('----------------------------------------', $content);
    
    $lastSend = null;
    $lastSuccess = null;
    $lastError = null;
    
    foreach ($blocks as $blockText) {
        $blockText = trim($blockText);
        if (empty($blockText)) continue;
        
        $parsed = parseLogBlockFromText($blockText);
        if ($parsed) {
            $lastSend = $parsed;
            
            // Separar por tipo
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
    
    // Priorizar último sucesso se existir, senão mostrar último erro
    $result = $lastSuccess ?? $lastError ?? $lastSend;
    
    echo json_encode([
        'success' => true,
        'last_send' => $result
    ]);
    exit;
}

// Ler estatísticas do dia
if (isset($_GET['action']) && $_GET['action'] === 'get_stats') {
    $date = $_GET['date'] ?? date('Y-m-d');
    $logFile = $logsDir . '/utmify-pendente-' . $date . '.log';
    
    if (!file_exists($logFile)) {
        echo json_encode([
            'success' => true,
            'stats' => [
                'total' => 0,
                'success' => 0,
                'error' => 0,
                'date' => $date
            ]
        ]);
        exit;
    }
    
    $content = file_get_contents($logFile);
    
    // Dividir por blocos usando o separador
    $blocks = explode('----------------------------------------', $content);
    
    $stats = [
        'total' => 0,
        'success' => 0,
        'error' => 0,
        'date' => $date
    ];
    
    foreach ($blocks as $blockText) {
        $blockText = trim($blockText);
        if (empty($blockText)) continue;
        
        // Verificar se é uma resposta da API (aceitar diferentes encodings)
        if (strpos($blockText, 'Resposta da API Otimizey') !== false) {
            $stats['total']++;
            
            // Verificar se foi sucesso (HTTP 200 ou 201)
            if (preg_match('/"http_code":\s*(200|201)/', $blockText)) {
                $stats['success']++;
            } else {
                $stats['error']++;
            }
        } elseif (preg_match('/Erro\s*\r?\nDados:/s', $blockText)) {
            $stats['total']++;
            $stats['error']++;
        }
    }
    
    echo json_encode([
        'success' => true,
        'stats' => $stats
    ]);
    exit;
}

function parseLogBlockFromText($blockText) {
    // Extrair timestamp
    if (!preg_match('/^\[([\d\-: ]+)\]/', $blockText, $matches)) {
        return null;
    }
    
    $timestamp = $matches[1];
    
    // Verificar se é uma resposta da API (aceitar diferentes encodings do emoji)
    if (strpos($blockText, 'Resposta da API Otimizey') !== false) {
        // Extrair dados JSON - pode estar em múltiplas linhas
        if (preg_match('/Dados:\s*(\{[\s\S]+)$/s', $blockText, $jsonMatch)) {
            $jsonString = trim($jsonMatch[1]);
            $data = json_decode($jsonString, true);
            
            if ($data && isset($data['http_code'])) {
                $httpCode = $data['http_code'];
                $isSuccess = in_array($httpCode, [200, 201]);
                
                // Extrair informações relevantes
                $email = null;
                $orderId = null;
                $status = null;
                
                if (isset($data['response']['data']['data'])) {
                    $responseData = $data['response']['data']['data'];
                    $email = $responseData['externalUserRef'] ?? null;
                    $orderId = $responseData['orderId'] ?? null;
                    $status = $responseData['status'] ?? null;
                }
                
                return [
                    'timestamp' => $timestamp,
                    'success' => $isSuccess,
                    'http_code' => $httpCode,
                    'email' => $email,
                    'order_id' => $orderId,
                    'status' => $status,
                    'type' => 'api_response'
                ];
            }
        }
    }
    
    // Verificar se é um erro (aceitar diferentes encodings do emoji)
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
