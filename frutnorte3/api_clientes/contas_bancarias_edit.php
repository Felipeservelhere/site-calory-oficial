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

try {
    $dbLogin = new DatabaseLogin();  // 👈 Classe correta para databaselogin.php
    $connlogin = $dbLogin->getConnection();
    
    if (!$connlogin) {
        throw new Exception('Falha na conexão com DB de autenticação (frutnorte). Verifique credenciais em databaselogin.php.');
    }
    error_log("Conexão auth OK: " . date('Y-m-d H:i:s'));
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro na conexão auth: ' . $e->getMessage()]);
    error_log("Erro conexão auth: " . $e->getMessage());
    exit;
}

$admin_id = $_SESSION['admin_id'];

// Buscar empresa_id (idcliente da empresa logada) do usuário autenticado (sem filtro de cargo)
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
    
    error_log("ID Empresa validado: $idcliente_empresa para admin_id: $admin_id");
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro na validação de usuário: ' . $e->getMessage()]);
    error_log("Erro validação usuário: " . $e->getMessage());
    exit;
}

// ==================== CONEXÃO SISTEMA (para operações de contas) ====================
require_once '../config/database.php';

try {
    $database = new Database();  // 👈 Classe correta para database.php
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        throw new Exception('Falha na conexão com DB operacional (empresaweb). Verifique credenciais em database.php.');
    }
    error_log("Conexão dados OK: " . date('Y-m-d H:i:s'));
    
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
            handleGet($pdo, $idcliente_empresa);
            break;
            
        case 'POST':
            handlePost($pdo, $input, $idcliente_empresa);
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
    error_log("Erro geral API: " . $e->getMessage());
}

function handleGet($pdo, $idcliente_empresa) {
    $action = $_GET['action'] ?? '';
    $codcliente = $_GET['codcliente'] ?? '';
    
    error_log("GET Debug: action=$action, codcliente recebido='$codcliente', idcliente_empresa=$idcliente_empresa");  // 👈 Log para debug
    
    if ($action === 'listar') {
        if (empty($codcliente)) {
            throw new Exception('Código do cliente (codcliente) é obrigatório para listar contas.');
        }
        
        // 👈 Validação: Verificar se o codcliente pertence à empresa logada
        validarClienteEmpresa($pdo, $codcliente, $idcliente_empresa);
        
        error_log("GET Final: Listando contas para codcliente=$codcliente (empresa $idcliente_empresa)");  // 👈 Log final
        
        listarContas($pdo, $codcliente);
    } else {
        throw new Exception('Ação não especificada');
    }
}

function handlePost($pdo, $input, $idcliente_empresa) {
    $action = $input['action'] ?? '';
    $input_codcliente = $input['codcliente'] ?? '';

    error_log("POST Debug: action=$action, codcliente input='$input_codcliente', idcliente_empresa=$idcliente_empresa");

    switch ($action) {
        case 'salvar':
            // salvar só usa sessão, codcliente não é obrigatório
            salvarContaTemp($input);
            break;

        case 'excluir':
            if (empty($input_codcliente)) {
                throw new Exception('Código do cliente (codcliente) é obrigatório para esta ação.');
            }
            validarClienteEmpresa($pdo, $input_codcliente, $idcliente_empresa);
            excluirConta($input, $input_codcliente, $pdo, $idcliente_empresa);
            break;

        case 'limpar':
            // Para limpar sessão, não precisa codcliente
            limparSessao();
            break;

        default:
            throw new Exception('Ação não especificada');
    }
}


// 👈 FUNÇÃO DE VALIDAÇÃO: Verificar se codcliente pertence à empresa logada
function validarClienteEmpresa($pdo, $codcliente, $idcliente_empresa) {
    try {
        // Query para verificar se o cliente existe e pertence à empresa
        // 👈 Corrigido: Usar 'codcliente' em vez de 'id' na tabela clientes (codcliente é o campo sequencial, id é auto-increment)
        $sql = "SELECT id FROM clientes WHERE codcliente = ? AND idcliente = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$codcliente, $idcliente_empresa]);
        
        if (!$stmt->fetch()) {
            error_log("Validação falhou: codcliente $codcliente não pertence à empresa $idcliente_empresa");
            throw new Exception('Cliente não encontrado ou sem permissão para acessar este cliente.');
        }
        
        error_log("Validação OK: codcliente $codcliente pertence à empresa $idcliente_empresa");
        
    } catch (Exception $e) {
        throw $e;
    }
}

function listarContas($pdo, $codcliente) {
    $contas = [];
    
    try {
        error_log("Listando contas para codcliente=$codcliente");  // 👈 Log para debug
        
        // Buscar contas existentes no banco (filtrado por codcliente específico do cliente)
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
        
        // Adicionar contas temporárias da sessão (incluindo novos campos)
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
            // 👈 Removido debug para evitar warning de variável indefinida
        ]);
        
    } catch (Exception $e) {
        throw $e;
    }
}

function salvarContaTemp($input) {
    // Validar dados obrigatórios (mantendo os existentes; novos são opcionais)
    $required = ['tipoconta', 'banco', 'agencia', 'nconta'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            throw new Exception("Campo obrigatório: {$field}");
        }
    }
    
    // Validar CPF se fornecido (básico)
    if (!empty($input['cpf_titular'])) {
        $cpfLimpo = preg_replace('/\D/', '', $input['cpf_titular']);
        if (!validarCPF($cpfLimpo)) {
            throw new Exception('CPF do titular inválido');
        }
    }
    
    // Adicionar à sessão (incluindo novos campos)
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

// Função auxiliar para validar CPF (básica)
function validarCPF($cpf) {
    $cpf = preg_replace('/\D/', '', $cpf);
    if (strlen($cpf) != 11 || preg_match('/^(\d)\1{10}$/', $cpf)) return false;
    
    for ($t = 9; $t < 11; $t++) {
        for ($d = 0, $c = 0; $c < $t; $c++) {
            $d += $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d) return false;
    }
    return true;
}

function excluirConta($input, $codcliente, $pdo, $idcliente_empresa) {
    $id = $input['id'] ?? '';
    
    error_log("Excluir Debug: id=$id, codcliente=$codcliente, idcliente_empresa=$idcliente_empresa");  // 👈 Log para debug
    
    // 👈 Validação: Se for conta do DB, verificar se pertence ao codcliente (e indiretamente à empresa, já validado antes)
    if (strpos($id, 'db_') === 0) {
        $db_id = (int) str_replace('db_', '', $id);
        // Verificar no DB se a conta pertence ao codcliente específico
        $sql_check = "SELECT id FROM contas_clientes WHERE id = ? AND codcliente = ?";
        $stmt_check = $pdo->prepare($sql_check);
        $stmt_check->execute([$db_id, $codcliente]);
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