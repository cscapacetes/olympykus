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
    mkdir($logDir, 0777, true);
}
$logFile = $logDir . '/utmify-' . date('Y-m-d') . '.log';

function writeLog($message, $data = null) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message\n";
    if ($data !== null) {
        $logMessage .= "Dados: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
    }
    $logMessage .= "----------------------------------------\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

try {
    $rawData = file_get_contents('php://input');
    writeLog("📥 Dados recebidos do PayHubr", ['raw' => $rawData]);

    $inputData = json_decode($rawData, true);
    if (!$inputData) {
        throw new Exception("Dados JSON inválidos");
    }

    writeLog("🔄 Processando dados recebidos", $inputData);

    if ($inputData['status'] !== 'paid' && $inputData['status'] !== 'PAID' && 
        $inputData['status'] !== 'approved' && $inputData['status'] !== 'APPROVED') {
        writeLog("⏭️ Status ignorado", ['status' => $inputData['status']]);
        http_response_code(200);
        echo json_encode(['message' => 'Status ignorado']);
        exit;
    }

    // Determinar o método de pagamento
    $paymentMethod = 'pix'; // Padrão
    if (isset($inputData['paymentMethod'])) {
        $paymentMethod = strtolower($inputData['paymentMethod']);
    }

    // Calcular valores
    $totalRaw = floatval($inputData['amount']);
$receivedRaw = $inputData['fee']['netAmount'] ?? $totalRaw;

// se for menor que 1000 provavelmente já é real
if ($totalRaw < 1000) {
    $totalPrice = $totalRaw;
} else {
    $totalPrice = $totalRaw / 100;
}

if ($receivedRaw < 1000) {
    $receivedPrice = $receivedRaw;
} else {
    $receivedPrice = $receivedRaw / 100;
}

if ($totalPrice <= 0) {
    $totalPrice = 1;
}

    // Extrair nome do produto do metadata se disponível
    $productName = 'Produto';
    $productId = 'produto-checkout';
    if (isset($inputData['metadata'])) {
        $metadata = is_string($inputData['metadata']) ? json_decode($inputData['metadata'], true) : $inputData['metadata'];
        if (isset($metadata['product_name'])) {
            $productName = $metadata['product_name'];
        }
        if (isset($metadata['product_id'])) {
            $productId = $metadata['product_id'];
        }
    }
    // Fallback: usar título do item se disponível
    if (isset($inputData['items'][0]['title'])) {
        $productName = $inputData['items'][0]['title'];
    }

    // Extrair CPF do cliente
    $customerDocument = 
    $inputData['customer']['document']['number'] ??
    $inputData['customer']['email'] ??
    ('user_' . time()); // fallback para não ficar null

    // Extrair nome do cliente (verifica no nível raiz primeiro, depois em customer)
    $customerName = $inputData['name'] ?? $inputData['customer']['name'] ?? 'Cliente';
    writeLog("ℹ️ Nome do cliente identificado", ['name' => $customerName]);

    // Extrair telefone do cliente (verifica no nível raiz primeiro, depois em customer)
    $customerPhone = $inputData['phone'] ?? $inputData['customer']['phone'] ?? null;
    $formattedPhone = null;
    if ($customerPhone && !empty($customerPhone)) {
        // Remove caracteres não numéricos
        $formattedPhone = preg_replace('/[^0-9]/', '', $customerPhone);
        
        if (strlen($formattedPhone) > 11 && substr($formattedPhone, 0, 2) === '55') {
            $formattedPhone = substr($formattedPhone, 2);
        }
        writeLog("ℹ️ Telefone do cliente identificado", ['phone' => $formattedPhone]);
    }

    // Estrutura correta conforme a API Otimizey
    $otimizeyData = [
        'externalUserRef' => $customerDocument, // Usando CPF como referência
        'product' => [
            'id' => $productId,
            'name' => $productName,
            'price' => floatval($totalPrice)
        ],
        'orderId' => $inputData['orderId'],
        'paymentMethod' => $paymentMethod,
        'status' => 'paid', // Status para pagamento confirmado
        'totalPrice' => floatval($totalPrice),
        'receivedPrice' => floatval($receivedPrice),
        'name' => $customerName, // Nome completo do cliente
        'phone' => $formattedPhone // Telefone do cliente
    ];

    
    $tracking = $inputData['trackingParameters'] ?? [];

if (!empty($tracking['sck'])) {
    $otimizeyData['sck'] = $tracking['sck'];
}

if (!empty($tracking['src'])) {
    $otimizeyData['src'] = $tracking['src'];
}

if (!empty($tracking['utm_source'])) {
    $otimizeyData['utmSource'] = $tracking['utm_source'];
}

if (!empty($tracking['utm_medium'])) {
    $otimizeyData['utmMedium'] = $tracking['utm_medium'];
}

if (!empty($tracking['utm_campaign'])) {
    $otimizeyData['utmCampaign'] = $tracking['utm_campaign'];
}

if (!empty($tracking['utm_content'])) {
    $otimizeyData['utmContent'] = $tracking['utm_content'];
}

    writeLog("📤 Dados formatados para Otimizey", $otimizeyData);

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

    if ($httpCode !== 200 && $httpCode !== 201) {
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
