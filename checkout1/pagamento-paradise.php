<?php

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);


date_default_timezone_set('America/Sao_Paulo');


function getClientIP() {
    
    $headers = [
        'HTTP_CF_CONNECTING_IP',    
        'HTTP_X_REAL_IP',            
        'HTTP_X_FORWARDED_FOR',      
        'HTTP_CLIENT_IP',            
        'REMOTE_ADDR'                
    ];
    
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = $_SERVER[$header];
            
            
            
            if (strpos($ip, ',') !== false) {
                $ips = explode(',', $ip);
                $ip = trim($ips[0]);
            }
            
            
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                error_log("[IP] ✅ IP real capturado via $header: $ip");
                return $ip;
            }
            
            
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                error_log("[IP] ⚠️ IP capturado via $header (pode ser privado): $ip");
                return $ip;
            }
        }
    }
    
    error_log("[IP] ❌ Não foi possível capturar IP do cliente");
    return 'IP_DESCONHECIDO';
}

$client_ip = getClientIP();
error_log("[IP] 🌐 IP detectado do cliente: $client_ip");




$logDir = __DIR__ . '/logs';
$logFilePath = $logDir . '/pix-requests.log';


if (!file_exists($logDir)) {
    $created = @mkdir($logDir, 0777, true);
    if (!$created) {
        error_log("[ERRO CRÍTICO] ❌ Não foi possível criar diretório: $logDir");
    }
}


if (!is_writable($logDir)) {
    error_log("[ERRO CRÍTICO] ❌ Diretório não tem permissão de escrita: $logDir");
    @chmod($logDir, 0777);
}


$logLineInicial = date('Y-m-d H:i:s') . ' | IP: ' . $client_ip . ' | Status: REQUISIÇÃO INICIADA' . PHP_EOL;
$bytes = @file_put_contents($logFilePath, $logLineInicial, FILE_APPEND | LOCK_EX);

if ($bytes === false) {
    error_log("[ERRO CRÍTICO] ❌ Falha ao escrever no arquivo de log: $logFilePath");
    error_log("[ERRO CRÍTICO] 📁 Diretório existe? " . (file_exists($logDir) ? 'SIM' : 'NÃO'));
    error_log("[ERRO CRÍTICO] 🔓 Diretório gravável? " . (is_writable($logDir) ? 'SIM' : 'NÃO'));
} else {
    error_log("[LOG TXT] ✅ Log inicial salvo com sucesso: $bytes bytes em $logFilePath");
}



$blocked_ips = [
    '2804:14d:8e85:8025:5184:a4d6:5ad1:4270',
    '149.102.234.142',
    
];


if (in_array($client_ip, $blocked_ips)) {
    error_log("[BLOQUEIO] Acesso negado para IP bloqueado: " . $client_ip);
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Acesso negado'
    ]);
    exit;
}


function gerarCPF() {
    
    $cpf = '';
    for($i = 0; $i < 9; $i++) {
        $cpf .= rand(0, 9);
    }

    
    $soma = 0;
    for($i = 0; $i < 9; $i++) {
        $soma += intval($cpf[$i]) * (10 - $i);
    }
    $resto = $soma % 11;
    $digito1 = ($resto < 2) ? 0 : 11 - $resto;
    $cpf .= $digito1;

    
    $soma = 0;
    for($i = 0; $i < 10; $i++) {
        $soma += intval($cpf[$i]) * (11 - $i);
    }
    $resto = $soma % 11;
    $digito2 = ($resto < 2) ? 0 : 11 - $resto;
    $cpf .= $digito2;

    
    $invalidos = [
        '00000000000',
        '11111111111',
        '22222222222',
        '33333333333',
        '44444444444',
        '55555555555',
        '66666666666',
        '77777777777',
        '88888888888',
        '99999999999'
    ];

    if(in_array($cpf, $invalidos)) {
        return gerarCPF(); 
    }

    return $cpf;
}


function getUpsellTitle($valor) {
    
    switch($valor) {
        case 3995:
            return 'SMS';
        case 1970:
            return 'Upsell 2';
        case 3980:
            return 'Upsell 5';
        case 1790:
            return 'Upsell 3';
        case 2490:
            return 'Upsell 4';
        case 1890:
            return 'Upsell 6';
        case 6190:
            return 'Liberação de Benefício'; 
        case 2790:
            return 'Taxa de Verificação'; 
        default:
            return 'Produto ' . ($valor/100); 
    }
}

try {
    
    $apiUrl = 'https://multi.paradisepags.com/api/v1/transaction.php';
    $apiKey = 'sk_9771e4ac3de67f274a3d59672a60e646dde98611fa0fbe4b7b232e6d0ea4af23';

    
    $dbPath = __DIR__ . '/database.sqlite'; 
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    
    $createTableSQL = "
        CREATE TABLE IF NOT EXISTS pedidos (
            transaction_id TEXT PRIMARY KEY,
            status TEXT NOT NULL,
            valor INTEGER NOT NULL,
            nome TEXT,
            email TEXT,
            cpf TEXT,
            utm_params TEXT,
            created_at TEXT,
            updated_at TEXT
        )
    ";
    
    $db->exec($createTableSQL);
    
    
    $db->exec("CREATE INDEX IF NOT EXISTS idx_pedidos_status ON pedidos(status)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_pedidos_created_at ON pedidos(created_at)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_pedidos_valor ON pedidos(valor)");
    
    error_log("[Pagamento] 🔌 Conectado ao banco de dados SQLite em: " . $dbPath);
    error_log("[Pagamento] 📋 Tabela 'pedidos' verificada/criada com sucesso");

    
    $input = json_decode(file_get_contents('php://input'), true);
    
    
    $valor_centavos = $input['valor'] ?? $_POST['valor'] ?? $_GET['valor'] ?? null;
    
    
    if (!$valor_centavos || $valor_centavos <= 0) {
        $valor_centavos = 2169; 
        error_log("[Pagamento] ⚠️ Valor não recebido, usando padrão: " . $valor_centavos . " centavos");
    }
    
    $valor = $valor_centavos; 
    error_log("[Pagamento] 💰 Valor recebido: " . $valor_centavos . " centavos (R$ " . number_format($valor_centavos/100, 2, ',', '.') . ")");

    
    $nomes_masculinos = [
        'João', 'Pedro', 'Lucas', 'Miguel', 'Arthur', 'Gabriel', 'Bernardo', 'Rafael',
        'Gustavo', 'Felipe', 'Daniel', 'Matheus', 'Bruno', 'Thiago', 'Carlos'
    ];

    $nomes_femininos = [
        'Maria', 'Ana', 'Julia', 'Sofia', 'Isabella', 'Helena', 'Valentina', 'Laura',
        'Alice', 'Manuela', 'Beatriz', 'Clara', 'Luiza', 'Mariana', 'Sophia'
    ];

    $sobrenomes = [
        'Silva', 'Santos', 'Oliveira', 'Souza', 'Rodrigues', 'Ferreira', 'Alves', 
        'Pereira', 'Lima', 'Gomes', 'Costa', 'Ribeiro', 'Martins', 'Carvalho', 
        'Almeida', 'Lopes', 'Soares', 'Fernandes', 'Vieira', 'Barbosa'
    ];

    
    $dadosReais = [
        'nome' => $input['nome'] ?? $_POST['nome'] ?? $_GET['nome'] ?? null,
        'email' => $input['email'] ?? $_POST['email'] ?? $_GET['email'] ?? null,
        'cpf' => $input['cpf'] ?? $_POST['cpf'] ?? $_GET['cpf'] ?? null,
        'telefone' => $input['telefone'] ?? $_POST['telefone'] ?? $_GET['telefone'] ?? null
    ];
    
    
    $utmParams = [
        'utm_source' => $input['utm_source'] ?? $_POST['utm_source'] ?? $_GET['utm_source'] ?? null,
        'utm_medium' => $input['utm_medium'] ?? $_POST['utm_medium'] ?? $_GET['utm_medium'] ?? null,
        'utm_campaign' => $input['utm_campaign'] ?? $_POST['utm_campaign'] ?? $_GET['utm_campaign'] ?? null,
        'utm_content' => $input['utm_content'] ?? $_POST['utm_content'] ?? $_GET['utm_content'] ?? null,
        'utm_term' => $input['utm_term'] ?? $_POST['utm_term'] ?? $_GET['utm_term'] ?? null,
        'xcod' => $input['xcod'] ?? $_POST['xcod'] ?? $_GET['xcod'] ?? null,
        'sck' => $input['sck'] ?? $_POST['sck'] ?? $_GET['sck'] ?? null,
        'src' => $input['src'] ?? $_POST['src'] ?? $_GET['src'] ?? null,
        'utm_id' => $input['utm_id'] ?? $_POST['utm_id'] ?? $_GET['utm_id'] ?? null
    ];

    
    $utmParams = array_filter($utmParams, function($value) {
        return $value !== null && $value !== '';
    });

    error_log("[Pagamento] 👤 Dados reais recebidos: " . json_encode($dadosReais));
    error_log("[Pagamento] 📊 Parâmetros UTM recebidos: " . json_encode($utmParams));

    $utmQuery = http_build_query($utmParams);

    
    function sanitizeInput($input, $maxLength = 255) {
        if (empty($input)) return null;
        
        
        $input = trim($input);
        
        
        $input = preg_replace('/[\x00-\x1F\x7F]/u', '', $input);
        
        
        $input = mb_substr($input, 0, $maxLength, 'UTF-8');
        
        return $input;
    }

    function validateNome($nome) {
        
        $nome = sanitizeInput($nome, 100);
        if (empty($nome)) return null;
        
        
        $sqlPatterns = [
            '/(\bUNION\b|\bSELECT\b|\bINSERT\b|\bUPDATE\b|\bDELETE\b|\bDROP\b|\bEXEC\b|\bEXECUTE\b)/i',
            '/(\-\-|\/\*|\*\/|;)/i',  
            '/(\bOR\b|\bAND\b)\s*[\'\"]?\d+[\'\"]?\s*=\s*[\'\"]?\d+/i', 
        ];
        
        foreach ($sqlPatterns as $pattern) {
            if (preg_match($pattern, $nome)) {
                error_log("[SECURITY ALERT] 🚨 SQL Injection attempt detected in nome: " . $nome);
                return null; 
            }
        }
        
        
        if (!preg_match('/^[\p{L}\s\'\-]+$/u', $nome)) {
            error_log("[SECURITY ALERT] ⚠️ Invalid character in nome: " . $nome);
            return null;
        }
        
        return $nome;
    }

    function validateEmail($email) {
        $email = sanitizeInput($email, 255);
        if (empty($email)) return null;
        
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            error_log("[SECURITY ALERT] ⚠️ Invalid email format: " . $email);
            return null;
        }
        
        return $email;
    }

    function validateCPF($cpf) {
        $cpf = sanitizeInput($cpf, 14);
        if (empty($cpf)) return null;
        
       
        $cpf = preg_replace('/\D/', '', $cpf);
        
        
        if (strlen($cpf) !== 11) {
            return null;
        }
        
        return $cpf;
    }
    

    
    $dadosValidados = [
        'nome' => validateNome($dadosReais['nome']),
        'email' => validateEmail($dadosReais['email']),
        'cpf' => validateCPF($dadosReais['cpf']),
        'telefone' => !empty($dadosReais['telefone']) ? preg_replace('/\D/', '', $dadosReais['telefone']) : null
    ];

    
    if ($dadosReais['nome'] && !$dadosValidados['nome']) {
        error_log("[SECURITY ALERT] 🚨 Nome rejeitado por validação: " . $dadosReais['nome']);
    }
    if ($dadosReais['email'] && !$dadosValidados['email']) {
        error_log("[SECURITY ALERT] 🚨 Email rejeitado por validação: " . $dadosReais['email']);
    }
    if ($dadosReais['cpf'] && !$dadosValidados['cpf']) {
        error_log("[SECURITY ALERT] 🚨 CPF rejeitado por validação: " . $dadosReais['cpf']);
    }

    
    if (!empty($dadosValidados['nome']) && !empty($dadosValidados['cpf'])) {
        
        $nome_cliente = $dadosValidados['nome'];
        $cpf = $dadosValidados['cpf'];
        $telefone = $dadosValidados['telefone'] ?: '11999999999';
        
        // Usa email validado ou gera baseado no nome
        if (!empty($dadosValidados['email'])) {
            $email = $dadosValidados['email'];
            error_log("[Pagamento] 📧 Usando email REAL validado: " . $email);
        } else {
            $email = strtolower(str_replace([' ', '+'], ['.', '.'], $nome_cliente)) . '@email.com';
            error_log("[Pagamento] 📧 Email gerado baseado no nome: " . $email);
        }
        
        error_log("[Pagamento] ✅ Usando dados REAIS VALIDADOS do cliente: Nome: $nome_cliente, CPF: " . substr($cpf, 0, 3) . ".***.***-" . substr($cpf, -2));
    } else {
        
        $genero = rand(0, 1);
        $nome = $genero ? 
            $nomes_masculinos[array_rand($nomes_masculinos)] : 
            $nomes_femininos[array_rand($nomes_femininos)];
        
        $sobrenome1 = $sobrenomes[array_rand($sobrenomes)];
        $sobrenome2 = $sobrenomes[array_rand($sobrenomes)];
        
        $nome_cliente = "$nome $sobrenome1 $sobrenome2";
        $email = strtolower(str_replace(' ', '.', $nome_cliente)) . '@email.com';
        $cpf = gerarCPF();
        $telefone = '11999999999';
        
        error_log("[Pagamento] ⚠️ Usando dados FALSOS como fallback: Nome: $nome_cliente, CPF: $cpf, Telefone: $telefone");
    }

    
    $produtoTitulo = getUpsellTitle($valor_centavos);
    error_log("[Pagamento] 🏷️ Título do produto: " . $produtoTitulo);

    
    $logLineCompleto = date('Y-m-d H:i:s') . ' | IP: ' . $client_ip . ' | Nome: ' . $nome_cliente . ' | Valor: R$ ' . number_format($valor_centavos/100, 2, ',', '.') . PHP_EOL;
    $bytes = @file_put_contents($logFilePath, $logLineCompleto, FILE_APPEND | LOCK_EX);
    
    if ($bytes === false) {
        error_log("[LOG TXT] ❌ ERRO ao salvar log completo");
    } else {
        error_log("[LOG TXT] ✅ Log completo salvo: IP=$client_ip | Nome=$nome_cliente | Bytes: $bytes");
        error_log("[LOG TXT] 📂 Arquivo: $logFilePath");
    }
    

    // Gera uma referência única para a transação
    $reference = 'REF-' . time() . '-' . rand(1000, 9999);

    error_log("[ParadisePags] 📝 Preparando dados para envio: " . json_encode([
        'valor' => $valor,
        'valor_centavos' => $valor_centavos,
        'nome' => $nome_cliente,
        'email' => $email,
        'cpf' => $cpf,
        'telefone' => $telefone,
        'reference' => $reference
    ]));

    
    $data = [
        "amount" => $valor_centavos,
        "description" => $produtoTitulo,
        "reference" => $reference,
        "productHash" => 'prod_80c0314f663acfe8', 
        "customer" => [
            "name" => $nome_cliente,
            "document" => $cpf,
            "email" => $email,
            "phone" => $telefone
        ]
    ];

    
    if (!empty($utmParams)) {
        $data['tracking'] = $utmParams;
    }

    error_log("[ParadisePags] 🌐 URL da requisição: " . $apiUrl);
    error_log("[ParadisePags] 📦 Dados enviados: " . json_encode($data));

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-API-Key: ' . $apiKey
    ]);
    
    
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    
    
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');

    
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    $verbose = fopen('php://temp', 'w+');
    curl_setopt($ch, CURLOPT_STDERR, $verbose);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $curlErrno = curl_errno($ch);
    
    
    $redirectCount = curl_getinfo($ch, CURLINFO_REDIRECT_COUNT);
    $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    
    error_log("[ParadisePags] 🔄 Redirecionamentos: " . $redirectCount);
    error_log("[ParadisePags] 🎯 URL final: " . $effectiveUrl);

    
    rewind($verbose);
    $verboseLog = stream_get_contents($verbose);
    error_log("[ParadisePags] 🔍 Detalhes da requisição cURL:\n" . $verboseLog);

    if ($curlError) {
        error_log("[ParadisePags] ❌ Erro cURL: " . $curlError . " (errno: " . $curlErrno . ")");
        throw new Exception("Erro na requisição: " . $curlError);
    }

    curl_close($ch);

    error_log("[ParadisePags] 📊 HTTP Status Code: " . $httpCode);
    error_log("[ParadisePags] 📄 Resposta bruta: " . $response);

    
    if ($httpCode < 200 || $httpCode >= 300) {
        $errorMsg = "Erro na API: HTTP " . $httpCode;
        if (!empty($response)) {
            $errorMsg .= " - " . $response;
        }
        throw new Exception($errorMsg);
    }

    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Erro ao decodificar resposta: " . json_last_error_msg() . " - Resposta: " . $response);
    }

    
    if (!isset($result['status']) || $result['status'] !== 'success') {
        $errorMsg = isset($result['message']) ? $result['message'] : 'Erro desconhecido na API';
        throw new Exception("Erro na API: " . $errorMsg);
    }

    if (!isset($result['transaction_id'])) {
        throw new Exception("Transaction ID não encontrado na resposta da API");
    }

    
    $transactionId = $result['transaction_id'];

    
    $stmt = $db->prepare("INSERT OR REPLACE INTO pedidos (transaction_id, status, valor, nome, email, cpf, utm_params, created_at, updated_at) 
        VALUES (:transaction_id, 'pending', :valor, :nome, :email, :cpf, :utm_params, :created_at, :updated_at)");
    $stmt->execute([
        'transaction_id' => $transactionId,
        'valor' => $valor_centavos,
        'nome' => $nome_cliente,
        'email' => $email,
        'cpf' => $cpf,
        'utm_params' => json_encode($utmParams),
        'created_at' => date('c'),
        'updated_at' => date('c')
    ]);
    
    error_log("[Pagamento] 💾 Dados salvos/atualizados no banco SQLite com transaction_id: " . $transactionId);

    session_start();
    $_SESSION['payment_id'] = $transactionId;
    
    error_log("[ParadisePags] 💳 Transação criada com sucesso: " . $transactionId);
    error_log("[ParadisePags] 📄 Resposta completa da API: " . $response);
    error_log("[ParadisePags] 🔑 Token gerado: " . $transactionId);

   
    error_log("[Sistema] 📡 Iniciando comunicação com otimizey-pendente.php");

    $otimizeyData = [
        'externalUserRef' => $email,
        'product' => [
            'id' => 'produto-checkout',
            'name' => 'emagreca em 21 dias',
            'price' => floatval($valor_centavos / 100)
        ],
        'orderId' => (string)$transactionId,
        'paymentMethod' => 'pix',
        'status' => 'waiting_payment',
        'totalPrice' => floatval($valor_centavos / 100),
        'receivedPrice' => floatval($valor_centavos / 100),
        'name' => $nome_cliente,
        'phone' => $telefone
    ];

    
    if (isset($utmParams['sck']) && !empty($utmParams['sck'])) {
        $otimizeyData['sck'] = $utmParams['sck'];
    }
    if (isset($utmParams['src']) && !empty($utmParams['src'])) {
        $otimizeyData['src'] = $utmParams['src'];
    }
    if (isset($utmParams['utm_source']) && !empty($utmParams['utm_source'])) {
        $otimizeyData['utmSource'] = $utmParams['utm_source'];
    }
    if (isset($utmParams['utm_medium']) && !empty($utmParams['utm_medium'])) {
        $otimizeyData['utmMedium'] = $utmParams['utm_medium'];
    }
    if (isset($utmParams['utm_campaign']) && !empty($utmParams['utm_campaign'])) {
        $otimizeyData['utmCampaign'] = $utmParams['utm_campaign'];
    }
    if (isset($utmParams['utm_content']) && !empty($utmParams['utm_content'])) {
        $otimizeyData['utmContent'] = $utmParams['utm_content'];
    }

    error_log("[Otimizey] 📦 Preparando dados para envio ao otimizey-pendente.php: " . json_encode($otimizeyData));

    $serverUrlOtimizey = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
    $currentDirOtimizey = dirname($_SERVER['REQUEST_URI']);
    $otimizeyUrl = $serverUrlOtimizey . $currentDirOtimizey . "/otimizey-pendente.php";
    error_log("[Sistema] 🌐 URL Otimizey pendente construída dinamicamente: " . $otimizeyUrl);
    
    $ch = curl_init($otimizeyUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($otimizeyData),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);

    $otimizeyResponse = curl_exec($ch);
    $otimizeyHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $otimizeyError = curl_error($ch);
    $otimizeyErrno = curl_errno($ch);
    
    error_log("[Sistema] 🔍 Detalhes da requisição Otimizey: " . print_r([
        'url' => $otimizeyUrl,
        'status' => $otimizeyHttpCode,
        'resposta' => $otimizeyResponse,
        'erro' => $otimizeyError,
        'errno' => $otimizeyErrno
    ], true));
    
    curl_close($ch);

    error_log("[Sistema] ✉️ Resposta do otimizey-pendente.php: " . $otimizeyResponse);
    error_log("[Sistema] 📊 Status code do otimizey-pendente.php: " . $otimizeyHttpCode);

    $otimizeyResponseDecoded = json_decode($otimizeyResponse, true);

    if ($otimizeyHttpCode !== 200) {
        error_log("[Sistema] ❌ Erro ao enviar dados para otimizey-pendente.php: " . $otimizeyResponse);
        if ($otimizeyResponseDecoded) {
            error_log("[Sistema] 📋 Detalhes do erro Otimizey: " . json_encode($otimizeyResponseDecoded, JSON_PRETTY_PRINT));
        }
    } else {
        error_log("[Sistema] ✅ Dados enviados com sucesso para otimizey-pendente.php");
        if ($otimizeyResponseDecoded) {
            error_log("[Sistema] 📋 Resposta Otimizey: " . json_encode($otimizeyResponseDecoded, JSON_PRETTY_PRINT));
        }
    }
    

    error_log("[Sistema] 📡 Iniciando comunicação com utmify-pendente.php");

    $utmifyData = [
        'orderId' => (string)$transactionId,
        'platform' => 'MinhaPlataforma',
        'paymentMethod' => 'pix',
        'status' => 'waiting_payment',
        'createdAt' => date('Y-m-d H:i:s'),
        'approvedDate' => null,
        'refundedAt' => null,
        'customer' => [
            'name' => $nome_cliente,
            'email' => $email,
            'phone' => $telefone,
            'document' => $cpf,
            'country' => 'BR',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null
        ],
        'products' => [
            [
                'id' => uniqid('PROD_'),
                'name' => $produtoTitulo,
                'planId' => null,
                'planName' => null,
                'quantity' => 1,
                'priceInCents' => intval($valor_centavos)
            ]
        ],
        'trackingParameters' => $utmParams,
        'commission' => [
            'totalPriceInCents' => intval($valor_centavos),
            'gatewayFeeInCents' => 0,
            'userCommissionInCents' => intval($valor_centavos)
        ],
        'isTest' => false
    ];

    error_log("[Utmify] 📦 Preparando dados para envio ao utmify-pendente.php: " . json_encode($utmifyData));

    
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443 ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    
    
    $scriptPath = dirname($_SERVER['SCRIPT_NAME']);
    
    
    $scriptPath = rtrim($scriptPath, '/');
    
    
    $utmifyUrl = $protocol . "://" . $host . $scriptPath . "/utmify-pendente.php";
    
    error_log("[Utmify] 🌐 URL construída: " . $utmifyUrl);
    
    
    $utmifyPayload = json_encode($utmifyData);
    error_log("[Utmify] 📋 Payload JSON: " . $utmifyPayload);
    
    $ch = curl_init($utmifyUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $utmifyPayload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10
    ]);

    error_log("[Utmify] � Iniciando requisição para: " . $utmifyUrl);
    
    $utmifyResponse = curl_exec($ch);
    $utmifyHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $utmifyError = curl_error($ch);
    $utmifyErrno = curl_errno($ch);
    
    $curlInfo = curl_getinfo($ch);
    
    error_log("[Utmify] 🔍 Detalhes completos da requisição: " . json_encode([
        'url' => $utmifyUrl,
        'http_code' => $utmifyHttpCode,
        'total_time' => $curlInfo['total_time'],
        'connect_time' => $curlInfo['connect_time'],
        'size_download' => $curlInfo['size_download'],
        'resposta' => $utmifyResponse,
        'erro' => $utmifyError,
        'errno' => $utmifyErrno
    ], JSON_PRETTY_PRINT));
    
    curl_close($ch);

    error_log("[Utmify] ✉️ Resposta do utmify-pendente.php: " . $utmifyResponse);
    error_log("[Utmify] 📊 Status HTTP: " . $utmifyHttpCode);

    if ($utmifyErrno !== 0) {
        error_log("[Utmify] ❌ Erro cURL ao enviar para utmify-pendente.php: " . $utmifyError . " (errno: " . $utmifyErrno . ")");
    } elseif ($utmifyHttpCode !== 200) {
        error_log("[Utmify] ⚠️ Status HTTP não-200 do utmify-pendente.php: " . $utmifyHttpCode . " - Resposta: " . $utmifyResponse);
    } else {
        error_log("[Utmify] ✅ Dados enviados com sucesso para utmify-pendente.php");
        
        
        $utmifyResponseData = json_decode($utmifyResponse, true);
        if ($utmifyResponseData) {
            error_log("[Utmify] 📦 Resposta decodificada: " . json_encode($utmifyResponseData, JSON_PRETTY_PRINT));
        }
    }

     
    $qrCodeUrl = $result['qr_code_base64'] ?? null;
    
    
    if (empty($qrCodeUrl) && !empty($result['qr_code'])) {
        $qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($result['qr_code']);
        error_log("[ParadisePags] 🔄 QR Code gerado via API externa: " . $qrCodeUrl);
    }

    
    $responseData = [
        'success' => true,
        'token' => $transactionId,
        'pixCode' => $result['qr_code'] ?? null,
        'pixCopiaECola' => $result['qr_code'] ?? null, 
        'qrCodeUrl' => $qrCodeUrl, 
        'valor' => $valor,
        'expires_at' => $result['expires_at'] ?? null,
        'logs' => [
            'utmParams' => $utmParams,
            'transacao' => [
                'valor' => $valor,
                'cliente' => $nome_cliente,
                'email' => $email,
                'cpf' => $cpf,
                'reference' => $reference
            ],
            'otimizeyResponse' => [
                'status' => $otimizeyHttpCode,
                'resposta' => $otimizeyResponse
            ],
            'utmifyResponse' => [
                'status' => $utmifyHttpCode,
                'resposta' => $utmifyResponse
            ]
        ]
    ];

    error_log("[ParadisePags] 📤 Enviando resposta ao frontend: " . json_encode($responseData));
    echo json_encode($responseData);

} catch (Exception $e) {
    error_log("[ParadisePags] ❌ Erro: " . $e->getMessage());
    error_log("[ParadisePags] 🔍 Stack trace: " . $e->getTraceAsString());
    
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao gerar o PIX: ' . $e->getMessage()
    ]);
}