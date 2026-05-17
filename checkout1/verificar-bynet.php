<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Função para registrar logs
function logMsg($message) {
    error_log("[verificar.php] $message");
}

logMsg("Iniciando verificação de pagamento");

if (!isset($_GET['id'])) {
    logMsg("Erro: ID não fornecido na requisição");
    echo json_encode(['success' => false, 'status' => 'error', 'message' => 'ID não fornecido']);
    exit;
}

$id = $_GET['id'];
logMsg("ID recebido para verificação: " . $id);

// Remove qualquer caractere especial ou espaço do ID
$id = trim($id);
$id = preg_replace('/[^a-zA-Z0-9\-]/', '', $id);
logMsg("ID após sanitização: " . $id);

try {
    // Conecta ao SQLite
    $dbPath = __DIR__ . '/database.sqlite';
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    logMsg("Conexão com banco de dados estabelecida");

    // Busca o status do pagamento
    $stmt = $db->prepare("SELECT * FROM pedidos WHERE transaction_id = :transaction_id");
    $stmt->execute(['transaction_id' => $id]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
    
    logMsg("Consulta executada. Pedido encontrado: " . ($pedido ? 'Sim' : 'Não'));
    
    // Verificar todos os pedidos no banco de dados para diagnóstico
    $allOrders = $db->query("SELECT transaction_id FROM pedidos")->fetchAll(PDO::FETCH_COLUMN);
    logMsg("Total de pedidos no banco: " . count($allOrders));
    logMsg("IDs dos pedidos existentes: " . json_encode($allOrders));

    if (!$pedido) {
        logMsg("Pedido não encontrado para o ID: " . $id);
        echo json_encode([
            'success' => false,
            'status' => 'error',
            'message' => 'Pedido não encontrado',
            'transaction_id' => $id,
            'debug_info' => [
                'db_path' => $dbPath,
                'total_pedidos' => count($allOrders)
            ]
        ]);
        exit;
    }

    logMsg("Pedido encontrado, status: " . $pedido['status']);
    echo json_encode([
        'success' => true,
        'status' => $pedido['status'],
        'transaction_id' => $pedido['transaction_id'],
        'data' => [
            'amount' => $pedido['valor'],
            'created_at' => $pedido['created_at'],
            'updated_at' => $pedido['updated_at'],
            'customer' => [
                'name' => $pedido['nome'],
                'email' => $pedido['email'],
                'document' => $pedido['cpf']
            ]
        ]
    ]);

} catch (Exception $e) {
    logMsg("❌ Erro: " . $e->getMessage() . " - " . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => 'Erro ao verificar o status do pagamento: ' . $e->getMessage(),
        'transaction_id' => $id
    ]);
} 