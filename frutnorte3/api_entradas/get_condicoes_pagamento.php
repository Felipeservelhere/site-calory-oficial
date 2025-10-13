<?php
session_start();
include '../config/database.php';

header('Content-Type: application/json');

// ==================== VERIFICAÇÃO DE LOGIN ====================
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Acesso negado. Faça login para continuar.']);
    exit;
}

if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Sessão inválida. Faça login novamente.']);
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
    echo json_encode(['error' => 'Erro na conexão de autenticação.']);
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
        echo json_encode(['error' => 'Erro de autenticação. Acesso negado.']);
        exit;
    }

    $idcliente_empresa = $admin_data['empresa_id'];
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro na validação de usuário.']);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    $sql = "SELECT codcond, Descricao FROM condicoes WHERE idcliente = ? ORDER BY Descricao";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$idcliente_empresa]);
    $condicoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($condicoes);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro no banco de dados: ' . $e->getMessage()]);
}
?>