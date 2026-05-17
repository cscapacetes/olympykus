<?php
/**
 * CONTROLLER - GERENCIAMENTO DE CREDENCIAL OTIMIZEY
 */
session_start();

header('Content-Type: application/json');

// Verificar autenticação
if (!isset($_SESSION['gateway_admin_logged'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$configFile = __DIR__ . '/otimizey-config.json';

// GET - Obter configuração atual
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        if (file_exists($configFile)) {
            $config = json_decode(file_get_contents($configFile), true);
            echo json_encode([
                'success' => true,
                'config' => $config
            ]);
        } else {
            // Retornar configuração padrão se não existir
            echo json_encode([
                'success' => true,
                'config' => [
                    'credential_id' => '',
                    'tracking_script_id' => '',
                    'last_updated' => null
                ]
            ]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao carregar configuração: ' . $e->getMessage()
        ]);
    }
    exit;
}

// POST - Atualizar configuração
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_credential') {
        try {
            $credentialId = trim($_POST['credential_id'] ?? '');
            $trackingScriptId = trim($_POST['tracking_script_id'] ?? '');

            if (empty($credentialId)) {
                throw new Exception('Credential ID é obrigatório');
            }

            // Validar formato UUID (opcional, mas recomendado)
            if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $credentialId)) {
                throw new Exception('Formato de Credential ID inválido. Use o formato UUID (ex: 89e0337a-6fad-4fdf-83da-db08bf785fb8)');
            }

            $config = [
                'credential_id' => $credentialId,
                'tracking_script_id' => $trackingScriptId,
                'last_updated' => date('Y-m-d H:i:s')
            ];

            // Salvar configuração
            if (!file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT))) {
                throw new Exception('Erro ao salvar configuração');
            }

            // Atualizar os arquivos utmify
            $utmifyFile = __DIR__ . '/../utmify.php';
            $utmifyPendenteFile = __DIR__ . '/../utmify-pendente.php';

            // Atualizar utmify.php
            if (file_exists($utmifyFile)) {
                $content = file_get_contents($utmifyFile);
                $content = preg_replace(
                    '/\$credentialId\s*=\s*["\'][^"\']+["\'];/',
                    '$credentialId = "' . $credentialId . '";',
                    $content
                );
                file_put_contents($utmifyFile, $content);
            }

            // Atualizar utmify-pendente.php
            if (file_exists($utmifyPendenteFile)) {
                $content = file_get_contents($utmifyPendenteFile);
                $content = preg_replace(
                    '/\$credentialId\s*=\s*["\'][^"\']+["\'];/',
                    '$credentialId = "' . $credentialId . '";',
                    $content
                );
                file_put_contents($utmifyPendenteFile, $content);
            }

            echo json_encode([
                'success' => true,
                'message' => 'Credential ID atualizado com sucesso!',
                'config' => $config
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
        exit;
    }
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Ação inválida']);
?>
