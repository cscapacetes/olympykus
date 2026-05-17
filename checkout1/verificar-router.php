<?php
/**
 * ROTEADOR DE VERIFICAÇÃO DE PAGAMENTO
 * 
 * Este arquivo redireciona automaticamente para o verificador correto
 * baseado no gateway configurado em payment-config.php
 * 
 * NÃO EDITE ESTE ARQUIVO!
 * Para trocar de gateway, edite o arquivo payment-config.php
 */

// Carrega configuração do gateway
require_once __DIR__ . '/payment-config.php';

error_log("[Verificar Router] 🔄 Redirecionando verificação para gateway: " . getActiveGateway());

// Mapeia o gateway para o arquivo de verificação correto
$VERIFICATION_FILES = [
    'paradise' => 'verificar-paradise.php',
    'dutty' => 'verificar-dutty.php',
    'mangofy' => 'verificar-mangofy.php',
    'ghost' => 'verificar-ghost.php',
    'buck' => 'verificar-buck.php',
    'naut' => 'verificar-naut.php',
    'zero' => 'verificar-zero.php',
    'manutencao' => 'verificar-manutencao.php'
];

// Define o arquivo do verificador ativo
$VERIFICATION_FILE = $VERIFICATION_FILES[$ACTIVE_GATEWAY];

error_log("[Verificar Router] 📄 Arquivo de verificação: " . $VERIFICATION_FILE);

// Verifica se o arquivo do verificador existe
if (!file_exists(__DIR__ . '/' . $VERIFICATION_FILE)) {
    error_log("[Verificar Router] ❌ ERRO: Arquivo verificador não encontrado: " . $VERIFICATION_FILE);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => 'Verificador de pagamento não encontrado. Contate o suporte.'
    ]);
    exit;
}

// Redireciona todos os dados (GET, POST, etc.) para o verificador configurado
require __DIR__ . '/' . $VERIFICATION_FILE;
