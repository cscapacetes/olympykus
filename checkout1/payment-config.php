<?php
/**
 * CONFIGURAÇÃO DE GATEWAY DE PAGAMENTO
 * 
 * ⚠️ NÃO EDITE ESTE ARQUIVO MANUALMENTE!
 * Use o painel administrativo: admin-gateway.php
 */

// ========== CARREGA CONFIGURAÇÃO DO JSON ==========
$configFile = __DIR__ . '/gateway-config.json';

// Verifica se o arquivo de configuração existe
if (!file_exists($configFile)) {
    // Cria configuração padrão se não existir
    $defaultConfig = [
        'active_gateway' => 'paradise'
    ];
    file_put_contents($configFile, json_encode($defaultConfig, JSON_PRETTY_PRINT));
}

// Lê a configuração do arquivo JSON
$config = json_decode(file_get_contents($configFile), true);

if (!$config || (!isset($config['active_gateway']) && !isset($config['gateway']))) {
    error_log("[Config] ❌ ERRO: Arquivo de configuração inválido!");
    
    // Define headers se ainda não foram definidos
    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code(500);
    }
    
    die(json_encode([
        'success' => false,
        'message' => 'Configuração de gateway inválida. Contate o suporte.'
    ]));
}

// Determina qual gateway está ativo (suporta ambas as chaves)
$ACTIVE_GATEWAY = $config['active_gateway'] ?? $config['gateway'] ?? 'paradise';

// Mapeia o gateway para o arquivo PHP correto
$GATEWAY_FILES = [
    'paradise' => 'pagamento-paradise.php',
    'dutty' => 'pagamento-dutty.php',
    'mangofy' => 'pagamento-mangofy.php',
    'ghost' => 'pagamento-ghost.php',
    'buck' => 'pagamento-buck.php',
    'naut' => 'pagamento-naut.php',
    'zero' => 'pagamento-zero.php',
    'manutencao' => 'pagamento-manutencao.php',
    'iron' => 'pagamento-iron.php',
    'black' => 'pagamento-black.php',
    'cashfly' => 'pagamento-cashfly.php',
    'umbrella' => 'pagamento-umbrella.php',
    'freepay' => 'pagamento-freepay.php',
    'quantum' => 'pagamento-quantum.php',
    'pagz' => 'pagamento-pagz.php',
    'brutal' => 'pagamento-brutal.php',
    'allow' => 'pagamento-allow.php',
    'pollar' => 'pagamento-pollar.php'
];

// Define o arquivo do gateway ativo
$PAYMENT_FILE = $GATEWAY_FILES[$ACTIVE_GATEWAY];

// Log da configuração
error_log("[Config] ✅ Gateway ativo: " . strtoupper($ACTIVE_GATEWAY));
error_log("[Config] 📄 Arquivo: " . $PAYMENT_FILE);

/**
 * Função para obter a URL do gateway de pagamento
 */
function getPaymentUrl() {
    global $PAYMENT_FILE;
    return './' . $PAYMENT_FILE;
}

/**
 * Função para obter o nome do gateway ativo
 */
function getActiveGateway() {
    global $ACTIVE_GATEWAY;
    return $ACTIVE_GATEWAY;
}

/**
 * Retorna configuração em JSON (para ser usado via AJAX)
 */
if (isset($_GET['json'])) {
    // Headers já definidos pelo roteador, não redefinir
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    echo json_encode([
        'success' => true,
        'gateway' => $ACTIVE_GATEWAY,
        'paymentUrl' => './' . $PAYMENT_FILE
    ]);
    exit;
}
