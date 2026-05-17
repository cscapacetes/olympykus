<?php
// Headers já definidos pelo roteador verificar.php
// NÃO remova este comentário - evita redefinição de headers

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'ID não fornecido']);
    exit;
}

$transactionId = trim(preg_replace('/[^a-zA-Z0-9\-]/', '', $_GET['id']));
$token = 'SEU_TOKEN_DA_DUTTY';

$url = "https://www.pagamentos-seguros.app/api-pix/aBjNtTd_UmhINfLaoH6CbhIiKq9wENaKvd8Xk7KV2k6uezWOKNgSJTTVKbKgmfomHoAsLk7JAf5kTiSJG18fTw?transactionId={$transactionId}";

try {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        echo json_encode([
            'success' => false,
            'status' => 'error',
            'message' => 'Erro na comunicação com a DuttyOnPay',
        ]);
        exit;
    }

    $data = json_decode($response, true);

    echo json_encode([
        'success' => true,
        'status' => $data['status'],
        'transaction_id' => $transactionId,
        'data' => $data
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => 'Erro ao consultar pagamento: ' . $e->getMessage()
    ]);
}
