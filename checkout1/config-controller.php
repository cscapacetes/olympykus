<?php
/**
 * CONTROLADOR DE CONFIGURAÇÕES DO CHECKOUT
 */
session_start();

// Verificar se está logado (exceto para GET)
if ($_SERVER['REQUEST_METHOD'] !== 'GET' && !isset($_SESSION['gateway_admin_logged'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$configFile = __DIR__ . '/checkout-config.json';

// Função para ler configurações
function readConfig($configFile) {
    if (!file_exists($configFile)) {
        return [
            'product_price' => '',
            'product_name' => '',
            'product_description' => '',
            'product_image' => '',
            'company_name' => 'Compra Segura',
            'company_cnpj' => '',
            'company_email' => '',
            'show_cnpj' => true,
            'show_email' => true,
            'company_logo' => '',
            'checkout_model' => 'vega',
            'last_updated' => date('Y-m-d H:i:s')
        ];
    }
    
    $content = file_get_contents($configFile);
    return json_decode($content, true) ?: [];
}

// Função para salvar configurações
function saveConfig($configFile, $config) {
    $config['last_updated'] = date('Y-m-d H:i:s');
    return file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// Processar requisições
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Retornar configurações atuais
    $action = $_GET['action'] ?? '';
    
    if ($action === 'get_config') {
        $config = readConfig($configFile);
        echo json_encode([
            'success' => true,
            'config' => $config
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Ação inválida']);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_config') {
        // Ação específica para salvar apenas checkout_model sem validar outros campos
        $checkoutModel = $_POST['checkout_model'] ?? 'vega';
        
        // Validar modelo
        if (!in_array($checkoutModel, ['vega', 'luna'])) {
            echo json_encode(['success' => false, 'message' => 'Modelo inválido']);
            exit;
        }
        
        // Ler configuração atual
        $config = readConfig($configFile);
        
        // Atualizar apenas o modelo
        $config['checkout_model'] = $checkoutModel;
        
        // Salvar configurações
        if (saveConfig($configFile, $config)) {
            echo json_encode([
                'success' => true,
                'message' => 'Modelo salvo com sucesso!',
                'checkout_model' => $checkoutModel
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao salvar modelo']);
        }
        
    } elseif ($action === 'update_config') {
        $price = $_POST['price'] ?? '';
        $name = $_POST['name'] ?? '';
        $description = $_POST['description'] ?? '';
        $image = $_POST['image'] ?? '';
        $companyName = $_POST['company_name'] ?? '';
        $companyCnpj = $_POST['company_cnpj'] ?? '';
        $companyEmail = $_POST['company_email'] ?? '';
        $showCnpj = $_POST['show_cnpj'] ?? 'true';
        $showEmail = $_POST['show_email'] ?? 'true';
        $companyLogo = $_POST['company_logo'] ?? '';
        $isDigital = $_POST['is_digital'] ?? 'false';
        $depoimentosEnabled = $_POST['depoimentos_enabled'] ?? 'true';
        $showSafeBadge = $_POST['show_safe_badge'] ?? 'true';
        $safeBadgeImage = $_POST['safe_badge_image'] ?? '';
        $showCompanyLogo = $_POST['show_company_logo'] ?? 'true';
        $offersData = $_POST['offers'] ?? '';
        $freteData = $_POST['frete'] ?? '';
        $depoimentosData = $_POST['depoimentos'] ?? '';
        $colorsData = $_POST['colors'] ?? '';
        
        // Validar preço
        // Remover separador de milhares (.) e depois substituir vírgula decimal por ponto
        $priceClean = str_replace('.', '', $price); // Remove separador de milhares
        $priceClean = str_replace(',', '.', $priceClean); // Substitui vírgula decimal por ponto
        
        if (empty($price) || !is_numeric($priceClean)) {
            echo json_encode(['success' => false, 'message' => 'Preço inválido']);
            exit;
        }
        
        // Validar nome do produto
        if (empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Nome do produto é obrigatório']);
            exit;
        }
        
        // Ler configuração atual
        $config = readConfig($configFile);
        
        // Atualizar valores (manter formato brasileiro com vírgula)
        $config['product_price'] = $price;
        $config['product_name'] = $name;
        
        if (!empty($description)) {
            $config['product_description'] = $description;
        }
        
        if (!empty($image)) {
            $config['product_image'] = $image;
        }
        
        if (!empty($companyName)) {
            $config['company_name'] = $companyName;
        }
        
        if (!empty($companyCnpj)) {
            $config['company_cnpj'] = $companyCnpj;
        }
        
        if (!empty($companyEmail)) {
            $config['company_email'] = $companyEmail;
        }
        
        // Salvar configuração de exibição de CNPJ e Email
        $config['show_cnpj'] = ($showCnpj === 'true' || $showCnpj === true);
        $config['show_email'] = ($showEmail === 'true' || $showEmail === true);
        
        if (!empty($companyLogo)) {
            $config['company_logo'] = $companyLogo;
        }
        
        // Salvar configuração de produto digital
        $config['is_digital'] = ($isDigital === 'true' || $isDigital === true);
        
        // Salvar configuração de depoimentos
        $config['depoimentos_enabled'] = ($depoimentosEnabled === 'true' || $depoimentosEnabled === true);
        
        // Salvar configuração do badge seguro
        $config['show_safe_badge'] = ($showSafeBadge === 'true' || $showSafeBadge === true);
        
        // Salvar imagem do badge seguro se fornecida
        if (!empty($safeBadgeImage)) {
            $config['safe_badge_image'] = $safeBadgeImage;
        } else {
            $config['safe_badge_image'] = '';
        }
        
        // Salvar configuração da logo da empresa
        $config['show_company_logo'] = ($showCompanyLogo === 'true' || $showCompanyLogo === true);
        
        // Processar ofertas se fornecidas
        if (!empty($offersData)) {
            $offers = json_decode($offersData, true);
            if ($offers !== null) {
                $config['offers'] = $offers;
            }
        }
        
        // Processar frete se fornecido
        if (!empty($freteData)) {
            $frete = json_decode($freteData, true);
            if ($frete !== null) {
                $config['frete'] = $frete;
            }
        }
        
        // Processar depoimentos se fornecidos
        if (!empty($depoimentosData)) {
            $depoimentos = json_decode($depoimentosData, true);
            if ($depoimentos !== null) {
                $config['depoimentos'] = $depoimentos;
            }
        }
        
        // Processar cores se fornecidas
        if (!empty($colorsData)) {
            $colors = json_decode($colorsData, true);
            if ($colors !== null) {
                $config['colors'] = $colors;
            }
        }
        
        // Processar contador se fornecido
        if (!empty($_POST['contador'])) {
            $contador = json_decode($_POST['contador'], true);
            if ($contador !== null) {
                $config['contador'] = $contador;
            }
        }

        // Processar topbar (banner) se fornecido
        if (!empty($_POST['topbar'])) {
            $topbar = json_decode($_POST['topbar'], true);
            if ($topbar !== null) {
                $config['topbar'] = $topbar;
                error_log('✅ TOPBAR RECEBIDO E PROCESSADO: ' . json_encode($topbar));
            } else {
                error_log('⚠️ TOPBAR RECEBIDO MAS NÃO FOI DECODIFICADO: ' . $_POST['topbar']);
            }
        } else {
            error_log('⚠️ TOPBAR NÃO RECEBIDO NO POST');
        }
        
        // Processar URL de upsell se fornecida
        if (isset($_POST['upsell_url'])) {
            $config['upsell_url'] = $_POST['upsell_url'];
            error_log('✅ UPSELL_URL RECEBIDO E PROCESSADO: ' . $_POST['upsell_url']);
        }
        
        // Salvar configurações
        if (saveConfig($configFile, $config)) {
            echo json_encode([
                'success' => true,
                'message' => 'Configurações salvas com sucesso!',
                'config' => $config
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao salvar configurações']);
        }
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Ação inválida']);
    }
    
} else {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
}
?>