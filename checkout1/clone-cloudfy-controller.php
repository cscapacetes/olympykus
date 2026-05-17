<?php

/**
 * Cloudfy Checkout Cloner
 * 
 * Módulo responsável por detectar e extrair dados de checkouts Cloudfy.
 * O Cloudfy usa Next.js SSR e injeta dados em formato JSON serializado.
 * 
 * @author Checkout Builder Team
 * @version 1.4.0
 */

/**
 * Detecta se a URL é um checkout Cloudfy
 */
function isCloudfy($html, $xpath) {
    if (strpos($html, 'self.__next_f.push') !== false) {
        error_log("✅ Cloudfy detectado: scripts Next.js encontrados");
        return true;
    }

    $cloudfyClasses = $xpath->query("//*[contains(@class, '__className_')]");
    if ($cloudfyClasses->length > 0) {
        error_log("✅ Cloudfy detectado: classes CSS Next.js encontradas");
        return true;
    }

    if (preg_match('/--primary:#[0-9a-fA-F]{6}/', $html) &&
        preg_match('/--background:#[0-9a-fA-F]{6}/', $html)) {
        error_log("✅ Cloudfy detectado: variáveis CSS customizadas encontradas");
        return true;
    }

    $topbar = $xpath->query("//*[@id='cloudfy-topbar']");
    if ($topbar->length > 0) {
        error_log("✅ Cloudfy detectado: elemento #cloudfy-topbar encontrado");
        return true;
    }

    error_log("❌ Cloudfy não detectado");
    return false;
}

/**
 * Extrai a cor primária do HTML com múltiplos métodos em cascata
 */
function extractPrimaryColor($html, $fullData) {
    // MÉTODO 1: CSS inline --primary
    if (preg_match('/--primary\s*:\s*(#[0-9a-fA-F]{3,6})/', $html, $m)) {
        return $m[1];
    }

    // MÉTODO 2: primaryColor no fullData
    if (preg_match('/"primaryColor"\s*:\s*"(#[0-9a-fA-F]{3,6})"/', $fullData, $m)) {
        return $m[1];
    }

    // MÉTODO 3: primaryColor no HTML bruto
    if (preg_match('/"primaryColor":"(#[0-9a-fA-F]{3,6})"/', $html, $m)) {
        return $m[1];
    }

    // MÉTODO 4: Next.js RSC
    if (preg_match('/["\']--primary["\']\s*:\s*["\'](\s*#[0-9a-fA-F]{3,6})["\']/', $html, $m)) {
        return trim($m[1]);
    }

    // MÉTODO 5: JSON escape
    if (preg_match('/\\"primaryColor\\":\\"(#[0-9a-fA-F]{3,6})\\"/', $html, $m)) {
        return $m[1];
    }

    // MÉTODO 6: Unicode escape
    if (preg_match('/primaryColor\\\\u0022:(#[0-9a-fA-F]{3,6})/', $fullData, $m)) {
        return $m[1];
    }

    return '#262626';
}


/**
 * Extrai dados do JSON Next.js serializado
 */
function extractNextJsData($html) {
    error_log("🔍 Extraindo dados do JSON Next.js...");

    preg_match_all('/self\.__next_f\.push\(\[1,\s*"((?:[^"\\\\]|\\\\.)*)"\]\)/s', $html, $matches);

    if (empty($matches[1])) {
        error_log("❌ Nenhum bloco Next.js encontrado");
        return null;
    }

    error_log("📊 Total de blocos Next.js encontrados: " . count($matches[1]));

    // IMPORTANTE: a ordem do str_replace importa.
    // Processar \\\\ ANTES de \\" para não corromper sequências duplas.
    $fullData = '';
    foreach ($matches[1] as $block) {
        $decoded = str_replace(['\\\\', '\\n', '\\"'], ['\\', "\n", '"'], $block);
        $fullData .= $decoded . "\n";
    }

    $extractedData = [
        'salesPrice'         => null,
        'productName'        => null,
        'productDescription' => null,
        'productImage'       => null,
        'productType'        => null,
        'isDigital'          => false,
        'primaryColor'       => null,
        'secondaryColor'     => null,
        'backgroundColor'    => null,
        'textColor'          => null,
        'textBar'            => null,
        'textBarVisible'     => false,
        'mobileBanner'       => null,
        'desktopBanner'      => null,
        'isEmailRequired'    => true,
        'isPhoneRequired'    => true,
        'isDocumentRequired' => true,
        'reviews'            => [],
        'orderBumps'         => [],
        'shippings'          => []
    ];

    // ── Cores do tema ───────────────────────────────────────────────────────────
    // Cor primária
    $extractedData['primaryColor'] = extractPrimaryColor($html, $fullData);
    
    // Cor secundária
    if (preg_match('/"secondaryColor"\s*:\s*"(#[0-9a-fA-F]{3,6})"/', $fullData, $m)) {
        $extractedData['secondaryColor'] = $m[1];
        error_log("✅ Cor secundária: " . $m[1]);
    } elseif (preg_match('/--secondary\s*:\s*(#[0-9a-fA-F]{3,6})/', $html, $m)) {
        $extractedData['secondaryColor'] = $m[1];
        error_log("✅ Cor secundária (CSS): " . $m[1]);
    }
    
    // Background color
    if (preg_match('/"backgroundColor"\s*:\s*"(#[0-9a-fA-F]{3,6})"/', $fullData, $m)) {
        $extractedData['backgroundColor'] = $m[1];
        error_log("✅ Background color: " . $m[1]);
    } elseif (preg_match('/--background\s*:\s*(#[0-9a-fA-F]{3,6})/', $html, $m)) {
        $extractedData['backgroundColor'] = $m[1];
        error_log("✅ Background color (CSS): " . $m[1]);
    }
    
    // Text color
    if (preg_match('/"textColor"\s*:\s*"(#[0-9a-fA-F]{3,6})"/', $fullData, $m)) {
        $extractedData['textColor'] = $m[1];
        error_log("✅ Text color: " . $m[1]);
    } elseif (preg_match('/--foreground\s*:\s*(#[0-9a-fA-F]{3,6})/', $html, $m)) {
        $extractedData['textColor'] = $m[1];
        error_log("✅ Text color (CSS): " . $m[1]);
    }
    
    // TextBar (banner de aviso)
    if (preg_match('/"textBar"\s*:\s*"([^"]*)"/', $fullData, $m)) {
        $textBar = $m[1];
        $extractedData['textBar'] = $textBar;
        $extractedData['textBarVisible'] = !empty($textBar);
        error_log("✅ TextBar: " . ($textBar ?: '(vazio)') . " | Visível: " . ($extractedData['textBarVisible'] ? 'SIM' : 'NÃO'));
    }

    // ── Preço principal ─────────────────────────────────────────────────────────
    if (preg_match('/[0-9a-f]+:\{"_id":"69d5896de1285315d418960d","products":"[^"]+","salesPrice":([0-9.]+)/', $fullData, $match)) {
        $extractedData['salesPrice'] = floatval($match[1]);
        error_log("✅ Preço do produto principal (via URL ID): " . $match[1]);
    } elseif (preg_match('/[0-9a-f]+:\{"_id":"[^"]+","products":"[^"]+","salesPrice":([0-9.]+),"store"/', $fullData, $match)) {
        $extractedData['salesPrice'] = floatval($match[1]);
        error_log("✅ Preço do produto principal (fallback): " . $match[1]);
    }

    // ── Produto principal ───────────────────────────────────────────────────────
    // Regex para produtos FÍSICOS (com width, height, length, weight)
    preg_match_all('/[0-9a-f]+:\{"name":"([^"]+)","description":"([^"]+)","type":"(PHYSICAL|DIGITAL)","storeId":"[^"]+","linkExpirationTime":null,"img":"[^"]+","width":0,"height":0,"length":0,"weight":0,"_id":"([^"]+)"/', $fullData, $productMatches);

    // Regex alternativa para produtos DIGITAIS (com file)
    if (empty($productMatches[1])) {
        preg_match_all('/[0-9a-f]+:\{"name":"([^"]+)","description":"([^"]+)","type":"(DIGITAL)","storeId":"[^"]+","linkExpirationTime":null,"img":"[^"]+","file":"[^"]+"/', $fullData, $productMatches);
    }

    if (!empty($productMatches[1])) {
        $lastIndex = count($productMatches[1]) - 1;
        $extractedData['productName']        = trim($productMatches[1][$lastIndex]);
        $extractedData['productDescription'] = trim($productMatches[2][$lastIndex]);
        $extractedData['productType']        = $productMatches[3][$lastIndex];
        error_log("✅ Produto principal: " . $extractedData['productName'] . " (" . $productMatches[3][$lastIndex] . ")");
    }

    // ── Imagem do produto principal ─────────────────────────────────────────────
    preg_match_all('/[0-9a-f]+:\{"key":"products-images[^"]*","url":"([^"]+)"\}/', $fullData, $imageMatches);
    if (!empty($imageMatches[1])) {
        $extractedData['productImage'] = $imageMatches[1][count($imageMatches[1]) - 1];
        error_log("✅ Imagem do produto: " . substr($extractedData['productImage'], 0, 80));
    }

    // ── Banners ─────────────────────────────────────────────────────────────────
    preg_match_all('/[0-9a-f]+:\{"key":"[^"]*banner[^"]*","url":"([^"]+)"\}/', $fullData, $bannerMatches);
    if (!empty($bannerMatches[1])) {
        $extractedData['mobileBanner']  = $bannerMatches[1][0];
        $extractedData['desktopBanner'] = $bannerMatches[1][0];
        error_log("✅ Banner (método 1): " . substr($bannerMatches[1][0], 0, 80));
    } else {
        if (preg_match('/"src":"(https:\/\/d1frh8xn9wll8b\.cloudfront\.net\/[^"]*banner[^"]*)"/', $fullData, $bannerMatch)) {
            $extractedData['mobileBanner']  = $bannerMatch[1];
            $extractedData['desktopBanner'] = $bannerMatch[1];
            error_log("✅ Banner (método 2 - cloudfront): " . substr($bannerMatch[1], 0, 80));
        }
    }
    
    // ── Upsell Link (externalThanksPageLink) ────────────────────────────────────
    if (preg_match('/"externalThanksPageLink":"(https?:\/\/[^"]+)"/', $fullData, $upsellMatch)) {
        $extractedData['upsellLink'] = $upsellMatch[1];
        error_log("🎯 Upsell Link encontrado: " . $upsellMatch[1]);
    } else {
        $extractedData['upsellLink'] = '';
        error_log("ℹ️ Nenhum Upsell Link encontrado");
    }

    // ── Configurações do checkout ───────────────────────────────────────────────
    // IMPORTANTE: Usar regex mais específica para capturar os valores booleanos
    if (preg_match('/"isEmailRequired"\s*:\s*(true|false)/', $fullData, $match)) {
        $extractedData['isEmailRequired'] = ($match[1] === 'true');
        error_log("✅ isEmailRequired extraído: " . $match[1] . " -> " . ($extractedData['isEmailRequired'] ? 'true' : 'false'));
    } else {
        error_log("⚠️ isEmailRequired NÃO encontrado no JSON - usando padrão: true");
    }
    
    if (preg_match('/"isPhoneRequired"\s*:\s*(true|false)/', $fullData, $match)) {
        $extractedData['isPhoneRequired'] = ($match[1] === 'true');
        error_log("✅ isPhoneRequired extraído: " . $match[1] . " -> " . ($extractedData['isPhoneRequired'] ? 'true' : 'false'));
    } else {
        error_log("⚠️ isPhoneRequired NÃO encontrado no JSON - usando padrão: true");
    }
    
    if (preg_match('/"isDocumentRequired"\s*:\s*(true|false)/', $fullData, $match)) {
        $extractedData['isDocumentRequired'] = ($match[1] === 'true');
        error_log("✅ isDocumentRequired extraído: " . $match[1] . " -> " . ($extractedData['isDocumentRequired'] ? 'true' : 'false'));
    } else {
        error_log("⚠️ isDocumentRequired NÃO encontrado no JSON - usando padrão: true");
    }

    // ── Tipo de checkout ────────────────────────────────────────────────────────
    if (preg_match('/"type":"(THREE_STEPS|TWO_STEPS)"[^}]*"template":/', $fullData, $match)) {
        $extractedData['checkoutType'] = $match[1];
        error_log("✅ Tipo de checkout: " . $match[1]);
    }
    
    // Detectar se é digital baseado no tipo do produto
    if ($extractedData['productType'] === 'DIGITAL') {
        $extractedData['isDigital'] = true;
        error_log("✅ Produto DIGITAL detectado");
    } elseif ($extractedData['checkoutType'] === 'TWO_STEPS') {
        $extractedData['isDigital'] = true;
        error_log("✅ Digital detectado via TWO_STEPS");
    }

    // ── Depoimentos ─────────────────────────────────────────────────────────────
    error_log("=== INICIANDO EXTRAÇÃO DE DEPOIMENTOS ===");
    
    // PASSO 1: Extrair todas as URLs de imagens de reviews diretamente
    preg_match_all('/"url":"(https:\/\/cloudfy-reviews-images[^"]+)"/', $fullData, $reviewImageMatches);
    
    $reviewImages = [];
    if (!empty($reviewImageMatches[1])) {
        $reviewImages = $reviewImageMatches[1];
        error_log("🖼️ Total de imagens de reviews encontradas: " . count($reviewImages));
        foreach ($reviewImages as $idx => $url) {
            error_log("   🖼️ Imagem " . ($idx + 1) . ": " . substr($url, 0, 80));
        }
    } else {
        error_log("⚠️ Nenhuma imagem de review encontrada");
    }
    
    // PASSO 2: Extrair depoimentos
    preg_match_all('/[0-9a-f]+:\{"_id":"[^"]+","active":true,"description":"((?:[^"\\\\]|\\\\.)*)","name":"([^"]+)","rating":(\d+)/', $fullData, $reviewMatches);
    
    if (!empty($reviewMatches[1])) {
        error_log("📊 Total de depoimentos encontrados: " . count($reviewMatches[1]));
        
        for ($i = 0; $i < count($reviewMatches[1]); $i++) {
            $description = str_replace(['\\"', '\\\\'], ['"', '\\'], $reviewMatches[1][$i]);
            $description = trim($description, '"');
            $name = $reviewMatches[2][$i];
            $rating = intval($reviewMatches[3][$i]);
            
            // Associar imagem pelo índice (mesma ordem)
            $imageUrl = $reviewImages[$i] ?? '';
            
            $extractedData['reviews'][] = [
                'name'        => $name,
                'description' => $description,
                'rating'      => $rating,
                'image'       => $imageUrl
            ];
            
            error_log("   ✅ Depoimento: $name (rating: $rating) | Imagem: " . ($imageUrl ? 'SIM' : 'NÃO'));
        }
        
        error_log("📊 Depoimentos extraídos com imagens: " . count($extractedData['reviews']));
    } else {
        error_log("⚠️ Nenhum depoimento encontrado");
    }

    // ── Opções de frete ─────────────────────────────────────────────────────────
    preg_match_all('/[0-9a-f]+:\{"_id":"[^"]+","deadline":"([^"]+)","description":"([^"]+)"[^}]*"type":"FIXED"[^}]*"active":true[^}]*"value":([0-9.]+)/', $fullData, $shippingMatches);
    if (!empty($shippingMatches[1])) {
        for ($i = 0; $i < count($shippingMatches[1]); $i++) {
            $extractedData['shippings'][] = [
                'description' => $shippingMatches[2][$i],
                'deadline'    => $shippingMatches[1][$i],
                'value'       => floatval($shippingMatches[3][$i])
            ];
        }
        error_log("📊 Opções de frete: " . count($extractedData['shippings']));
    }

    preg_match_all('/[0-9a-f]+:\{"_id":"([^"]+)","name":"([^"]+)","products":"[^"]+","salesPrice":([0-9.]+)/', $fullData, $obSimpleMatches);

    if (!empty($obSimpleMatches[1])) {
        error_log("📦 Total de salesPlans encontrados: " . count($obSimpleMatches[1]));

        for ($i = 0; $i < count($obSimpleMatches[1]); $i++) {
            $obId    = $obSimpleMatches[1][$i];
            $obName  = $obSimpleMatches[2][$i];
            $obPrice = floatval($obSimpleMatches[3][$i]);

            if ($obName !== $extractedData['productName'] && $obPrice !== $extractedData['salesPrice']) {
                error_log("   ✅ Order Bump: $obName - R$ $obPrice (ID: $obId)");
                $extractedData['orderBumps'][] = [
                    'id'          => $obId,
                    'name'        => $obName,
                    'salesPrice'  => $obPrice,
                    'description' => $obName
                ];
            }
        }
    }

    preg_match_all('/\{"_id":"([^"]+)"[^}]*"discountType":"PERCENTUAL"[^}]*"value":([0-9.]+)\}/', $fullData, $discountMatches);

    if (!empty($discountMatches[1])) {
        error_log("💰 Total de descontos encontrados: " . count($discountMatches[1]));

        $discountMap = [];
        for ($i = 0; $i < count($discountMatches[1]); $i++) {
            $discountMap[$discountMatches[1][$i]] = floatval($discountMatches[2][$i]);
        }

        foreach ($extractedData['orderBumps'] as &$ob) {
            $pattern = '/"discountType":"PERCENTUAL"[^}]*"offerPlans":\[[^\]]*"_id":"' . preg_quote($ob['id'], '/') . '"[^\]]*\][^}]*"value":([0-9.]+)/';

            if (preg_match($pattern, $fullData, $obDiscountMatch)) {
                $discount      = floatval($obDiscountMatch[1]);
                $ob['discount'] = $discount;
                $ob['oldPrice'] = $ob['salesPrice'];
                $ob['price']    = $ob['salesPrice'] * (1 - $discount / 100);
                error_log("   ✅ {$ob['name']}: {$discount}% off");
            } elseif (!empty($discountMap)) {
                $discount      = reset($discountMap);
                $ob['discount'] = $discount;
                $ob['oldPrice'] = $ob['salesPrice'];
                $ob['price']    = $ob['salesPrice'] * (1 - $discount / 100);
                error_log("   ⚠️ {$ob['name']}: desconto padrão {$discount}%");
            }
        }
        unset($ob);
    }

    // ── Imagens dos Order Bumps ─────────────────────────────────────────────────
    if (!empty($extractedData['orderBumps'])) {
        preg_match_all('/[0-9a-f]+:\{"key":"products-images[^"]*","url":"([^"]+)"\}/', $fullData, $allImageMatches);

        if (!empty($allImageMatches[1])) {
            $totalImages  = count($allImageMatches[1]);
            $numOrderBumps = count($extractedData['orderBumps']);

            for ($i = 0; $i < $numOrderBumps && $i < ($totalImages - 1); $i++) {
                $extractedData['orderBumps'][$i]['image'] = $allImageMatches[1][$i];
            }
        }
    }

    error_log("📊 Resumo da extração:");
    error_log("   - Preço: "        . ($extractedData['salesPrice']   ?? 'NÃO ENCONTRADO'));
    error_log("   - Nome: "         . ($extractedData['productName']  ?? 'NÃO ENCONTRADO'));
    error_log("   - Cor primária: " . $extractedData['primaryColor']);
    error_log("   - Cor secundária: " . ($extractedData['secondaryColor'] ?? 'NÃO ENCONTRADO'));
    error_log("   - Background: " . ($extractedData['backgroundColor'] ?? 'NÃO ENCONTRADO'));
    error_log("   - Text color: " . ($extractedData['textColor'] ?? 'NÃO ENCONTRADO'));
    error_log("   - TextBar: " . ($extractedData['textBar'] ?? 'NÃO ENCONTRADO'));
    error_log("   - Upsell Link: " . ($extractedData['upsellLink'] ? $extractedData['upsellLink'] : 'NÃO ENCONTRADO'));
    error_log("   - Depoimentos: "  . count($extractedData['reviews']));
    error_log("   - Order Bumps: "  . count($extractedData['orderBumps']));
    error_log("   - Frete: "        . count($extractedData['shippings']));

    return $extractedData;
}

/**
 * Extrai dados do Cloudfy via DOM (fallback)
 */
function extractCloudfyDataFromDOM($xpath, $dom) {
    error_log("🔍 Extraindo dados via DOM (fallback)...");

    $data = [];

    // ── Cor primária via DOM ─────────────────────────────────────────────────────
    // No Cloudfy o --primary fica no style de uma <div> filha do <body>,
    // NÃO no elemento <html>. Buscamos em qualquer elemento que contenha --primary.
    $styledNode = $xpath->query("//*[contains(@style, '--primary:') or contains(@style, '--primary :')]")->item(0);
    if ($styledNode) {
        $style = $styledNode->getAttribute('style');
        if (preg_match('/--primary\s*:\s*(#[0-9a-fA-F]{3,6})/', $style, $match)) {
            $data['primaryColor'] = $match[1];
            error_log("✅ Cor primária (DOM - elemento com style): " . $match[1]);
        }
        if (preg_match('/--background\s*:\s*(#[0-9a-fA-F]{3,6})/', $style, $match)) {
            $data['backgroundColor'] = $match[1];
        }
        if (preg_match('/--foreground\s*:\s*(#[0-9a-fA-F]{3,6})/', $style, $match)) {
            $data['foregroundColor'] = $match[1];
        }
    } else {
        // Fallback: tentar no elemento <html> mesmo assim
        $htmlNode = $xpath->query("//html")->item(0);
        if ($htmlNode) {
            $style = $htmlNode->getAttribute('style');
            if (preg_match('/--primary\s*:\s*(#[0-9a-fA-F]{3,6})/', $style, $match)) {
                $data['primaryColor'] = $match[1];
                error_log("✅ Cor primária (DOM - html element): " . $match[1]);
            }
        }
    }

    $topbar = $xpath->query("//*[@id='cloudfy-topbar']")->item(0);
    if ($topbar) {
        $topbarStyle   = $topbar->getAttribute('style');
        $topbarDisplay = strpos($topbarStyle, 'display: none') !== false ? 'none' : 'block';

        $data['topbar'] = [
            'text'    => trim($topbar->textContent),
            'visible' => $topbarDisplay !== 'none'
        ];
        error_log("✅ Topbar extraído (DOM)");
    }

    $titleNode = $xpath->query("//title")->item(0);
    if ($titleNode) {
        $title = preg_replace('/ - Checkout$/', '', trim($titleNode->textContent));
        $data['productName'] = $title;
        error_log("✅ Nome do produto (DOM): " . $title);
    }

    $bannerImg = $xpath->query("//img[contains(@alt, 'Banner')]")->item(0);
    if ($bannerImg) {
        $data['bannerImage'] = $bannerImg->getAttribute('src');
        error_log("✅ Banner extraído (DOM)");
    }

    return $data;
}

/**
 * Normaliza os dados extraídos para o formato checkout-config.json
 */
function normalizeCloudfyData($jsonData, $domData) {
    error_log("🔄 Normalizando dados do Cloudfy...");

    $merged = array_merge($domData, $jsonData);
    
    // Log dos valores extraídos ANTES da normalização
    error_log("🔍 DEBUG - Valores extraídos do JSON:");
    error_log("   - isEmailRequired: " . (isset($merged['isEmailRequired']) ? ($merged['isEmailRequired'] ? 'true' : 'false') : 'NÃO DEFINIDO'));
    error_log("   - isPhoneRequired: " . (isset($merged['isPhoneRequired']) ? ($merged['isPhoneRequired'] ? 'true' : 'false') : 'NÃO DEFINIDO'));
    error_log("   - isDocumentRequired: " . (isset($merged['isDocumentRequired']) ? ($merged['isDocumentRequired'] ? 'true' : 'false') : 'NÃO DEFINIDO'));

    $normalized = [
        'product_price'       => isset($merged['salesPrice']) ? number_format($merged['salesPrice'], 2, ',', '.') : '0,00',
        'product_name'        => $merged['productName']        ?? 'Produto',
        'product_description' => isset($merged['productDescription']) && trim($merged['productDescription']) !== '' 
                                 ? $merged['productDescription'] 
                                 : '',
        'product_image'       => $merged['productImage']       ?? '',

        'banner_photo'        => $merged['desktopBanner'] ?? $merged['mobileBanner'] ?? $merged['bannerImage'] ?? '',
        'show_banner_photo'   => !empty($merged['desktopBanner']) || !empty($merged['mobileBanner']) || !empty($merged['bannerImage']),

        'company_name'        => 'Loja',
        'checkout_logo'       => '',
        'is_digital'          => $merged['isDigital']          ?? false,

        'show_email_field'    => $merged['isEmailRequired']    ?? true,
        'show_phone_field'    => $merged['isPhoneRequired']    ?? true,
        'show_cpf_field'      => $merged['isDocumentRequired'] ?? true,
        
        'upsellLink'          => $merged['upsellLink']         ?? '', // Link do upsell (externalThanksPageLink)
        
        'colors' => [
            'principal'  => $merged['primaryColor']     ?? '#e02525',
            'secundaria' => $merged['secondaryColor']   ?? '#000000',
            'background' => $merged['backgroundColor']  ?? '#ededed',
            'text'       => $merged['textColor']        ?? '#000000',
            'hover'      => '#222222'
        ],

        'topbar' => [
            'visible'  => $merged['textBarVisible'] ?? $merged['topbar']['visible'] ?? false,
            'text'     => $merged['textBar']        ?? $merged['topbar']['text']    ?? '',
            'bg_color' => $merged['primaryColor']   ?? '#02ad5b'
        ],

        'offers' => [
            'visible' => !empty($merged['orderBumps']),
            'items'   => []
        ],

        'depoimentos_enabled' => !empty($merged['reviews']),
        'depoimentos'         => $merged['reviews'] ?? [],

        'frete' => [
            'enabled' => !empty($merged['shippings']),
            'opcoes'  => []
        ]
    ];

    foreach ($merged['orderBumps'] ?? [] as $ob) {
        $normalized['offers']['items'][] = [
            'name'        => $ob['name']     ?? '',
            'price'       => number_format($ob['price']    ?? 0, 2, ',', '.'),
            'oldPrice'    => isset($ob['oldPrice']) ? number_format($ob['oldPrice'], 2, ',', '.') : '',
            'image'       => $ob['image']    ?? '',
            'description' => !empty($ob['description']) ? $ob['description'] : 'Desconto Imperdível!'
        ];
    }
    error_log("📦 Order bumps normalizados: " . count($normalized['offers']['items']));

    foreach ($merged['shippings'] ?? [] as $index => $shipping) {
        $normalized['frete']['opcoes'][] = [
            'name'        => $shipping['description'] ?? "Opção " . ($index + 1),
            'price'       => number_format($shipping['value'] ?? 0, 2, ',', '.'),
            'description' => $shipping['deadline'] ?? 'Prazo a calcular',
            'selected'    => $index === 0
        ];
    }

    error_log("✅ Dados normalizados. Resumo: Preço=" . $normalized['product_price'] .
              " | Cor=" . $normalized['colors']['principal'] .
              " | Digital=" . ($normalized['is_digital'] ? 'SIM' : 'NÃO') .
              " | Email=" . ($normalized['show_email_field'] ? 'SIM' : 'NÃO') .
              " | Phone=" . ($normalized['show_phone_field'] ? 'SIM' : 'NÃO') .
              " | CPF=" . ($normalized['show_cpf_field'] ? 'SIM' : 'NÃO') .
              " | Depoimentos=" . count($normalized['depoimentos']) .
              " | Order Bumps=" . count($normalized['offers']['items']) .
              " | Frete=" . count($normalized['frete']['opcoes']) .
              " | Upsell Link=" . ($normalized['upsellLink'] ? 'SIM' : 'NÃO'));
    
    // Log detalhado dos campos de formulário APÓS normalização
    error_log("🔍 DEBUG - Valores normalizados finais:");
    error_log("   - show_email_field: " . ($normalized['show_email_field'] ? 'true' : 'false'));
    error_log("   - show_phone_field: " . ($normalized['show_phone_field'] ? 'true' : 'false'));
    error_log("   - show_cpf_field: " . ($normalized['show_cpf_field'] ? 'true' : 'false'));

    return $normalized;
}

/**
 * Função principal de scraping do Cloudfy
 */
function scrapeCloudfyCheckout($html, $xpath, $dom) {
    error_log("☁️ Iniciando scraping do checkout Cloudfy...");

    $jsonData       = extractNextJsData($html);
    $domData        = extractCloudfyDataFromDOM($xpath, $dom);
    $normalizedData = normalizeCloudfyData($jsonData ?? [], $domData);

    error_log("☁️ Scraping Cloudfy concluído!");

    return $normalizedData;
}