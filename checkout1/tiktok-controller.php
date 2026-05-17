<?php
/**
 * CONTROLADOR DE CONTAS TIKTOK
 */
session_start();

// Verificar se está logado (exceto para GET)
if ($_SERVER['REQUEST_METHOD'] !== 'GET' && !isset($_SESSION['gateway_admin_logged'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$accountsFile = __DIR__ . '/tiktok-accounts.json';

// Função para ler contas
function readAccounts($accountsFile) {
    if (!file_exists($accountsFile)) {
        return [];
    }
    
    $content = file_get_contents($accountsFile);
    return json_decode($content, true) ?: [];
}

// Função para salvar contas
function saveAccounts($accountsFile, $accounts) {
    return file_put_contents($accountsFile, json_encode($accounts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// Processar requisições
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Retornar contas
    $accounts = readAccounts($accountsFile);
    echo json_encode([
        'success' => true,
        'accounts' => $accounts
    ]);
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_account') {
        $name = $_POST['name'] ?? '';
        $pixelId = $_POST['pixel_id'] ?? '';
        $accessToken = $_POST['access_token'] ?? '';
        
        if (empty($name) || empty($pixelId) || empty($accessToken)) {
            echo json_encode(['success' => false, 'message' => 'Todos os campos são obrigatórios']);
            exit;
        }
        
        $accounts = readAccounts($accountsFile);
        
        // Gerar ID único
        $id = count($accounts) > 0 ? max(array_column($accounts, 'id')) + 1 : 1;
        
        $newAccount = [
            'id' => $id,
            'name' => $name,
            'pixel_id' => $pixelId,
            'access_token' => $accessToken,
            'active' => false,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $accounts[] = $newAccount;
        
        if (saveAccounts($accountsFile, $accounts)) {
            echo json_encode([
                'success' => true,
                'message' => 'Conta TikTok adicionada com sucesso!'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao salvar conta']);
        }
        
    } elseif ($action === 'edit_account') {
        $id = (int)($_POST['id'] ?? 0);
        $name = $_POST['name'] ?? '';
        $pixelId = $_POST['pixel_id'] ?? '';
        $accessToken = $_POST['access_token'] ?? '';
        
        if ($id <= 0 || empty($name) || empty($pixelId) || empty($accessToken)) {
            echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
            exit;
        }
        
        $accounts = readAccounts($accountsFile);
        $found = false;
        
        foreach ($accounts as &$account) {
            if ($account['id'] === $id) {
                $account['name'] = $name;
                $account['pixel_id'] = $pixelId;
                $account['access_token'] = $accessToken;
                $account['updated_at'] = date('Y-m-d H:i:s');
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            echo json_encode(['success' => false, 'message' => 'Conta não encontrada']);
            exit;
        }
        
        if (saveAccounts($accountsFile, $accounts)) {
            echo json_encode([
                'success' => true,
                'message' => 'Conta TikTok atualizada com sucesso!'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao salvar conta']);
        }
        
    } elseif ($action === 'toggle_active') {
        $id = (int)($_POST['id'] ?? 0);
        
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID inválido']);
            exit;
        }
        
        $accounts = readAccounts($accountsFile);
        $found = false;
        
        foreach ($accounts as &$account) {
            if ($account['id'] === $id) {
                $account['active'] = !$account['active'];
                $account['updated_at'] = date('Y-m-d H:i:s');
                $found = true;
                $status = $account['active'] ? 'ativada' : 'desativada';
                break;
            }
        }
        
        if (!$found) {
            echo json_encode(['success' => false, 'message' => 'Conta não encontrada']);
            exit;
        }
        
        if (saveAccounts($accountsFile, $accounts)) {
            echo json_encode([
                'success' => true,
                'message' => "Conta {$status} com sucesso!"
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao salvar conta']);
        }
        
    } elseif ($action === 'delete_account') {
        $id = (int)($_POST['id'] ?? 0);
        
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID inválido']);
            exit;
        }
        
        $accounts = readAccounts($accountsFile);
        $newAccounts = array_filter($accounts, function($account) use ($id) {
            return $account['id'] !== $id;
        });
        
        if (count($newAccounts) === count($accounts)) {
            echo json_encode(['success' => false, 'message' => 'Conta não encontrada']);
            exit;
        }
        
        // Reindexar array
        $newAccounts = array_values($newAccounts);
        
        if (saveAccounts($accountsFile, $newAccounts)) {
            echo json_encode([
                'success' => true,
                'message' => 'Conta removida com sucesso!'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao remover conta']);
        }
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Ação inválida']);
    }
    
} else {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
}
?>