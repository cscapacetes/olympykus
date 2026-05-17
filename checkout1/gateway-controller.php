<?php
/**
 * CONTROLADOR DE GATEWAY
 */
session_start();

$configFile = __DIR__ . '/gateway-config.json';
$adminPassword = 'admin123'; // Altere esta senha

// Função para ler configuração do gateway
function readGatewayConfig($configFile) {
    if (!file_exists($configFile)) {
        return ['gateway' => 'paradise'];
    }
    
    $content = file_get_contents($configFile);
    return json_decode($content, true) ?: ['gateway' => 'paradise'];
}

// Função para salvar configuração do gateway
function saveGatewayConfig($configFile, $config) {
    return file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
}

// Processar requisições
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Retornar configuração atual
    $config = readGatewayConfig($configFile);
    echo json_encode([
        'success' => true,
        'gateway' => $config['gateway']
    ]);
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'login') {
        $password = $_POST['password'] ?? '';
        
        if ($password === $adminPassword) {
            $_SESSION['gateway_admin_logged'] = true;
            echo json_encode([
                'success' => true,
                'message' => 'Login realizado com sucesso!'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Senha incorreta!'
            ]);
        }
        
    } elseif ($action === 'logout') {
        unset($_SESSION['gateway_admin_logged']);
        echo json_encode([
            'success' => true,
            'message' => 'Logout realizado com sucesso!'
        ]);
        
    } elseif ($action === 'change_gateway') {
        // Verificar se está logado
        if (!isset($_SESSION['gateway_admin_logged'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Não autorizado']);
            exit;
        }
        
        $gateway = $_POST['gateway'] ?? '';
        $validGateways = ['paradise', 'dutty', 'mangofy', 'iron', 'ghost', 'buck', 'naut', 'zero', 'manutencao', 'black', 'cashfly', 'umbrella', 'freepay', 'quantum', 'pagz', 'brutal', 'allow', 'pollar'];
        
        if (!in_array($gateway, $validGateways)) {
            echo json_encode(['success' => false, 'message' => 'Gateway inválido']);
            exit;
        }
        
        $config = ['gateway' => $gateway];
        
        if (saveGatewayConfig($configFile, $config)) {
            echo json_encode([
                'success' => true,
                'message' => 'Gateway alterado com sucesso!',
                'gateway' => $gateway
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao salvar configuração']);
        }
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Ação inválida']);
    }
    
} else {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
}
?>