<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
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

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit();
}

try {
    // Include database connection
    require_once '../config/database.php';
    
    // Create database connection
    $database = new Database();
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        throw new Exception('Falha na conexão com o banco de dados');
    }
    
    // Get JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        throw new Exception('Dados inválidos recebidos');
    }
    
    // Validate required fields
    if (empty($data['descricao'])) {
        throw new Exception('Descrição é obrigatória');
    }
    
    if (empty($data['tipo'])) {
        throw new Exception('Tipo é obrigatório');
    }

    // ==================== VALIDAÇÃO DE SEGURANÇA ====================
    
    // Usar o idcliente da empresa logada (não confiar no input)
    $idcliente = $idcliente_empresa;
    
    // Sanitize and validate input data
    $descricao = trim($data['descricao']);
    $tipo = $data['tipo'];
    
    // Validate description length
    if (strlen($descricao) > 255) {
        throw new Exception('Descrição deve ter no máximo 255 caracteres');
    }
    
    if (strlen($descricao) < 1) {
        throw new Exception('Descrição não pode estar vazia');
    }
    
    // Validar caracteres na descrição
    if (!preg_match('/^[a-zA-Z0-9\sÀ-ÿ.,\-_]+$/u', $descricao)) {
        throw new Exception('Descrição contém caracteres inválidos');
    }
    
    // Validar tipo
    if (!in_array($tipo, ['C', 'B'])) {
        throw new Exception('Tipo de conta inválido. Use "C" para Caixa ou "B" para Banco');
    }
    
    // Verificar se já existe conta com mesma descrição para esta empresa
    $checkDesc = $pdo->prepare("SELECT COUNT(*) FROM contas WHERE idcliente = ? AND descricao = ?");
    $checkDesc->execute([$idcliente, $descricao]);
    if ($checkDesc->fetchColumn() > 0) {
        throw new Exception('Já existe uma conta com esta descrição para sua empresa');
    }
    
    // Gerar próximo codconta automaticamente baseado no idcliente
    $stmt = $pdo->prepare("SELECT MAX(codconta) as max_cod FROM contas WHERE idcliente = ?");
    $stmt->execute([$idcliente]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $proximoCodigo = ($result['max_cod'] ?? 0) + 1;
    
    // Verificar se o código gerado já existe (precaução extra)
    $stmt = $pdo->prepare("SELECT id FROM contas WHERE codconta = ? AND idcliente = ?");
    $stmt->execute([$proximoCodigo, $idcliente]);
    if ($stmt->fetch()) {
        // Se por algum motivo já existir, incrementar até encontrar um livre
        do {
            $proximoCodigo++;
            $stmt = $pdo->prepare("SELECT id FROM contas WHERE codconta = ? AND idcliente = ?");
            $stmt->execute([$proximoCodigo, $idcliente]);
        } while ($stmt->fetch());
    }
    
    // Prepare data for insertion
    $insertData = [
        'idcliente' => $idcliente,
        'codconta' => $proximoCodigo,
        'descricao' => $descricao,
        'tipo' => $tipo
    ];
    
    // Build INSERT query
    $columns = array_keys($insertData);
    $placeholders = array_fill(0, count($columns), '?');
    
    $sql = "INSERT INTO contas (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
    
    // Execute the query
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_values($insertData));
    
    $contaId = $pdo->lastInsertId();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Conta cadastrada com sucesso',
        'conta' => [
            'id' => $contaId,
            'codconta' => $proximoCodigo,
            'descricao' => $descricao,
            'tipo' => $tipo,
            'tipo_descricao' => $tipo === 'C' ? 'Caixa' : 'Banco'
        ],
        'empresa_id' => $idcliente_empresa,
        'admin_id' => $admin_id
    ]);
    
} catch (PDOException $e) {
    // Database error
    $errorMessage = 'Erro no banco de dados';
    
    // Check for specific database errors
    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        $errorMessage = 'Erro interno: código duplicado detectado';
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $errorMessage,
        'error' => $e->getMessage()
    ]);
    
} catch (Exception $e) {
    // General error
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>