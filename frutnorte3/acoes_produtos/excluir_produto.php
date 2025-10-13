<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ==================== VERIFICAÇÃO DE LOGIN ====================
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Acesso negado. Faça login para continuar.'
    ]);
    exit;
}

if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Sessão inválida. Faça login novamente.'
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
        'message' => 'Erro na conexão de autenticação.'
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
            'message' => 'Erro de autenticação. Acesso negado.'
        ]);
        exit;
    }

    $idcliente_empresa = $admin_data['empresa_id'];
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro na validação de usuário.'
    ]);
    exit;
}

// ==================== CONEXÃO SISTEMA (para operações principais) ====================
require_once '../config/database.php';

try {
    // Verificar se é POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido. Use POST.');
    }

    // Verificar se o ID do produto foi fornecido
    if (!isset($_POST['id']) || empty($_POST['id'])) {
        throw new Exception('ID do produto é obrigatório.');
    }

    $produto_id = (int)$_POST['id'];

    // Conectar ao banco
    $database = new Database();
    $pdo = $database->getConnection();

    // Iniciar transação
    $pdo->beginTransaction();

    // Usar ID do cliente dinâmico da sessão
    $id_cliente = $idcliente_empresa;

    // Verificar se o produto existe e buscar informações
    $stmt = $pdo->prepare("SELECT id, nome FROM produtos WHERE id = ? AND idcliente = ?");
    $stmt->execute([$produto_id, $id_cliente]);
    $produto = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$produto) {
        throw new Exception('Produto não encontrado.');
    }

    // Verificar se o produto tem movimentações (opcional - para evitar exclusão de produtos com histórico)
    // Você pode descomentar as linhas abaixo se quiser impedir a exclusão de produtos com movimentações
    /*
    $stmt_mov = $pdo->prepare("SELECT COUNT(*) as total FROM movimentacoes WHERE produto_id = ? AND idcliente = ?");
    $stmt_mov->execute([$produto_id, $id_cliente]);
    $tem_movimentacoes = $stmt_mov->fetch()['total'] > 0;
    
    if ($tem_movimentacoes) {
        throw new Exception('Não é possível excluir este produto pois ele possui movimentações no sistema.');
    }
    */

    // Excluir o produto
    $stmt_delete = $pdo->prepare("DELETE FROM produtos WHERE id = ? AND idcliente = ?");
    
    if (!$stmt_delete->execute([$produto_id, $id_cliente])) {
        $errorInfo = $stmt_delete->errorInfo();
        throw new Exception('Erro ao excluir produto do banco de dados: ' . $errorInfo[2]);
    }

    // Verificar se algum registro foi afetado
    if ($stmt_delete->rowCount() === 0) {
        throw new Exception('Nenhum produto foi excluído. Verifique se o produto existe.');
    }

    // Commit da transação
    $pdo->commit();

    // Log de sucesso
    error_log("Produto excluído - ID: {$produto_id}, Nome: {$produto['nome']}, Empresa: {$id_cliente}");

    // Resposta de sucesso
    echo json_encode([
        'success' => true,
        'message' => 'Produto excluído com sucesso!',
        'produto_id' => $produto_id,
        'produto_nome' => $produto['nome']
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    // Rollback em caso de erro
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollback();
    }

    // Log do erro
    error_log("Erro ao excluir produto: " . $e->getMessage());

    // Resposta de erro
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'post_data' => $_POST,
            'produto_id' => isset($produto_id) ? $produto_id : null,
            'empresa_id' => isset($idcliente_empresa) ? $idcliente_empresa : null
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    // Rollback em caso de erro de banco
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollback();
    }

    // Log do erro
    error_log("Erro PDO ao excluir produto: " . $e->getMessage());

    // Resposta de erro genérica (não expor detalhes do banco)
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor. Tente novamente.',
        'debug' => [
            'error_type' => 'database_error',
            'sql_state' => $e->getCode()
        ]
    ], JSON_UNESCAPED_UNICODE);
}
?>