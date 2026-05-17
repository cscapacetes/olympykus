<?php
/**
 * PAINEL ADMINISTRATIVO - CONTROLE DE GATEWAY
 */
session_start();

// Endpoint para verificar disponibilidade dos gateways
if (isset($_GET['action']) && $_GET['action'] === 'check_gateway_availability') {
    header('Content-Type: application/json');
    
    $gatewayFiles = [
        'allow' => 'pagamento-allow.php',
        'brutal' => 'pagamento-brutal.php',
        'pagz' => 'pagamento-pagz.php',
        'paradise' => 'pagamento-paradise.php',
        'dutty' => 'pagamento-dutty.php',
        'mangofy' => 'pagamento-mangofy.php',
        'iron' => 'pagamento-iron.php',
        'ghost' => 'pagamento-ghost.php',
        'buck' => 'pagamento-buck.php',
        'naut' => 'pagamento-naut.php',
        'zero' => 'pagamento-zero.php',
        'black' => 'pagamento-black.php',
        'cashfly' => 'pagamento-cashfly.php',
        'umbrella' => 'pagamento-umbrella.php',
        'freepay' => 'pagamento-freepay.php',
        'quantum' => 'pagamento-quantum.php',
        'pollar' => 'pagamento-pollar.php'
    ];
    
    $availability = [];
    foreach ($gatewayFiles as $gateway => $file) {
        $availability[$gateway] = file_exists(__DIR__ . '/' . $file);
    }
    
    echo json_encode(['success' => true, 'availability' => $availability]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Controle de Gateway - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            /* Tron Design System */
            --color-surface-base: hsl(228 24% 7%);
            --color-surface-card: hsl(228 20% 11%);
            --color-surface-elevated: hsl(228 16% 16%);
            --color-surface-overlay: hsl(228 20% 11% / 0.72);
            
            --color-accent: hsl(239 84% 67%);
            --color-accent-hover: hsl(239 84% 72%);
            --color-accent-strong: hsl(239 84% 60%);
            --color-accent-muted: hsl(239 84% 67% / 0.15);
            --color-accent-glow: hsl(239 84% 67% / 0.25);
            
            --color-text-primary: hsl(220 14% 92%);
            --color-text-secondary: hsl(220 14% 85%);
            --color-text-muted: hsl(220 9% 50%);
            
            --color-border: hsl(228 16% 16%);
            --color-border-hover: hsl(228 18% 22%);
            --color-border-active: hsl(239 84% 67% / 0.5);
            
            --color-success: hsl(142 52% 44%);
            --color-danger: hsl(0 65% 51%);
            --color-warning: hsl(38 92% 50%);
            
            /* Legacy compatibility */
            --primary: hsl(239 84% 67%);
            --primary-dark: hsl(239 84% 60%);
            --primary-light: hsl(239 84% 72%);
            --success: hsl(142 52% 44%);
            --danger: hsl(0 65% 51%);
            --bg-dark: hsl(228 24% 7%);
            --bg-card: hsl(228 20% 11%);
            --text-primary: hsl(220 14% 92%);
            --text-secondary: hsl(220 14% 85%);
            --border: hsl(228 16% 16%);
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--color-surface-base);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: var(--color-text-primary);
            position: relative;
            overflow-x: hidden;
        }
        
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 50%, hsl(239 84% 67% / 0.08) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, hsl(38 92% 50% / 0.06) 0%, transparent 50%);
            pointer-events: none;
            z-index: 0;
        }
        
        .tron-scanline {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, hsl(239 84% 67% / 0.15), transparent);
            animation: tron-scan 8s linear infinite;
            pointer-events: none;
            z-index: 1;
        }
        
        @keyframes tron-scan {
            0% { transform: translateY(-100%); }
            100% { transform: translateY(100vh); }
        }

        .container {
            background: var(--color-surface-card);
            border: 1px solid var(--color-border);
            border-radius: 16px;
            box-shadow: 
                0 4px 20px -5px hsl(228 24% 5% / 0.6),
                0 0 40px -10px hsl(239 84% 67% / 0.05),
                inset 0 1px 0 hsl(239 84% 67% / 0.05);
            max-width: 1200px;
            width: 100%;
            padding: 48px;
            position: relative;
            z-index: 2;
            backdrop-filter: blur(20px);
        }
        
        .container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(
                90deg,
                transparent 0%,
                hsl(239 84% 67% / 0.3) 50%,
                transparent 100%
            );
        }

        .header {
            margin-bottom: 48px;
            border-bottom: 1px solid var(--border);
            padding-bottom: 24px;
        }

        .header-top {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 8px;
        }

        .header-icon {
            width: 48px;
            height: 48px;
            background: var(--color-accent-strong);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            box-shadow: 
                0 0 20px hsl(239 84% 67% / 0.3),
                inset 0 1px 0 hsl(239 84% 67% / 0.2);
            position: relative;
        }
        
        .header-icon::after {
            content: '';
            position: absolute;
            inset: -2px;
            border-radius: 14px;
            padding: 2px;
            background: linear-gradient(135deg, hsl(239 84% 67% / 0.5), transparent);
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            pointer-events: none;
        }

        .header-icon i {
            font-size: 24px;
        }

        .header h1 {
            color: var(--text-primary);
            font-size: 28px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .header h2 i {
            margin-right: 8px;
        }

        .header p {
            color: var(--text-secondary);
            font-size: 15px;
            margin-left: 64px;
        }

        .login-box {
            max-width: 420px;
            margin: 0 auto;
        }

        .login-box h2 {
            color: var(--text-primary);
            margin-bottom: 32px;
            font-size: 24px;
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            color: var(--text-secondary);
            font-weight: 500;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            background: var(--bg-dark);
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 15px;
            color: var(--text-primary);
            transition: all 0.2s;
            font-family: 'Inter', sans-serif;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .btn {
            width: 100%;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-family: 'Inter', sans-serif;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }

        .btn-logout {
            background: var(--danger);
            color: white;
            margin-bottom: 32px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-logout:hover {
            background: #dc2626;
            transform: translateY(-1px);
        }

        .btn-logout i {
            font-size: 16px;
        }

        .tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 32px;
            border-bottom: 1px solid var(--border);
        }

        .tab-btn {
            padding: 12px 24px;
            background: transparent;
            border: none;
            color: var(--text-secondary);
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            border-bottom: 2px solid transparent;
            font-family: 'Inter', sans-serif;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .tab-btn:hover {
            color: var(--text-primary);
        }

        .tab-btn.active {
            color: var(--primary-light);
            border-bottom-color: var(--primary);
        }

        .gateway-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 20px;
        }

        .gateway-card {
            background: var(--color-surface-elevated);
            border: 1px solid var(--color-border);
            border-radius: 12px;
            padding: 24px;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .gateway-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, hsl(239 84% 67% / 0.3), transparent);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .gateway-card:hover {
            border-color: var(--color-border-active);
            transform: translateY(-2px);
            box-shadow: 
                0 0 20px -4px hsl(239 84% 67% / 0.15),
                inset 0 0 30px -10px hsl(239 84% 67% / 0.05);
        }
        
        .gateway-card:hover::before {
            opacity: 1;
        }

        .gateway-card.active {
            background: var(--color-accent-muted);
            border-color: var(--color-border-active);
            box-shadow: 
                0 0 20px -4px hsl(239 84% 67% / 0.2),
                inset 0 0 30px -10px hsl(239 84% 67% / 0.08);
        }
        
        .gateway-card.active::before {
            opacity: 1;
        }

        .gateway-icon {
            width: 56px;
            height: 56px;
            background: var(--bg-card);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
            transition: all 0.3s ease;
        }

        .gateway-icon img {
            width: 36px;
            height: 36px;
            object-fit: contain;
            transition: all 0.3s ease;
        }

        .gateway-card:hover .gateway-icon {
            background: rgba(139, 92, 246, 0.15);
            transform: scale(1.08);
        }

        .gateway-card:hover .gateway-icon img {
            transform: scale(1.1);
        }

        .gateway-card.active .gateway-icon {
            background: rgba(139, 92, 246, 0.2);
        }

        .gateway-card.active .gateway-icon img {
            transform: scale(1.05);
        }

        .gateway-card h3 {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .gateway-card .status {
            font-size: 13px;
            font-weight: 500;
            color: var(--text-secondary);
        }

        .gateway-card.active .status {
            color: var(--primary-light);
        }

        .gateway-card.unavailable {
            opacity: 0.6;
            cursor: not-allowed;
            position: relative;
        }

        .gateway-card.unavailable::after {
            content: '';
            position: absolute;
            inset: 0;
            background: repeating-linear-gradient(
                45deg,
                transparent,
                transparent 10px,
                rgba(239, 68, 68, 0.05) 10px,
                rgba(239, 68, 68, 0.05) 20px
            );
            pointer-events: none;
            border-radius: 12px;
        }

        .gateway-card.unavailable:hover {
            transform: none;
            border-color: var(--color-border);
            box-shadow: none;
        }

        .unavailable-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            background: var(--danger);
            color: white;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .unavailable-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.7);
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 12px;
            opacity: 1;
            pointer-events: all;
        }

        .btn-details {
            background: var(--primary);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-details:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }

        .active-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            background: var(--success);
            color: white;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
        }

        .alert {
            padding: 14px 20px;
            border-radius: 8px;
            margin-bottom: 24px;
            display: none;
            font-weight: 500;
            font-size: 14px;
        }

        .alert.show {
            display: block;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .loading {
            text-align: center;
            padding: 48px;
            color: var(--text-secondary);
            font-size: 15px;
        }

        .webhook-info {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 8px;
            padding: 16px 20px;
            margin-bottom: 24px;
        }

        .webhook-info h3 {
            color: #60a5fa;
            margin-bottom: 8px;
            font-size: 15px;
            font-weight: 600;
        }

        .webhook-info p {
            color: var(--text-secondary);
            font-size: 14px;
            line-height: 1.6;
        }

        .webhook-card {
            background: var(--bg-dark);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
        }

        .webhook-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 16px;
        }

        .webhook-icon {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-card);
            border-radius: 10px;
        }

        .webhook-icon img {
            width: 32px;
            height: 32px;
            object-fit: contain;
        }

        .webhook-icon i {
            font-size: 22px;
            color: var(--primary);
        }

        .webhook-info h3 i {
            margin-right: 8px;
        }

        .webhook-header h3 {
            color: var(--text-primary);
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 2px;
        }

        .webhook-desc {
            color: var(--text-secondary);
            font-size: 13px;
        }

        .webhook-url-box {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .webhook-url {
            flex: 1;
            padding: 10px 14px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 6px;
            color: var(--primary-light);
            font-size: 13px;
            font-family: 'Courier New', monospace;
        }

        .webhook-url:focus {
            outline: none;
            border-color: var(--primary);
        }

        .btn-copy {
            padding: 10px 20px;
            background: var(--primary);
            border: none;
            border-radius: 6px;
            color: white;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            font-family: 'Inter', sans-serif;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-copy:hover {
            background: var(--primary-dark);
        }

        .btn-copy.copied {
            background: var(--success);
        }

        .btn-copy i {
            font-size: 14px;
        }

        .loading i {
            font-size: 32px;
            color: var(--primary);
            margin-bottom: 12px;
        }

        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(4px);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 32px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        /* Estilos do Preview */
        .preview-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 32px;
            height: 80vh;
        }

        .preview-controls {
            background: var(--bg-dark);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 24px;
            overflow-y: auto;
        }

        .preview-iframe-container {
            background: var(--bg-dark);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px;
            position: relative;
        }

        .preview-iframe {
            width: 100%;
            height: 100%;
            border: none;
            border-radius: 8px;
            background: white;
        }

        .preview-header {
            display: flex;
            align-items: center;
            justify-content: between;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border);
        }

        .preview-title {
            color: var(--text-primary);
            font-size: 18px;
            font-weight: 600;
            margin: 0;
            flex: 1;
        }

        .preview-refresh {
            background: var(--primary);
            border: none;
            border-radius: 6px;
            color: white;
            padding: 8px 16px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .preview-refresh:hover {
            background: var(--primary-dark);
        }

        .preview-form-group {
            margin-bottom: 20px;
        }

        .preview-form-group label {
            display: block;
            color: var(--text-secondary);
            font-weight: 500;
            margin-bottom: 6px;
            font-size: 13px;
        }

        .preview-form-group input {
            width: 100%;
            padding: 10px 12px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 14px;
            color: var(--text-primary);
            transition: all 0.2s;
        }

        .preview-form-group input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(139, 92, 246, 0.1);
        }

        .preview-updating {
            position: relative;
        }

        .preview-updating::after {
            content: '';
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary);
            font-size: 14px;
            animation: pulse 1s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        /* Estilos para upload de imagem */
        .upload-container {
            border: 2px dashed var(--border);
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            margin-bottom: 12px;
        }

        .upload-container:hover {
            border-color: var(--primary);
            background: rgba(139, 92, 246, 0.05);
        }

        .upload-container.dragover {
            border-color: var(--primary);
            background: rgba(139, 92, 246, 0.1);
        }

        .upload-input {
            display: none;
        }

        .upload-preview {
            max-width: 200px;
            max-height: 150px;
            border-radius: 6px;
            margin: 10px auto;
            display: block;
        }

        .upload-text {
            color: var(--text-secondary);
            font-size: 14px;
            margin-bottom: 8px;
        }

        .upload-button {
            background: var(--primary);
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            margin: 5px;
        }

        .upload-button:hover {
            background: var(--primary-dark);
        }

        .upload-url-toggle {
            background: var(--text-secondary);
            color: white;
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            margin-top: 8px;
        }

        .upload-url-toggle:hover {
            background: var(--primary);
        }

        .image-option {
            margin-bottom: 16px;
        }

        .image-option-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 12px;
        }

        .image-tab {
            padding: 6px 12px;
            border: 1px solid var(--border);
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.2s;
        }

        .image-tab.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .image-tab:hover:not(.active) {
            border-color: var(--primary);
        }

        .preview-loading {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: var(--text-secondary);
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        @media (max-width: 1200px) {
            .preview-container {
                grid-template-columns: 1fr;
                height: auto;
            }
            
            .preview-iframe-container {
                height: 600px;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 32px 24px;
            }

            .gateway-cards {
                grid-template-columns: 1fr;
            }

            .header h1 {
                font-size: 24px;
            }

            .webhook-url-box {
                flex-direction: column;
            }

            .btn-copy {
                width: 100%;
            }

            .modal-content {
                padding: 24px;
                margin: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-top">
                <div class="header-icon">
                    <i class="fa-solid fa-layer-group"></i>
                </div>
                <h1>Controle de gateway</h1>
            </div>
            <p>Gerencie e monitore gateways de pagamento ativos</p>
        </div>

        <div id="alertBox" class="alert"></div>

        <!-- Login Box -->
        <div id="loginBox" class="login-box" style="display: none;">
            <h2><i class="fa-solid fa-lock"></i> Autenticação Necessária</h2>
            <form id="loginForm">
                <div class="form-group">
                    <label for="password">Senha de Administrador</label>
                    <input type="password" id="password" name="password" placeholder="Digite sua senha" required>
                </div>
                <button type="submit" class="btn btn-primary">Entrar no Painel</button>
            </form>
        </div>

        <!-- Admin Panel -->
        <div id="adminPanel" style="display: none;">
            <button id="logoutBtn" class="btn btn-logout"><i class="fa-solid fa-right-from-bracket"></i> Sair</button>

            <!-- Tabs -->
            <div class="tabs">
                <button class="tab-btn active" data-tab="gateways">
                    <i class="fa-solid fa-credit-card"></i>
                    Gateways
                </button>
                <button class="tab-btn" data-tab="webhooks">
                    <i class="fa-solid fa-webhook"></i>
                    Webhooks
                </button>
                <button class="tab-btn" data-tab="preview" onclick="openPreviewBuilder()">
                    <i class="fa-solid fa-eye"></i>
                    Preview Builder
                </button>
                <button class="tab-btn" data-tab="tiktok">
                    <i class="fa-brands fa-tiktok"></i>
                    TikTok Pixels
                </button>
                <button class="tab-btn" data-tab="otimizey">
                    <i class="fa-solid fa-chart-line"></i>
                    Otimizey
                </button>
                <button class="tab-btn" data-tab="utmify">
                    <i class="fa-solid fa-link"></i>
                    Utmify
                </button>
            </div>

            <div id="loadingBox" class="loading">
                <i class="fa-solid fa-spinner fa-spin"></i> Carregando configuração...
            </div>

            <div id="gatewayBox" class="tab-content" style="display: none;">
                <div class="gateway-cards">

                    <div class="gateway-card" data-gateway="allow">
                        <div class="gateway-icon">
                            <img src="https://files.readme.io/e055ffe9065326a1157230e04187eb192db9472835993473f6225dbf70473194-small-allow-favicon.png" alt="AllowPay">
                        </div>
                        <h3>AllowPay</h3>
                        <div class="status">Inativo</div>
                    </div>

                    <div class="gateway-card" data-gateway="brutal">
                        <div class="gateway-icon">
                            <img src="https://hisoftware-assets.s3.us-east-2.amazonaws.com/uploads/1770901028178-logo-brutal-black.png" alt="BrutalCash">
                        </div>
                        <h3>BrutalCash</h3>
                        <div class="status">Inativo</div>
                    </div>

                    <div class="gateway-card" data-gateway="pagz">
                        <div class="gateway-icon">
                            <img src="./79A5D03B-B2CB-4EDC-A311-2836C4779EC4-removebg-preview.png" alt="Pagz">
                        </div>
                        <h3>Pagz</h3>
                        <div class="status">Inativo</div>
                    </div>

                    <div class="gateway-card" data-gateway="paradise">
                        <div class="gateway-icon">
                            <img src="https://raichu-uploads.s3.amazonaws.com/logo_paradise-tecnologia-servicos-e-pagamentos-ltda_d7diDL.png" alt="Paradise">
                        </div>
                        <h3>Paradise Pags</h3>
                        <div class="status">Inativo</div>
                    </div>

                    <div class="gateway-card" data-gateway="dutty">
                        <div class="gateway-icon">
                            <img src="https://app.duttyfy.com.br/favicon.ico" alt="DuttyOnPay">
                        </div>
                        <h3>DuttyOnPay</h3>
                        <div class="status">Inativo</div>
                    </div>

                    <div class="gateway-card" data-gateway="mangofy">
                        <div class="gateway-icon">
                            <img src="https://framerusercontent.com/images/vM4i9kiMoQnOShIVBLDFcEF31xU.png" alt="MangoFy">
                        </div>
                        <h3>MangoFy</h3>
                        <div class="status">Inativo</div>
                    </div>

                    <div class="gateway-card" data-gateway="iron">
                        <div class="gateway-icon">
                            <img src="https://ironpayapp.com.br/wp-content/uploads/2025/07/Favicon-70x70.png" alt="IronPay">
                        </div>
                        <h3>IronPay</h3>
                        <div class="status">Inativo</div>
                    </div>

                    <div class="gateway-card" data-gateway="ghost">
                        <div class="gateway-icon">
                            <img src="https://ryvfltjmmodesgsjyvii.supabase.co/storage/v1/object/public/documents/b65e6569-65a4-4bbd-b02b-793846dbe993-Group__1_.png" alt="Ghost Pays">
                        </div>
                        <h3>Ghost Pays</h3>
                        <div class="status">Inativo</div>
                    </div>

                    <div class="gateway-card" data-gateway="buck">
                        <div class="gateway-icon">
                            <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSjx_WWXra0rlcKE_uF3loJuzc3Evj5G3Tkvw&s" alt="BuckPay">
                        </div>
                        <h3>BuckPay</h3>
                        <div class="status">Inativo</div>
                    </div>

                    <div class="gateway-card" data-gateway="naut">
                        <div class="gateway-icon">
                            <img src="https://navenaut.com/_next/static/media/box-icon-naut-yellow.bd3780db.svg" alt="Naut">
                        </div>
                        <h3>Naut</h3>
                        <div class="status">Inativo</div>
                    </div>

                    <div class="gateway-card" data-gateway="zero">
                        <div class="gateway-icon">
                            <img src="https://storage.googleapis.com/gpt-engineer-file-uploads/Hv82OPNoNiXWe4ZBlPlPGK2yvKS2/uploads/1758545117335-zeroone_favicon.svg" alt="ZeroOnePay">
                        </div>
                        <h3>ZeroOnePay</h3>
                        <div class="status">Inativo</div>
                    </div>

                    <div class="gateway-card" data-gateway="black">
                        <div class="gateway-icon">
                            <img src="https://app.gateway-magicpay.com/_next/image?url=https%3A%2F%2Fcontent-images.shieldtecnologia.com%2Fimages%2F9ce02d8c-c93c-439d-b9b1-d4ac8b7918a8%2Feb1b139d-30eb-4448-812e-aeb41a9cbb88.png&w=384&q=75" alt="BlackPay">
                        </div>
                        <h3>MagicPay</h3>
                        <div class="status">Inativo</div>
                    </div>

                    <div class="gateway-card" data-gateway="cashfly">
                        <div class="gateway-icon">
                            <img src="https://azpakhcealgqpczvjcjr.supabase.co/storage/v1/object/public/documents/20f3ebd6-45b8-4b6e-8839-feb284a2d742-favicon_28x28_1.png" alt="CashFly">
                        </div>
                        <h3>CashFly</h3>
                        <div class="status">Inativo</div>
                    </div>

                    <div class="gateway-card" data-gateway="umbrella">
                        <div class="gateway-icon">
                            <img src="https://assets.apidog.com/app/project-icon/custom/20250808/eea4e62a-4b17-43bd-abcf-895f69535dd8.png" alt="Umbrella">
                        </div>
                        <h3>Umbrella</h3>
                        <div class="status">Inativo</div>
                    </div>

                    <div class="gateway-card" data-gateway="freepay">
                        <div class="gateway-icon">
                            <img src="https://files.readme.io/cfebe0e0fd9f5b12f276be5511d728762949a8f7190774fe78c427c3691a64a2-1762294240244-Favicon-FreePay.webp" alt="FreePay">
                        </div>
                        <h3>FreePay</h3>
                        <div class="status">Inativo</div>
                    </div>

                    <div class="gateway-card" data-gateway="quantum">
                        <div class="gateway-icon">
                            <img src="https://app.quantumpay.com.br/assets/svgs/logoT.svg" alt="QuantumPay">
                        </div>
                        <h3>QuantumPay</h3>
                        <div class="status">Inativo</div>
                    </div>

                    <div class="gateway-card" data-gateway="pollar">
                        <div class="gateway-icon">
                            <img src="https://cdn.qivotech.com.br/gateways/db8f6c0e-c180-4fce-b09b-15abbcfbfd03/assets/logo-icon/03074b07-859c-490a-abee-92e0188d0b03.svg" alt="Pollar Gateway">
                        </div>
                        <h3>Pollar Gateway</h3>
                        <div class="status">Inativo</div>
                    </div>

                    <?php if (isset($_GET['debug_mode']) && $_GET['debug_mode'] === 'on'): ?>
                    <div class="gateway-card" data-gateway="manutencao">
                        <div class="gateway-icon">
                            <i class="fa-solid fa-wrench"></i>
                        </div>
                        <h3>astropay (inacabado)</h3>
                        <div class="status">Inativo</div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Webhooks Tab -->
            <div id="webhooksBox" class="tab-content" style="display: none;">
                <div class="webhooks-section">
                    <div class="webhook-info">
                        <h3><i class="fa-solid fa-circle-info"></i> Como usar os Webhooks</h3>
                        <p>Configure estes URLs nos painéis dos gateways de pagamento para receber notificações automáticas de pagamentos aprovados, estornos e outros eventos.</p>
                    </div>

                    <div class="webhook-card">
                        <div class="webhook-header">
                            <div class="webhook-icon">
                                <img src="https://files.readme.io/e055ffe9065326a1157230e04187eb192db9472835993473f6225dbf70473194-small-allow-favicon.png" alt="AllowPay">
                            </div>
                            <div>
                                <h3>AllowPay</h3>
                                <p class="webhook-desc">Webhook específico para AllowPay</p>
                            </div>
                        </div>
                        <div class="webhook-url-box">
                            <input type="text" class="webhook-url" value="" data-webhook="webhook-allow.php" readonly>
                            <button class="btn-copy" onclick="copyWebhook(this)">
                                <i class="fa-solid fa-copy"></i> Copiar
                            </button>
                        </div>
                    </div>

                    <div class="webhook-card">
                        <div class="webhook-header">
                            <div class="webhook-icon">
                                <img src="https://hisoftware-assets.s3.us-east-2.amazonaws.com/uploads/1770901028178-logo-brutal-black.png" alt="BrutalCash">
                            </div>
                            <div>
                                <h3>BrutalCash</h3>
                                <p class="webhook-desc">Webhook específico para BrutalCash</p>
                            </div>
                        </div>
                        <div class="webhook-url-box">
                            <input type="text" class="webhook-url" value="" data-webhook="webhook-brutal.php" readonly>
                            <button class="btn-copy" onclick="copyWebhook(this)">
                                <i class="fa-solid fa-copy"></i> Copiar
                            </button>
                        </div>
                    </div>

                    <div class="webhook-card">
                        <div class="webhook-header">
                            <div class="webhook-icon">
                                <img src="./79A5D03B-B2CB-4EDC-A311-2836C4779EC4-removebg-preview.png" alt="Pagz">
                            </div>
                            <div>
                                <h3>Pagz</h3>
                                <p class="webhook-desc">Webhook específico para Pagz</p>
                            </div>
                        </div>
                        <div class="webhook-url-box">
                            <input type="text" class="webhook-url" value="" data-webhook="webhook-pagz.php" readonly>
                            <button class="btn-copy" onclick="copyWebhook(this)">
                                <i class="fa-solid fa-copy"></i> Copiar
                            </button>
                        </div>
                    </div>
                    <div class="webhook-card">
                        <div class="webhook-header">
                            <div class="webhook-icon">
                                <img src="https://raichu-uploads.s3.amazonaws.com/logo_paradise-tecnologia-servicos-e-pagamentos-ltda_d7diDL.png" alt="Paradise">
                            </div>
                            <div>
                                <h3>Paradise Pags</h3>
                                <p class="webhook-desc">Webhook específico para Paradise Pags</p>
                            </div>
                        </div>
                        <div class="webhook-url-box">
                            <input type="text" class="webhook-url" value="" data-webhook="webhook-paradise.php" readonly>
                            <button class="btn-copy" onclick="copyWebhook(this)">
                                <i class="fa-solid fa-copy"></i> Copiar
                            </button>
                        </div>
                    </div>

                    <div class="webhook-card">
                        <div class="webhook-header">
                            <div class="webhook-icon">
                                <img src="https://navenaut.com/_next/static/media/box-icon-naut-yellow.bd3780db.svg" alt="Naut">
                            </div>
                            <div>
                                <h3>Naut</h3>
                                <p class="webhook-desc">Webhook específico para Naut</p>
                            </div>
                        </div>
                        <div class="webhook-url-box">
                            <input type="text" class="webhook-url" value="" data-webhook="webhook-naut.php" readonly>
                            <button class="btn-copy" onclick="copyWebhook(this)">
                                <i class="fa-solid fa-copy"></i> Copiar
                            </button>
                        </div>
                    </div>

                    <div class="webhook-card">
                        <div class="webhook-header">
                            <div class="webhook-icon">
                                <img src="https://ironpayapp.com.br/wp-content/uploads/2025/07/Favicon-70x70.png" alt="IronPay">
                            </div>
                            <div>
                                <h3>IronPay</h3>
                                <p class="webhook-desc">Webhook específico para IronPay</p>
                            </div>
                        </div>
                        <div class="webhook-url-box">
                            <input type="text" class="webhook-url" value="" data-webhook="webhook-iron.php" readonly>
                            <button class="btn-copy" onclick="copyWebhook(this)">
                                <i class="fa-solid fa-copy"></i> Copiar
                            </button>
                        </div>
                    </div>

                    <div class="webhook-card">
                        <div class="webhook-header">
                            <div class="webhook-icon">
                                <img src="https://ryvfltjmmodesgsjyvii.supabase.co/storage/v1/object/public/documents/b65e6569-65a4-4bbd-b02b-793846dbe993-Group__1_.png" alt="Ghost Pays">
                            </div>
                            <div>
                                <h3>Ghost Pays</h3>
                                <p class="webhook-desc">Webhook específico para Ghost Pays</p>
                            </div>
                        </div>
                        <div class="webhook-url-box">
                            <input type="text" class="webhook-url" value="" data-webhook="webhook-ghost.php" readonly>
                            <button class="btn-copy" onclick="copyWebhook(this)">
                                <i class="fa-solid fa-copy"></i> Copiar
                            </button>
                        </div>
                    </div>

                    <div class="webhook-card">
                        <div class="webhook-header">
                            <div class="webhook-icon">
                                <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSjx_WWXra0rlcKE_uF3loJuzc3Evj5G3Tkvw&s" alt="BuckPay">
                            </div>
                            <div>
                                <h3>BuckPay</h3>
                                <p class="webhook-desc">Webhook específico para BuckPay</p>
                            </div>
                        </div>
                        <div class="webhook-url-box">
                            <input type="text" class="webhook-url" value="" data-webhook="webhook-buck.php" readonly>
                            <button class="btn-copy" onclick="copyWebhook(this)">
                                <i class="fa-solid fa-copy"></i> Copiar
                            </button>
                        </div>
                    </div>

                    <div class="webhook-card">
                        <div class="webhook-header">
                            <div class="webhook-icon">
                                <img src="https://storage.googleapis.com/gpt-engineer-file-uploads/Hv82OPNoNiXWe4ZBlPlPGK2yvKS2/uploads/1758545117335-zeroone_favicon.svg" alt="ZeroOnePay">
                            </div>
                            <div>
                                <h3>ZeroOnePay</h3>
                                <p class="webhook-desc">Webhook específico para ZeroOnePay</p>
                            </div>
                        </div>
                        <div class="webhook-url-box">
                            <input type="text" class="webhook-url" value="" data-webhook="webhook-zero.php" readonly>
                            <button class="btn-copy" onclick="copyWebhook(this)">
                                <i class="fa-solid fa-copy"></i> Copiar
                            </button>
                        </div>
                    </div>

                    <div class="webhook-card">
                        <div class="webhook-header">
                            <div class="webhook-icon">
                                <img src="https://app.gateway-magicpay.com/_next/image?url=https%3A%2F%2Fcontent-images.shieldtecnologia.com%2Fimages%2F9ce02d8c-c93c-439d-b9b1-d4ac8b7918a8%2Feb1b139d-30eb-4448-812e-aeb41a9cbb88.png&w=384&q=75" alt="BlackPay">
                            </div>
                            <div>
                                <h3>Magicpay</h3>
                                <p class="webhook-desc">Webhook específico para Magicpay</p>
                            </div>
                        </div>
                        <div class="webhook-url-box">
                            <input type="text" class="webhook-url" value="" data-webhook="webhook-black.php" readonly>
                            <button class="btn-copy" onclick="copyWebhook(this)">
                                <i class="fa-solid fa-copy"></i> Copiar
                            </button>
                        </div>
                    </div>

                    <div class="webhook-card">
                        <div class="webhook-header">
                            <div class="webhook-icon">
                                <img src="https://azpakhcealgqpczvjcjr.supabase.co/storage/v1/object/public/documents/20f3ebd6-45b8-4b6e-8839-feb284a2d742-favicon_28x28_1.png" alt="CashFly">
                            </div>
                            <div>
                                <h3>CashFly</h3>
                                <p class="webhook-desc">Webhook específico para CashFly</p>
                            </div>
                        </div>
                        <div class="webhook-url-box">
                            <input type="text" class="webhook-url" value="" data-webhook="webhook-cashfly.php" readonly>
                            <button class="btn-copy" onclick="copyWebhook(this)">
                                <i class="fa-solid fa-copy"></i> Copiar
                            </button>
                        </div>
                    </div>

                    <div class="webhook-card">
                        <div class="webhook-header">
                            <div class="webhook-icon">
                                <img src="https://app.duttyfy.com.br/favicon.ico" alt="DuttyOnPay">
                            </div>
                            <div>
                                <h3>DuttyOnPay</h3>
                                <p class="webhook-desc">Webhook específico para DuttyOnPay</p>
                            </div>
                        </div>
                        <div class="webhook-url-box">
                            <input type="text" class="webhook-url" value="" data-webhook="webhook-dutty.php" readonly>
                            <button class="btn-copy" onclick="copyWebhook(this)">
                                <i class="fa-solid fa-copy"></i> Copiar
                            </button>
                        </div>
                    </div>

                    <div class="webhook-card">
                        <div class="webhook-header">
                            <div class="webhook-icon">
                                <img src="https://framerusercontent.com/images/vM4i9kiMoQnOShIVBLDFcEF31xU.png" alt="MangoFy">
                            </div>
                            <div>
                                <h3>MangoFy</h3>
                                <p class="webhook-desc">Webhook específico para MangoFy</p>
                            </div>
                        </div>
                        <div class="webhook-url-box">
                            <input type="text" class="webhook-url" value="" data-webhook="webhook-mangofy.php" readonly>
                            <button class="btn-copy" onclick="copyWebhook(this)">
                                <i class="fa-solid fa-copy"></i> Copiar
                            </button>
                        </div>
                    </div>

                    <div class="webhook-card">
                        <div class="webhook-header">
                            <div class="webhook-icon">
                                <img src="https://assets.apidog.com/app/project-icon/custom/20250808/eea4e62a-4b17-43bd-abcf-895f69535dd8.png" alt="Umbrella">
                            </div>
                            <div>
                                <h3>Umbrella</h3>
                                <p class="webhook-desc">Webhook específico para Umbrella</p>
                            </div>
                        </div>
                        <div class="webhook-url-box">
                            <input type="text" class="webhook-url" value="" data-webhook="webhook-umbrella.php" readonly>
                            <button class="btn-copy" onclick="copyWebhook(this)">
                                <i class="fa-solid fa-copy"></i> Copiar
                            </button>
                        </div>
                    </div>

                    <div class="webhook-card">
                        <div class="webhook-header">
                            <div class="webhook-icon">
                                <img src="https://files.readme.io/cfebe0e0fd9f5b12f276be5511d728762949a8f7190774fe78c427c3691a64a2-1762294240244-Favicon-FreePay.webp" alt="FreePay">
                            </div>
                            <div>
                                <h3>FreePay</h3>
                                <p class="webhook-desc">Webhook específico para FreePay</p>
                            </div>
                        </div>
                        <div class="webhook-url-box">
                            <input type="text" class="webhook-url" value="" data-webhook="webhook-freepay.php" readonly>
                            <button class="btn-copy" onclick="copyWebhook(this)">
                                <i class="fa-solid fa-copy"></i> Copiar
                            </button>
                        </div>
                    </div>

                    <div class="webhook-card">
                        <div class="webhook-header">
                            <div class="webhook-icon">
                                <img src="https://app.quantumpay.com.br/assets/svgs/logoT.svg" alt="QuantumPay">
                            </div>
                            <div>
                                <h3>QuantumPay</h3>
                                <p class="webhook-desc">Webhook específico para QuantumPay</p>
                            </div>
                        </div>
                        <div class="webhook-url-box">
                            <input type="text" class="webhook-url" value="" data-webhook="webhook-quantum.php" readonly>
                            <button class="btn-copy" onclick="copyWebhook(this)">
                                <i class="fa-solid fa-copy"></i> Copiar
                            </button>
                        </div>
                    </div>

                    <div class="webhook-card">
                        <div class="webhook-header">
                            <div class="webhook-icon">
                                <img src="https://cdn.qivotech.com.br/gateways/db8f6c0e-c180-4fce-b09b-15abbcfbfd03/assets/logo-icon/03074b07-859c-490a-abee-92e0188d0b03.svg" alt="Pollar Gateway">
                            </div>
                            <div>
                                <h3>Pollar Gateway</h3>
                                <p class="webhook-desc">Webhook específico para Pollar Gateway</p>
                            </div>
                        </div>
                        <div class="webhook-url-box">
                            <input type="text" class="webhook-url" value="" data-webhook="webhook-pollar.php" readonly>
                            <button class="btn-copy" onclick="copyWebhook(this)">
                                <i class="fa-solid fa-copy"></i> Copiar
                            </button>
                        </div>
                    </div>

                    
                </div>
            </div>

            <!-- Preview Tab - Agora abre em nova janela -->
            <div id="previewBox" class="tab-content" style="display: none;">
                <div style="text-align: center; padding: 80px 20px;">
                    <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: 16px; padding: 48px; max-width: 600px; margin: 0 auto;">
                        <div style="width: 80px; height: 80px; background: var(--primary); border-radius: 16px; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; box-shadow: 0 0 30px rgba(139, 92, 246, 0.3);">
                            <i class="fa-solid fa-eye" style="font-size: 36px; color: white;"></i>
                        </div>
                        
                        <h2 style="color: var(--text-primary); font-size: 28px; font-weight: 700; margin-bottom: 16px;">
                            Preview Builder
                        </h2>
                        
                        <p style="color: var(--text-secondary); font-size: 16px; line-height: 1.6; margin-bottom: 32px;">
                            Personalize seu checkout em tempo real com nossa interface visual intuitiva. 
                            Configure cores, textos, imagens e muito mais!
                        </p>
                        
                        <button onclick="openPreviewBuilder()" class="btn btn-primary" style="width: auto; padding: 16px 48px; font-size: 16px; display: inline-flex; align-items: center; gap: 12px;">
                            <i class="fa-solid fa-rocket"></i>
                            Abrir Preview Builder
                        </button>
                        
                        <div style="margin-top: 32px; padding-top: 32px; border-top: 1px solid var(--border);">
                            <p style="color: var(--text-muted); font-size: 13px; margin: 0;">
                                💡 Dica: O Preview Builder abre em uma nova janela para melhor experiência
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TikTok Pixels Tab -->
            <div id="tiktokBox" class="tab-content" style="display: none;">
                <div class="tiktok-section">
                    <div class="webhook-info">
                        <h3><i class="fa-brands fa-tiktok"></i> Gerenciar Contas TikTok Pixel</h3>
                        <p>Adicione e gerencie suas contas TikTok Pixel para rastreamento de conversões.</p>
                    </div>

                    <div style="margin-bottom: 24px; text-align: right;">
                        <button id="addTiktokAccountBtn" class="btn btn-primary" style="width: auto; display: inline-flex; align-items: center; gap: 8px;">
                            <i class="fa-solid fa-plus"></i> Adicionar Conta
                        </button>
                    </div>

                    <div id="tiktokAccountsContainer">
                        <!-- Contas serão carregadas aqui -->
                    </div>

                    <div id="noTiktokAccounts" style="display: none; text-align: center; padding: 48px; color: var(--text-secondary);">
                        <i class="fa-brands fa-tiktok" style="font-size: 48px; margin-bottom: 16px; opacity: 0.3;"></i>
                        <p>Nenhuma conta TikTok cadastrada</p>
                        <p style="font-size: 13px; margin-top: 8px;">Clique em "Adicionar Conta" para começar</p>
                    </div>
                </div>
            </div>

            <!-- Otimizey Tab -->
            <div id="otimizeyBox" class="tab-content" style="display: none;">
                <div class="otimizey-section">
                    <div class="webhook-info">
                        <h3><i class="fa-solid fa-chart-line"></i> Configuração Otimizey</h3>
                        <p>Configure a credencial da API Otimizey para rastreamento de conversões e UTMs.</p>
                    </div>

                    <!-- Script de Rastreamento -->
                    <div class="webhook-card" style="margin-bottom: 24px;">
                        <div class="webhook-header">
                            <div class="webhook-icon">
                                <i class="fa-solid fa-code" style="color: var(--primary);"></i>
                            </div>
                            <div style="flex: 1;">
                                <h3>Script de Rastreamento Avançado</h3>
                                <p class="webhook-desc">Adicione este script no &lt;head&gt; de todas as páginas do funil (obs: se seu site foi feito pelo dev, ignore esse passo)</p>
                            </div>
                        </div>
                        
                        <div style="margin-top: 16px; padding: 16px; background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.3); border-radius: 8px;">
                            <p style="color: #60a5fa; font-size: 13px; margin-bottom: 12px; font-weight: 500;">
                                <i class="fa-solid fa-info-circle"></i> Importante: Cole este código no &lt;head&gt; de todas as páginas do seu funil (landing page, checkout, upsells, etc.)
                            </p>
                            <div style="position: relative;">
                                <pre id="otimizeyTrackingScript" style="background: var(--bg-dark); border: 1px solid var(--border); border-radius: 6px; padding: 16px; color: var(--text-primary); font-family: 'Courier New', monospace; font-size: 12px; overflow-x: auto; margin: 0; line-height: 1.6;">&lt;script&gt;(function() {const script = document.createElement('script');const path = window.location.pathname;const pathParts = path.split('/').filter(p => p);if (pathParts.length > 0 && pathParts[pathParts.length - 1].includes('.html')) {pathParts.pop();}const origin = window.location.origin;const basePath = pathParts.length > 0 ? '/' + pathParts[0] + '/' : '/';script.src = origin + basePath + 'checkout/otimizey-loader.js?v=' + new Date().getTime();document.head.appendChild(script);})();&lt;/script&gt;</pre>
                                <button onclick="copyOtimizeyTrackingScript()" class="btn-copy" style="position: absolute; top: 12px; right: 12px;">
                                    <i class="fa-solid fa-copy"></i> Copiar
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="webhook-card">
                        <div class="webhook-header">
                            <div class="webhook-icon">
                                <i class="fa-solid fa-key" style="color: var(--primary);"></i>
                            </div>
                            <div>
                                <h3>Credential ID</h3>
                                <p class="webhook-desc">Identificador único da sua credencial na API Otimizey</p>
                            </div>
                        </div>
                        
                        <form id="otimizeyForm" style="margin-top: 20px;">
                            <div class="form-group">
                                <label for="otimizey_credential_id">
                                    <i class="fa-solid fa-fingerprint"></i> Credential ID (UUID)
                                </label>
                                <input 
                                    type="text" 
                                    id="otimizey_credential_id" 
                                    name="credential_id" 
                                    placeholder="89e0337a-6fad-4fdf-83da-db08bf785fb8" 
                                    required
                                    style="font-family: 'Courier New', monospace; font-size: 14px;"
                                >
                                <small style="color: var(--text-secondary); font-size: 12px; display: block; margin-top: 6px;">
                                    Formato: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
                                </small>
                            </div>
                            
                            <div class="form-group">
                                <label for="otimizey_tracking_script_id">
                                    <i class="fa-solid fa-code"></i> Tracking Script ID
                                </label>
                                <input 
                                    type="text" 
                                    id="otimizey_tracking_script_id" 
                                    name="tracking_script_id" 
                                    placeholder="NqdJQ9M2Bmv6ZE3UIMglL2LWYUmlTZtR"
                                    style="font-family: 'Courier New', monospace; font-size: 14px;"
                                >
                                <small style="color: var(--text-secondary); font-size: 12px; display: block; margin-top: 6px;">
                                    ID do script de tracking que será carregado em todas as páginas
                                </small>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fa-solid fa-save"></i> Salvar Configurações
                            </button>
                        </form>
                        
                        <div id="otimizeyLastUpdate" style="margin-top: 16px; color: var(--text-secondary); font-size: 13px;"></div>
                    </div>

                    <!-- Estatísticas e Logs -->
                    <div class="webhook-card" style="margin-top: 24px;">
                        <div class="webhook-header">
                            <div class="webhook-icon">
                                <i class="fa-solid fa-chart-bar" style="color: var(--primary);"></i>
                            </div>
                            <div style="flex: 1;">
                                <h3>Estatísticas de Envios</h3>
                                <p class="webhook-desc">Monitoramento de envios para a API Otimizey</p>
                            </div>
                            <button onclick="refreshOtimizeyStats()" style="background: var(--primary); border: none; border-radius: 6px; color: white; padding: 8px 16px; cursor: pointer; display: flex; align-items: center; gap: 6px; font-size: 13px;">
                                <i class="fa-solid fa-refresh"></i> Atualizar
                            </button>
                        </div>

                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px; margin-top: 20px;">
                            <div style="background: var(--bg-dark); border: 1px solid var(--border); border-radius: 8px; padding: 16px; text-align: center;">
                                <div style="color: var(--text-secondary); font-size: 12px; margin-bottom: 4px;">Total Hoje</div>
                                <div id="stats_total" style="color: var(--text-primary); font-size: 28px; font-weight: 700;">-</div>
                            </div>
                            <div style="background: var(--bg-dark); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 8px; padding: 16px; text-align: center;">
                                <div style="color: var(--text-secondary); font-size: 12px; margin-bottom: 4px;">Sucesso</div>
                                <div id="stats_success" style="color: #34d399; font-size: 28px; font-weight: 700;">-</div>
                            </div>
                            <div style="background: var(--bg-dark); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 8px; padding: 16px; text-align: center;">
                                <div style="color: var(--text-secondary); font-size: 12px; margin-bottom: 4px;">Erros</div>
                                <div id="stats_error" style="color: #f87171; font-size: 28px; font-weight: 700;">-</div>
                            </div>
                        </div>
                    </div>

                    <!-- Último Envio -->
                    <div class="webhook-card" style="margin-top: 24px;">
                        <div class="webhook-header">
                            <div class="webhook-icon">
                                <i class="fa-solid fa-clock-rotate-left" style="color: var(--primary);"></i>
                            </div>
                            <div>
                                <h3>Último Envio</h3>
                                <p class="webhook-desc">Informações do envio mais recente</p>
                            </div>
                        </div>

                        <div id="lastSendInfo" style="margin-top: 20px;">
                            <div style="text-align: center; padding: 32px; color: var(--text-secondary);">
                                <i class="fa-solid fa-spinner fa-spin" style="font-size: 24px; margin-bottom: 12px;"></i>
                                <p>Carregando informações...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Utmify Tab -->
            <div id="utmifyBox" class="tab-content" style="display: none;">
                <div class="utmify-section">
                    <div class="webhook-info">
                        <h3><i class="fa-solid fa-link"></i> Configuração Utmify</h3>
                        <p>Configure o token da API Utmify para rastreamento de conversões e UTMs.</p>
                    </div>

                    <div class="webhook-card">
                        <div class="webhook-header">
                            <div class="webhook-icon">
                                <i class="fa-solid fa-key" style="color: var(--primary);"></i>
                            </div>
                            <div>
                                <h3>API Token</h3>
                                <p class="webhook-desc">Token de autenticação da API Utmify</p>
                            </div>
                        </div>
                        
                        <form id="utmifyForm" style="margin-top: 20px;">
                            <div class="form-group">
                                <label for="utmify_token">
                                    <i class="fa-solid fa-fingerprint"></i> API Token
                                </label>
                                <input 
                                    type="text" 
                                    id="utmify_token" 
                                    name="token" 
                                    placeholder="i5P5qBnhTRvVrRKJ3LQrbYaCcq1zowq7HSgN" 
                                    required
                                    style="font-family: 'Courier New', monospace; font-size: 14px;"
                                >
                                <small style="color: var(--text-secondary); font-size: 12px; display: block; margin-top: 6px;">
                                    Token fornecido pela plataforma Utmify
                                </small>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fa-solid fa-save"></i> Salvar Token
                            </button>
                        </form>
                        
                        <div id="utmifyLastUpdate" style="margin-top: 16px; color: var(--text-secondary); font-size: 13px;"></div>
                    </div>

                    <!-- Estatísticas Utmify -->
                    <div class="webhook-card" style="margin-top: 24px;">
                        <div class="webhook-header">
                            <div class="webhook-icon">
                                <i class="fa-solid fa-chart-bar" style="color: var(--primary);"></i>
                            </div>
                            <div style="flex: 1;">
                                <h3>Estatísticas de Envios</h3>
                                <p class="webhook-desc">Monitoramento de envios para a API Utmify</p>
                            </div>
                            <button onclick="refreshUtmifyStats()" style="background: var(--primary); border: none; border-radius: 6px; color: white; padding: 8px 16px; cursor: pointer; display: flex; align-items: center; gap: 6px; font-size: 13px;">
                                <i class="fa-solid fa-refresh"></i> Atualizar
                            </button>
                        </div>

                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px; margin-top: 20px;">
                            <div style="background: var(--bg-dark); border: 1px solid var(--border); border-radius: 8px; padding: 16px; text-align: center;">
                                <div style="color: var(--text-secondary); font-size: 12px; margin-bottom: 4px;">Total Hoje</div>
                                <div id="utmify_stats_total" style="color: var(--text-primary); font-size: 28px; font-weight: 700;">-</div>
                            </div>
                            <div style="background: var(--bg-dark); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 8px; padding: 16px; text-align: center;">
                                <div style="color: var(--text-secondary); font-size: 12px; margin-bottom: 4px;">Sucesso</div>
                                <div id="utmify_stats_success" style="color: #34d399; font-size: 28px; font-weight: 700;">-</div>
                            </div>
                            <div style="background: var(--bg-dark); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 8px; padding: 16px; text-align: center;">
                                <div style="color: var(--text-secondary); font-size: 12px; margin-bottom: 4px;">Erros</div>
                                <div id="utmify_stats_error" style="color: #f87171; font-size: 28px; font-weight: 700;">-</div>
                            </div>
                        </div>
                    </div>

                    <!-- Último Envio Utmify -->
                    <div class="webhook-card" style="margin-top: 24px;">
                        <div class="webhook-header">
                            <div class="webhook-icon">
                                <i class="fa-solid fa-clock-rotate-left" style="color: var(--primary);"></i>
                            </div>
                            <div>
                                <h3>Último Envio</h3>
                                <p class="webhook-desc">Informações do envio mais recente</p>
                            </div>
                        </div>

                        <div id="utmifyLastSendInfo" style="margin-top: 20px;">
                            <div style="text-align: center; padding: 32px; color: var(--text-secondary);">
                                <i class="fa-solid fa-spinner fa-spin" style="font-size: 24px; margin-bottom: 12px;"></i>
                                <p>Carregando informações...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Adicionar/Editar Conta TikTok -->
    <div id="tiktokModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: 16px; padding: 32px; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto;">
            <div style="display: flex; align-items: center; justify-content: between; margin-bottom: 24px;">
                <h3 id="tiktokModalTitle" style="color: var(--text-primary); font-size: 20px; font-weight: 600; margin: 0; flex: 1;">Adicionar Conta TikTok</h3>
                <button id="closeTiktokModal" style="background: none; border: none; color: var(--text-secondary); font-size: 24px; cursor: pointer; padding: 0; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;">
                    <i class="fa-solid fa-times"></i>
                </button>
            </div>
            
            <form id="tiktokForm">
                <input type="hidden" id="tiktokAccountId" name="id">
                
                <div class="form-group">
                    <label for="tiktokAccountName">Nome da Conta</label>
                    <input type="text" id="tiktokAccountName" name="name" placeholder="Ex: Conta Principal" required>
                </div>
                
                <div class="form-group">
                    <label for="tiktokPixelId">Pixel ID</label>
                    <input type="text" id="tiktokPixelId" name="pixel_id" placeholder="Ex: D65U11JC77U1M7M29O2G" required style="font-family: 'Courier New', monospace;">
                </div>
                
                <div class="form-group">
                    <label for="tiktokAccessToken">Access Token</label>
                    <textarea id="tiktokAccessToken" name="access_token" placeholder="Cole aqui seu Access Token" required rows="4"></textarea>
                </div>
                
                <div style="display: flex; gap: 12px; margin-top: 24px;">
                    <button type="button" id="cancelTiktokModal" class="btn" style="background: var(--text-secondary); flex: 1;">
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary" style="flex: 1;">
                        <i class="fa-solid fa-save"></i> Salvar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Gateway Indisponível -->
    <div id="unavailableGatewayModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); backdrop-filter: blur(4px); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: 16px; padding: 32px; max-width: 500px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.5);">
            <div style="text-align: center; margin-bottom: 24px;">
                <div style="width: 80px; height: 80px; background: rgba(239, 68, 68, 0.15); border-radius: 16px; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                    <i class="fa-solid fa-lock" style="font-size: 36px; color: var(--danger);"></i>
                </div>
                <h3 style="color: var(--text-primary); font-size: 22px; font-weight: 600; margin-bottom: 8px;">Gateway Não Configurado</h3>
                <p id="unavailableGatewayName" style="color: var(--text-secondary); font-size: 15px; margin: 0;"></p>
            </div>
            
            <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 12px; padding: 20px; margin-bottom: 24px;">
                <p style="color: var(--text-primary); font-size: 15px; line-height: 1.6; margin: 0;">
                    <i class="fa-solid fa-circle-info" style="color: var(--danger); margin-right: 8px;"></i>
                    Você não configurou esse gateway. Por favor, configure no <strong>Checkout Builder de Attrion</strong> e faça o download novamente.
                </p>
            </div>
            
            <div style="background: var(--bg-dark); border: 1px solid var(--border); border-radius: 8px; padding: 16px; margin-bottom: 24px;">
                <h4 style="color: var(--text-primary); font-size: 14px; font-weight: 600; margin-bottom: 12px;">
                    <i class="fa-solid fa-list-check" style="color: var(--primary); margin-right: 6px;"></i>
                    Como resolver:
                </h4>
                <ol style="color: var(--text-secondary); font-size: 14px; line-height: 1.8; margin: 0; padding-left: 20px;">
                    <li>Acesse o <strong>Checkout Builder de Attrion</strong></li>
                    <li>Configure as credenciais deste gateway</li>
                    <li>Faça o download do checkout atualizado</li>
                    <li>Substitua os arquivos no servidor</li>
                </ol>
            </div>
            
            <button onclick="closeUnavailableGatewayModal()" class="btn btn-primary" style="width: 100%;">
                <i class="fa-solid fa-check"></i> Entendi
            </button>
        </div>
    </div>

    <script>
        // Estado da aplicação
        let isLoggedIn = <?php echo isset($_SESSION['gateway_admin_logged']) ? 'true' : 'false'; ?>;
        let currentGateway = null;
        let currentDomain = window.location.origin + window.location.pathname.replace('/admin-gateway.php', '');
        
        // Mapeamento de gateways para seus arquivos
        const gatewayFiles = {
            'allow': 'pagamento-allow.php',
            'brutal': 'pagamento-brutal.php',
            'pagz': 'pagamento-pagz.php',
            'paradise': 'pagamento-paradise.php',
            'dutty': 'pagamento-dutty.php',
            'mangofy': 'pagamento-mangofy.php',
            'iron': 'pagamento-iron.php',
            'ghost': 'pagamento-ghost.php',
            'buck': 'pagamento-buck.php',
            'naut': 'pagamento-naut.php',
            'zero': 'pagamento-zero.php',
            'black': 'pagamento-black.php',
            'cashfly': 'pagamento-cashfly.php',
            'umbrella': 'pagamento-umbrella.php',
            'freepay': 'pagamento-freepay.php',
            'quantum': 'pagamento-quantum.php',
            'pollar': 'pagamento-pollar.php'
        };
        
        // Armazenar status de disponibilidade dos gateways
        let gatewayAvailability = {};

        // Elementos
        const loginBox = document.getElementById('loginBox');
        const adminPanel = document.getElementById('adminPanel');
        const loginForm = document.getElementById('loginForm');
        const logoutBtn = document.getElementById('logoutBtn');
        const alertBox = document.getElementById('alertBox');
        const loadingBox = document.getElementById('loadingBox');
        const gatewayBox = document.getElementById('gatewayBox');
        const webhooksBox = document.getElementById('webhooksBox');
        const lastUpdate = document.getElementById('lastUpdate');
        const gatewayCards = document.querySelectorAll('.gateway-card');
        const tabBtns = document.querySelectorAll('.tab-btn');

        // Inicialização
        init();

        function init() {
            if (isLoggedIn) {
                showAdminPanel();
                checkGatewayAvailability(); // Verificar disponibilidade primeiro
                loadGatewayConfig();
                initializeWebhookUrls();
                setupTabs();
            } else {
                showLoginBox();
            }
        }

        // Verificar disponibilidade dos gateways
        async function checkGatewayAvailability() {
            try {
                const response = await fetch('admin-gateway.php?action=check_gateway_availability');
                const data = await response.json();

                if (data.success) {
                    gatewayAvailability = data.availability;
                    updateGatewayAvailabilityUI();
                }
            } catch (error) {
                console.error('Erro ao verificar disponibilidade dos gateways:', error);
            }
        }

        // Atualizar UI com status de disponibilidade
        function updateGatewayAvailabilityUI() {
            gatewayCards.forEach(card => {
                const gateway = card.dataset.gateway;
                
                // Ignorar card de manutenção
                if (gateway === 'manutencao') return;
                
                const isAvailable = gatewayAvailability[gateway];
                
                if (!isAvailable) {
                    card.classList.add('unavailable');
                    
                    // Adicionar badge de indisponível
                    const badge = document.createElement('div');
                    badge.className = 'unavailable-badge';
                    badge.innerHTML = '<i class="fa-solid fa-lock"></i> Indisponível';
                    card.appendChild(badge);
                    
                    // Adicionar overlay com botão de detalhes
                    const overlay = document.createElement('div');
                    overlay.className = 'unavailable-overlay';
                    overlay.innerHTML = `
                        <i class="fa-solid fa-lock" style="font-size: 32px; color: var(--danger);"></i>
                        <button class="btn-details" onclick="showUnavailableGatewayModal('${gateway}', '${card.querySelector('h3').textContent}')">
                            <i class="fa-solid fa-circle-info"></i> Ver Detalhes
                        </button>
                    `;
                    card.appendChild(overlay);
                    
                    // Remover evento de clique do card
                    card.style.pointerEvents = 'none';
                    overlay.style.pointerEvents = 'all';
                }
            });
        }

        // Mostrar modal de gateway indisponível
        window.showUnavailableGatewayModal = function(gateway, gatewayName) {
            const modal = document.getElementById('unavailableGatewayModal');
            const nameElement = document.getElementById('unavailableGatewayName');
            
            nameElement.textContent = gatewayName;
            modal.style.display = 'flex';
        };

        // Fechar modal de gateway indisponível
        window.closeUnavailableGatewayModal = function() {
            const modal = document.getElementById('unavailableGatewayModal');
            modal.style.display = 'none';
        };

        document.addEventListener('click', function(e) {
            const modal = document.getElementById('unavailableGatewayModal');
            if (e.target === modal) {
                closeUnavailableGatewayModal();
            }
        });

        function setupTabs() {
            tabBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    const targetTab = btn.dataset.tab;
                    
                    tabBtns.forEach(b => b.classList.remove('active'));
                    document.querySelectorAll('.tab-content').forEach(content => {
                        content.style.display = 'none';
                    });
                    
                    btn.classList.add('active');
                    if (targetTab === 'gateways') {
                        gatewayBox.style.display = 'block';
                    } else if (targetTab === 'webhooks') {
                        webhooksBox.style.display = 'block';
                    } else if (targetTab === 'preview') {
                        document.getElementById('previewBox').style.display = 'block';
                    } else if (targetTab === 'tiktok') {
                        document.getElementById('tiktokBox').style.display = 'block';
                        loadTiktokAccounts(); // Carregar contas quando a aba for aberta
                    } else if (targetTab === 'otimizey') {
                        document.getElementById('otimizeyBox').style.display = 'block';
                        loadOtimizeyConfig(); 
                    } else if (targetTab === 'utmify') {
                        document.getElementById('utmifyBox').style.display = 'block';
                        loadUtmifyConfig(); 
                    }
                });
            });
        }

        function initializeWebhookUrls() {
            document.querySelectorAll('.webhook-url').forEach(input => {
                const webhookFile = input.dataset.webhook;
                input.value = currentDomain + '/' + webhookFile;
            });
        }

        function copyWebhook(button) {
            const input = button.previousElementSibling;
            input.select();
            input.setSelectionRange(0, 99999); 
            
            try {
                navigator.clipboard.writeText(input.value).then(() => {
                    const originalText = button.textContent;
                    button.textContent = '✓ Copiado!';
                    button.classList.add('copied');
                    
                    setTimeout(() => {
                        button.textContent = originalText;
                        button.classList.remove('copied');
                    }, 2000);
                    
                    showAlert('URL do webhook copiada!', 'success');
                }).catch(() => {
                    document.execCommand('copy');
                    showAlert('URL do webhook copiada!', 'success');
                });
            } catch (err) {
                showAlert('Erro ao copiar URL', 'error');
            }
        }

        function showLoginBox() {
            loginBox.style.display = 'block';
            adminPanel.style.display = 'none';
        }

        function showAdminPanel() {
            loginBox.style.display = 'none';
            adminPanel.style.display = 'block';
        }

        function showAlert(message, type = 'success') {
            alertBox.textContent = message;
            alertBox.className = `alert alert-${type} show`;
            setTimeout(() => {
                alertBox.classList.remove('show');
            }, 5000);
        }

        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const password = document.getElementById('password').value;

            try {
                const response = await fetch('gateway-controller.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=login&password=${encodeURIComponent(password)}`
                });

                const data = await response.json();

                if (data.success) {
                    isLoggedIn = true;
                    showAdminPanel();
                    loadGatewayConfig();
                    initializeWebhookUrls();
                    setupTabs();
                } else {
                    showAlert(data.message, 'error');
                }
            } catch (error) {
                showAlert('Erro ao fazer login', 'error');
            }
        });

        logoutBtn.addEventListener('click', async () => {
            try {
                const response = await fetch('gateway-controller.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=logout'
                });

                const data = await response.json();

                if (data.success) {
                    isLoggedIn = false;
                    showLoginBox();
                }
            } catch (error) {
                showAlert('Erro ao fazer logout', 'error');
            }
        });

        async function loadGatewayConfig() {
            try {
                const response = await fetch('gateway-controller.php');
                const data = await response.json();

                if (data.success) {
                    currentGateway = data.gateway;
                    updateUI(data.gateway);
                    loadingBox.style.display = 'none';
                    gatewayBox.style.display = 'block';
                }
            } catch (error) {
                showAlert('Erro ao carregar configuração', 'error');
                loadingBox.textContent = 'Erro ao carregar';
            }
        }

        function updateUI(activeGateway) {
            gatewayCards.forEach(card => {
                const gateway = card.dataset.gateway;
                const statusEl = card.querySelector('.status');
                
                const oldBadge = card.querySelector('.active-badge');
                if (oldBadge) oldBadge.remove();
                card.classList.remove('active');

                if (gateway === activeGateway) {
                    card.classList.add('active');
                    statusEl.textContent = 'Ativo';
                    
                    const badge = document.createElement('div');
                    badge.className = 'active-badge';
                    badge.textContent = '✓ Ativo';
                    card.appendChild(badge);
                } else {
                    statusEl.textContent = 'Inativo';
                }
            });
        }

        gatewayCards.forEach(card => {
            card.addEventListener('click', async () => {
                const gateway = card.dataset.gateway;

                if (card.classList.contains('unavailable')) {
                    return; 
                }

                if (gateway === currentGateway) {
                    showAlert('Este gateway já está ativo', 'error');
                    return;
                }

                if (!confirm(`Deseja ativar o gateway ${card.querySelector('h3').textContent}?`)) {
                    return;
                }

                try {
                    const response = await fetch('gateway-controller.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=change_gateway&gateway=${gateway}`
                    });

                    const data = await response.json();

                    if (data.success) {
                        currentGateway = data.gateway;
                        updateUI(data.gateway);
                        showAlert(data.message, 'success');
                    } else {
                        showAlert(data.message, 'error');
                    }
                } catch (error) {
                    showAlert('Erro ao alterar gateway', 'error');
                }
            });
        });

        let tiktokAccounts = [];
        let editingTiktokAccountId = null;

        const tiktokModal = document.getElementById('tiktokModal');
        const tiktokForm = document.getElementById('tiktokForm');
        const addTiktokAccountBtn = document.getElementById('addTiktokAccountBtn');
        const closeTiktokModalBtn = document.getElementById('closeTiktokModal');
        const cancelTiktokModalBtn = document.getElementById('cancelTiktokModal');

        if (addTiktokAccountBtn) {
            addTiktokAccountBtn.addEventListener('click', showAddTiktokAccountModal);
        }
        if (closeTiktokModalBtn) {
            closeTiktokModalBtn.addEventListener('click', closeTiktokModal);
        }
        if (cancelTiktokModalBtn) {
            cancelTiktokModalBtn.addEventListener('click', closeTiktokModal);
        }
        if (tiktokForm) {
            tiktokForm.addEventListener('submit', saveTiktokAccount);
        }

        async function loadTiktokAccounts() {
            try {
                const response = await fetch('tiktok-controller.php');
                const data = await response.json();

                if (data.success) {
                    tiktokAccounts = data.accounts || [];
                    renderTiktokAccounts();
                } else {
                    console.error('Erro ao carregar contas TikTok:', data.message);
                    tiktokAccounts = [];
                    renderTiktokAccounts();
                }
            } catch (error) {
                console.error('Erro na requisição TikTok:', error);
                tiktokAccounts = [];
                renderTiktokAccounts();
            }
        }

        function renderTiktokAccounts() {
            const container = document.getElementById('tiktokAccountsContainer');
            const noAccountsMsg = document.getElementById('noTiktokAccounts');

            if (tiktokAccounts.length === 0) {
                container.innerHTML = '';
                noAccountsMsg.style.display = 'block';
                return;
            }

            noAccountsMsg.style.display = 'none';

            container.innerHTML = tiktokAccounts.map(account => `
                <div class="webhook-card" style="margin-bottom: 16px;">
                    <div class="webhook-header">
                        <div class="webhook-icon">
                            <i class="fa-brands fa-tiktok" style="color: var(--primary);"></i>
                        </div>
                        <div style="flex: 1;">
                            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 4px;">
                                <h3>${escapeHtml(account.name)}</h3>
                                <span style="padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; ${account.active ? 'background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3);' : 'background: rgba(107, 114, 128, 0.15); color: #9ca3af; border: 1px solid rgba(107, 114, 128, 0.3);'}">
                                    ${account.active ? '● ATIVA' : '○ INATIVA'}
                                </span>
                            </div>
                            <p class="webhook-desc">Pixel ID: ${escapeHtml(account.pixel_id)}</p>
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <button onclick="toggleTiktokAccount(${account.id})" style="background: none; border: none; color: var(--text-secondary); cursor: pointer; padding: 8px; border-radius: 6px; transition: all 0.2s;" title="${account.active ? 'Desativar' : 'Ativar'}">
                                <i class="fa-solid fa-power-off"></i>
                            </button>
                            <button onclick="editTiktokAccount(${account.id})" style="background: none; border: none; color: var(--text-secondary); cursor: pointer; padding: 8px; border-radius: 6px; transition: all 0.2s;" title="Editar">
                                <i class="fa-solid fa-edit"></i>
                            </button>
                            <button onclick="deleteTiktokAccount(${account.id}, '${escapeHtml(account.name)}')" style="background: none; border: none; color: var(--danger); cursor: pointer; padding: 8px; border-radius: 6px; transition: all 0.2s;" title="Remover">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--border);">
                        <p style="color: var(--text-secondary); font-size: 13px; margin: 0;">
                            <strong>Access Token:</strong> ${escapeHtml(account.access_token.substring(0, 40))}...
                        </p>
                    </div>
                </div>
            `).join('');
        }

        function showAddTiktokAccountModal() {
            editingTiktokAccountId = null;
            document.getElementById('tiktokModalTitle').textContent = 'Adicionar Conta TikTok';
            tiktokForm.reset();
            tiktokModal.style.display = 'flex';
        }

        function editTiktokAccount(id) {
            const account = tiktokAccounts.find(acc => acc.id === id);
            if (!account) return;

            editingTiktokAccountId = id;
            document.getElementById('tiktokModalTitle').textContent = 'Editar Conta TikTok';
            document.getElementById('tiktokAccountId').value = account.id;
            document.getElementById('tiktokAccountName').value = account.name;
            document.getElementById('tiktokPixelId').value = account.pixel_id;
            document.getElementById('tiktokAccessToken').value = account.access_token;
            tiktokModal.style.display = 'flex';
        }

        function closeTiktokModal() {
            tiktokModal.style.display = 'none';
            tiktokForm.reset();
            editingTiktokAccountId = null;
        }

        async function saveTiktokAccount(e) {
            e.preventDefault();

            const formData = new FormData(tiktokForm);
            formData.append('action', editingTiktokAccountId ? 'edit_account' : 'add_account');

            try {
                const response = await fetch('tiktok-controller.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    closeTiktokModal();
                    loadTiktokAccounts();
                    showAlert(data.message, 'success');
                } else {
                    showAlert(data.message, 'error');
                }
            } catch (error) {
                showAlert('Erro ao salvar conta TikTok', 'error');
            }
        }

        async function toggleTiktokAccount(id) {
            try {
                const formData = new FormData();
                formData.append('action', 'toggle_active');
                formData.append('id', id);

                const response = await fetch('tiktok-controller.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    loadTiktokAccounts();
                    showAlert(data.message, 'success');
                } else {
                    showAlert(data.message, 'error');
                }
            } catch (error) {
                showAlert('Erro ao alterar status da conta', 'error');
            }
        }

        async function deleteTiktokAccount(id, name) {
            if (!confirm(`Deseja realmente remover a conta "${name}"?`)) return;

            try {
                const formData = new FormData();
                formData.append('action', 'delete_account');
                formData.append('id', id);

                const response = await fetch('tiktok-controller.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    loadTiktokAccounts();
                    showAlert(data.message, 'success');
                } else {
                    showAlert(data.message, 'error');
                }
            } catch (error) {
                showAlert('Erro ao remover conta TikTok', 'error');
            }
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        window.editTiktokAccount = editTiktokAccount;
        window.toggleTiktokAccount = toggleTiktokAccount;
        window.deleteTiktokAccount = deleteTiktokAccount;

        
        
        async function loadOtimizeyConfig() {
            try {
                const response = await fetch('otimizey-controller.php');
                const data = await response.json();

                if (data.success && data.config) {
                    document.getElementById('otimizey_credential_id').value = data.config.credential_id || '';
                    document.getElementById('otimizey_tracking_script_id').value = data.config.tracking_script_id || '';
                    
                    if (data.config.last_updated) {
                        document.getElementById('otimizeyLastUpdate').textContent = 
                            '✓ Última atualização: ' + new Date(data.config.last_updated).toLocaleString('pt-BR');
                    }
                }
                
                await loadOtimizeyStats();
                await loadLastSend();
            } catch (error) {
                console.error('Erro ao carregar configuração Otimizey:', error);
            }
        }

        async function loadOtimizeyStats() {
            try {
                const today = new Date().toISOString().split('T')[0];
                const response = await fetch(`otimizey-logs-controller.php?action=get_stats&date=${today}`);
                const data = await response.json();

                console.log('Stats response:', data);

                const statsTotal = document.getElementById('stats_total');
                const statsSuccess = document.getElementById('stats_success');
                const statsError = document.getElementById('stats_error');

                if (!statsTotal || !statsSuccess || !statsError) {
                    console.error('Elementos de estatísticas não encontrados');
                    return;
                }

                if (data.success && data.stats) {
                    statsTotal.textContent = data.stats.total;
                    statsSuccess.textContent = data.stats.success;
                    statsError.textContent = data.stats.error;
                } else {
                    statsTotal.textContent = '0';
                    statsSuccess.textContent = '0';
                    statsError.textContent = '0';
                }
            } catch (error) {
                console.error('Erro ao carregar estatísticas:', error);
            }
        }

        async function loadLastSend() {
            try {
                const today = new Date().toISOString().split('T')[0];
                const response = await fetch(`otimizey-logs-controller.php?action=get_last_send&date=${today}`);
                const data = await response.json();

                console.log('Last send response:', data);

                const container = document.getElementById('lastSendInfo');
                
                if (!container) {
                    console.error('Container lastSendInfo não encontrado');
                    return;
                }

                if (data.success && data.last_send) {
                    const send = data.last_send;
                    
                    const utcDate = new Date(send.timestamp + ' UTC');
                    const localDate = new Date(utcDate.getTime() - (3 * 60 * 60 * 1000));
                    const timeStr = localDate.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                    
                    const statusColor = send.success ? '#34d399' : '#f87171';
                    const statusIcon = send.success ? 'fa-circle-check' : 'fa-circle-xmark';
                    const statusText = send.success ? 'SUCESSO' : 'FALHA';
                    
                    let detailsHTML = '';
                    
                    if (send.type === 'api_response') {
                        detailsHTML = `
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-top: 12px;">
                                ${send.email ? `
                                <div>
                                    <div style="color: var(--text-secondary); font-size: 12px; margin-bottom: 4px;">Email</div>
                                    <div style="color: var(--text-primary); font-size: 14px; font-family: 'Courier New', monospace;">${escapeHtml(send.email)}</div>
                                </div>
                                ` : ''}
                                ${send.order_id ? `
                                <div>
                                    <div style="color: var(--text-secondary); font-size: 12px; margin-bottom: 4px;">Order ID</div>
                                    <div style="color: var(--text-primary); font-size: 14px; font-family: 'Courier New', monospace;">${escapeHtml(send.order_id.substring(0, 20))}...</div>
                                </div>
                                ` : ''}
                                ${send.status ? `
                                <div>
                                    <div style="color: var(--text-secondary); font-size: 12px; margin-bottom: 4px;">Status</div>
                                    <div style="color: var(--text-primary); font-size: 14px;">${escapeHtml(send.status)}</div>
                                </div>
                                ` : ''}
                                <div>
                                    <div style="color: var(--text-secondary); font-size: 12px; margin-bottom: 4px;">HTTP Code</div>
                                    <div style="color: ${statusColor}; font-size: 14px; font-weight: 600;">${send.http_code}</div>
                                </div>
                            </div>
                        `;
                    } else if (send.type === 'error') {
                        detailsHTML = `
                            <div style="margin-top: 12px; padding: 12px; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 6px;">
                                <div style="color: var(--text-secondary); font-size: 12px; margin-bottom: 4px;">Mensagem de Erro</div>
                                <div style="color: #f87171; font-size: 13px;">${escapeHtml(send.message)}</div>
                            </div>
                        `;
                    }
                    
                    container.innerHTML = `
                        <div style="display: flex; align-items: center; gap: 16px; padding: 16px; background: var(--bg-dark); border: 1px solid var(--border); border-radius: 8px;">
                            <div style="width: 48px; height: 48px; background: rgba(${send.success ? '16, 185, 129' : '239, 68, 68'}, 0.15); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                                <i class="fa-solid ${statusIcon}" style="font-size: 24px; color: ${statusColor};"></i>
                            </div>
                            <div style="flex: 1;">
                                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 4px;">
                                    <span style="color: ${statusColor}; font-weight: 600; font-size: 14px;">${statusText}</span>
                                    <span style="color: var(--text-secondary); font-size: 13px;">às ${timeStr}</span>
                                </div>
                                ${detailsHTML}
                            </div>
                        </div>
                    `;
                } else {
                    container.innerHTML = `
                        <div style="text-align: center; padding: 32px; color: var(--text-secondary);">
                            <i class="fa-solid fa-inbox" style="font-size: 32px; margin-bottom: 12px; opacity: 0.3;"></i>
                            <p>Nenhum envio registrado hoje</p>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Erro ao carregar último envio:', error);
                const container = document.getElementById('lastSendInfo');
                if (container) {
                    container.innerHTML = `
                        <div style="text-align: center; padding: 32px; color: var(--danger);">
                            <i class="fa-solid fa-exclamation-triangle" style="font-size: 32px; margin-bottom: 12px;"></i>
                            <p>Erro ao carregar informações</p>
                        </div>
                    `;
                }
            }
        }

        window.refreshOtimizeyStats = function() {
            loadOtimizeyStats();
            loadLastSend();
            showAlert('Estatísticas atualizadas!', 'success');
        };



        window.copyOtimizeyWebhook = function(inputId) {
            const input = document.getElementById(inputId);
            input.select();
            input.setSelectionRange(0, 99999);
            
            try {
                navigator.clipboard.writeText(input.value).then(() => {
                    showAlert('URL do webhook copiada!', 'success');
                }).catch(() => {
                    document.execCommand('copy');
                    showAlert('URL do webhook copiada!', 'success');
                });
            } catch (err) {
                showAlert('Erro ao copiar URL', 'error');
            }
        };

        window.copyOtimizeyTrackingScript = function() {
            const scriptElement = document.getElementById('otimizeyTrackingScript');
            const scriptText = scriptElement.textContent;
            
            try {
                navigator.clipboard.writeText(scriptText).then(() => {
                    showAlert('Script de rastreamento copiado! Cole no <head> de todas as páginas do funil.', 'success');
                }).catch(() => {
                    const textarea = document.createElement('textarea');
                    textarea.value = scriptText;
                    textarea.style.position = 'fixed';
                    textarea.style.opacity = '0';
                    document.body.appendChild(textarea);
                    textarea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textarea);
                    showAlert('Script de rastreamento copiado! Cole no <head> de todas as páginas do funil.', 'success');
                });
            } catch (err) {
                showAlert('Erro ao copiar script', 'error');
            }
        };

        const otimizeyForm = document.getElementById('otimizeyForm');
        if (otimizeyForm) {
            otimizeyForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                
                const credentialId = document.getElementById('otimizey_credential_id').value.trim();
                const trackingScriptId = document.getElementById('otimizey_tracking_script_id').value.trim();
                
                const uuidRegex = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;
                if (!uuidRegex.test(credentialId)) {
                    showAlert('Formato de Credential ID inválido. Use o formato UUID (ex: 89e0337a-6fad-4fdf-83da-db08bf785fb8)', 'error');
                    return;
                }

                try {
                    const response = await fetch('otimizey-controller.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=update_credential&credential_id=${encodeURIComponent(credentialId)}&tracking_script_id=${encodeURIComponent(trackingScriptId)}`
                    });

                    const data = await response.json();

                    if (data.success) {
                        showAlert(data.message, 'success');
                        if (data.config && data.config.last_updated) {
                            document.getElementById('otimizeyLastUpdate').textContent = 
                                '✓ Última atualização: ' + new Date(data.config.last_updated).toLocaleString('pt-BR');
                        }
                    } else {
                        showAlert(data.message, 'error');
                    }
                } catch (error) {
                    showAlert('Erro ao salvar configuração Otimizey', 'error');
                }
            });
        }

      
        
        async function loadUtmifyConfig() {
            try {
                const response = await fetch('utmify-controller.php');
                const data = await response.json();

                if (data.success && data.config) {
                    document.getElementById('utmify_token').value = data.config.token || '';
                    
                    if (data.config.last_updated) {
                        document.getElementById('utmifyLastUpdate').textContent = 
                            '✓ Última atualização: ' + new Date(data.config.last_updated).toLocaleString('pt-BR');
                    }
                }
                
                await loadUtmifyStats();
                await loadUtmifyLastSend();
            } catch (error) {
                console.error('Erro ao carregar configuração Utmify:', error);
            }
        }



        window.copyUtmifyWebhook = function(inputId) {
            const input = document.getElementById(inputId);
            input.select();
            input.setSelectionRange(0, 99999);
            
            try {
                navigator.clipboard.writeText(input.value).then(() => {
                    showAlert('URL do webhook copiada!', 'success');
                }).catch(() => {
                    document.execCommand('copy');
                    showAlert('URL do webhook copiada!', 'success');
                });
            } catch (err) {
                showAlert('Erro ao copiar URL', 'error');
            }
        };

        async function loadUtmifyStats() {
            try {
                const today = new Date().toISOString().split('T')[0];
                const response = await fetch(`utmify-logs-controller.php?action=get_stats&date=${today}`);
                const data = await response.json();

                const statsTotal = document.getElementById('utmify_stats_total');
                const statsSuccess = document.getElementById('utmify_stats_success');
                const statsError = document.getElementById('utmify_stats_error');

                if (!statsTotal || !statsSuccess || !statsError) return;

                if (data.success && data.stats) {
                    statsTotal.textContent = data.stats.total;
                    statsSuccess.textContent = data.stats.success;
                    statsError.textContent = data.stats.error;
                } else {
                    statsTotal.textContent = '0';
                    statsSuccess.textContent = '0';
                    statsError.textContent = '0';
                }
            } catch (error) {
                console.error('Erro ao carregar estatísticas Utmify:', error);
            }
        }

        async function loadUtmifyLastSend() {
            try {
                const today = new Date().toISOString().split('T')[0];
                const response = await fetch(`utmify-logs-controller.php?action=get_last_send&date=${today}`);
                const data = await response.json();

                const container = document.getElementById('utmifyLastSendInfo');
                
                if (!container) return;

                if (data.success && data.last_send) {
                    const send = data.last_send;
                    
                    const utcDate = new Date(send.timestamp + ' UTC');
                    const localDate = new Date(utcDate.getTime() - (3 * 60 * 60 * 1000));
                    const timeStr = localDate.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                    
                    const statusColor = send.success ? '#34d399' : '#f87171';
                    const statusIcon = send.success ? 'fa-circle-check' : 'fa-circle-xmark';
                    const statusText = send.success ? 'SUCESSO' : 'FALHA';
                    
                    let detailsHTML = '';
                    
                    if (send.type === 'api_response') {
                        detailsHTML = `
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-top: 12px;">
                                ${send.email ? `
                                <div>
                                    <div style="color: var(--text-secondary); font-size: 12px; margin-bottom: 4px;">Email</div>
                                    <div style="color: var(--text-primary); font-size: 14px; font-family: 'Courier New', monospace;">${escapeHtml(send.email)}</div>
                                </div>
                                ` : ''}
                                ${send.order_id ? `
                                <div>
                                    <div style="color: var(--text-secondary); font-size: 12px; margin-bottom: 4px;">Order ID</div>
                                    <div style="color: var(--text-primary); font-size: 14px; font-family: 'Courier New', monospace;">${escapeHtml(send.order_id.substring(0, 20))}...</div>
                                </div>
                                ` : ''}
                                <div>
                                    <div style="color: var(--text-secondary); font-size: 12px; margin-bottom: 4px;">HTTP Code</div>
                                    <div style="color: ${statusColor}; font-size: 14px; font-weight: 600;">${send.http_code}</div>
                                </div>
                            </div>
                        `;
                    } else if (send.type === 'error') {
                        detailsHTML = `
                            <div style="margin-top: 12px; padding: 12px; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 6px;">
                                <div style="color: var(--text-secondary); font-size: 12px; margin-bottom: 4px;">Mensagem de Erro</div>
                                <div style="color: #f87171; font-size: 13px;">${escapeHtml(send.message)}</div>
                            </div>
                        `;
                    }
                    
                    container.innerHTML = `
                        <div style="display: flex; align-items: center; gap: 16px; padding: 16px; background: var(--bg-dark); border: 1px solid var(--border); border-radius: 8px;">
                            <div style="width: 48px; height: 48px; background: rgba(${send.success ? '16, 185, 129' : '239, 68, 68'}, 0.15); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                                <i class="fa-solid ${statusIcon}" style="font-size: 24px; color: ${statusColor};"></i>
                            </div>
                            <div style="flex: 1;">
                                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 4px;">
                                    <span style="color: ${statusColor}; font-weight: 600; font-size: 14px;">${statusText}</span>
                                    <span style="color: var(--text-secondary); font-size: 13px;">às ${timeStr}</span>
                                </div>
                                ${detailsHTML}
                            </div>
                        </div>
                    `;
                } else {
                    container.innerHTML = `
                        <div style="text-align: center; padding: 32px; color: var(--text-secondary);">
                            <i class="fa-solid fa-inbox" style="font-size: 32px; margin-bottom: 12px; opacity: 0.3;"></i>
                            <p>Nenhum envio registrado hoje</p>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Erro ao carregar último envio Utmify:', error);
            }
        }

        window.refreshUtmifyStats = function() {
            loadUtmifyStats();
            loadUtmifyLastSend();
            showAlert('Estatísticas atualizadas!', 'success');
        };

        const utmifyForm = document.getElementById('utmifyForm');
        if (utmifyForm) {
            utmifyForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                
                const token = document.getElementById('utmify_token').value.trim();
                
                if (!token) {
                    showAlert('Token não pode estar vazio', 'error');
                    return;
                }

                try {
                    const response = await fetch('utmify-controller.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=update_token&token=${encodeURIComponent(token)}`
                    });

                    const data = await response.json();

                    if (data.success) {
                        showAlert(data.message, 'success');
                        if (data.config && data.config.last_updated) {
                            document.getElementById('utmifyLastUpdate').textContent = 
                                '✓ Última atualização: ' + new Date(data.config.last_updated).toLocaleString('pt-BR');
                        }
                    } else {
                        showAlert(data.message, 'error');
                    }
                } catch (error) {
                    showAlert('Erro ao salvar token Utmify', 'error');
                }
            });
        }

        
        window.openPreviewBuilder = function() {
            const width = 1600;
            const height = 900;
            const left = (screen.width - width) / 2;
            const top = (screen.height - height) / 2;
            
            window.open(
                'preview-builder.php',
                'PreviewBuilder',
                `width=${width},height=${height},left=${left},top=${top},resizable=yes,scrollbars=yes`
            );
        };

      
        window.switchImageTab = function(type, mode) {
            const uploadContainer = document.getElementById(`${type === 'product' ? 'product_image' : 'company_logo'}_upload`);
            const urlContainer = document.getElementById(`${type === 'product' ? 'product_image' : 'company_logo'}_url_container`);
            const tabs = event.target.parentElement.querySelectorAll('.image-tab');
            
            tabs.forEach(tab => tab.classList.remove('active'));
            event.target.classList.add('active');
            
            if (mode === 'upload') {
                uploadContainer.style.display = 'block';
                urlContainer.style.display = 'none';
            } else {
                uploadContainer.style.display = 'none';
                urlContainer.style.display = 'block';
            }
        };

        window.switchPreviewImageTab = function(type, mode) {
            let uploadContainerId, urlContainerId;
            
            if (type === 'product') {
                uploadContainerId = 'preview_product_image_upload';
                urlContainerId = 'preview_product_image_url_container';
            } else if (type === 'logo') {
                uploadContainerId = 'preview_company_logo_upload';
                urlContainerId = 'preview_company_logo_url_container';
            } else if (type.startsWith('offer_')) {
                uploadContainerId = `preview_${type}_image_upload`;
                urlContainerId = `preview_${type}_image_url_container`;
            }
            
            const uploadContainer = document.getElementById(uploadContainerId);
            const urlContainer = document.getElementById(urlContainerId);
            const tabs = event.target.parentElement.querySelectorAll('.image-tab');
            
            tabs.forEach(tab => tab.classList.remove('active'));
            event.target.classList.add('active');
            
            if (mode === 'upload') {
                if (uploadContainer) uploadContainer.style.display = 'block';
                if (urlContainer) urlContainer.style.display = 'none';
            } else {
                if (uploadContainer) uploadContainer.style.display = 'none';
                if (urlContainer) urlContainer.style.display = 'block';
            }
        };

        window.handleImageUpload = async function(input, type) {
            const file = input.files[0];
            if (!file) return;

            if (!file.type.startsWith('image/')) {
                showAlert('Por favor, selecione apenas arquivos de imagem', 'error');
                return;
            }

            if (file.size > 5 * 1024 * 1024) {
                showAlert('Arquivo muito grande. Máximo: 5MB', 'error');
                return;
            }

            const preview = document.getElementById(`${type === 'product' ? 'product_image' : 'company_logo'}_preview`);
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            };
            reader.readAsDataURL(file);

            const formData = new FormData();
            formData.append('image', file);

            try {
                showAlert('Enviando imagem...', 'success');
                
                const response = await fetch('upload-handler.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    const urlInput = document.getElementById(type === 'product' ? 'product_image' : 'company_logo');
                    urlInput.value = data.url;
                    
                    showAlert('✅ Imagem enviada com sucesso!', 'success');
                } else {
                    showAlert(data.message, 'error');
                }
            } catch (error) {
                showAlert('Erro ao enviar imagem', 'error');
                console.error('Erro no upload:', error);
            }
        };

        window.handlePreviewImageUpload = async function(input, type) {
            const file = input.files[0];
            if (!file) return;

            if (!file.type.startsWith('image/')) {
                showAlert('Por favor, selecione apenas arquivos de imagem', 'error');
                return;
            }

            if (file.size > 5 * 1024 * 1024) {
                showAlert('Arquivo muito grande. Máximo: 5MB', 'error');
                return;
            }

            let previewId, urlInputId, dataKey;
            
            if (type === 'product') {
                previewId = 'preview_product_image_preview';
                urlInputId = 'preview_product_image';
                dataKey = 'product_image';
            } else if (type === 'logo') {
                previewId = 'preview_company_logo_preview';
                urlInputId = 'preview_company_logo';
                dataKey = 'company_logo';
            } else if (type.startsWith('offer_')) {
                previewId = `preview_${type}_image_preview`;
                urlInputId = `preview_${type}_image`;
                dataKey = `${type}_image`;
            }

            const preview = document.getElementById(previewId);
            const reader = new FileReader();
            reader.onload = function(e) {
                if (preview) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                
                updatePreview();
            };
            reader.readAsDataURL(file);

            const formData = new FormData();
            formData.append('image', file);

            try {
                const response = await fetch('upload-handler.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    const urlInput = document.getElementById(urlInputId);
                    if (urlInput) {
                        urlInput.value = data.url;
                    }
                    
                    setTimeout(() => {
                        updatePreview();
                    }, 500);
                    
                    console.log('✅ Upload concluído:', data.url);
                } else {
                    showAlert(data.message, 'error');
                }
            } catch (error) {
                console.error('Erro no upload:', error);
                showAlert('Erro ao fazer upload da imagem', 'error');
            }
        };

        document.addEventListener('DOMContentLoaded', function() {
            const uploadContainers = document.querySelectorAll('.upload-container');
            
            uploadContainers.forEach(container => {
                container.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    this.classList.add('dragover');
                });
                
                container.addEventListener('dragleave', function(e) {
                    e.preventDefault();
                    this.classList.remove('dragover');
                });
                
                container.addEventListener('drop', function(e) {
                    e.preventDefault();
                    this.classList.remove('dragover');
                    
                    const files = e.dataTransfer.files;
                    if (files.length > 0) {
                        const input = this.querySelector('input[type="file"]');
                        input.files = files;
                        
                        const event = new Event('change', { bubbles: true });
                        input.dispatchEvent(event);
                    }
                });
            });
        });
        ;
    </script>
</body>
</html>
