<?php
header('Content-Type: application/json');

// Carregar credencial do arquivo de configuração
$configFile = __DIR__ . '/otimizey-config.json';

if (!file_exists($configFile)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Arquivo de configuração Otimizey não encontrado. Configure no painel administrativo.'
    ]);
    exit;
}

$config = json_decode(file_get_contents($configFile), true);
if (!isset($config['credential_id']) || empty($config['credential_id'])) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Credential ID não configurado. Configure no painel administrativo.'
    ]);
    exit;
}

$credentialId = $config['credential_id'];
$otimizeyApiUrl = "https://api.otimizey.com.br/webhooks/credential/{$credentialId}";
$logDir = __DIR__ . '/logs';
if (!file_exists($logDir)) {
    if (!mkdir($logDir, 0777, true)) {
        error_log("Erro ao criar diretório de logs: " . $logDir);
    } else {
        chmod($logDir, 0777);
    }
}
$logFile = $logDir . '/utmify-pendente-' . date('Y-m-d') . '.log';

function writeLog($message, $data = null) {
    global $logFile;
    $timestamp = gmdate('Y-m-d H:i:s'); // Usando UTC nos logs também
    $logMessage = "[$timestamp] $message\n";
    if ($data !== null) {
        $logMessage .= "Dados: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
    }
    $logMessage .= "----------------------------------------\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

try {
    $rawData = file_get_contents('php://input');
    writeLog("📥 Dados recebidos", ['raw' => $rawData]);

    $otimizeyData = json_decode($rawData, true);
    if (!$otimizeyData) {
        throw new Exception("Dados JSON inválidos");
    }

    writeLog("🔄 Processando dados recebidos", $otimizeyData);

    // Validar campos obrigatórios na estrutura correta
    if (!isset($otimizeyData['externalUserRef']) || !isset($otimizeyData['product'])) {
        throw new Exception("Dados incompletos: externalUserRef e product são obrigatórios");
    }

    // Validar campos dentro de product
    if (!isset($otimizeyData['product']['id']) || !isset($otimizeyData['product']['name']) || 
        !isset($otimizeyData['product']['price'])) {
        throw new Exception("Dados do produto incompletos: id, name e price são obrigatórios");
    }

    // Validar campos no nível raiz
    if (!isset($otimizeyData['orderId']) || !isset($otimizeyData['paymentMethod']) || 
        !isset($otimizeyData['status']) || !isset($otimizeyData['totalPrice']) || 
        !isset($otimizeyData['receivedPrice'])) {
        throw new Exception("Dados da transação incompletos: orderId, paymentMethod, status, totalPrice e receivedPrice são obrigatórios");
    }

    // Garantir que os valores numéricos sejam float
    $otimizeyData['product']['price'] = floatval($otimizeyData['product']['price']);
    $otimizeyData['totalPrice'] = floatval($otimizeyData['totalPrice']);
    $otimizeyData['receivedPrice'] = floatval($otimizeyData['receivedPrice']);

    
    if (isset($otimizeyData['name'])) {
        writeLog("ℹ️ Campo opcional recebido: name", ['name' => $otimizeyData['name']]);
    }
    if (isset($otimizeyData['phone'])) {
        writeLog("ℹ️ Campo opcional recebido: phone", ['phone' => $otimizeyData['phone']]);
    }
    if (isset($otimizeyData['postalCode'])) {
        writeLog("ℹ️ Campo opcional recebido: postalCode", ['postalCode' => $otimizeyData['postalCode']]);
    }
    if (isset($otimizeyData['birthDate'])) {
        writeLog("ℹ️ Campo opcional recebido: birthDate", ['birthDate' => $otimizeyData['birthDate']]);
    }

    // Remove campos nulos ou vazios (mantém apenas os obrigatórios e opcionais preenchidos)
    $otimizeyData = array_filter($otimizeyData, function($value) {
        return $value !== null && $value !== '';
    });

    writeLog("📤 Dados prontos para envio à Otimizey", $otimizeyData);

    $ch = curl_init($otimizeyApiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json"
        ],
        CURLOPT_POSTFIELDS => json_encode($otimizeyData)
    ]);

    writeLog("📡 Enviando requisição para Otimizey", [
        'url' => $otimizeyApiUrl
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        writeLog("❌ Erro CURL", ['error' => curl_error($ch)]);
        throw new Exception("Erro ao enviar dados para Otimizey: " . curl_error($ch));
    }
    
    curl_close($ch);

    writeLog("✅ Resposta da API Otimizey", [
        'http_code' => $httpCode,
        'response' => json_decode($response, true),
        'response_raw' => $response
    ]);

    // Aceita qualquer código de sucesso 2xx (200-299)
    if ($httpCode < 200 || $httpCode >= 300) {
        // Retorna a resposta exata da API Otimizey
        $errorResponse = json_decode($response, true);
        throw new Exception("Erro na API Otimizey. HTTP Code: $httpCode - Resposta: " . $response);
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Dados enviados com sucesso para Otimizey',
        'otimizey_response' => json_decode($response, true)
    ]);

} catch (Exception $e) {
    writeLog("❌ Erro", ['message' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
