<?php

session_start();

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';

$request = \Illuminate\Http\Request::createFromGlobals();

$app->instance('request', $request);

$app->bootstrapWith([
    \Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables::class,
    \Illuminate\Foundation\Bootstrap\LoadConfiguration::class,
    \Illuminate\Foundation\Bootstrap\HandleExceptions::class,
    \Illuminate\Foundation\Bootstrap\RegisterFacades::class,
    \Illuminate\Foundation\Bootstrap\RegisterProviders::class,
    \Illuminate\Foundation\Bootstrap\BootProviders::class,
]);

try {
    if (isset($_SESSION['checkout_builder_user_id']) && isset($_SESSION['checkout_builder_is_admin']) && $_SESSION['checkout_builder_is_admin']) {
        $userId = $_SESSION['checkout_builder_user_id'];
    } else {
        $guard = $app->make('auth');
        
        if (!$guard->check() || !$guard->user()->isAdmin()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acesso negado']);
            exit;
        }
        
        $userId = $guard->user()->id;
    }
    
} catch (Exception $e) {
    if (!isset($_SESSION['checkout_builder_user_id']) || !$_SESSION['checkout_builder_is_admin']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Acesso negado']);
        exit;
    }
    
    $userId = $_SESSION['checkout_builder_user_id'];
}

$userId = (int)$userId;
$cloneId = $_SESSION['checkout_builder_clone_id'] ?? null;

try {
    $clone = \App\Models\CheckoutClone::where('user_id', $userId)
        ->where('is_active', true)
        ->when($cloneId, function($query) use ($cloneId) {
            return $query->where('id', $cloneId);
        })
        ->first();
    
    if (!$clone) {
        throw new Exception('Clone não encontrado');
    }
    
    echo json_encode([
        'success' => true,
        'clone_id' => $clone->id,
        'clone_name' => $clone->name,
        'tiktok_pixels' => $clone->tiktok_pixels,
        'tiktok_pixels_raw' => $clone->getAttributes()['tiktok_pixels'] ?? null,
        'otimizey_config' => $clone->otimizey_config,
        'otimizey_config_raw' => $clone->getAttributes()['otimizey_config'] ?? null,
        'utmify_config' => $clone->utmify_config,
        'utmify_config_raw' => $clone->getAttributes()['utmify_config'] ?? null,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro: ' . $e->getMessage()
    ]);
}
?>
