<?php

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
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

// ==================== CONEXÃO SISTEMA (para buscar clientes) ====================
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

try {
    // Parâmetros de busca e paginação
    $busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
    $limite = isset($_GET['limite']) ? (int)$_GET['limite'] : 10;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $ativo = isset($_GET['ativo']) ? $_GET['ativo'] : '';
    $tipo_pessoa = isset($_GET['tipo_pessoa']) ? $_GET['tipo_pessoa'] : '';

    // Construir query com filtros (sempre incluir filtro por idcliente_empresa para multi-tenant)
    $where_conditions = ["idcliente = ?"];  // 👈 Filtro obrigatório por empresa logada
    $params = [$idcliente_empresa];  // 👈 Primeiro param: idcliente da empresa

    if (!empty($busca)) {
        $where_conditions[] = "(Nome LIKE ? OR Fantasia LIKE ? OR cnpj_cpf LIKE ? OR Email LIKE ?)";
        $params[] = "%$busca%";
        $params[] = "%$busca%";
        $params[] = "%$busca%";
        $params[] = "%$busca%";
    }

    if ($ativo !== '') {
        $where_conditions[] = "ativo = ?";
        $params[] = $ativo;
    }

    if (!empty($tipo_pessoa)) {
        $where_conditions[] = "tipo_pessoa = ?";
        $params[] = $tipo_pessoa;
    }

    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

    error_log("Query Debug: Filtros aplicados para idcliente_empresa=$idcliente_empresa, busca='$busca', ativo='$ativo', tipo_pessoa='$tipo_pessoa'");  // 👈 Log para debug

    // Contar total de registros (filtrado por empresa)
    $sql_count = "SELECT COUNT(*) as total FROM clientes $where_clause";
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute($params);
    $total = $stmt_count->fetch()['total'];

    // Buscar clientes (filtrado por empresa)
    $sql = "SELECT id, idcliente, codcliente, Nome, Fantasia, cnpj_cpf, Email, Fone, celular, 
                   Endereco, numero, Bairro, Cidade, Uf, CEP, ativo, tipo_pessoa, Data_cad, 
                   limite, saldo_devedor, PercDesconto, obs
            FROM clientes 
            $where_clause 
            ORDER BY Data_cad DESC, Nome ASC 
            LIMIT $limite OFFSET $offset";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Formatar dados para resposta
    $clientes_formatados = array_map(function($cliente) {
        return [
            'id' => (int)$cliente['id'],
            'idcliente' => (int)$cliente['idcliente'],
            'codcliente' => (int)$cliente['codcliente'],
            'nome' => $cliente['Nome'],
            'fantasia' => $cliente['Fantasia'],
            'documento' => $cliente['cnpj_cpf'],
            'email' => $cliente['Email'],
            'telefone' => $cliente['Fone'],
            'celular' => $cliente['celular'],
            'endereco_completo' => trim($cliente['Endereco'] . ', ' . $cliente['numero']),
            'bairro' => $cliente['Bairro'],
            'cidade' => $cliente['Cidade'],
            'uf' => $cliente['Uf'],
            'cep' => $cliente['CEP'],
            'ativo' => $cliente['ativo'] === 'S',
            'tipo_pessoa' => $cliente['tipo_pessoa'],
            'data_cadastro' => $cliente['Data_cad'],
            'limite' => (float)$cliente['limite'],
            'saldo_devedor' => (float)$cliente['saldo_devedor'],
            'desconto_percentual' => (float)$cliente['PercDesconto'],
            'observacoes' => $cliente['obs']
        ];
    }, $clientes);

    echo json_encode([
        'success' => true,
        'data' => $clientes_formatados,
        'total' => (int)$total,
        'limite' => $limite,
        'offset' => $offset,
        'debug_idcliente_empresa' => $idcliente_empresa  // 👈 Para debug no JSON (remova depois)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar clientes: ' . $e->getMessage()
    ]);
    error_log("Erro busca clientes: " . $e->getMessage());  // 👈 Log do erro
}
?>