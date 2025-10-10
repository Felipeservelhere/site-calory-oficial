<?php

session_start();
header('Content-Type: application/json; charset=utf-8');
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

// ==================== CONEXÃO LOGIN (para validar empresa_id/idcliente) ====================
require_once '../config/databaselogin.php';  // 👈 Ajuste o caminho se necessário

try {
    $dbLogin = new DatabaseLogin();  // 👈 Classe correta para databaselogin.php
    $connlogin = $dbLogin->getConnection();
    
    if (!$connlogin) {
        throw new Exception('Falha na conexão com DB de autenticação. Verifique credenciais em databaselogin.php.');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro na conexão auth: ' . $e->getMessage()]);
    error_log("Erro conexão auth: " . $e->getMessage());
    exit;
}

$admin_id = $_SESSION['admin_id'];

// Buscar empresa_id (idcliente da empresa logada) do usuário autenticado
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

    $idcliente_empresa = $admin_data['empresa_id'];  // 👈 ID da empresa logada (idcliente da empresa)
    $_SESSION['empresa_id'] = $idcliente_empresa;
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro na validação de usuário: ' . $e->getMessage()]);
    error_log("Erro validação usuário: " . $e->getMessage());
    exit;
}

// ==================== CONEXÃO SISTEMA (para operações de exclusão) ====================
require_once '../config/database.php';  // 👈 Sua conexão com o banco (assumindo que define $pdo ou classe Database)

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        throw new Exception('Falha na conexão com DB operacional. Verifique credenciais em database.php.');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro na conexão dados: ' . $e->getMessage()]);
    error_log("Erro conexão dados: " . $e->getMessage());
    exit;
}

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

if (!isset($_POST['id']) || empty($_POST['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID do cliente não informado']);
    exit;
}

$id = (int)$_POST['id'];

try {
    // 👈 Validação de segurança: Verificar se o cliente existe e pertence à empresa logada
    $stmt = $pdo->prepare("SELECT id, Nome, codcliente, idcliente FROM clientes WHERE id = ? AND idcliente = ?");
    $stmt->execute([$id, $idcliente_empresa]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cliente) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Cliente não encontrado ou sem permissão para acessar este cliente.']);
        exit;
    }

    $codcliente = $cliente['codcliente'];
    
    // Verificar se o cliente tem vendas relacionadas (filtrado por empresa)
    // 👈 Assumindo que 'vendas' tem campo 'codcliente' ou 'id' do cliente; ajuste se necessário
    // Aqui uso 'codcliente' para matching; se for 'id', mude para WHERE cliente_id = ? AND idcliente = ?
    $stmt_vendas = $pdo->prepare("SELECT COUNT(*) as total FROM vendas WHERE codcliente = ? AND idcliente = ?");
    $stmt_vendas->execute([$codcliente, $idcliente_empresa]);
    $total_vendas = $stmt_vendas->fetch(PDO::FETCH_ASSOC)['total'];
    
    if ($total_vendas > 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Não é possível excluir cliente com vendas associadas']);
        exit;
    }
    
    // Excluir contas do cliente na tabela contas_clientes (filtrado por empresa)
    $stmt_contas = $pdo->prepare("DELETE FROM contas_clientes WHERE codcliente = ? AND idcliente = ?");
    $stmt_contas->execute([$codcliente, $idcliente_empresa]);
    $contas_excluidas = $stmt_contas->rowCount();
    
    // Excluir o cliente (filtrado por empresa)
    $stmt_delete = $pdo->prepare("DELETE FROM clientes WHERE id = ? AND idcliente = ?");
    $stmt_delete->execute([$id, $idcliente_empresa]);
    
    if ($stmt_delete->rowCount() > 0) {
        echo json_encode([
            'success' => true, 
            'message' => 'Cliente e ' . $contas_excluidas . ' conta(s) excluídos com sucesso',
            'cliente_excluido' => [
                'id' => $id,
                'codcliente' => $codcliente,
                'nome' => $cliente['Nome']
            ]
        ]);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Erro ao excluir cliente (pode já ter sido excluído ou sem permissão)']);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro no banco de dados: ' . $e->getMessage()]);
    error_log("Erro PDO exclusão cliente ID $id: " . $e->getMessage());
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor: ' . $e->getMessage()]);
    error_log("Erro geral exclusão cliente ID $id: " . $e->getMessage());
}
?>