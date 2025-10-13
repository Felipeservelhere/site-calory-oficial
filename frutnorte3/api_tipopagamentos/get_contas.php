<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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
    
    // ==================== VALIDAÇÃO DE SEGURANÇA ====================
    
    // Usar o idcliente da empresa logada (não confiar no input)
    $idcliente = $idcliente_empresa;
    
    // Validar que o idcliente é numérico e válido
    if (!is_numeric($idcliente) || $idcliente <= 0) {
        throw new Exception('ID do cliente inválido');
    }
    
    // Get accounts from the contas table
    $stmt = $pdo->prepare("
        SELECT id, codconta, descricao, tipo 
        FROM contas 
        WHERE idcliente = ? 
        ORDER BY codconta ASC
    ");
    
    $stmt->execute([$idcliente]);
    
    $contas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatar os dados para resposta
    $contasFormatadas = array_map(function($conta) {
        return [
            'id' => (int)$conta['id'],
            'codconta' => (int)$conta['codconta'],
            'descricao' => $conta['descricao'],
            'tipo' => $conta['tipo'],
            'tipo_descricao' => $conta['tipo'] === 'C' ? 'Caixa' : ($conta['tipo'] === 'B' ? 'Banco' : 'Outro')
        ];
    }, $contas);
    
    echo json_encode([
        'success' => true,
        'contas' => $contasFormatadas,
        'total' => count($contasFormatadas),
        'empresa_id' => $idcliente_empresa,
        'admin_id' => $admin_id
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro no banco de dados ao carregar contas',
        'error' => $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>