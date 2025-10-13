<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
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
require_once '../config/databaselogin.php';
$dbLogin = new DatabaseLogin();  // Assumindo que a classe é Database em databaselogin.php
$connlogin = $dbLogin->getConnection();

$admin_id = $_SESSION['admin_id'];

// Buscar empresa_id (idcliente) do usuário autenticado (sem filtro de cargo)
$stmt = $connlogin->prepare("SELECT empresa_id FROM usuarios WHERE id = ? AND status = 1");
$stmt->execute([$admin_id]);
$admin_data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin_data || empty($admin_data['empresa_id'])) {
    session_destroy();
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Erro de autenticação. Acesso negado.']);
    exit;
}

$idcliente = $admin_data['empresa_id'];  // 👈 idcliente = empresa_id da sessão
$_SESSION['empresa_id'] = $idcliente;  // Atualiza na sessão

// ==================== CONEXÃO SISTEMA (para operações de contas) ====================
require_once '../config/database.php';
$db = new Database();
$pdo = $db->getConnection();

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Initialize session array for bank accounts if not exists
    if (!isset($_SESSION['contas_bancarias_temp'])) {
        $_SESSION['contas_bancarias_temp'] = [];
    }
    
    if (!isset($_SESSION['contas_excluir'])) {
        $_SESSION['contas_excluir'] = [];
    }
    
    switch ($method) {
        case 'GET':
            handleGet($pdo, $idcliente);
            break;
            
        case 'POST':
            handlePost($pdo, $input, $idcliente);
            break;
            
        default:
            throw new Exception('Método não permitido');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function handleGet($pdo, $idcliente) {
    $action = $_GET['action'] ?? '';
    $codcliente = $_GET['codcliente'] ?? '';
    
    // 👈 Validação de segurança: Se codcliente fornecido, deve matching com idcliente da sessão
    if (!empty($codcliente) && $codcliente != $idcliente) {
        throw new Exception('Acesso negado: Cliente não autorizado.');
    }
    
    // 👈 Se não fornecido, usar o da sessão (multi-tenant)
    $codcliente = $codcliente ?: $idcliente;
    
    if ($action === 'listar') {
        if ($codcliente) {
            listarContasExistentes($pdo, $codcliente);
        } else {
            listarContasTemp();
        }
    } else {
        throw new Exception('Ação não especificada');
    }
}

function handlePost($pdo, $input, $idcliente) {
    $action = $input['action'] ?? '';
    
    // 👈 Para ações que afetam DB, validar codcliente se fornecido
    if (isset($input['codcliente']) && $input['codcliente'] != $idcliente) {
        throw new Exception('Acesso negado: Cliente não autorizado.');
    }
    
    switch ($action) {
        case 'salvar':
            salvarContaTemp($input);
            break;
            
        case 'excluir':
            excluirConta($input, $idcliente);
            break;
            
        case 'limpar':
            limparSessao();
            break;
            
        default:
            throw new Exception('Ação não especificada');
    }
}

function listarContasTemp() {
    $contas = [];
    
    try {
        // Adicionar contas temporárias da sessão
        foreach ($_SESSION['contas_bancarias_temp'] as $index => $conta) {
            $contas[] = [
                'id' => 'temp_' . $index,
                'tipoconta' => $conta['tipoconta'],
                'banco' => $conta['banco'],
                'agencia' => $conta['agencia'],
                'nconta' => $conta['nconta'],
                'chavepix' => $conta['chavepix'] ?? '',
                'cpf_titular' => $conta['cpf_titular'] ?? '',
                'nome_titular' => $conta['nome_titular'] ?? '',
                'origem' => 'session'
            ];
        }
        
        echo json_encode([
            'success' => true,
            'contas' => $contas
        ]);
        
    } catch (Exception $e) {
        throw $e;
    }
}

function listarContasExistentes($pdo, $codcliente) {
    $contas = [];
    
    try {
        // Buscar contas existentes no banco (filtrado por codcliente = idcliente da empresa)
        $sql = "SELECT * FROM contas_clientes WHERE codcliente = ? ORDER BY id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$codcliente]);
        $contasDB = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Adicionar contas do banco (exceto as marcadas para exclusão)
        foreach ($contasDB as $conta) {
            $id = 'db_' . $conta['id'];
            
            // Verificar se não está marcada para exclusão
            if (!in_array($id, $_SESSION['contas_excluir'])) {
                $contas[] = [
                    'id' => $id,
                    'tipoconta' => $conta['tipoconta'] ?? 'Conta Corrente',
                    'banco' => $conta['banco'],
                    'agencia' => $conta['agencia'],
                    'nconta' => $conta['nconta'],
                    'chavepix' => $conta['chavepix'] ?? '',
                    'cpf_titular' => $conta['cpf_titular'] ?? '',
                    'nome_titular' => $conta['nome_titular'] ?? '',
                    'origem' => 'database'
                ];
            }
        }
        
        // Adicionar contas temporárias da sessão
        foreach ($_SESSION['contas_bancarias_temp'] as $index => $conta) {
            $contas[] = [
                'id' => 'temp_' . $index,
                'tipoconta' => $conta['tipoconta'],
                'banco' => $conta['banco'],
                'agencia' => $conta['agencia'],
                'nconta' => $conta['nconta'],
                'chavepix' => $conta['chavepix'] ?? '',
                'cpf_titular' => $conta['cpf_titular'] ?? '',
                'nome_titular' => $conta['nome_titular'] ?? '',
                'origem' => 'session'
            ];
        }
        
        echo json_encode([
            'success' => true,
            'contas' => $contas
        ]);
        
    } catch (Exception $e) {
        throw $e;
    }
}

function salvarContaTemp($input) {
    // Validar dados obrigatórios
    $required = ['tipoconta', 'banco', 'agencia', 'nconta'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            throw new Exception("Campo obrigatório: {$field}");
        }
    }
    
    // Adicionar à sessão
    $novaConta = [
        'tipoconta' => $input['tipoconta'],
        'banco' => $input['banco'],
        'agencia' => $input['agencia'],
        'nconta' => $input['nconta'],
        'chavepix' => $input['chavepix'] ?? '',
        'cpf_titular' => $input['cpf_titular'] ?? '',
        'nome_titular' => $input['nome_titular'] ?? ''
    ];
    
    $_SESSION['contas_bancarias_temp'][] = $novaConta;
    
    echo json_encode([
        'success' => true,
        'message' => 'Conta adicionada à sessão',
        'conta_adicionada' => $novaConta,
        'total_contas' => count($_SESSION['contas_bancarias_temp'])
    ]);
}

function excluirConta($input, $idcliente) {
    $id = $input['id'] ?? '';
    
    // 👈 Validação: Se for conta do DB, verificar se pertence ao idcliente
    if (strpos($id, 'db_') === 0) {
        $db_id = (int) str_replace('db_', '', $id);
        // Verificar no DB se a conta pertence ao cliente logado
        $sql_check = "SELECT id FROM contas_clientes WHERE id = ? AND codcliente = ?";
        $stmt_check = $GLOBALS['pdo']->prepare($sql_check);  // 👈 Usar $pdo global (definido fora)
        $stmt_check->execute([$db_id, $idcliente]);
        if (!$stmt_check->fetch()) {
            throw new Exception('Conta não encontrada ou sem permissão para excluir.');
        }
    }
    
    if (strpos($id, 'temp_') === 0) {
        // Remover conta temporária
        $index = (int) str_replace('temp_', '', $id);
        if (isset($_SESSION['contas_bancarias_temp'][$index])) {
            unset($_SESSION['contas_bancarias_temp'][$index]);
            $_SESSION['contas_bancarias_temp'] = array_values($_SESSION['contas_bancarias_temp']);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Conta temporária removida'
        ]);
        
    } elseif (strpos($id, 'db_') === 0) {
        // Marcar conta do banco para exclusão
        if (!in_array($id, $_SESSION['contas_excluir'])) {
            $_SESSION['contas_excluir'][] = $id;
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Conta marcada para exclusão'
        ]);
        
    } else {
        throw new Exception('ID de conta inválido');
    }
}

function limparSessao() {
    $_SESSION['contas_bancarias_temp'] = [];
    $_SESSION['contas_excluir'] = [];
    
    echo json_encode([
        'success' => true,
        'message' => 'Sessão limpa'
    ]);
}
?>