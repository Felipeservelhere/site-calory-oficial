<?php
session_start();
header('Content-Type: application/json');

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

// ==================== CONEXÃO SISTEMA (para operações) ====================
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

if (!isset($_POST['id']) || empty($_POST['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID do centro de custo não informado']);
    exit;
}

$id = (int)$_POST['id'];

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Verificar se o centro de custo existe e pertence à empresa logada
    $stmt = $pdo->prepare("SELECT id, descricao, codigo FROM centro_custo WHERE id = ? AND idcliente = ?");
    $stmt->execute([$id, $idcliente_empresa]);
    $centro = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$centro) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Centro de custo não encontrado ou sem permissão para excluir']);
        exit;
    }
    
    // Verificar se o centro de custo tem movimentos relacionados (opcional)
    // Descomente e adapte as linhas abaixo se houver tabelas relacionadas
    /*
    $stmt_movimentos = $pdo->prepare("SELECT COUNT(*) as total FROM movimentos WHERE idcentrocusto = ?");
    $stmt_movimentos->execute([$id]);
    $total_movimentos = $stmt_movimentos->fetch()['total'];
    
    if ($total_movimentos > 0) {
        echo json_encode(['success' => false, 'message' => 'Não é possível excluir centro de custo com movimentos associados']);
        exit;
    }
    */
    
    // Excluir o centro de custo (garantindo que pertence à empresa)
    $stmt_delete = $pdo->prepare("DELETE FROM centro_custo WHERE id = ? AND idcliente = ?");
    $stmt_delete->execute([$id, $idcliente_empresa]);
    
    if ($stmt_delete->rowCount() > 0) {
        echo json_encode([
            'success' => true, 
            'message' => 'Centro de custo "' . $centro['descricao'] . '" excluído com sucesso',
            'empresa_id' => $idcliente_empresa,
            'admin_id' => $admin_id
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao excluir centro de custo']);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro no banco de dados: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro no sistema: ' . $e->getMessage()]);
}
?>