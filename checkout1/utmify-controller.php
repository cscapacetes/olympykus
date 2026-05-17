<?php
/**
 * CONTROLLER PARA GERENCIAR TOKEN UTMIFY
 */
session_start();

header('Content-Type: application/json');

// Verificar autenticação
if (!isset($_SESSION['gateway_admin_logged'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$configFile = __DIR__ . '/utmify-config.json';

// GET - Carregar configuração
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (file_exists($configFile)) {
        $config = json_decode(file_get_contents($configFile), true);
        echo json_encode([
            'success' => true,
            'config' => $config
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'config' => [
                'token' => '',
                'last_updated' => null
            ]
        ]);
    }
    exit;
}

// POST - Atualizar token
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_token') {
        $token = trim($_POST['token'] ?? '');
        
        if (empty($token)) {
            echo json_encode([
                'success' => false,
                'message' => 'Token não pode estar vazio'
            ]);
            exit;
        }
        
        // Salvar configuração
        $config = [
            'token' => $token,
            'last_updated' => date('Y-m-d H:i:s')
        ];
        
        if (file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT))) {
            echo json_encode([
                'success' => true,
                'message' => 'Token Utmify atualizado com sucesso!',
                'config' => $config
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Erro ao salvar configuração'
            ]);
        }
        exit;
    }
}
