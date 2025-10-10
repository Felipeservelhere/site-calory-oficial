<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// ==================== VERIFICAÇÃO DE LOGIN ====================
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Acesso negado. Faça login para continuar.',
        'has_photo' => false
    ]);
    exit;
}

if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Sessão inválida. Faça login novamente.',
        'has_photo' => false
    ]);
    exit;
}

// ==================== CONEXÃO LOGIN (para validar empresa_id) ====================
require_once '../config/databaselogin.php';

try {
    $dbLogin = new DatabaseLogin();
    $connlogin = $dbLogin->getConnection();
    
    if (!$connlogin) {
        throw new Exception('Falha na conexão com DB de autenticação.');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro na conexão de autenticação.',
        'has_photo' => false
    ]);
    exit;
}

$admin_id = $_SESSION['admin_id'];

// Buscar empresa_id do usuário autenticado
try {
    $stmt = $connlogin->prepare("SELECT empresa_id FROM usuarios WHERE id = ? AND status = 1");
    $stmt->execute([$admin_id]);
    $admin_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin_data || empty($admin_data['empresa_id'])) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Erro de autenticação. Acesso negado.',
            'has_photo' => false
        ]);
        exit;
    }

    $idcliente_empresa = $admin_data['empresa_id'];
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro na validação de usuário.',
        'has_photo' => false
    ]);
    exit;
}

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Incluir conexão com banco
require_once '../config/database.php';

try {
    // Verificar se é GET
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Método não permitido. Use GET.');
    }

    // Verificar se o ID foi fornecido
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        throw new Exception('ID do produto é obrigatório.');
    }

    $produto_id = (int)$_GET['id'];

    // Conectar ao banco
    $database = new Database();
    $pdo = $database->getConnection();

    // Buscar apenas a foto do produto (com filtro por empresa)
    $stmt = $pdo->prepare("SELECT foto FROM produtos WHERE id = ? AND idcliente = ?");
    $stmt->execute([$produto_id, $idcliente_empresa]);
    $produto = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$produto) {
        throw new Exception('Produto não encontrado em sua empresa.');
    }

    // Verificar se tem foto
    if (empty($produto['foto'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Produto não possui foto',
            'has_photo' => false,
            'produto_id' => $produto_id,
            'empresa_id' => $idcliente_empresa
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Converter BLOB para base64
    $foto_base64 = base64_encode($produto['foto']);
    
    // Detectar tipo de imagem
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->buffer($produto['foto']);
    
    // Validar se é uma imagem válida
    $tipos_validos = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($mime_type, $tipos_validos)) {
        $mime_type = 'image/jpeg'; // fallback
    }

    // Log de sucesso
    error_log("Foto recuperada com sucesso. Produto ID: {$produto_id}, Empresa: {$idcliente_empresa}, Tamanho: " . strlen($produto['foto']) . " bytes");

    // Resposta de sucesso
    echo json_encode([
        'success' => true,
        'has_photo' => true,
        'photo_data' => "data:{$mime_type};base64,{$foto_base64}",
        'mime_type' => $mime_type,
        'size_bytes' => strlen($produto['foto']),
        'produto_id' => $produto_id,
        'empresa_id' => $idcliente_empresa
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    // Log do erro
    error_log("Erro ao buscar foto do produto: " . $e->getMessage() . " - Produto ID: " . ($produto_id ?? 'N/A') . " - Empresa: " . $idcliente_empresa);

    // Resposta de erro
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'has_photo' => false,
        'produto_id' => isset($produto_id) ? $produto_id : null,
        'empresa_id' => $idcliente_empresa
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    // Log do erro
    error_log("Erro de banco ao buscar foto: " . $e->getMessage() . " - Produto ID: " . ($produto_id ?? 'N/A') . " - Empresa: " . $idcliente_empresa);

    // Resposta de erro genérica
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor.',
        'has_photo' => false,
        'produto_id' => isset($produto_id) ? $produto_id : null,
        'empresa_id' => $idcliente_empresa
    ], JSON_UNESCAPED_UNICODE);
}
?>