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
    include_once '../config/database.php';
    
    // Obter conexão com banco usando a classe Database
    $database = new Database();
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        throw new Exception('Falha na conexão com o banco de dados principal');
    }
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Dados inválidos recebidos');
    }
    
    // Validate required fields
    if (empty($input['Descricao'])) {
        throw new Exception('Descrição é obrigatória');
    }

    // ==================== VALIDAÇÃO DE SEGURANÇA ====================
    
    // Usar o idcliente da empresa logada (não confiar no input)
    $idcliente = $idcliente_empresa;

    // Sanitize and validate input data
    $descricao = trim($input['Descricao']);
    $insumos = isset($input['insumos']) && $input['insumos'] === 'S' ? 'S' : 'N';
    
    // Validate description length
    if (strlen($descricao) > 30) {
        throw new Exception('Descrição deve ter no máximo 30 caracteres');
    }
    
    if (strlen($descricao) < 1) {
        throw new Exception('Descrição não pode estar vazia');
    }
    
    // Validar caracteres na descrição
    if (!preg_match('/^[a-zA-Z0-9\sÀ-ÿ.,\-_]+$/u', $descricao)) {
        throw new Exception('Descrição contém caracteres inválidos');
    }
    
    // Generate codtpdes - get the next available code for this client
    $getMaxCode = $pdo->prepare("SELECT COALESCE(MAX(codtpdes), 0) + 1 as next_code FROM tipodespesas WHERE idcliente = ?");
    $getMaxCode->execute([$idcliente]);
    $codtpdes = $getMaxCode->fetchColumn();
    
    // Verificar se o código gerado já existe (precaução extra)
    $stmt = $pdo->prepare("SELECT id FROM tipodespesas WHERE codtpdes = ? AND idcliente = ?");
    $stmt->execute([$codtpdes, $idcliente]);
    if ($stmt->fetch()) {
        // Se por algum motivo já existir, incrementar até encontrar um livre
        do {
            $codtpdes++;
            $stmt = $pdo->prepare("SELECT id FROM tipodespesas WHERE codtpdes = ? AND idcliente = ?");
            $stmt->execute([$codtpdes, $idcliente]);
        } while ($stmt->fetch());
    }
    
    // Check if description already exists for this client
    $checkDesc = $pdo->prepare("SELECT COUNT(*) FROM tipodespesas WHERE idcliente = ? AND Descricao = ?");
    $checkDesc->execute([$idcliente, $descricao]);
    if ($checkDesc->fetchColumn() > 0) {
        throw new Exception('Já existe um tipo de despesa com esta descrição para sua empresa');
    }
    
    // Insert the new expense type
    $stmt = $pdo->prepare("
        INSERT INTO tipodespesas (idcliente, codtpdes, Descricao, insumos) 
        VALUES (?, ?, ?, ?)
    ");
    
    $stmt->execute([$idcliente, $codtpdes, $descricao, $insumos]);
    
    // Get the inserted record ID
    $insertedId = $pdo->lastInsertId();
    
    // Return success response with the created record data
    $response = [
        'success' => true,
        'message' => 'Tipo de despesa cadastrado com sucesso',
        'tipodespesa' => [
            'id' => $insertedId,
            'idcliente' => $idcliente,
            'codtpdes' => $codtpdes,
            'Descricao' => $descricao,
            'insumos' => $insumos
        ],
        'empresa_id' => $idcliente_empresa,
        'admin_id' => $admin_id
    ];
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    // Database error
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro no banco de dados: ' . $e->getMessage()
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