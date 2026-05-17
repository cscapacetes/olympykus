<?php

session_start();

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
            echo 'Acesso negado';
            exit;
        }
        
        $userId = $guard->user()->id;
    }
    
} catch (Exception $e) {
    if (!isset($_SESSION['checkout_builder_user_id']) || !$_SESSION['checkout_builder_is_admin']) {
        http_response_code(403);
        echo 'Acesso negado';
        exit;
    }
    
    $userId = $_SESSION['checkout_builder_user_id'];
}

if (!is_numeric($userId) || $userId <= 0) {
    http_response_code(400);
    echo 'ID de usuário inválido';
    exit;
}

$userId = (int)$userId;
$cloneId = $_SESSION['checkout_builder_clone_id'] ?? null;

$filename = $_GET['file'] ?? '';

if (empty($filename)) {
    http_response_code(400);
    echo 'Nome do arquivo não especificado';
    exit;
}

if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $filename)) {
    http_response_code(400);
    echo 'Nome de arquivo inválido';
    exit;
}

try {
    $clone = \App\Models\CheckoutClone::where('user_id', $userId)
        ->where('is_active', true)
        ->when($cloneId, function($query) use ($cloneId) {
            return $query->where('id', $cloneId);
        })
        ->first();
    
    if (!$clone) {
        http_response_code(404);
        echo 'Clone não encontrado';
        exit;
    }
    
    $base64Content = $clone->getUpload($filename);
    
    if (!$base64Content) {
        http_response_code(404);
        echo 'Imagem não encontrada';
        exit;
    }
    
    $imageData = base64_decode($base64Content);
    
    if ($imageData === false) {
        http_response_code(500);
        echo 'Erro ao decodificar imagem';
        exit;
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_buffer($finfo, $imageData);
    finfo_close($finfo);
    
    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . strlen($imageData));
    header('Cache-Control: public, max-age=31536000'); 
    
    echo $imageData;
    
} catch (Exception $e) {
    http_response_code(500);
    echo 'Erro no servidor: ' . $e->getMessage();
}
?>
