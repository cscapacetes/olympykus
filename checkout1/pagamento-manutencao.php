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
    for ($i = 0; $i < 9; $i++) {
        $cpf .= rand(0, 9);
    }

    $soma = 0;
    for ($i = 0; $i < 9; $i++) {
        $soma += intval($cpf[$i]) * (10 - $i);
    }
    $resto = $soma % 11;
    $digito1 = ($resto < 2) ? 0 : 11 - $resto;
    $cpf .= $digito1;

    $soma = 0;
    for ($i = 0; $i < 10; $i++) {
        $soma += intval($cpf[$i]) * (11 - $i);
    }
    $resto = $soma % 11;
    $digito2 = ($resto < 2) ? 0 : 11 - $resto;
    $cpf .= $digito2;

    $invalidos = [
        '00000000000', '11111111111', '22222222222', '33333333333', 
        '44444444444', '55555555555', '66666666666', '77777777777', 
        '88888888888', '99999999999'
    ];

    if (in_array($cpf, $invalidos)) {
        return gerarCPF();
    }

    return $cpf;
}

try {
    
    $apiUrl = 'https://www.pagamentos-seguros.app/api-pix/aBjNtTd_UmhINfLaoH6CbhIiKq9wENaKvd8Xk7KV2k6uezWOKNgSJTTVKbKgmfomHoAsLk7JAf5kTiSJG18fTw';
    $chaveEncriptada = 'fee6a61ce9eed3d80cc303ffea41c9b0';

    
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

    // RECEBE DADOS DO CLIENTE DA REQUISIÇÃO (index.html envia valor JÁ EM CENTAVOS)
    $input = json_decode(file_get_contents('php://input'), true);
    
    $valor_recebido = $input['valor'] ?? $_POST['valor'] ?? $_GET['valor'] ?? null;
    
    // O valor já vem em CENTAVOS do index.html (ex: 2456 centavos = R$ 24.56)
    $valor_centavos = $valor_recebido ? intval($valor_recebido) : null;
    $valor_reais = $valor_centavos ? $valor_centavos / 100 : null;
    
    // Se ainda não tiver valor, usa o valor padrão
    if (!$valor_centavos || $valor_centavos <= 0) {
        $valor_centavos = 5581; // 55.81 em centavos
        $valor_reais = 55.81;
        error_log("[Pagamento] ⚠️ Valor não recebido, usando padrão: R$ " . $valor_reais . " (" . $valor_centavos . " centavos)");
    }
    
    error_log("[Pagamento] 💰 Valor recebido: " . $valor_centavos . " centavos = R$ " . number_format($valor_reais, 2, ',', '.'));

    if (!$valor_centavos || $valor_centavos <= 0) {
        throw new Exception('Valor inválido');
    }

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
    

    
    $dadosReais = [
        'nome' => $input['nome'] ?? $_POST['nome'] ?? $_GET['nome'] ?? null,
        'email' => $input['email'] ?? $_POST['email'] ?? $_GET['email'] ?? null,
        'cpf' => $input['cpf'] ?? $_POST['cpf'] ?? $_GET['cpf'] ?? null,
        'telefone' => $input['telefone'] ?? $_POST['telefone'] ?? $_POST['phone'] ?? $_GET['telefone'] ?? $_GET['phone'] ?? null
    ];

    error_log("[Pagamento] 👤 Dados reais recebidos: " . json_encode($dadosReais));

    
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

    $nome_requisicao = $dadosValidados['nome'];
    
    if ($nome_requisicao && !empty(trim($nome_requisicao)) && trim($nome_requisicao) !== 'Cliente') {
        $nome_cliente = trim($nome_requisicao);
    } else {
        $genero = rand(0, 1);
        $nome = $genero ? 
            $nomes_masculinos[array_rand($nomes_masculinos)] : 
            $nomes_femininos[array_rand($nomes_femininos)];
        
        $sobrenome1 = $sobrenomes[array_rand($sobrenomes)];
        $sobrenome2 = $sobrenomes[array_rand($sobrenomes)];
        
        $nome_cliente = "$nome $sobrenome1 $sobrenome2";
        error_log("[Pagamento] ⚠️ Usando dados FALSOS como fallback: Nome: $nome_cliente");
    }
    
    // Pega o email do frontend - usa dados validados
    $email_requisicao = $dadosValidados['email'];
    
    if ($email_requisicao && !empty(trim($email_requisicao))) {
        $email = trim($email_requisicao);
        error_log("[Pagamento] 📧 Usando email REAL validado: " . $email);
    } else {
        // Gerar email baseado no nome do cliente como fallback
        function gerarEmailDoNome($nome) {
            $nome_limpo = iconv('UTF-8', 'ASCII//TRANSLIT', $nome);
            $nome_limpo = preg_replace('/[^a-zA-Z0-9\s]/', '', $nome_limpo);
            $nome_limpo = strtolower(trim($nome_limpo));
            $nome_limpo = preg_replace('/\s+/', '.', $nome_limpo);
            
            if (empty($nome_limpo)) {
                $nome_limpo = 'cliente';
            }
            
            return $nome_limpo . '@gmail.com';
        }
        $email = gerarEmailDoNome($nome_cliente);
        error_log("[Pagamento] 📧 Email gerado baseado no nome: " . $email);
    }
    
    // Pega o telefone do frontend - usa dados validados
    $phone_requisicao = $dadosValidados['telefone'];
    
    if ($phone_requisicao && !empty(trim($phone_requisicao))) {
        $phone = trim($phone_requisicao);
        error_log("[Pagamento] 📱 Telefone recebido: " . $phone);
    } else {
        $phone = "11999999999"; // Fallback
        error_log("[Pagamento] 📱 Telefone não enviado, usando fallback: " . $phone);
    }
    
    // Pega o CPF do frontend - usa dados validados
    $cpf_requisicao = $dadosValidados['cpf'];
    
    if ($cpf_requisicao && !empty(trim($cpf_requisicao))) {
        $cpf = trim($cpf_requisicao);
        error_log("[Pagamento] ✅ Usando CPF REAL validado: " . substr($cpf, 0, 3) . ".***.***-" . substr($cpf, -2));
    } else {
        $cpf = gerarCPF();
        error_log("[Pagamento] ⚠️ CPF gerado como fallback");
    }

    
    $logLineCompleto = date('Y-m-d H:i:s') . ' | IP: ' . $client_ip . ' | Nome: ' . $nome_cliente . ' | Valor: R$ ' . number_format($valor_centavos/100, 2, ',', '.') . PHP_EOL;
    $bytes = @file_put_contents($logFilePath, $logLineCompleto, FILE_APPEND | LOCK_EX);
    
    if ($bytes === false) {
        error_log("[LOG TXT] ❌ ERRO ao salvar log completo");
    } else {
        error_log("[LOG TXT] ✅ Log completo salvo: IP=$client_ip | Nome=$nome_cliente | Bytes: $bytes");
        error_log("[LOG TXT] 📂 Arquivo: $logFilePath");
    }
    

    error_log("[DuttyOnPay] 📝 Preparando dados para envio: " . json_encode([
        'valor_reais' => $valor_reais,
        'valor_centavos' => $valor_centavos,
        'nome' => $nome_cliente,
        'email' => $email,
        'cpf' => $cpf,
        'phone' => $phone
    ]));

    $data = [
        "amount" => $valor_centavos,
        "description" => "Corre",
        "customer" => [
            "name" => $nome_cliente,
            "document" => $cpf,
            "email" => $email,
            "phone" => $phone
        ],
        "item" => [
            "title" => "Corre",
            "price" => $valor_centavos,
            "quantity" => 1
        ],
        "paymentMethod" => "PIX",
        "utm" => $utmQuery
    ];

    error_log("[DuttyOnPay] 🌐 URL da requisição: " . $apiUrl);
    error_log("[DuttyOnPay] 📦 Dados enviados: " . json_encode($data));

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);

    curl_setopt($ch, CURLOPT_VERBOSE, true);
    $verbose = fopen('php://temp', 'w+');
    curl_setopt($ch, CURLOPT_STDERR, $verbose);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $curlErrno = curl_errno($ch);

    rewind($verbose);
    $verboseLog = stream_get_contents($verbose);
    error_log("[DuttyOnPay] 🔍 Detalhes da requisição cURL:\n" . $verboseLog);

    if ($curlError) {
        error_log("[DuttyOnPay] ❌ Erro cURL: " . $curlError . " (errno: " . $curlErrno . ")");
        throw new Exception("Erro na requisição: " . $curlError);
    }

    curl_close($ch);

    error_log("[DuttyOnPay] 📊 HTTP Status Code: " . $httpCode);
    error_log("[DuttyOnPay] 📄 Resposta bruta: " . $response);

    if ($httpCode !== 200 && $httpCode !== 201) {
        // Decodifica resposta de erro para mais detalhes
        $errorData = json_decode($response, true);
        $errorMessage = "Erro na API: HTTP " . $httpCode;
        
        if ($errorData && isset($errorData['error'])) {
            $errorMessage .= " - " . $errorData['error'];
        } else if ($errorData && isset($errorData['message'])) {
            $errorMessage .= " - " . $errorData['message'];
        } else {
            $errorMessage .= " - " . $response;
        }
        
        error_log("[DuttyOnPay] ❌ Detalhes do erro: " . print_r($errorData, true));
        throw new Exception($errorMessage);
    }

    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Erro ao decodificar resposta: " . json_last_error_msg() . " - Resposta: " . $response);
    }

    if (!isset($result['transactionId'])) {
        throw new Exception("Transaction ID não encontrado na resposta da API");
    }

    // Usa transaction_id da DuttyOnPay
    $transactionId = $result['transactionId'];

    
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

    error_log("[DuttyOnPay] 💳 Transação criada com sucesso: " . $transactionId);
    error_log("[DuttyOnPay] 📄 Resposta completa da API: " . $response);
    error_log("[DuttyOnPay] 🔑 Token gerado: " . $transactionId);

    error_log("[Sistema] 📡 Iniciando comunicação com utmify-pendente.php");

    $utmifyData = [
        'orderId' => $transactionId,
        'platform' => 'MinhaPlataforma',
        'paymentMethod' => 'pix',
        'status' => 'waiting_payment',
        'createdAt' => date('Y-m-d H:i:s'),
        'approvedDate' => null,
        'refundedAt' => null,
        'customer' => [
            'name' => $nome_cliente,
            'email' => $email,
            'phone' => null,
            'document' => $cpf,
            'country' => 'BR',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null
        ],
        'products' => [
            [
                'id' => uniqid('PROD_'),
                'name' => 'Corre',
                'planId' => null,
                'planName' => null,
                'quantity' => 1,
                'priceInCents' => $valor_centavos
            ]
        ],
        'trackingParameters' => $utmParams,
        'commission' => [
            'totalPriceInCents' => $valor_centavos,
            'gatewayFeeInCents' => 0,
            'userCommissionInCents' => $valor_centavos
        ],
        'isTest' => false
    ];

    error_log("[Utmify] 📦 Preparando dados para envio ao utmify-pendente.php: " . json_encode($utmifyData));

    // Envia para utmify-pendente.php
    error_log("[Sistema] 📡 Enviando requisição POST para ../utmify-pendente.php");
    
    $serverUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
        $currentDir = dirname($_SERVER['REQUEST_URI']);
        $utmifyUrl = $serverUrl . $currentDir . "/utmify-penden2222te.php";
        $logs[] = "🌐 URL Utmify pendente construída dinamicamente: " . $utmifyUrl;
    
    $ch = curl_init($utmifyUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($utmifyData),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);

    $utmifyResponse = curl_exec($ch);
    $utmifyHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $utmifyError = curl_error($ch);
    $utmifyErrno = curl_errno($ch);
    
    error_log("[Sistema] 🔍 Detalhes da requisição Utmify: " . print_r([
        'url' => $utmifyUrl,
        'status' => $utmifyHttpCode,
        'resposta' => $utmifyResponse,
        'erro' => $utmifyError,
        'errno' => $utmifyErrno
    ], true));
    
    curl_close($ch);

    error_log("[Sistema] ✉️ Resposta do utmify-pendente.php: " . $utmifyResponse);
    error_log("[Sistema] 📊 Status code do utmify-pendente.php: " . $utmifyHttpCode);

    if ($utmifyHttpCode !== 200) {
        error_log("[Sistema] ❌ Erro ao enviar dados para utmify-pendente.php: " . $utmifyResponse);
    } else {
        error_log("[Sistema] ✅ Dados enviados com sucesso para utmify-pendente.php");
    }

    
    $responseData = [
        'success' => true,
        'token' => $transactionId,
        'pixCode' => $result['pixCode'] ?? null,
        'pixCopiaECola' => $result['pixCode'] ?? null, 
        'qrCodeUrl' => isset($result['pixCode']) ? 
            'https://api.qrserver.com/v1/create-qr-code/?data=' . urlencode($result['pixCode']) . '&size=300x300&charset-source=UTF-8&charset-target=UTF-8&qzone=1&format=png&ecc=L' : 
            null,
        'valor' => $valor_centavos,
        'logs' => [
            'utmParams' => $utmParams,
            'transacao' => [
                'valor' => $valor_centavos,
                'valor_reais' => $valor_reais,
                'cliente' => $nome_cliente,
                'email' => $email,
                'cpf' => $cpf,
                'phone' => $phone
            ],
            'utmifyResponse' => [
                'status' => $utmifyHttpCode,
                'resposta' => $utmifyResponse
            ]
        ]
    ];

    error_log("[DuttyOnPay] 📤 Enviando resposta ao frontend: " . json_encode($responseData));
    echo json_encode($responseData);

} catch (Exception $e) {
    error_log("[DuttyOnPay] ❌ Erro: " . $e->getMessage());
    error_log("[DuttyOnPay] 🔍 Stack trace: " . $e->getTraceAsString());
    
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao gerar o PIX: ' . $e->getMessage()
    ]);
}
?>