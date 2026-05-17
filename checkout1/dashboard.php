<?php
// Dashboard de administração para visualizar pedidos
error_reporting(E_ALL);
ini_set('display_errors', 1);

$error = null;
$pedidos = [];
$total = 0;
$paid = 0;
$pending = 0;
$totalValor = 0;
$totalValorPaid = 0;
$percentPaid = 0;
$percentPending = 0;

try {
    $dbPath = __DIR__ . '/database.sqlite';
    
    if (!file_exists($dbPath)) {
        throw new Exception("Banco de dados não encontrado");
    }
    
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Obter data de hoje em horário de Brasília
    $hoje = date('Y-m-d');
    
    // Filtrar apenas pedidos do dia atual
    $stmt = $db->prepare("SELECT * FROM pedidos WHERE DATE(created_at) = :hoje ORDER BY created_at DESC");
    $stmt->bindParam(':hoje', $hoje, PDO::PARAM_STR);
    $stmt->execute();
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total = count($pedidos);
    
    foreach ($pedidos as $pedido) {
        $totalValor += $pedido['valor'];
        
        if ($pedido['status'] === 'paid') {
            $paid++;
            $totalValorPaid += $pedido['valor'];
        } else if ($pedido['status'] === 'pending') {
            $pending++;
        }
    }
    
    $percentPaid = $total > 0 ? round(($paid / $total) * 100, 2) : 0;
    $percentPending = $total > 0 ? round(($pending / $total) * 100, 2) : 0;
    
    // Função para obter nome do produto baseado no valor
    function getNomeProduto($valorCentavos) {
        $produtos = [
            2456 => 'Front',
            
            2846 => 'Upsell 1',
            3650 => 'Upsell 2',
            2174 => 'Upsell 3',
            2076 => 'Upsell 4',
            4394 => 'Upsell 5',
            2384 => 'Upsell 6',
            3894 => 'Upsell 7',
            2550 => 'Upsell 8',
            1944 => 'Upsell 9',
            2224 => 'Upsell 10',
            4990 => 'Upsell 11'
        ];
        
        return $produtos[$valorCentavos] ?? 'Produto';
    }
    
    // Estatísticas por valor
    $valorStats = [];
    foreach ($pedidos as $pedido) {
        $valor = $pedido['valor'];
        if (!isset($valorStats[$valor])) {
            $valorStats[$valor] = ['total' => 0, 'paid' => 0, 'pending' => 0];
        }
        $valorStats[$valor]['total']++;
        if ($pedido['status'] === 'paid') {
            $valorStats[$valor]['paid']++;
        } else if ($pedido['status'] === 'pending') {
            $valorStats[$valor]['pending']++;
        }
    }
    
    // Estatísticas por UTM ID
    $utmStats = [];
    foreach ($pedidos as $pedido) {
        if (!empty($pedido['utm_params'])) {
            $utmData = json_decode($pedido['utm_params'], true);
            if (isset($utmData['utm_id'])) {
                $utmId = $utmData['utm_id'];
                if (!isset($utmStats[$utmId])) {
                    $utmStats[$utmId] = [
                        'total' => 0, 
                        'paid' => 0, 
                        'pending' => 0,
                        'valorTotal' => 0,
                        'valorPaid' => 0,
                        'utm_source' => $utmData['utm_source'] ?? '-',
                        'utm_campaign' => $utmData['utm_campaign'] ?? '-'
                    ];
                }
                $utmStats[$utmId]['total']++;
                $utmStats[$utmId]['valorTotal'] += $pedido['valor'];
                
                if ($pedido['status'] === 'paid') {
                    $utmStats[$utmId]['paid']++;
                    $utmStats[$utmId]['valorPaid'] += $pedido['valor'];
                } else if ($pedido['status'] === 'pending') {
                    $utmStats[$utmId]['pending']++;
                }
            }
        }
    }
    
    // Ordenar UTM stats por total de vendas (decrescente)
    uasort($utmStats, function($a, $b) {
        return $b['total'] - $a['total'];
    });
    
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Inter', sans-serif; 
            background: #000000;
        }
        .glass-card { 
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(139, 92, 246, 0.2);
            backdrop-filter: blur(16px);
        }
        .glass-card:hover {
            border-color: rgba(139, 92, 246, 0.4);
            transform: translateY(-2px);
            transition: all 0.3s ease;
        }
        .purple-badge {
            background: rgba(139, 92, 246, 0.2);
            border: 1px solid rgba(139, 92, 246, 0.3);
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
    </style>
    <script>
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active state from all nav links
            document.querySelectorAll('nav a[id^="tab-"]').forEach(link => {
                link.classList.remove('text-white');
                link.classList.add('text-gray-400');
            });
            
            // Show selected tab
            document.getElementById('content-' + tabName).classList.add('active');
            
            // Activate nav link
            document.getElementById('tab-' + tabName).classList.remove('text-gray-400');
            document.getElementById('tab-' + tabName).classList.add('text-white');
            
            // Save current tab to localStorage
            localStorage.setItem('activeTab', tabName);
        }
        
        // Initialize tab on load (restore last active tab or default to dashboard)
        window.addEventListener('DOMContentLoaded', function() {
            const savedTab = localStorage.getItem('activeTab') || 'dashboard';
            showTab(savedTab);
        });
    </script>
</head>
<body class="min-h-screen">
    <div class="w-full">
        <!-- Header -->
        <nav class="bg-black border-b border-purple-900/30 px-8 py-4">
            <div class="max-w-[1600px] mx-auto flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-gradient-to-br from-purple-600 to-purple-800 rounded-lg flex items-center justify-center">
                        <span class="text-white font-bold text-lg">L</span>
                    </div>
                    <h1 class="text-white text-xl font-bold">Admin <span class="text-gray-400 font-normal">Dashboard</span></h1>
                </div>
                <div class="flex items-center gap-8">
                    <a href="#dashboard" onclick="showTab('dashboard')" id="tab-dashboard" class="text-white font-medium hover:text-purple-400 transition-colors">Dashboard</a>
                    <a href="#utm" onclick="showTab('utm')" id="tab-utm" class="text-gray-400 font-medium hover:text-purple-400 transition-colors">Vendas por UTM</a>
                    <a href="#transactions" onclick="showTab('transactions')" id="tab-transactions" class="text-gray-400 font-medium hover:text-purple-400 transition-colors">Transações</a>
                    <a href="#tiktok" onclick="showTab('tiktok')" id="tab-tiktok" class="text-gray-400 font-medium hover:text-purple-400 transition-colors">TikTok Pixels</a>
                    <button onclick="location.reload()" class="flex items-center gap-2 px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white font-medium rounded-lg transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        Atualizar Métricas
                    </button>
                </div>
            </div>
        </nav>

        <div class="max-w-[1600px] mx-auto px-8 py-8">
        
            <?php if ($error): ?>
                <div class="glass-card rounded-xl p-6 mb-8 border-l-4 border-red-500">
                    <p class="font-bold text-red-400">Erro: <?php echo htmlspecialchars($error); ?></p>
                </div>
            <?php else: ?>
                
                <!-- Dashboard Tab -->
                <div id="content-dashboard" class="tab-content active">
                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-12">
                    <!-- Total de Participantes -->
                    <div class="glass-card rounded-2xl p-6">
                        <div class="flex items-start justify-between mb-4">
                            <div>
                                <p class="text-gray-400 text-sm font-medium mb-2">Total de Pix gerados</p>
                                <h2 class="text-5xl font-bold text-white mb-2"><?php echo $total; ?></h2>
                                <p class="text-green-400 text-sm flex items-center gap-2">
                                    <span>↗</span> Atualizado em tempo real
                                </p>
                            </div>
                            <div class="w-12 h-12 bg-purple-600/20 rounded-xl flex items-center justify-center">
                                <svg class="w-6 h-6 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Pagamentos Aprovados -->
                    <div class="glass-card rounded-2xl p-6">
                        <div class="flex items-start justify-between mb-4">
                            <div>
                                <p class="text-gray-400 text-sm font-medium mb-2">Pagamentos Aprovados</p>
                                <h2 class="text-5xl font-bold text-white mb-2"><?php echo $paid; ?></h2>
                                <p class="text-purple-400 text-sm flex items-center gap-2">
                                    <span>↗</span> <?php echo $percentPaid; ?>% de conversão
                                </p>
                            </div>
                            <div class="w-12 h-12 bg-purple-600/20 rounded-xl flex items-center justify-center">
                                <svg class="w-6 h-6 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Último Registro -->
                    <div class="glass-card rounded-2xl p-6">
                        <div class="flex items-start justify-between mb-4">
                            <div>
                                <p class="text-gray-400 text-sm font-medium mb-2">Último Pagamento</p>
                                <h2 class="text-2xl font-bold text-white mb-2">
                                    <?php 
                                    if (!empty($pedidos)) {
                                        $ultimoPedido = $pedidos[0];
                                        $data = new DateTime($ultimoPedido['created_at']);
                                        echo $data->format('d/m/Y H:i');
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </h2>
                                <p class="text-purple-400 text-sm flex items-center gap-2">
                                    <?php if (!empty($pedidos)): ?>
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
                                    </svg>
                                    Por <?php echo htmlspecialchars($pedidos[0]['nome'] ?? 'Cliente'); ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="w-12 h-12 bg-purple-600/20 rounded-xl flex items-center justify-center">
                                <svg class="w-6 h-6 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Receita Aprovada -->
                    <div class="glass-card rounded-2xl p-6">
                        <div class="flex items-start justify-between mb-4">
                            <div>
                                <p class="text-gray-400 text-sm font-medium mb-2">Receita Aprovada</p>
                                <h2 class="text-3xl font-bold text-white mb-2">
                                    R$ <?php echo number_format($totalValorPaid / 100, 2, ',', '.'); ?>
                                </h2>
                                <p class="text-green-400 text-sm flex items-center gap-2">
                                    <span>💰</span> Total em vendas pagas
                                </p>
                            </div>
                            <div class="w-12 h-12 bg-purple-600/20 rounded-xl flex items-center justify-center">
                                <svg class="w-6 h-6 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Análise por Produto -->
                <?php if (!empty($valorStats)): ?>
                <div class="mb-12">
                    <div class="mb-6">
                        <h2 class="text-3xl font-bold text-white mb-2">Análise por Produto</h2>
                        <p class="text-gray-400">Performance detalhada de cada item</p>
                    </div>
                    
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                        <?php foreach ($valorStats as $valor => $stats): 
                            $percentPaidValor = $stats['total'] > 0 ? round(($stats['paid'] / $stats['total']) * 100, 1) : 0;
                        ?>
                        <div class="glass-card rounded-2xl p-6">
                            <div class="flex items-start justify-between mb-5">
                                <div>
                                    <h3 class="text-2xl font-bold text-white mb-1"><?php echo getNomeProduto($valor); ?></h3>
                                    <p class="text-purple-400 text-sm font-bold">R$ <?php echo number_format($valor / 100, 2, ',', '.'); ?></p>
                                </div>
                                <div class="purple-badge px-3 py-1.5 rounded-lg">
                                    <p class="text-xs font-bold text-purple-300">Total: <span class="text-white"><?php echo $stats['total']; ?></span></p>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <div class="flex justify-between mb-2">
                                    <span class="text-xs text-gray-400 uppercase">Taxa de Conversão</span>
                                    <span class="text-xs font-bold text-green-400"><?php echo $percentPaidValor; ?>%</span>
                                </div>
                                <div class="w-full bg-gray-800 rounded-full h-2">
                                    <div class="h-2 rounded-full bg-gradient-to-r from-green-500 to-purple-500" 
                                         style="width: <?php echo $percentPaidValor; ?>%"></div>
                                </div>
                            </div>
                            
                            <div class="flex justify-between pt-4 border-t border-gray-800 text-sm">
                                <span class="text-gray-400"><?php echo $stats['paid']; ?> aprovados</span>
                                <span class="text-gray-400"><?php echo $stats['pending']; ?> pendentes</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                </div>
                <!-- End Dashboard Tab -->
                
                <!-- UTM Analytics Tab -->
                <div id="content-utm" class="tab-content">
                    <div class="mb-8">
                        <h2 class="text-3xl font-bold text-white mb-2">Análise de Vendas por UTM ID</h2>
                        <p class="text-gray-400">Performance detalhada de cada fonte de tráfego</p>
                    </div>
                    
                    <?php if (empty($utmStats)): ?>
                        <div class="glass-card rounded-2xl p-8">
                            <p class="text-gray-400 text-center py-20">Nenhuma venda com UTM ID registrada</p>
                        </div>
                    <?php else: ?>
                        <!-- Summary Cards -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                            <div class="glass-card rounded-2xl p-6">
                                <div class="flex items-start justify-between mb-4">
                                    <div>
                                        <p class="text-gray-400 text-sm font-medium mb-2">Total de UTMs Ativos</p>
                                        <h2 class="text-4xl font-bold text-white mb-2"><?php echo count($utmStats); ?></h2>
                                        <p class="text-purple-400 text-sm">Diferentes fontes de tráfego</p>
                                    </div>
                                    <div class="w-12 h-12 bg-purple-600/20 rounded-xl flex items-center justify-center">
                                        <svg class="w-6 h-6 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"></path>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="glass-card rounded-2xl p-6">
                                <div class="flex items-start justify-between mb-4">
                                    <div>
                                        <p class="text-gray-400 text-sm font-medium mb-2">Melhor UTM (Conversões)</p>
                                        <?php 
                                        $bestUtm = array_reduce($utmStats, function($best, $current) {
                                            return (!$best || $current['paid'] > $best['paid']) ? $current : $best;
                                        });
                                        ?>
                                        <h2 class="text-2xl font-bold text-white mb-2">
                                            <?php echo array_search($bestUtm, $utmStats); ?>
                                        </h2>
                                        <p class="text-green-400 text-sm"><?php echo $bestUtm['paid']; ?> vendas aprovadas</p>
                                    </div>
                                    <div class="w-12 h-12 bg-purple-600/20 rounded-xl flex items-center justify-center">
                                        <svg class="w-6 h-6 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="glass-card rounded-2xl p-6">
                                <div class="flex items-start justify-between mb-4">
                                    <div>
                                        <p class="text-gray-400 text-sm font-medium mb-2">Receita Total por UTM</p>
                                        <?php 
                                        $totalUtmRevenue = array_sum(array_column($utmStats, 'valorPaid'));
                                        ?>
                                        <h2 class="text-3xl font-bold text-white mb-2">
                                            R$ <?php echo number_format($totalUtmRevenue / 100, 2, ',', '.'); ?>
                                        </h2>
                                        <p class="text-green-400 text-sm">De vendas aprovadas</p>
                                    </div>
                                    <div class="w-12 h-12 bg-purple-600/20 rounded-xl flex items-center justify-center">
                                        <svg class="w-6 h-6 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- UTM Cards Grid -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                            <?php foreach ($utmStats as $utmId => $stats): 
                                $percentPaidUtm = $stats['total'] > 0 ? round(($stats['paid'] / $stats['total']) * 100, 1) : 0;
                                $ticketMedio = $stats['paid'] > 0 ? $stats['valorPaid'] / $stats['paid'] : 0;
                            ?>
                            <div class="glass-card rounded-2xl p-6 hover:shadow-lg hover:shadow-purple-500/10 transition-all">
                                <div class="flex items-start justify-between mb-5">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 mb-2">
                                            <h3 class="text-xl font-bold text-white">UTM ID: <?php echo htmlspecialchars($utmId); ?></h3>
                                            <span class="purple-badge px-2 py-1 rounded text-xs font-bold text-purple-300">
                                                <?php echo $stats['total']; ?> vendas
                                            </span>
                                        </div>
                                        <div class="space-y-1">
                                            <p class="text-gray-400 text-sm">
                                                <span class="text-gray-500">Source:</span> 
                                                <span class="text-purple-400 font-medium"><?php echo htmlspecialchars($stats['utm_source']); ?></span>
                                            </p>
                                            <p class="text-gray-400 text-sm">
                                                <span class="text-gray-500">Campaign:</span> 
                                                <span class="text-purple-400 font-medium"><?php echo htmlspecialchars($stats['utm_campaign']); ?></span>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Conversion Rate Bar -->
                                <div class="mb-5">
                                    <div class="flex justify-between mb-2">
                                        <span class="text-xs text-gray-400 uppercase font-semibold">Taxa de Conversão</span>
                                        <span class="text-sm font-bold text-green-400"><?php echo $percentPaidUtm; ?>%</span>
                                    </div>
                                    <div class="w-full bg-gray-800 rounded-full h-2.5">
                                        <div class="h-2.5 rounded-full bg-gradient-to-r from-green-500 to-purple-500" 
                                             style="width: <?php echo $percentPaidUtm; ?>%"></div>
                                    </div>
                                </div>
                                
                                <!-- Stats Grid -->
                                <div class="grid grid-cols-2 gap-4 mb-4">
                                    <div class="bg-gray-900/50 rounded-lg p-3">
                                        <p class="text-xs text-gray-400 mb-1">Aprovados</p>
                                        <p class="text-2xl font-bold text-green-400"><?php echo $stats['paid']; ?></p>
                                    </div>
                                    <div class="bg-gray-900/50 rounded-lg p-3">
                                        <p class="text-xs text-gray-400 mb-1">Pendentes</p>
                                        <p class="text-2xl font-bold text-amber-400"><?php echo $stats['pending']; ?></p>
                                    </div>
                                </div>
                                
                                <!-- Revenue Info -->
                                <div class="pt-4 border-t border-gray-800">
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <p class="text-xs text-gray-400 mb-1">Receita Aprovada</p>
                                            <p class="text-xl font-bold text-white">
                                                R$ <?php echo number_format($stats['valorPaid'] / 100, 2, ',', '.'); ?>
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-xs text-gray-400 mb-1">Ticket Médio</p>
                                            <p class="text-xl font-bold text-purple-400">
                                                R$ <?php echo number_format($ticketMedio / 100, 2, ',', '.'); ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <!-- End UTM Analytics Tab -->
                
                <!-- Transactions Tab -->
                <div id="content-transactions" class="tab-content">
                    <div class="mb-8">
                        <h2 class="text-3xl font-bold text-white mb-2">Todas as Transações</h2>
                        <p class="text-gray-400">Histórico completo de pedidos do dia</p>
                    </div>

                <!-- Tabela de Transações -->
                <div class="glass-card rounded-2xl p-8">
                    <?php if (empty($pedidos)): ?>
                        <p class="text-gray-400 text-center py-20">Nenhuma transação registrada</p>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="border-b border-gray-800">
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-400">ID Transação</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-400">Status</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-400">Valor</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-400">Cliente</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-400">Email</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-400">CPF</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-400">Data</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-400">Atualização</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-800">
                                    <?php foreach ($pedidos as $pedido): ?>
                                    <tr class="hover:bg-purple-900/10 transition-colors">
                                        <td class="px-6 py-4">
                                            <span class="text-purple-400 font-mono text-sm"><?php echo htmlspecialchars(substr($pedido['transaction_id'] ?? '-', 0, 12)); ?>...</span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php if ($pedido['status'] === 'paid'): ?>
                                                <span class="purple-badge text-purple-300 text-xs font-semibold px-3 py-1 rounded-lg">PAGO</span>
                                            <?php elseif ($pedido['status'] === 'pending'): ?>
                                                <span class="bg-amber-500/20 border border-amber-500/30 text-amber-300 text-xs font-semibold px-3 py-1 rounded-lg">PENDENTE</span>
                                            <?php else: ?>
                                                <span class="bg-red-500/20 border border-red-500/30 text-red-300 text-xs font-semibold px-3 py-1 rounded-lg"><?php echo strtoupper($pedido['status']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 text-green-400 font-semibold">
                                            R$ <?php echo number_format($pedido['valor'] / 100, 2, ',', '.'); ?>
                                        </td>
                                        <td class="px-6 py-4 text-white font-medium"><?php echo htmlspecialchars($pedido['nome'] ?? '-'); ?></td>
                                        <td class="px-6 py-4 text-gray-400"><?php echo htmlspecialchars($pedido['email'] ?? '-'); ?></td>
                                        <td class="px-6 py-4 text-gray-400 font-mono text-sm"><?php echo htmlspecialchars($pedido['cpf'] ?? '-'); ?></td>
                                        <td class="px-6 py-4 text-gray-400">
                                            <?php 
                                            $date = new DateTime($pedido['created_at']);
                                            echo $date->format('d/m/Y H:i');
                                            ?>
                                        </td>
                                        <td class="px-6 py-4 text-gray-400">
                                            <?php 
                                            $updateDate = new DateTime($pedido['updated_at']);
                                            echo $updateDate->format('d/m/Y H:i');
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                </div>
                <!-- End Transactions Tab -->
                
                <!-- TikTok Pixels Tab -->
                <div id="content-tiktok" class="tab-content">
                    <div class="mb-8 flex items-center justify-between">
                        <div>
                            <h2 class="text-3xl font-bold text-white mb-2">Gerenciar Contas TikTok Pixel</h2>
                            <p class="text-gray-400">Adicione e gerencie suas contas TikTok Pixel</p>
                        </div>
                        <button onclick="showAddAccountModal()" class="flex items-center gap-2 px-6 py-3 bg-purple-600 hover:bg-purple-700 text-white font-medium rounded-lg transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            Adicionar Conta
                        </button>
                    </div>
                    
                    <div id="tiktok-accounts-container" class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                        <!-- Contas serão carregadas aqui via JavaScript -->
                    </div>
                    
                    <div id="no-accounts-message" class="glass-card rounded-2xl p-12 text-center" style="display: none;">
                        <svg class="w-16 h-16 text-gray-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                        </svg>
                        <p class="text-gray-400 text-lg font-medium">Nenhuma conta TikTok cadastrada</p>
                        <p class="text-gray-500 text-sm mt-2">Clique em "Adicionar Conta" para começar</p>
                    </div>
                </div>
                <!-- End TikTok Pixels Tab -->
                
                <!-- Modal Adicionar/Editar Conta -->
                <div id="account-modal" class="fixed inset-0 bg-black/70 backdrop-blur-sm hidden items-center justify-center z-50" style="display: none;">
                    <div class="glass-card rounded-2xl p-8 max-w-md w-full mx-4">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-2xl font-bold text-white" id="modal-title">Adicionar Conta TikTok</h3>
                            <button onclick="closeAccountModal()" class="text-gray-400 hover:text-white transition-colors">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                        
                        <form id="account-form" class="space-y-4">
                            <input type="hidden" id="account-id" name="id">
                            
                            <div>
                                <label class="block text-gray-300 font-semibold mb-2">Nome da Conta</label>
                                <input type="text" id="account-name" name="name" placeholder="Ex: Conta Principal" required
                                    class="w-full px-4 py-3 bg-gray-900/50 border border-purple-500/30 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:border-purple-500">
                            </div>
                            
                            <div>
                                <label class="block text-gray-300 font-semibold mb-2">Pixel ID</label>
                                <input type="text" id="account-pixel-id" name="pixel_id" placeholder="Ex: D65U11JC77U1M7M29O2G" required
                                    class="w-full px-4 py-3 bg-gray-900/50 border border-purple-500/30 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:border-purple-500 font-mono">
                            </div>
                            
                            <div>
                                <label class="block text-gray-300 font-semibold mb-2">Access Token</label>
                                <textarea id="account-access-token" name="access_token" placeholder="Cole aqui seu Access Token" required rows="3"
                                    class="w-full px-4 py-3 bg-gray-900/50 border border-purple-500/30 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:border-purple-500 font-mono text-sm resize-none"></textarea>
                            </div>
                            
                            <div class="flex gap-3 mt-6">
                                <button type="button" onclick="closeAccountModal()" class="flex-1 px-4 py-3 bg-gray-700 hover:bg-gray-600 text-white font-medium rounded-lg transition-colors">
                                    Cancelar
                                </button>
                                <button type="submit" class="flex-1 px-4 py-3 bg-purple-600 hover:bg-purple-700 text-white font-medium rounded-lg transition-colors">
                                    Salvar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <script>
                // TikTok Accounts Management
                let tiktokAccounts = [];
                let editingAccountId = null;
                
                // Carregar contas ao abrir a aba
                document.getElementById('tab-tiktok').addEventListener('click', loadTiktokAccounts);
                
                // Carregar contas quando a página carregar se a aba TikTok estiver ativa
                window.addEventListener('DOMContentLoaded', function() {
                    // Aguarda um pouco para garantir que a aba foi ativada
                    setTimeout(() => {
                        const tiktokTab = document.getElementById('content-tiktok');
                        if (tiktokTab && tiktokTab.classList.contains('active')) {
                            loadTiktokAccounts();
                        }
                    }, 100);
                });
                
                async function loadTiktokAccounts() {
                    try {
                        const response = await fetch('tiktok-controller.php');
                        
                        if (!response.ok) {
                            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                        }
                        
                        const data = await response.json();
                        
                        if (data.success) {
                            tiktokAccounts = data.accounts || [];
                            renderTiktokAccounts();
                        } else {
                            console.error('Erro ao carregar contas:', data.message);
                            tiktokAccounts = [];
                            renderTiktokAccounts();
                        }
                    } catch (error) {
                        console.error('Erro na requisição:', error);
                        tiktokAccounts = [];
                        renderTiktokAccounts();
                    }
                }
                
                function renderTiktokAccounts() {
                    const container = document.getElementById('tiktok-accounts-container');
                    const noAccountsMsg = document.getElementById('no-accounts-message');
                    
                    if (tiktokAccounts.length === 0) {
                        container.innerHTML = '';
                        noAccountsMsg.style.display = 'block';
                        return;
                    }
                    
                    noAccountsMsg.style.display = 'none';
                    
                    container.innerHTML = tiktokAccounts.map((account, index) => `
                        <div class="glass-card rounded-2xl p-6 hover:shadow-lg hover:shadow-purple-500/10 transition-all" data-account-index="${index}">
                            <div class="flex items-start justify-between mb-4">
                                <div class="flex-1">
                                    <div class="flex items-center gap-3 mb-2">
                                        <h3 class="text-xl font-bold text-white">${escapeHtml(account.name)}</h3>
                                        <span class="px-3 py-1 rounded-lg text-xs font-bold ${account.active ? 'bg-green-500/20 border border-green-500/30 text-green-300' : 'bg-gray-500/20 border border-gray-500/30 text-gray-300'}">
                                            ${account.active ? '● ATIVA' : '○ INATIVA'}
                                        </span>
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <button data-action="toggle" data-id="${account.id}" class="p-2 text-gray-400 hover:text-purple-400 transition-colors" title="${account.active ? 'Desativar' : 'Ativar'}">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                        </svg>
                                    </button>
                                    <button data-action="edit" data-index="${index}" class="p-2 text-gray-400 hover:text-blue-400 transition-colors" title="Editar">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                    </button>
                                    <button data-action="delete" data-id="${account.id}" data-name="${escapeHtml(account.name)}" class="p-2 text-gray-400 hover:text-red-400 transition-colors" title="Remover">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="space-y-3 pt-4 border-t border-gray-800">
                                <div>
                                    <p class="text-xs text-gray-500 mb-1">PIXEL ID</p>
                                    <p class="text-sm text-purple-400 font-mono">${escapeHtml(account.pixel_id)}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 mb-1">ACCESS TOKEN</p>
                                    <p class="text-sm text-gray-400 font-mono truncate">${escapeHtml(account.access_token.substring(0, 40))}...</p>
                                </div>
                            </div>
                        </div>
                    `).join('');
                    
                    // Adiciona event listeners aos botões
                    container.querySelectorAll('[data-action]').forEach(button => {
                        button.addEventListener('click', function() {
                            const action = this.getAttribute('data-action');
                            
                            if (action === 'toggle') {
                                const id = parseInt(this.getAttribute('data-id'));
                                toggleAccountActive(id);
                            } else if (action === 'edit') {
                                const index = parseInt(this.getAttribute('data-index'));
                                editAccount(tiktokAccounts[index]);
                            } else if (action === 'delete') {
                                const id = parseInt(this.getAttribute('data-id'));
                                const name = this.getAttribute('data-name');
                                deleteAccount(id, name);
                            }
                        });
                    });
                }
                
                function escapeHtml(text) {
                    const div = document.createElement('div');
                    div.textContent = text;
                    return div.innerHTML;
                }
                
                function showAddAccountModal() {
                    editingAccountId = null;
                    document.getElementById('modal-title').textContent = 'Adicionar Conta TikTok';
                    document.getElementById('account-form').reset();
                    document.getElementById('account-modal').style.display = 'flex';
                }
                
                function editAccount(account) {
                    editingAccountId = account.id;
                    document.getElementById('modal-title').textContent = 'Editar Conta TikTok';
                    document.getElementById('account-id').value = account.id;
                    document.getElementById('account-name').value = account.name;
                    document.getElementById('account-pixel-id').value = account.pixel_id;
                    document.getElementById('account-access-token').value = account.access_token;
                    document.getElementById('account-modal').style.display = 'flex';
                }
                
                function closeAccountModal() {
                    document.getElementById('account-modal').style.display = 'none';
                    document.getElementById('account-form').reset();
                    editingAccountId = null;
                }
                
                document.getElementById('account-form').addEventListener('submit', async (e) => {
                    e.preventDefault();
                    
                    const formData = new FormData(e.target);
                    formData.append('action', editingAccountId ? 'edit_account' : 'add_account');
                    
                    try {
                        const response = await fetch('tiktok-controller.php', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const data = await response.json();
                        
                        if (data.success) {
                            closeAccountModal();
                            loadTiktokAccounts();
                            showNotification(data.message, 'success');
                        } else {
                            showNotification(data.message, 'error');
                        }
                    } catch (error) {
                        showNotification('Erro ao salvar conta', 'error');
                    }
                });
                
                async function deleteAccount(id, name) {
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
                            showNotification(data.message, 'success');
                        } else {
                            showNotification(data.message, 'error');
                        }
                    } catch (error) {
                        showNotification('Erro ao remover conta', 'error');
                    }
                }
                
                async function toggleAccountActive(id) {
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
                            showNotification(data.message, 'success');
                        } else {
                            showNotification(data.message, 'error');
                        }
                    } catch (error) {
                        showNotification('Erro ao alterar status', 'error');
                    }
                }
                
                function showNotification(message, type) {
                    // Cria notificação temporária
                    const notification = document.createElement('div');
                    notification.className = `fixed top-4 right-4 px-6 py-4 rounded-lg shadow-lg z-50 ${type === 'success' ? 'bg-green-500' : 'bg-red-500'} text-white font-medium`;
                    notification.textContent = message;
                    document.body.appendChild(notification);
                    
                    setTimeout(() => {
                        notification.remove();
                    }, 3000);
                }
                </script>
                
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
