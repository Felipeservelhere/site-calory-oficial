<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// ==================== VERIFICAÇÃO DE LOGIN ====================
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Acesso negado. Faça login para continuar.']);
    exit;
}

if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    session_destroy();
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sessão inválida. Faça login novamente.']);
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
    echo json_encode(['success' => false, 'message' => 'Erro na conexão auth: ' . $e->getMessage()]);
    exit;
}

$admin_id = $_SESSION['admin_id'];

// Validar usuário e obter empresa_id
try {
    $stmt = $connlogin->prepare("SELECT empresa_id FROM usuarios WHERE id = ? AND status = 1");
    $stmt->execute([$admin_id]);
    $admin_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin_data || empty($admin_data['empresa_id'])) {
        session_destroy();
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Erro de autenticação. Acesso negado.']);
        exit;
    }

    $idcliente_empresa = $admin_data['empresa_id'];
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro na validação de usuário: ' . $e->getMessage()]);
    exit;
}

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit();
}

try {
    require_once '../config/database.php';
    $database = new Database();
    $pdo = $database->getConnection();

    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        throw new Exception('Dados inválidos recebidos');
    }

    // Validações
    if (empty($input['descricao'])) {
        throw new Exception('Descrição é obrigatória');
    }

    // ==================== VALIDAÇÃO DE SEGURANÇA ====================
    // Verificar se o idcliente enviado é o mesmo da empresa logada
    $input_idcliente = isset($input['idcliente']) ? (int) $input['idcliente'] : 0;
    
    if ($input_idcliente !== $idcliente_empresa) {
        http_response_code(403);
        echo json_encode([
            'success' => false, 
            'message' => 'Permissão negada. ID do cliente não corresponde à empresa logada.',
            'empresa_logada' => $idcliente_empresa,
            'idcliente_enviado' => $input_idcliente
        ]);
        exit;
    }

    $descricao = trim($input['descricao']);
    $ativo = isset($input['ativo']) && $input['ativo'] === 'S' ? 'S' : 'N';

    if (strlen($descricao) > 255) {
        throw new Exception('Descrição deve ter no máximo 255 caracteres');
    }

    if (strlen($descricao) < 1) {
        throw new Exception('Descrição não pode estar vazia');
    }

    // Gerar código sequencial baseado no idcliente
    $getMaxCode = $pdo->prepare("SELECT COALESCE(MAX(CAST(codigo AS UNSIGNED)), 0) + 1 as next_code FROM centro_custo WHERE idcliente = ?");
    $getMaxCode->execute([$idcliente_empresa]);
    $codigo = $getMaxCode->fetchColumn();

    // Verificar se já existe centro de custo com mesma descrição para esta empresa
    $checkDesc = $pdo->prepare("SELECT COUNT(*) FROM centro_custo WHERE idcliente = ? AND descricao = ?");
    $checkDesc->execute([$idcliente_empresa, $descricao]);
    if ($checkDesc->fetchColumn() > 0) {
        throw new Exception('Já existe um centro de custo com esta descrição para sua empresa');
    }

    // Inserir centro de custo
    $stmt = $pdo->prepare("
        INSERT INTO centro_custo (idcliente, codigo, descricao, ativo) 
        VALUES (?, ?, ?, ?)
    ");

    $stmt->execute([$idcliente_empresa, $codigo, $descricao, $ativo]);

    $insertedId = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'message' => 'Centro de custo cadastrado com sucesso',
        'centrocusto' => [
            'id' => $insertedId,
            'idcliente' => $idcliente_empresa,
            'codigo' => $codigo,
            'descricao' => $descricao,
            'ativo' => $ativo
        ],
        'empresa_id' => $idcliente_empresa,
        'admin_id' => $admin_id
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro no banco de dados: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>